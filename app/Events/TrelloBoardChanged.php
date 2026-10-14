<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * A Trello webhook reported a change on a board. Pushed to the team's open CRM boards
 * (via Pusher) so they update right away instead of waiting for the next poll.
 */
class TrelloBoardChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @var array<string, mixed> */
    public array $action;

    /**
     * @param  array<string, mixed>  $action  Trello webhook "action" payload
     */
    public function __construct(
        public int $teamId,
        public string $boardId,
        array $action = [],
    ) {
        $this->action = self::slimAction($action);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('trello.team.'.$this->teamId);
    }

    public function broadcastAs(): string
    {
        return 'trello.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'boardId' => $this->boardId,
            'cardId' => (string) ($this->action['data']['card']['id'] ?? ''),
            'action' => $this->action,
        ];
    }

    /**
     * Only what the browser needs to apply the change, kept well under Pusher's 10 KB limit.
     *
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private static function slimAction(array $action): array
    {
        $data = is_array($action['data'] ?? null) ? $action['data'] : [];
        $creator = is_array($action['memberCreator'] ?? null) ? $action['memberCreator'] : [];
        $pick = static fn ($v, array $keys) => is_array($v) ? array_intersect_key($v, array_flip($keys)) : null;

        return [
            'id' => (string) ($action['id'] ?? ''),
            'type' => (string) ($action['type'] ?? ''),
            'date' => $action['date'] ?? null,
            'memberCreator' => $pick($creator, ['id', 'fullName', 'username', 'initials']),
            'data' => array_filter([
                'text' => isset($data['text']) ? Str::limit((string) $data['text'], 6000, '') : null,
                'card' => $pick($data['card'] ?? null, ['id', 'name', 'idList', 'closed', 'due', 'dueComplete', 'pos']),
                'old' => $pick($data['old'] ?? null, ['name', 'idList', 'closed', 'due', 'dueComplete', 'desc', 'pos']),
                'listBefore' => $pick($data['listBefore'] ?? null, ['id', 'name']),
                'listAfter' => $pick($data['listAfter'] ?? null, ['id', 'name']),
                'list' => $pick($data['list'] ?? null, ['id', 'name']),
                'action' => $pick($data['action'] ?? null, ['id', 'text']),
                'member' => $pick($data['member'] ?? null, ['id', 'name']),
                'label' => $pick($data['label'] ?? null, ['id', 'name', 'color']),
            ], static fn ($v) => $v !== null),
        ];
    }
}
