<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamTrelloIntegration;
use App\Services\Trello\TrelloActivityRecorder;
use App\Services\Trello\TrelloApiClient;
use App\Support\TeamTrelloAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrelloCardController extends Controller
{
    public function show(Request $request, Team $team, string $cardId): JsonResponse
    {
        $client = $this->clientFor($team);

        // Don't hold the session lock while waiting on Trello (lets tick/poll run on artisan serve).
        try {
            if ($request->hasSession()) {
                $request->session()->save();
            }
        } catch (\Throwable) {
            // ignore
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $boardIdHint = $request->query('board_id');
        $boardIdHint = is_string($boardIdHint) && $boardIdHint !== '' ? $boardIdHint : null;

        try {
            $workspace = $client->cardWorkspace($cardId, $boardIdHint);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $card = $workspace['card'];
        // Larger preview so modal cover matches Trello sharpness (~700px wide).
        $refs = TrelloApiClient::cardCoverRefs($card, 700);
        $cover = is_array($card['cover'] ?? null) ? $card['cover'] : null;
        $edgeColor = is_string($cover['edgeColor'] ?? null) ? $cover['edgeColor'] : null;
        $namedColor = TrelloApiClient::cardCoverColor($cover);
        $coverBg = $edgeColor ?: $namedColor;
        $coverSize = is_string($cover['size'] ?? null) ? $cover['size'] : 'normal';

        $attachments = array_map(function (array $att) use ($team, $cardId) {
            $att['download_url'] = route('admin.trello.attachment', [
                'team' => $team->id,
                'cardId' => $cardId,
                'attachmentId' => $att['id'],
            ]);
            $preview = null;
            if (! empty($att['previews']) && is_array($att['previews'])) {
                $scaled = $att['previews'];
                usort($scaled, fn ($a, $b) => abs(((int) ($a['width'] ?? 0)) - 300) <=> abs(((int) ($b['width'] ?? 0)) - 300));
                $previewId = (string) ($scaled[0]['id'] ?? $scaled[0]['_id'] ?? '');
                if ($previewId !== '') {
                    $preview = route('admin.trello.cover', [
                        'team' => $team->id,
                        'cardId' => $cardId,
                        'attachmentId' => $att['id'],
                        'previewId' => $previewId,
                    ]);
                }
            }
            $att['preview_url'] = $preview;

            return $att;
        }, $workspace['attachments']);

        $integration = $team->trelloIntegration;
        $meId = $integration?->trello_member_id;
        if (! $meId) {
            try {
                $me = $client->me();
                $meId = is_string($me['id'] ?? null) ? $me['id'] : null;
                if ($meId && $integration) {
                    $integration->forceFill([
                        'trello_member_id' => $meId,
                        'trello_username' => $me['username'] ?? $integration->trello_username,
                        'trello_full_name' => $me['fullName'] ?? $integration->trello_full_name,
                    ])->save();
                }
            } catch (RuntimeException) {
                $meId = null;
            }
        }

        return response()->json([
            'ok' => true,
            'card' => $card,
            // Real CRM user on actions done through the CRM (team shares one Trello account)
            'comments' => TrelloActivityRecorder::attribute($workspace['comments'], $cardId, $meId),
            'activity' => TrelloActivityRecorder::attribute($workspace['activity'] ?? $workspace['comments'], $cardId, $meId),
            'reactions' => $workspace['reactions'] ?? [],
            'checklists' => $workspace['checklists'],
            'attachments' => $attachments,
            'members' => $workspace['members'],
            'labels' => $workspace['labels'],
            'board_labels' => $workspace['board_labels'],
            'board_members' => $workspace['board_members'],
            'lists' => $workspace['lists'],
            'me_id' => $meId,
            'cover_proxy_url' => $refs ? route('admin.trello.cover', [
                'team' => $team->id,
                'cardId' => $refs['card_id'],
                'attachmentId' => $refs['attachment_id'],
                'previewId' => $refs['preview_id'],
            ]) : null,
            'cover_bg' => $coverBg,
            'cover_size' => $coverSize,
        ]);
    }

    /** "Add another list" on a board. */
    public function storeList(Request $request, Team $team, string $boardId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:16384'],
        ]);
        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['ok' => false, 'message' => 'Enter a list name.'], 422);
        }

        try {
            $list = $client->createList($boardId, $name);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'list_created', ['board_id' => $boardId, 'name' => $name]);

        return response()->json([
            'ok' => true,
            'list' => [
                'id' => (string) ($list['id'] ?? ''),
                'name' => (string) ($list['name'] ?? $name),
                'pos' => $list['pos'] ?? null,
            ],
        ]);
    }

    public function store(Request $request, Team $team): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate([
            'list_id' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:16384'],
            'url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'pos' => ['sometimes', 'string', 'max:32'],
        ]);

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['ok' => false, 'message' => 'Enter a title for this card.'], 422);
        }

        $opts = ['pos' => $data['pos'] ?? 'bottom'];
        $url = isset($data['url']) && is_string($data['url']) ? trim($data['url']) : '';
        if ($url === '' && preg_match('#^https?://\S+$#i', $name)) {
            $url = $name;
        }
        if ($url !== '') {
            $opts['urlSource'] = $url;
        }

        try {
            $card = $client->createCard($data['list_id'], $name, $opts);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'card_created', [
            'card_id' => $card['id'] ?? null,
            'card_name' => $card['name'] ?? $name,
            'list_id' => $data['list_id'],
        ]);

        $cover = is_array($card['cover'] ?? null) ? $card['cover'] : null;
        $refs = TrelloApiClient::cardCoverRefs($card, 300);

        return response()->json([
            'ok' => true,
            'card' => [
                'id' => $card['id'] ?? null,
                'name' => $card['name'] ?? $name,
                'idList' => $card['idList'] ?? $data['list_id'],
                'pos' => $card['pos'] ?? null,
                'due' => $card['due'] ?? null,
                'dueComplete' => $card['dueComplete'] ?? false,
                'labels' => is_array($card['labels'] ?? null) ? $card['labels'] : [],
                'dateLastActivity' => $card['dateLastActivity'] ?? null,
                'cover_proxy_url' => $refs
                    ? route('admin.trello.cover', [
                        'team' => $team->id,
                        'cardId' => $refs['card_id'],
                        'attachmentId' => $refs['attachment_id'],
                        'previewId' => $refs['preview_id'],
                    ])
                    : null,
                'cover_color' => TrelloApiClient::cardCoverColor($cover),
            ],
        ]);
    }

    public function update(Request $request, Team $team, string $cardId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:16384'],
            'desc' => ['sometimes', 'nullable', 'string'],
            'due' => ['sometimes', 'nullable', 'string'],
            'dueComplete' => ['sometimes', 'boolean'],
            'start' => ['sometimes', 'nullable', 'string'],
            'dueReminder' => ['sometimes', 'nullable', 'integer'],
            'idList' => ['sometimes', 'string', 'max:64'],
            'closed' => ['sometimes', 'boolean'],
            'subscribed' => ['sometimes', 'boolean'],
        ]);

        $payload = [];
        foreach (['name', 'desc', 'idList'] as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = $data[$key];
            }
        }
        if (array_key_exists('due', $data)) {
            // Empty / null clears due date on Trello.
            $payload['due'] = $data['due'] ?: '';
        }
        if (array_key_exists('start', $data)) {
            $payload['start'] = $data['start'] ?: '';
        }
        if (array_key_exists('dueReminder', $data)) {
            $payload['dueReminder'] = $data['dueReminder'];
        }
        if (array_key_exists('dueComplete', $data)) {
            $payload['dueComplete'] = (bool) $data['dueComplete'];
        }
        if (array_key_exists('closed', $data)) {
            $payload['closed'] = (bool) $data['closed'];
        }
        if (array_key_exists('subscribed', $data)) {
            $payload['subscribed'] = (bool) $data['subscribed'];
        }
        if ($payload === []) {
            return response()->json(['ok' => false, 'message' => 'Nothing to update.'], 422);
        }

        try {
            $card = $client->updateCard($cardId, $payload);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        // Who changed what (Watch on/off is personal noise, not logged)
        $log = fn (string $action, array $ctx = []) => TrelloActivityRecorder::record($team, $action, ['card_id' => $cardId] + $ctx);
        if (array_key_exists('name', $payload)) {
            $log('card_renamed', ['card_name' => $payload['name']]);
        }
        if (array_key_exists('desc', $payload)) {
            $log('desc_updated');
        }
        if (array_key_exists('idList', $payload)) {
            $log('card_moved', ['list_id' => $payload['idList']]);
        }
        if (array_key_exists('closed', $payload)) {
            $log($payload['closed'] ? 'card_archived' : 'card_unarchived');
        }
        if (array_key_exists('due', $payload)) {
            // The dates panel also sends dueComplete; the due date is the change worth logging
            $log($payload['due'] !== '' ? 'due_set' : 'due_removed', ['due' => $payload['due'] ?: null]);
        } elseif (array_key_exists('dueComplete', $payload)) {
            $log($payload['dueComplete'] ? 'card_completed' : 'card_incomplete');
        }

        return response()->json(['ok' => true, 'card' => $card]);
    }

    public function comment(Request $request, Team $team, string $cardId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate(['text' => ['required', 'string', 'max:16384']]);

        try {
            $action = $client->addComment($cardId, $data['text']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'comment_added', [
            'card_id' => $cardId,
            'text' => $data['text'],
            // Exact link to the Trello comment, so the card activity can show the real author
            'trello_action_id' => $action['id'] ?? null,
        ]);

        return response()->json(['ok' => true, 'comment' => $action]);
    }

    public function deleteComment(Request $request, Team $team, string $actionId): JsonResponse
    {
        $client = $this->clientFor($team);

        try {
            $client->deleteComment($actionId);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        // card_id comes from the page: once deleted, Trello can't tell which card the comment was on
        TrelloActivityRecorder::record($team, 'comment_deleted', [
            'card_id' => $request->query('card_id'),
            'trello_action_id' => $actionId,
        ]);

        return response()->json(['ok' => true]);
    }

    public function updateComment(Request $request, Team $team, string $actionId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate(['text' => ['required', 'string', 'max:16384']]);

        try {
            $action = $client->updateComment($actionId, $data['text']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'comment_edited', [
            'card_id' => $action['data']['card']['id'] ?? null,
            'comment_id' => $actionId,
            'text' => $data['text'],
        ]);

        return response()->json(['ok' => true, 'comment' => $action]);
    }

    public function attachUrl(Request $request, Team $team, string $cardId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'name' => ['sometimes', 'nullable', 'string', 'max:256'],
        ]);

        try {
            $att = $client->attachUrl($cardId, $data['url'], $data['name'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'attachment_added', [
            'card_id' => $cardId,
            'name' => $att['name'] ?? $data['name'] ?? $data['url'],
        ]);

        return response()->json(['ok' => true, 'attachment' => $att]);
    }

    public function addReaction(Request $request, Team $team, string $actionId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate([
            'native' => ['required', 'string', 'max:32'],
        ]);

        try {
            $reaction = $client->addReaction($actionId, $data['native']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'reaction_added', ['comment_id' => $actionId, 'emoji' => $data['native']]);

        return response()->json(['ok' => true, 'reaction' => $reaction]);
    }

    public function toggleLabel(Request $request, Team $team, string $cardId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate([
            'label_id' => ['required', 'string', 'max:64'],
            'active' => ['required', 'boolean'],
        ]);

        try {
            if ($data['active']) {
                $client->addCardLabel($cardId, $data['label_id']);
            } else {
                $client->removeCardLabel($cardId, $data['label_id']);
            }
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, $data['active'] ? 'label_added' : 'label_removed', [
            'card_id' => $cardId,
            'label_id' => $data['label_id'],
            'board_id' => $request->query('board_id'),
        ]);

        return response()->json(['ok' => true]);
    }

    public function createLabel(Request $request, Team $team, string $cardId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:16384'],
            'color' => ['required', 'string', 'max:32'],
            'board_id' => ['required', 'string', 'max:64'],
        ]);

        try {
            $label = $client->createBoardLabel($data['board_id'], (string) ($data['name'] ?? ''), $data['color']);
            $client->addCardLabel($cardId, (string) $label['id']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'label_created', [
            'card_id' => $cardId,
            'board_id' => $data['board_id'],
            'name' => ($data['name'] ?? '') !== '' ? $data['name'] : $data['color'],
        ]);

        return response()->json(['ok' => true, 'label' => $label]);
    }

    public function updateLabel(Request $request, Team $team, string $labelId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:16384'],
            'color' => ['sometimes', 'nullable', 'string', 'max:32'],
            'board_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        try {
            $fields = [];
            if (array_key_exists('name', $data)) {
                $fields['name'] = (string) ($data['name'] ?? '');
            }
            if (array_key_exists('color', $data)) {
                $fields['color'] = $data['color'];
            }
            if ($fields === []) {
                return response()->json(['ok' => false, 'message' => 'Nothing to update.'], 422);
            }
            $label = $client->updateBoardLabel($labelId, $fields, $data['board_id'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'label_updated', [
            'board_id' => $data['board_id'] ?? null,
            'name' => ($label['name'] ?? '') !== '' ? $label['name'] : ($label['color'] ?? 'label'),
        ]);

        return response()->json(['ok' => true, 'label' => $label]);
    }

    public function toggleMember(Request $request, Team $team, string $cardId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate([
            'member_id' => ['required', 'string', 'max:64'],
            'active' => ['required', 'boolean'],
        ]);

        try {
            if ($data['active']) {
                $client->addCardMember($cardId, $data['member_id']);
            } else {
                $client->removeCardMember($cardId, $data['member_id']);
            }
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, $data['active'] ? 'member_added' : 'member_removed', [
            'card_id' => $cardId,
            'member_id' => $data['member_id'],
            'board_id' => $request->query('board_id'),
        ]);

        return response()->json(['ok' => true]);
    }

    public function createChecklist(Request $request, Team $team, string $cardId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'source_id' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $checklist = $client->createChecklist($cardId, $data['name'], $data['source_id'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'checklist_added', ['card_id' => $cardId, 'name' => $data['name']]);

        return response()->json(['ok' => true, 'checklist' => $checklist]);
    }

    public function deleteChecklist(Request $request, Team $team, string $checklistId): JsonResponse
    {
        $client = $this->clientFor($team);

        try {
            $client->deleteChecklist($checklistId);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        // Gone from Trello now, so the card comes from the page
        TrelloActivityRecorder::record($team, 'checklist_deleted', ['card_id' => $request->query('card_id')]);

        return response()->json(['ok' => true]);
    }

    public function addCheckItem(Request $request, Team $team, string $checklistId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate(['name' => ['required', 'string', 'max:16384']]);

        try {
            $item = $client->addCheckItem($checklistId, $data['name']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'checkitem_added', [
            'card_id' => $request->query('card_id'),
            'checklist_id' => $checklistId,
            'name' => $data['name'],
        ]);

        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function updateCheckItem(Request $request, Team $team, string $cardId, string $checkItemId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate(['state' => ['required', 'in:complete,incomplete']]);

        try {
            $item = $client->updateCheckItem($cardId, $checkItemId, $data['state']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, $data['state'] === 'complete' ? 'checkitem_completed' : 'checkitem_uncompleted', [
            'card_id' => $cardId,
            'name' => $item['name'] ?? 'an item',
        ]);

        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function deleteCheckItem(Request $request, Team $team, string $checklistId, string $checkItemId): JsonResponse
    {
        $client = $this->clientFor($team);

        try {
            $client->deleteCheckItem($checklistId, $checkItemId);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'checkitem_deleted', [
            'card_id' => $request->query('card_id'),
            'checklist_id' => $checklistId,
        ]);

        return response()->json(['ok' => true]);
    }

    public function uploadAttachment(Request $request, Team $team, string $cardId): JsonResponse
    {
        $client = $this->clientFor($team);
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        try {
            $att = $client->uploadAttachment($cardId, $file->getRealPath(), $file->getClientOriginalName());
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'attachment_added', [
            'card_id' => $cardId,
            'name' => $att['name'] ?? $file->getClientOriginalName(),
        ]);

        return response()->json(['ok' => true, 'attachment' => $att]);
    }

    public function updateAttachment(Request $request, Team $team, string $cardId, string $attachmentId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:256'],
        ]);

        try {
            $att = $client->updateAttachment($cardId, $attachmentId, $data['name']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'attachment_renamed', ['card_id' => $cardId, 'name' => $data['name']]);

        return response()->json(['ok' => true, 'attachment' => $att]);
    }

    public function setCover(Request $request, Team $team, string $cardId): JsonResponse
    {
        $client = $this->clientFor($team);
        $data = $request->validate([
            'attachment_id' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'in:green,yellow,orange,red,purple,blue,sky,lime,pink,black'],
            'size' => ['nullable', 'in:normal,full'],
            'remove' => ['nullable', 'boolean'],
        ]);
        $attachmentId = ($data['attachment_id'] ?? '') !== '' ? $data['attachment_id'] : null;

        try {
            if ($request->boolean('remove')) {
                $card = $client->removeCardCover($cardId);
            } elseif (! empty($data['color']) || ! empty($data['size'])) {
                // Cover popover: color or attachment, optionally with size.
                $cover = array_filter([
                    'idAttachment' => $attachmentId,
                    'color' => $data['color'] ?? null,
                    'size' => $data['size'] ?? null,
                ]);
                $card = $client->setCardCover($cardId, $cover);
            } else {
                // Attachments "Make cover" / "Remove cover"
                $card = $client->setCardCoverAttachment($cardId, $attachmentId);
            }
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        $removed = $request->boolean('remove') || (empty($data['color']) && empty($data['size']) && $attachmentId === null);
        TrelloActivityRecorder::record($team, $removed ? 'cover_removed' : 'cover_changed', ['card_id' => $cardId]);

        return response()->json(['ok' => true, 'card' => $card]);
    }

    public function deleteAttachment(Team $team, string $cardId, string $attachmentId): JsonResponse
    {
        $client = $this->clientFor($team);

        try {
            $client->deleteAttachment($cardId, $attachmentId);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        TrelloActivityRecorder::record($team, 'attachment_deleted', ['card_id' => $cardId]);

        return response()->json(['ok' => true]);
    }

    public function attachment(Team $team, string $cardId, string $attachmentId): Response|StreamedResponse
    {
        $client = $this->clientFor($team);

        try {
            $file = $client->downloadAttachment($cardId, $attachmentId);
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        // Only images and PDFs open inline (preview / new tab). Anything else — e.g. an attached
        // .html — is forced to download so it can never run as a page on the CRM's origin.
        $type = strtolower((string) $file['content_type']);
        $inline = (str_starts_with($type, 'image/') && ! str_contains($type, 'svg')) || str_starts_with($type, 'application/pdf');
        $headers = [
            'Content-Type' => $file['content_type'],
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if (str_contains($type, 'svg')) {
            // SVG can carry script: show it, but sandboxed
            $headers['Content-Security-Policy'] = 'sandbox';
        } elseif (! $inline) {
            $headers['Content-Disposition'] = 'attachment';
        }

        return response($file['body'], 200, $headers);
    }

    private function clientFor(Team $team): TrelloApiClient
    {
        // Own Trello per team; a sub-team without one works in its parent's (TeamTrelloAccounts)
        abort_unless(TeamTrelloAccounts::userCanUse($team), 403);

        $integration = $team->trelloIntegration;
        abort_unless($integration instanceof TeamTrelloIntegration && $integration->isReady(), 422, 'Trello is not connected.');

        return TrelloApiClient::forIntegration($integration);
    }
}
