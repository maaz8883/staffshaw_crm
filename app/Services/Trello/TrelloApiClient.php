<?php

namespace App\Services\Trello;

use App\Models\TeamTrelloIntegration;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TrelloApiClient
{
    private const BASE = 'https://api.trello.com/1';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiToken,
    ) {}

    public static function forIntegration(TeamTrelloIntegration $integration): self
    {
        if (! $integration->isReady()) {
            throw new RuntimeException('Trello is not connected for this team.');
        }

        return new self($integration->api_key, $integration->api_token);
    }

    /** @return array<string, mixed> */
    public function me(): array
    {
        return $this->get('members/me', [
            'fields' => 'id,fullName,username,email,avatarUrl',
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function boards(): array
    {
        $boards = $this->get('members/me/boards', [
            'filter' => 'open',
            'fields' => 'id,name,desc,url,shortUrl,shortLink,closed,dateLastActivity,prefs,idOrganization',
            'organization' => 'true',
            'organization_fields' => 'displayName,name',
        ]);

        return is_array($boards) ? array_values($boards) : [];
    }

    /** @return array<string, mixed> */
    public function board(string $boardId): array
    {
        return $this->get("boards/{$boardId}", [
            'fields' => 'id,name,desc,url,shortUrl,shortLink,closed,dateLastActivity,prefs',
        ]);
    }

    /**
     * CSS-ready board background from Trello prefs (photo / color / gradient).
     *
     * @return array{image:?string,color:?string,top:?string,bottom:?string,tile:bool,brightness:string}
     */
    public static function boardBackground(?array $board): array
    {
        $prefs = is_array($board['prefs'] ?? null) ? $board['prefs'] : [];
        $scaled = is_array($prefs['backgroundImageScaled'] ?? null) ? $prefs['backgroundImageScaled'] : [];
        $image = null;

        if ($scaled !== []) {
            usort($scaled, function ($a, $b) {
                $wa = (int) ($a['width'] ?? 0);
                $wb = (int) ($b['width'] ?? 0);

                return abs($wa - 1920) <=> abs($wb - 1920);
            });
            $candidate = $scaled[0]['url'] ?? null;
            if (is_string($candidate) && $candidate !== '') {
                $image = $candidate;
            }
        }

        if ($image === null && is_string($prefs['backgroundImage'] ?? null) && $prefs['backgroundImage'] !== '') {
            $image = $prefs['backgroundImage'];
        }

        $color = is_string($prefs['backgroundColor'] ?? null) && $prefs['backgroundColor'] !== ''
            ? $prefs['backgroundColor']
            : null;
        $top = is_string($prefs['backgroundTopColor'] ?? null) && $prefs['backgroundTopColor'] !== ''
            ? $prefs['backgroundTopColor']
            : null;
        $bottom = is_string($prefs['backgroundBottomColor'] ?? null) && $prefs['backgroundBottomColor'] !== ''
            ? $prefs['backgroundBottomColor']
            : null;
        $brightness = is_string($prefs['backgroundBrightness'] ?? null) && $prefs['backgroundBrightness'] !== ''
            ? $prefs['backgroundBrightness']
            : 'dark';

        return [
            'image' => $image,
            'color' => $color,
            'top' => $top,
            'bottom' => $bottom,
            'tile' => (bool) ($prefs['backgroundTile'] ?? false),
            'brightness' => $brightness,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function lists(string $boardId): array
    {
        $lists = $this->get("boards/{$boardId}/lists", [
            'filter' => 'open',
            'fields' => 'id,name,pos,closed',
        ]);

        return is_array($lists) ? array_values($lists) : [];
    }

    /** @return list<array<string, mixed>> */
    public function cards(string $boardId): array
    {
        // attachments=cover is required for cover.scaled preview URLs.
        $cards = $this->get("boards/{$boardId}/cards", [
            'filter' => 'open',
            'fields' => 'id,name,desc,idList,url,shortUrl,due,dueComplete,labels,idMembers,pos,closed,badges,cover,dateLastActivity',
            'attachments' => 'cover',
            'attachment_fields' => 'id,url,previews,mimeType,name',
        ]);

        return is_array($cards) ? array_values($cards) : [];
    }

    /**
     * Lightweight board snapshot for CRM live refresh (Trello → CRM).
     *
     * @return array{
     *   board: array<string, mixed>,
     *   lists: list<array<string, mixed>>,
     *   cards: list<array<string, mixed>>
     * }
     */
    public function boardLiveSnapshot(string $boardId): array
    {
        $board = $this->get("boards/{$boardId}", [
            'fields' => 'id,name,dateLastActivity',
        ]);
        $lists = $this->lists($boardId);
        $cards = $this->get("boards/{$boardId}/cards", [
            'filter' => 'open',
            'fields' => 'id,name,idList,pos,due,dueComplete,labels,idMembers,badges,cover,dateLastActivity',
            'attachments' => 'cover',
            'attachment_fields' => 'id,url,previews,mimeType,name',
        ]);
        $cards = is_array($cards) ? array_values($cards) : [];

        return [
            'board' => $board,
            'lists' => $lists,
            'cards' => $cards,
        ];
    }

    public function boardDateLastActivity(string $boardId): ?string
    {
        $board = $this->get("boards/{$boardId}", [
            'fields' => 'dateLastActivity',
        ]);
        $act = $board['dateLastActivity'] ?? null;

        return is_string($act) && $act !== '' ? $act : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tokenWebhooks(): array
    {
        $hooks = $this->get('tokens/'.$this->apiToken.'/webhooks');

        return is_array($hooks) ? array_values($hooks) : [];
    }

    /** @return array<string, mixed> */
    public function createWebhook(string $idModel, string $callbackUrl, string $description = 'Staffshaw CRM'): array
    {
        return $this->post('webhooks', [
            'idModel' => $idModel,
            'callbackURL' => $callbackUrl,
            'description' => $description,
            'active' => true,
        ]);
    }

    public function deleteWebhook(string $webhookId): void
    {
        $this->delete('webhooks/'.$webhookId);
    }

    /**
     * Download a cover preview with OAuth (browser cannot load trello.com attachment URLs directly).
     */
    public function downloadCoverPreview(string $cardId, string $attachmentId, string $previewId): array
    {
        $url = self::BASE.'/cards/'.$cardId.'/attachments/'.$attachmentId.'/previews/'.$previewId.'/download';
        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'OAuth oauth_consumer_key="'.$this->apiKey.'", oauth_token="'.$this->apiToken.'"',
            ])
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Could not download Trello cover image.');
        }

        return [
            'body' => $response->body(),
            'content_type' => $response->header('Content-Type') ?: 'image/jpeg',
        ];
    }

    /**
     * Pick cover refs for our authenticated proxy route.
     *
     * @return array{card_id:string,attachment_id:string,preview_id:string}|null
     */
    public static function cardCoverRefs(?array $card, int $preferredWidth = 300): ?array
    {
        if (! is_array($card)) {
            return null;
        }

        $cardId = (string) ($card['id'] ?? '');
        $cover = is_array($card['cover'] ?? null) ? $card['cover'] : null;
        if ($cardId === '' || ! $cover) {
            return null;
        }

        $attachmentId = (string) ($cover['idAttachment'] ?? '');
        $scaled = is_array($cover['scaled'] ?? null) ? $cover['scaled'] : [];

        // Fallback: cover attachment listed under attachments=cover
        if ($attachmentId === '' && ! empty($card['attachments'][0]['id'])) {
            $attachmentId = (string) $card['attachments'][0]['id'];
            if ($scaled === [] && is_array($card['attachments'][0]['previews'] ?? null)) {
                $scaled = $card['attachments'][0]['previews'];
            }
        }

        if ($attachmentId === '' || $scaled === []) {
            return null;
        }

        $target = max(100, $preferredWidth);
        usort($scaled, function ($a, $b) use ($target) {
            $wa = (int) ($a['width'] ?? 0);
            $wb = (int) ($b['width'] ?? 0);

            return abs($wa - $target) <=> abs($wb - $target);
        });

        $previewId = (string) ($scaled[0]['id'] ?? $scaled[0]['_id'] ?? '');
        if ($previewId === '') {
            // Parse from preview URL if id missing.
            $url = (string) ($scaled[0]['url'] ?? '');
            if (preg_match('#/previews/([^/]+)/#', $url, $m)) {
                $previewId = $m[1];
            }
        }

        if ($previewId === '') {
            return null;
        }

        return [
            'card_id' => $cardId,
            'attachment_id' => $attachmentId,
            'preview_id' => $previewId,
        ];
    }

    /** Best direct Trello preview URL (often 401 in browser without auth). */
    public static function cardCoverUrl(?array $cover): ?string
    {
        if (! is_array($cover)) {
            return null;
        }

        $scaled = $cover['scaled'] ?? null;
        if (is_array($scaled) && $scaled !== []) {
            usort($scaled, function ($a, $b) {
                $wa = (int) ($a['width'] ?? 0);
                $wb = (int) ($b['width'] ?? 0);

                return abs($wa - 300) <=> abs($wb - 300);
            });
            $url = $scaled[0]['url'] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        foreach (['sharedSourceUrl', 'url'] as $key) {
            $url = $cover[$key] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    public static function cardCoverColor(?array $cover): ?string
    {
        $color = is_array($cover) ? ($cover['color'] ?? null) : null;
        if (! is_string($color) || $color === '') {
            return null;
        }

        return match ($color) {
            'green' => '#4bce97',
            'yellow' => '#f5cd47',
            'orange' => '#fea362',
            'red' => '#f87168',
            'purple' => '#9f8fef',
            'blue' => '#579dff',
            'sky' => '#6cc3e0',
            'lime' => '#94c748',
            'pink' => '#e774bb',
            'black' => '#8590a2',
            default => null,
        };
    }

    /**
     * @return array{board: array<string, mixed>, lists: list<array<string, mixed>>, cards_by_list: array<string, list<array<string, mixed>>>}
     */
    public function boardWorkspace(string $boardId): array
    {
        $board = $this->board($boardId);
        $lists = $this->lists($boardId);
        $cards = $this->cards($boardId);

        $cardsByList = [];
        foreach ($lists as $list) {
            $cardsByList[$list['id']] = [];
        }
        foreach ($cards as $card) {
            $listId = (string) ($card['idList'] ?? '');
            if ($listId === '') {
                continue;
            }
            if (! isset($cardsByList[$listId])) {
                $cardsByList[$listId] = [];
            }
            $cardsByList[$listId][] = $card;
        }

        return [
            'board' => $board,
            'lists' => $lists,
            'cards_by_list' => $cardsByList,
        ];
    }

    /**
     * Move / reorder a card. $pos = "top"|"bottom"|numeric string.
     *
     * @return array<string, mixed>
     */
    public function moveCard(string $cardId, string $listId, string|int|float $pos = 'bottom', ?string $boardId = null): array
    {
        $params = [
            'idList' => $listId,
            'pos' => $pos,
        ];
        if ($boardId) {
            $params['idBoard'] = $boardId;
        }

        return $this->put('cards/'.$cardId, $params);
    }

    /**
     * Open lists of a board with their open cards' ids/positions ("Move card" popover).
     *
     * @return list<array{id: string, name: string, cards: list<array{id: string, pos: float|int}>}>
     */
    public function listsWithCardPositions(string $boardId): array
    {
        $lists = $this->get("boards/{$boardId}/lists", [
            'filter' => 'open',
            'fields' => 'id,name,pos',
            'cards' => 'open',
            'card_fields' => 'id,pos',
        ]);

        return array_map(fn (array $l) => [
            'id' => (string) ($l['id'] ?? ''),
            'name' => (string) ($l['name'] ?? ''),
            'cards' => array_values(array_map(fn (array $c) => [
                'id' => (string) ($c['id'] ?? ''),
                'pos' => $c['pos'] ?? 0,
            ], is_array($l['cards'] ?? null) ? $l['cards'] : [])),
        ], is_array($lists) ? array_values($lists) : []);
    }

    /** @return array<string, mixed> */
    public function card(string $cardId): array
    {
        return $this->get('cards/'.$cardId, [
            'fields' => 'id,name,desc,idList,idBoard,url,shortUrl,due,dueComplete,start,dueReminder,labels,idMembers,idLabels,pos,closed,badges,cover,dateLastActivity',
            'members' => 'true',
            'member_fields' => 'id,fullName,username,avatarUrl,initials',
            'attachments' => 'true',
            'attachment_fields' => 'id,name,url,bytes,mimeType,date,isUpload,previews,fileName',
            'checklists' => 'all',
            'checklist_fields' => 'id,name,pos,idCard',
            'checkItems' => 'all',
            'checkItem_fields' => 'id,name,state,pos,due,idMember',
            'board' => 'true',
            'board_fields' => 'id,name,url',
            'list' => 'true',
            'list_fields' => 'id,name',
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function cardComments(string $cardId): array
    {
        return array_values(array_filter(
            $this->cardActions($cardId),
            static fn (array $a): bool => ($a['type'] ?? '') === 'commentCard'
        ));
    }

    /**
     * Card activity feed (comments + attachments + moves + membership…).
     *
     * @return list<array<string, mixed>>
     */
    public function cardActions(string $cardId): array
    {
        $actions = $this->get('cards/'.$cardId.'/actions', [
            'filter' => implode(',', [
                'commentCard',
                'addAttachmentToCard',
                'deleteAttachmentFromCard',
                'createCard',
                'copyCard',
                'addMemberToCard',
                'removeMemberFromCard',
                'updateCard',
                'addChecklistToCard',
                'removeChecklistFromCard',
                'createCheckItem',
                'updateCheckItemStateOnCard',
                'moveCardToBoard',
                'moveCardFromBoard',
            ]),
            'limit' => 100,
        ]);

        return is_array($actions) ? array_values($actions) : [];
    }

    /** @return array<string, mixed> */
    public function addComment(string $cardId, string $text): array
    {
        return $this->post('cards/'.$cardId.'/actions/comments', [
            'text' => $text,
        ]);
    }

    public function deleteComment(string $actionId): void
    {
        $this->delete('actions/'.$actionId);
    }

    /** @return array<string, mixed> */
    public function updateComment(string $actionId, string $text): array
    {
        return $this->put('actions/'.$actionId.'/comments', [
            'text' => $text,
        ]);
    }

    /** Attach a remote URL to a card (Trello "Add link as attachment"). */
    public function attachUrl(string $cardId, string $url, ?string $name = null): array
    {
        $payload = ['url' => $url];
        if ($name !== null && $name !== '') {
            $payload['name'] = $name;
        }

        return $this->post('cards/'.$cardId.'/attachments', $payload);
    }

    /** @return list<array<string, mixed>> */
    public function reactionsSummary(string $actionId): array
    {
        $rows = $this->get('actions/'.$actionId.'/reactionsSummary');

        return is_array($rows) ? array_values($rows) : [];
    }

    /** @return array<string, mixed> */
    public function addReaction(string $actionId, string $native): array
    {
        return $this->postJson('actions/'.$actionId.'/reactions', [
            'native' => $native,
        ]);
    }

    public function deleteReaction(string $actionId, string $reactionId): void
    {
        $this->delete('actions/'.$actionId.'/reactions/'.$reactionId);
    }

    /**
     * @param  list<string>  $actionIds
     * @return array<string, list<array<string, mixed>>>
     */
    /**
     * Same shape as reactionsSummary (count + emoji per emoji), built from the reactions
     * that /actions?reactions=true already returned.
     *
     * @param  list<array<string, mixed>>  $comments
     * @return array<string, list<array<string, mixed>>>
     */
    public static function reactionsSummaryFromActions(array $comments): array
    {
        $out = [];
        foreach ($comments as $action) {
            $id = (string) ($action['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $rows = [];
            foreach ((array) ($action['reactions'] ?? []) as $reaction) {
                $emoji = is_array($reaction['emoji'] ?? null) ? $reaction['emoji'] : [];
                $key = (string) ($emoji['unified'] ?? $reaction['idEmoji'] ?? '');
                if ($key === '') {
                    continue;
                }
                $rows[$key] ??= [
                    'count' => 0,
                    'id' => $id.':'.$key,
                    'idEmoji' => (string) ($reaction['idEmoji'] ?? $key),
                    'idModel' => $id,
                    'emoji' => $emoji,
                ];
                $rows[$key]['count']++;
            }
            $out[$id] = array_values($rows);
        }

        return $out;
    }

    public function reactionsSummaryMap(array $actionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => is_string($id) ? $id : '',
            $actionIds
        ))));
        $ids = array_slice($ids, 0, 20);
        if ($ids === []) {
            return [];
        }

        $auth = [
            'key' => $this->apiKey,
            'token' => $this->apiToken,
        ];
        $responses = Http::pool(function (Pool $pool) use ($ids, $auth) {
            foreach ($ids as $id) {
                $pool->as($id)->acceptJson()->timeout(12)->get(
                    self::BASE.'/actions/'.$id.'/reactionsSummary',
                    $auth
                );
            }
        });

        $out = [];
        foreach ($ids as $id) {
            $res = $responses[$id] ?? null;
            if (! $res instanceof Response || $res->failed()) {
                $out[$id] = [];
                continue;
            }
            $json = $res->json();
            $out[$id] = is_array($json) ? array_values($json) : [];
        }

        return $out;
    }

    /**
     * @param  array<string, scalar|null|bool>  $fields
     * @return array<string, mixed>
     */
    public function updateCard(string $cardId, array $fields): array
    {
        return $this->put('cards/'.$cardId, $fields);
    }

    /** @return list<array<string, mixed>> */
    public function boardLabels(string $boardId): array
    {
        $labels = $this->get('boards/'.$boardId.'/labels', [
            'fields' => 'id,name,color',
            'limit' => 1000,
        ]);

        return is_array($labels) ? array_values($labels) : [];
    }

    /** Board members from the short-lived board meta cache. @return list<array<string, mixed>> */
    public function boardMembersCached(string $boardId): array
    {
        return $this->cachedBoardMeta($boardId)['board_members'];
    }

    /** @return list<array<string, mixed>> */
    public function boardMembers(string $boardId): array
    {
        $members = $this->get('boards/'.$boardId.'/members', [
            'fields' => 'id,fullName,username,avatarUrl,initials',
        ]);

        return is_array($members) ? array_values($members) : [];
    }

    public function addCardLabel(string $cardId, string $labelId): array
    {
        return $this->post('cards/'.$cardId.'/idLabels', ['value' => $labelId]);
    }

    public function removeCardLabel(string $cardId, string $labelId): void
    {
        $this->delete('cards/'.$cardId.'/idLabels/'.$labelId);
    }

    /** @return array<string, mixed> */
    public function createBoardLabel(string $boardId, string $name, string $color): array
    {
        $label = $this->post('boards/'.$boardId.'/labels', [
            'name' => $name,
            'color' => $color,
        ]);
        Cache::forget('trello.board.meta.'.$boardId);

        return $label;
    }

    /**
     * @param  array{name?: string, color?: string|null}  $fields
     * @return array<string, mixed>
     */
    public function updateBoardLabel(string $labelId, array $fields, ?string $boardId = null): array
    {
        $payload = [];
        if (array_key_exists('name', $fields)) {
            $payload['name'] = (string) $fields['name'];
        }
        if (array_key_exists('color', $fields)) {
            $payload['color'] = $fields['color'] ?? '';
        }
        $label = $this->put('labels/'.$labelId, $payload);
        if ($boardId) {
            Cache::forget('trello.board.meta.'.$boardId);
        }

        return $label;
    }

    /**
     * Create a card on a list (Trello POST /cards).
     *
     * @param  array{pos?: string|float|int, urlSource?: string}  $opts
     * @return array<string, mixed>
     */
    public function createCard(string $listId, string $name, array $opts = []): array
    {
        $payload = [
            'idList' => $listId,
            'name' => $name,
            'pos' => $opts['pos'] ?? 'bottom',
        ];
        if (! empty($opts['urlSource']) && is_string($opts['urlSource'])) {
            $payload['urlSource'] = $opts['urlSource'];
        }

        return $this->post('cards', $payload);
    }

    public function addCardMember(string $cardId, string $memberId): array
    {
        return $this->post('cards/'.$cardId.'/idMembers', ['value' => $memberId]);
    }

    public function removeCardMember(string $cardId, string $memberId): void
    {
        $this->delete('cards/'.$cardId.'/idMembers/'.$memberId);
    }

    /** @return array<string, mixed> */
    /** $sourceId copies the items of an existing checklist ("Copy items from…"). */
    public function createChecklist(string $cardId, string $name, ?string $sourceId = null): array
    {
        $params = ['name' => $name];
        if ($sourceId) {
            $params['idChecklistSource'] = $sourceId;
        }

        return $this->post('cards/'.$cardId.'/checklists', $params);
    }

    public function deleteChecklist(string $checklistId): void
    {
        $this->delete('checklists/'.$checklistId);
    }

    /** @return array<string, mixed> */
    public function addCheckItem(string $checklistId, string $name): array
    {
        return $this->post('checklists/'.$checklistId.'/checkItems', ['name' => $name]);
    }

    /** @return array<string, mixed> */
    public function updateCheckItem(string $cardId, string $checkItemId, string $state): array
    {
        return $this->put('cards/'.$cardId.'/checkItem/'.$checkItemId, [
            'state' => $state === 'complete' ? 'complete' : 'incomplete',
        ]);
    }

    public function deleteCheckItem(string $checklistId, string $checkItemId): void
    {
        $this->delete('checklists/'.$checklistId.'/checkItems/'.$checkItemId);
    }

    /**
     * @return array{body:string,content_type:string}
     */
    public function downloadAttachment(string $cardId, string $attachmentId): array
    {
        $url = self::BASE.'/cards/'.$cardId.'/attachments/'.$attachmentId.'/download';
        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => 'OAuth oauth_consumer_key="'.$this->apiKey.'", oauth_token="'.$this->apiToken.'"',
            ])
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Could not download Trello attachment.');
        }

        return [
            'body' => $response->body(),
            'content_type' => $response->header('Content-Type') ?: 'application/octet-stream',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadAttachment(string $cardId, string $filePath, string $fileName): array
    {
        $response = Http::timeout(90)
            ->acceptJson()
            ->attach('file', file_get_contents($filePath), $fileName)
            ->post(self::BASE.'/cards/'.$cardId.'/attachments', [
                'key' => $this->apiKey,
                'token' => $this->apiToken,
            ]);

        if ($response->failed()) {
            $body = trim((string) $response->body());
            throw new RuntimeException('Trello upload failed: '.($body !== '' ? \Illuminate\Support\Str::limit($body, 180) : 'HTTP '.$response->status()));
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Trello upload returned an unexpected response.');
        }

        return $json;
    }

    public function deleteAttachment(string $cardId, string $attachmentId): void
    {
        $this->delete('cards/'.$cardId.'/attachments/'.$attachmentId);
    }

    /** @return array<string, mixed> */
    public function updateAttachment(string $cardId, string $attachmentId, string $name): array
    {
        return $this->put('cards/'.$cardId.'/attachments/'.$attachmentId, [
            'name' => $name,
        ]);
    }

    /**
     * Set or clear card cover from an attachment.
     *
     * @return array<string, mixed>
     */
    public function setCardCoverAttachment(string $cardId, ?string $attachmentId): array
    {
        return $this->put('cards/'.$cardId, [
            'idAttachmentCover' => $attachmentId ?? '',
        ]);
    }

    /**
     * Set the card cover (Trello "Cover" popover): an attachment or a color, plus size.
     *
     * @param  array{idAttachment?: string, color?: string, size?: string}  $cover
     * @return array<string, mixed>
     */
    public function setCardCover(string $cardId, array $cover): array
    {
        return $this->putJson('cards/'.$cardId, ['cover' => $cover]);
    }

    /** Remove an image or color cover. @return array<string, mixed> */
    public function removeCardCover(string $cardId): array
    {
        try {
            return $this->putJson('cards/'.$cardId, ['cover' => ['idAttachment' => null, 'color' => null]]);
        } catch (RuntimeException) {
            return $this->setCardCoverAttachment($cardId, null);
        }
    }

    /**
     * Full card workspace for CRM modal.
     * Card + activity load in parallel; board labels/members/lists are cached.
     */
    public function cardWorkspace(string $cardId, ?string $boardIdHint = null): array
    {
        $auth = [
            'key' => $this->apiKey,
            'token' => $this->apiToken,
        ];

        $actionFilter = implode(',', [
            'commentCard',
            'addAttachmentToCard',
            'deleteAttachmentFromCard',
            'createCard',
            'copyCard',
            'addMemberToCard',
            'removeMemberFromCard',
            'updateCard',
            'addChecklistToCard',
            'removeChecklistFromCard',
            'createCheckItem',
            'updateCheckItemStateOnCard',
            'moveCardToBoard',
            'moveCardFromBoard',
        ]);

        // Parallel: card detail + activity (biggest localhost win vs 5 sequential Trello round-trips).
        $responses = Http::pool(fn (Pool $pool) => [
            $pool->as('card')->acceptJson()->timeout(25)->get(self::BASE.'/cards/'.$cardId, array_merge($auth, [
                'fields' => 'id,name,desc,idList,idBoard,url,shortUrl,due,dueComplete,start,dueReminder,labels,idMembers,idLabels,pos,closed,badges,cover,dateLastActivity,subscribed',
                'members' => 'true',
                'member_fields' => 'id,fullName,username,avatarUrl,initials',
                'attachments' => 'true',
                'attachment_fields' => 'id,name,url,bytes,mimeType,date,isUpload,previews,fileName',
                'checklists' => 'all',
                'checklist_fields' => 'id,name,pos,idCard',
                'checkItems' => 'all',
                'checkItem_fields' => 'id,name,state,pos,due,idMember',
                'board' => 'true',
                'board_fields' => 'id,name,url',
                'list' => 'true',
                'list_fields' => 'id,name',
            ])),
            // reactions=true returns each comment's reactions inline, so no extra round trip per comment
            $pool->as('actions')->acceptJson()->timeout(25)->get(self::BASE.'/cards/'.$cardId.'/actions', array_merge($auth, [
                'filter' => $actionFilter,
                'limit' => 100,
                'reactions' => 'true',
            ])),
        ]);

        $card = $this->jsonFromResponse($responses['card'] ?? null, 'card');
        $activityRaw = $this->jsonFromResponse($responses['actions'] ?? null, 'actions');
        $activity = is_array($activityRaw) ? array_values($activityRaw) : [];
        $comments = array_values(array_filter(
            $activity,
            static fn (array $a): bool => ($a['type'] ?? '') === 'commentCard'
        ));
        $reactions = self::reactionsSummaryFromActions($comments);

        $boardId = (string) ($card['idBoard'] ?? $boardIdHint ?? '');
        $boardMeta = $boardId !== ''
            ? $this->cachedBoardMeta($boardId)
            : ['board_labels' => [], 'board_members' => [], 'lists' => []];

        return [
            'card' => $card,
            'comments' => $comments,
            'activity' => $activity,
            'reactions' => $reactions,
            'checklists' => is_array($card['checklists'] ?? null) ? array_values($card['checklists']) : [],
            'attachments' => is_array($card['attachments'] ?? null) ? array_values($card['attachments']) : [],
            'members' => is_array($card['members'] ?? null) ? array_values($card['members']) : [],
            'labels' => is_array($card['labels'] ?? null) ? array_values($card['labels']) : [],
            'board_labels' => $boardMeta['board_labels'],
            'board_members' => $boardMeta['board_members'],
            'lists' => $boardMeta['lists'],
        ];
    }

    /**
     * @return array{board_labels: list<array<string, mixed>>, board_members: list<array<string, mixed>>, lists: list<array<string, mixed>>}
     */
    private function cachedBoardMeta(string $boardId): array
    {
        return Cache::remember('trello.board.meta.'.$boardId, 120, function () use ($boardId) {
            $auth = [
                'key' => $this->apiKey,
                'token' => $this->apiToken,
            ];

            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('labels')->acceptJson()->timeout(20)->get(self::BASE.'/boards/'.$boardId.'/labels', array_merge($auth, [
                    'fields' => 'id,name,color',
                    'limit' => 1000,
                ])),
                $pool->as('members')->acceptJson()->timeout(20)->get(self::BASE.'/boards/'.$boardId.'/members', array_merge($auth, [
                    'fields' => 'id,fullName,username,avatarUrl,initials',
                ])),
                $pool->as('lists')->acceptJson()->timeout(20)->get(self::BASE.'/boards/'.$boardId.'/lists', array_merge($auth, [
                    'filter' => 'open',
                    'fields' => 'id,name,pos,closed',
                ])),
            ]);

            $labels = $this->jsonFromResponse($responses['labels'] ?? null, 'labels');
            $members = $this->jsonFromResponse($responses['members'] ?? null, 'members');
            $lists = $this->jsonFromResponse($responses['lists'] ?? null, 'lists');

            return [
                'board_labels' => is_array($labels) ? array_values($labels) : [],
                'board_members' => is_array($members) ? array_values($members) : [],
                'lists' => is_array($lists) ? array_values($lists) : [],
            ];
        });
    }

    /** @return array<string, mixed>|list<mixed> */
    private function jsonFromResponse(mixed $response, string $label): array
    {
        if (! $response instanceof Response) {
            throw new RuntimeException('Trello API error: missing '.$label.' response.');
        }
        if ($response->failed()) {
            $status = $response->status();
            $body = trim((string) $response->body());
            $hint = $status === 401
                ? 'Invalid API key or token.'
                : ($body !== '' ? \Illuminate\Support\Str::limit($body, 180) : "HTTP {$status}");
            throw new RuntimeException('Trello API error: '.$hint);
        }
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Trello API returned an unexpected '.$label.' response.');
        }

        return $json;
    }

    /** @param array<string, scalar|null|bool> $query */
    private function get(string $path, array $query = []): array
    {
        return $this->request('get', $path, $query);
    }

    /** @param array<string, scalar|null|bool> $query */
    private function put(string $path, array $query = []): array
    {
        return $this->request('put', $path, $query);
    }

    /** @param array<string, scalar|null|bool> $query */
    private function post(string $path, array $query = []): array
    {
        return $this->request('post', $path, $query);
    }

    /** @param array<string, mixed> $body */
    private function putJson(string $path, array $body = []): array
    {
        return $this->postJson($path, $body, 'put');
    }

    /** @param array<string, mixed> $body */
    private function postJson(string $path, array $body = [], string $method = 'post'): array
    {
        $url = self::BASE.'/'.ltrim($path, '/').'?'.http_build_query([
            'key' => $this->apiKey,
            'token' => $this->apiToken,
        ]);
        $response = Http::timeout(30)
            ->acceptJson()
            ->asJson()
            ->{$method}($url, $body);

        if ($response->failed()) {
            $status = $response->status();
            $bodyText = trim((string) $response->body());
            $hint = $status === 401
                ? 'Invalid API key or token.'
                : ($bodyText !== '' ? \Illuminate\Support\Str::limit($bodyText, 180) : "HTTP {$status}");
            throw new RuntimeException('Trello API error: '.$hint);
        }

        if ($response->status() === 204 || $response->body() === '') {
            return [];
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Trello API returned an unexpected response.');
        }

        return $json;
    }

    /** @param array<string, scalar|null|bool> $query */
    private function delete(string $path, array $query = []): array
    {
        return $this->request('delete', $path, $query);
    }

    /** @param array<string, scalar|null|bool> $query */
    private function request(string $method, string $path, array $query = []): array
    {
        $params = array_merge($query, [
            'key' => $this->apiKey,
            'token' => $this->apiToken,
        ]);
        foreach ($params as $k => $v) {
            if (is_bool($v)) {
                $params[$k] = $v ? 'true' : 'false';
            }
        }

        $url = self::BASE.'/'.ltrim($path, '/');
        $http = Http::timeout(30)->acceptJson();
        $response = match ($method) {
            'get' => $http->get($url, $params),
            'delete' => $http->delete($url, $params),
            'post' => $http->asForm()->post($url, $params),
            'put' => $http->asForm()->put($url, $params),
            default => throw new RuntimeException('Unsupported HTTP method.'),
        };

        if ($response->failed()) {
            $status = $response->status();
            $body = trim((string) $response->body());
            $hint = $status === 401
                ? 'Invalid API key or token.'
                : ($body !== '' ? \Illuminate\Support\Str::limit($body, 180) : "HTTP {$status}");
            throw new RuntimeException('Trello API error: '.$hint);
        }

        if ($response->status() === 204 || $response->body() === '') {
            return [];
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Trello API returned an unexpected response.');
        }

        return $json;
    }
}
