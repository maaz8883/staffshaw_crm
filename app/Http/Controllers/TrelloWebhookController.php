<?php

namespace App\Http\Controllers;

use App\Events\TrelloBoardChanged;
use App\Models\TrelloBoardWebhook;
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
                    is_array($payload['action'] ?? null) ? $payload['action'] : []
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response('OK', 200);
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
