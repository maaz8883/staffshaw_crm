<?php

namespace App\Http\Controllers;

use App\Events\TrelloBoardChanged;
use App\Models\TeamTrelloIntegration;
use App\Models\TrelloBoardWebhook;
use App\Services\Trello\TrelloActivityRecorder;
use App\Support\TrelloLive;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrelloWebhookController extends Controller
{
    /** Trello sends HEAD when creating/validating a webhook. */
    public function handle(Request $request, int $team, string $secret): Response
    {
        $hook = TrelloBoardWebhook::query()
            ->where('team_id', $team)
            ->where('secret', $secret)
            ->where('active', true)
            ->first();

        if (! $hook) {
            return response('Not found', 404);
        }

        if ($request->isMethod('head') || $request->isMethod('get')) {
            return response('OK', 200);
        }

        $payload = $request->all();
        $boardId = $this->resolveBoardId($payload, $hook->board_id);
        if ($boardId !== '') {
            TrelloLive::bump($boardId);
            $hook->forceFill(['last_event_at' => now()])->save();

            // Push the change to the team's open CRM boards right away (Pusher).
            // Never fail the webhook because of it — Trello disables hooks that keep erroring.
            try {
                broadcast(new TrelloBoardChanged(
                    (int) $hook->team_id,
                    $boardId,
                    $this->withCrmUser(is_array($payload['action'] ?? null) ? $payload['action'] : [], (int) $hook->team_id)
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response('OK', 200);
    }

    /**
     * Add `crm_user` when the change was made through the CRM, so other open boards show the
     * real person straight away instead of the team's shared Trello account.
     *
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function withCrmUser(array $action, int $teamId): array
    {
        $cardId = (string) ($action['data']['card']['id'] ?? '');
        if ($action === [] || $cardId === '') {
            return $action;
        }
        $shared = TeamTrelloIntegration::query()->where('team_id', $teamId)->value('trello_member_id');
        $creator = (string) ($action['memberCreator']['id'] ?? $action['idMemberCreator'] ?? '');
        if ($shared && $creator !== $shared) {
            return $action; // someone's own Trello account
        }

        $attributed = TrelloActivityRecorder::attribute([$action], $cardId, $shared)[0];
        $tracked = in_array($action['type'] ?? '', [
            'commentCard', 'createCard', 'updateCard', 'addMemberToCard', 'removeMemberFromCard',
            'addChecklistToCard', 'removeChecklistFromCard', 'createCheckItem', 'updateCheckItemStateOnCard',
            'addAttachmentToCard', 'deleteAttachmentFromCard',
        ], true);
        if ($tracked && empty($attributed['crm_user'])) {
            // Trello can be a hair faster than the CRM writing its log row — look once more
            usleep(800000);
            $attributed = TrelloActivityRecorder::attribute([$action], $cardId, $shared)[0];
        }

        return $attributed;
    }

    /** @param array<string, mixed> $payload */
    private function resolveBoardId(array $payload, string $fallback): string
    {
        $action = is_array($payload['action'] ?? null) ? $payload['action'] : [];
        $data = is_array($action['data'] ?? null) ? $action['data'] : [];
        $board = is_array($data['board'] ?? null) ? $data['board'] : [];
        if (! empty($board['id']) && is_string($board['id'])) {
            return $board['id'];
        }

        $model = is_array($payload['model'] ?? null) ? $payload['model'] : [];
        if (! empty($model['id']) && is_string($model['id']) && strlen($model['id']) >= 16) {
            // Board webhooks use the board as model.
            return $model['id'];
        }

        return $fallback;
    }
}
