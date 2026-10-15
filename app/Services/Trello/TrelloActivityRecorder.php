<?php

namespace App\Services\Trello;

use App\Models\Team;
use App\Models\TrelloActivityLog;
use App\Models\UserActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Records which CRM user made each Trello change.
 *
 * Teams share one Trello account, so on Trello everything looks like the same person.
 * The CRM knows who is logged in: it notes user + time when the action happens, then — after
 * the response has been sent, so the user never waits — looks up card / list / board names
 * on Trello and writes a readable line to trello_activity_logs and the Activity Logs page.
 */
class TrelloActivityRecorder
{
    /**
     * @param  array<string, mixed>  $ctx  card_id | checklist_id | comment_id (to find the card),
     *                                     board_id, list_id, label_id, member_id, name, text, due, emoji,
     *                                     trello_action_id
     */
    public static function record(Team $team, string $action, array $ctx = []): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }
        $request = request();
        $base = [
            'user_id' => $user->id,
            'team_id' => $team->id,
            'occurred_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        dispatch(function () use ($team, $action, $ctx, $base) {
            self::write($team, $action, $ctx, $base);
        })->afterResponse();
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $base
     */
    private static function write(Team $team, string $action, array $ctx, array $base): void
    {
        try {
            $client = $team->trelloIntegration?->isReady()
                ? TrelloApiClient::forIntegration($team->trelloIntegration)
                : null;
            $safe = static function (callable $fn) {
                try {
                    return $fn();
                } catch (\Throwable) {
                    return null; // names are nice-to-have; the log is written regardless
                }
            };

            $cardId = $ctx['card_id'] ?? null;
            if (! $cardId && $client && ! empty($ctx['checklist_id'])) {
                $cardId = $safe(fn () => $client->checklistCardId((string) $ctx['checklist_id']));
            }
            if (! $cardId && $client && ! empty($ctx['comment_id'])) {
                $cardId = $safe(fn () => $client->actionCardId((string) $ctx['comment_id']));
            }

            $card = $cardId && $client ? ($safe(fn () => $client->cardBasics((string) $cardId)) ?? []) : [];
            $boardId = (string) ($ctx['board_id'] ?? $card['idBoard'] ?? '');
            $boardName = (string) ($card['board']['name'] ?? '');
            if ($boardName === '' && $boardId !== '' && $client) {
                $boardName = (string) ($safe(fn () => $client->board($boardId)['name'] ?? '') ?? '');
            }

            $findName = static function (?array $rows, $id, string $field = 'name'): string {
                foreach ($rows ?? [] as $row) {
                    if (($row['id'] ?? null) === $id) {
                        return (string) ($row[$field] ?? '');
                    }
                }

                return '';
            };
            $listName = '';
            $listId = $ctx['list_id'] ?? $card['idList'] ?? null;
            if ($listId && $boardId && $client) {
                $listName = $findName($safe(fn () => $client->boardListsCached($boardId)), $listId);
            }
            $labelName = '';
            if (! empty($ctx['label_id']) && $boardId && $client) {
                $labels = $safe(fn () => $client->boardLabelsCached($boardId));
                $labelName = $findName($labels, $ctx['label_id']) ?: $findName($labels, $ctx['label_id'], 'color');
            }
            $memberName = '';
            if (! empty($ctx['member_id']) && $boardId && $client) {
                $memberName = $findName($safe(fn () => $client->boardMembersCached($boardId)), $ctx['member_id'], 'fullName');
            }

            $cardName = (string) ($card['name'] ?? $ctx['card_name'] ?? '');
            $description = self::describe($action, [
                'card' => $cardName !== '' ? '“'.$cardName.'”' : 'a card',
                'list' => $listName !== '' ? '“'.$listName.'”' : 'another list',
                'label' => $labelName !== '' ? '“'.$labelName.'”' : 'a label',
                'member' => $memberName !== '' ? $memberName : 'a member',
                'name' => '“'.Str::limit((string) ($ctx['name'] ?? ''), 80).'”',
                'text' => Str::limit(trim(preg_replace('/\s+/', ' ', (string) ($ctx['text'] ?? ''))), 120),
                'due' => ! empty($ctx['due']) ? \Illuminate\Support\Carbon::parse($ctx['due'])->format('M j, Y g:i A') : '',
                'emoji' => (string) ($ctx['emoji'] ?? ''),
            ]);

            $log = TrelloActivityLog::create([
                'user_id' => $base['user_id'],
                'team_id' => $base['team_id'],
                'board_id' => $boardId ?: null,
                'card_id' => $cardId ?: null,
                'card_name' => $cardName ?: null,
                'action' => $action,
                'description' => Str::limit($description, 990),
                'trello_action_id' => $ctx['trello_action_id'] ?? null,
                'meta' => array_filter([
                    'board_name' => $boardName ?: null,
                    'list_name' => $listName ?: null,
                ]) ?: null,
                'occurred_at' => $base['occurred_at'],
            ]);

            // Same line on the general Activity Logs page
            UserActivityLog::create([
                'user_id' => $base['user_id'],
                'type' => UserActivityLog::TYPE_TRELLO,
                'description' => Str::limit('Trello: '.$description.($boardName ? ' · '.$boardName : ''), 250),
                'ip_address' => $base['ip'],
                'user_agent' => $base['user_agent'],
                'meta' => [
                    'team_id' => $base['team_id'],
                    'board_id' => $boardId ?: null,
                    'card_id' => $cardId ?: null,
                    'trello_log_id' => $log->id,
                ],
            ]);
        } catch (\Throwable $e) {
            // Logging must never break or slow the Trello action itself
            Log::warning('Unable to write Trello activity log.', [
                'action' => $action,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Put the real CRM user on Trello card actions done through the CRM (adds `crm_user`).
     *
     * Comments match exactly by Trello action id. Other changes match a CRM log of the same kind
     * on the same card within 30 seconds. Only actions by the team's shared Trello account
     * ($sharedMemberId) are touched, so another person's own Trello account is never relabelled.
     *
     * @param  list<array<string, mixed>>  $actions
     * @return list<array<string, mixed>>
     */
    public static function attribute(array $actions, string $cardId, ?string $sharedMemberId): array
    {
        if ($actions === [] || $cardId === '') {
            return $actions;
        }
        $logs = TrelloActivityLog::query()
            ->with('user:id,name')
            ->where('card_id', $cardId)
            ->latest('occurred_at')
            ->limit(300)
            ->get();
        if ($logs->isEmpty()) {
            return $actions;
        }

        $old = static fn (array $a, string $key): bool => is_array($a['data']['old'] ?? null) && array_key_exists($key, $a['data']['old']);
        $isUpdate = static fn (array $a): bool => ($a['type'] ?? '') === 'updateCard';
        $fits = [
            'comment_added' => fn ($a) => ($a['type'] ?? '') === 'commentCard',
            'card_created' => fn ($a) => in_array($a['type'] ?? '', ['createCard', 'copyCard'], true),
            'card_moved' => fn ($a) => ($isUpdate($a) && isset($a['data']['listAfter'])) || ($a['type'] ?? '') === 'moveCardToBoard',
            'card_renamed' => fn ($a) => $isUpdate($a) && $old($a, 'name'),
            'card_archived' => fn ($a) => $isUpdate($a) && $old($a, 'closed'),
            'card_unarchived' => fn ($a) => $isUpdate($a) && $old($a, 'closed'),
            'card_completed' => fn ($a) => $isUpdate($a) && $old($a, 'dueComplete'),
            'card_incomplete' => fn ($a) => $isUpdate($a) && $old($a, 'dueComplete'),
            'due_set' => fn ($a) => $isUpdate($a) && $old($a, 'due'),
            'due_removed' => fn ($a) => $isUpdate($a) && $old($a, 'due'),
            'desc_updated' => fn ($a) => $isUpdate($a) && $old($a, 'desc'),
            'member_added' => fn ($a) => ($a['type'] ?? '') === 'addMemberToCard',
            'member_removed' => fn ($a) => ($a['type'] ?? '') === 'removeMemberFromCard',
            'checklist_added' => fn ($a) => ($a['type'] ?? '') === 'addChecklistToCard',
            'checklist_deleted' => fn ($a) => ($a['type'] ?? '') === 'removeChecklistFromCard',
            'checkitem_added' => fn ($a) => ($a['type'] ?? '') === 'createCheckItem',
            'checkitem_completed' => fn ($a) => ($a['type'] ?? '') === 'updateCheckItemStateOnCard',
            'checkitem_uncompleted' => fn ($a) => ($a['type'] ?? '') === 'updateCheckItemStateOnCard',
            'attachment_added' => fn ($a) => ($a['type'] ?? '') === 'addAttachmentToCard',
            'attachment_deleted' => fn ($a) => ($a['type'] ?? '') === 'deleteAttachmentFromCard',
        ];

        $byTrelloId = $logs->filter(fn ($l) => $l->trello_action_id)->keyBy('trello_action_id');
        $used = [];
        $who = static fn ($log): array => ['id' => $log->user_id, 'name' => $log->user?->name ?? 'CRM user'];

        foreach ($actions as $i => $action) {
            $creator = (string) ($action['memberCreator']['id'] ?? $action['idMemberCreator'] ?? '');
            if ($sharedMemberId && $creator !== '' && $creator !== $sharedMemberId) {
                continue; // done with someone's own Trello account — keep Trello's name
            }
            $id = (string) ($action['id'] ?? '');
            if ($id !== '' && isset($byTrelloId[$id])) {
                $actions[$i]['crm_user'] = $who($byTrelloId[$id]);
                $used[$byTrelloId[$id]->id] = true;
                continue;
            }
            $at = isset($action['date']) ? strtotime((string) $action['date']) : false;
            if (! $at) {
                continue;
            }
            $best = null;
            $bestGap = 31;
            foreach ($logs as $log) {
                if (isset($used[$log->id]) || ! isset($fits[$log->action]) || ! $fits[$log->action]($action)) {
                    continue;
                }
                $gap = abs($log->occurred_at->getTimestamp() - $at);
                if ($gap < $bestGap) {
                    $best = $log;
                    $bestGap = $gap;
                }
            }
            if ($best) {
                $actions[$i]['crm_user'] = $who($best);
                $used[$best->id] = true;
            }
        }

        return $actions;
    }

    /** @param  array<string, string>  $p */
    private static function describe(string $action, array $p): string
    {
        return match ($action) {
            'card_created' => "created card {$p['card']} in {$p['list']}",
            'card_moved' => "moved {$p['card']} to {$p['list']}",
            'card_renamed' => "renamed a card to {$p['card']}",
            'card_archived' => "archived {$p['card']}",
            'card_unarchived' => "sent {$p['card']} back to the board",
            'card_completed' => "marked {$p['card']} as complete",
            'card_incomplete' => "marked {$p['card']} as incomplete",
            'desc_updated' => "updated the description of {$p['card']}",
            'due_set' => "set the due date of {$p['card']}".($p['due'] ? " to {$p['due']}" : ''),
            'due_removed' => "removed the due date from {$p['card']}",
            'comment_added' => "commented on {$p['card']}".($p['text'] !== '' ? ": “{$p['text']}”" : ''),
            'comment_edited' => "edited a comment on {$p['card']}",
            'comment_deleted' => "deleted a comment on {$p['card']}",
            'reaction_added' => "reacted {$p['emoji']} to a comment on {$p['card']}",
            'label_added' => "added label {$p['label']} to {$p['card']}",
            'label_removed' => "removed label {$p['label']} from {$p['card']}",
            'label_created' => "created label {$p['name']} on {$p['card']}",
            'label_updated' => "edited label {$p['name']}",
            'member_added' => "added {$p['member']} to {$p['card']}",
            'member_removed' => "removed {$p['member']} from {$p['card']}",
            'checklist_added' => "added checklist {$p['name']} to {$p['card']}",
            'checklist_deleted' => "deleted a checklist from {$p['card']}",
            'checkitem_added' => "added {$p['name']} to a checklist on {$p['card']}",
            'checkitem_completed' => "completed {$p['name']} on {$p['card']}",
            'checkitem_uncompleted' => "marked {$p['name']} incomplete on {$p['card']}",
            'checkitem_deleted' => "removed a checklist item from {$p['card']}",
            'attachment_added' => "attached {$p['name']} to {$p['card']}",
            'attachment_renamed' => "renamed an attachment to {$p['name']} on {$p['card']}",
            'attachment_deleted' => "removed an attachment from {$p['card']}",
            'cover_changed' => "changed the cover of {$p['card']}",
            'cover_removed' => "removed the cover from {$p['card']}",
            'list_created' => "added list {$p['name']}",
            default => str_replace('_', ' ', $action).' on '.$p['card'],
        };
    }
}
