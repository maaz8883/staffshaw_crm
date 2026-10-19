<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamTrelloIntegration;
use App\Models\TrelloBoardWebhook;
use App\Services\Trello\TrelloActivityRecorder;
use App\Services\Trello\TrelloApiClient;
use App\Services\Trello\TrelloCardToClientSync;
use App\Support\AuthScope;
use App\Support\TeamTrelloAccounts;
use App\Support\TrelloLive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class TrelloController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $teams = $this->accessibleParentTeams();
        if ($teams->isEmpty()) {
            abort(403, 'No team access for Trello.');
        }

        $team = $teams->firstWhere('id', (int) $request->query('team')) ?? $this->defaultTeam($teams);

        $integration = $team->trelloIntegration;
        $boards = [];
        $error = null;
        $member = null;

        if ($integration?->isReady()) {
            try {
                $client = TrelloApiClient::forIntegration($integration);
                $member = $client->me();
                $boards = $client->boards();
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        return view('admin.trello.index', [
            'teams' => $teams,
            'team' => $team,
            'integration' => $integration,
            'boards' => $boards,
            'member' => $member,
            'error' => $error,
            'canManage' => $this->canManageTeam($team),
            'suggestedEmail' => TeamTrelloAccounts::suggestedEmail($team),
        ]);
    }

    public function board(Request $request, string $boardId): View|RedirectResponse
    {
        $teams = $this->accessibleParentTeams();
        $teamId = (int) $request->query('team');
        $team = $teams->firstWhere('id', $teamId) ?? $this->defaultTeam($teams);

        if (! $team) {
            abort(403);
        }

        $integration = $team->trelloIntegration;
        if (! $integration?->isReady()) {
            return redirect()
                ->route('admin.trello.index', ['team' => $team->id])
                ->with('error', 'Connect Trello for this team first.');
        }

        try {
            $workspace = TrelloApiClient::forIntegration($integration)->boardWorkspace($boardId);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.trello.index', ['team' => $team->id])
                ->with('error', $e->getMessage());
        }

        $cardsByList = [];
        foreach ($workspace['cards_by_list'] as $listId => $cards) {
            $cardsByList[$listId] = array_map(fn ($card) => $this->cardTile($team, (array) $card), $cards);
        }

        try {
            $boardMembers = TrelloApiClient::forIntegration($integration)->boardMembersCached($boardId);
        } catch (RuntimeException) {
            $boardMembers = [];
        }

        return view('admin.trello.board', [
            'team' => $team,
            'integration' => $integration,
            'board' => $workspace['board'],
            'boardBg' => TrelloApiClient::boardBackground($workspace['board']),
            'lists' => $workspace['lists'],
            'cardsByList' => $cardsByList,
            'boardMembers' => array_map(fn (array $m) => [
                'id' => $m['id'] ?? '',
                'fullName' => $m['fullName'] ?? '',
                'username' => $m['username'] ?? '',
                'initials' => $m['initials'] ?? '',
            ], $boardMembers),
            'canManage' => $this->canManageTeam($team),
        ]);
    }

    /**
     * Fields a board card tile needs (labels, cover, due, badges, members).
     *
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function cardTile(Team $team, array $card): array
    {
        $refs = TrelloApiClient::cardCoverRefs($card);
        $cover = is_array($card['cover'] ?? null) ? $card['cover'] : null;
        $badges = is_array($card['badges'] ?? null) ? $card['badges'] : [];

        return [
            'id' => $card['id'] ?? '',
            'name' => $card['name'] ?? '',
            'idList' => $card['idList'] ?? '',
            'pos' => $card['pos'] ?? null,
            'due' => $card['due'] ?? null,
            'dueComplete' => (bool) ($card['dueComplete'] ?? false),
            'dateLastActivity' => $card['dateLastActivity'] ?? null,
            'labels' => is_array($card['labels'] ?? null) ? $card['labels'] : [],
            'idMembers' => is_array($card['idMembers'] ?? null) ? array_values($card['idMembers']) : [],
            'badges' => [
                'description' => (bool) ($badges['description'] ?? false),
                'comments' => (int) ($badges['comments'] ?? 0),
                'attachments' => (int) ($badges['attachments'] ?? 0),
                'checkItems' => (int) ($badges['checkItems'] ?? 0),
                'checkItemsChecked' => (int) ($badges['checkItemsChecked'] ?? 0),
                'subscribed' => (bool) ($badges['subscribed'] ?? false),
            ],
            'cover_proxy_url' => $refs
                ? route('admin.trello.cover', [
                    'team' => $team->id,
                    'cardId' => $refs['card_id'],
                    'attachmentId' => $refs['attachment_id'],
                    'previewId' => $refs['preview_id'],
                ])
                : null,
            'cover_color' => TrelloApiClient::cardCoverColor($cover),
        ];
    }

    public function live(Team $team, string $boardId): JsonResponse
    {
        abort_unless(TeamTrelloAccounts::userCanUse($team), 403);

        $integration = $team->trelloIntegration;
        if (! $integration?->isReady()) {
            return response()->json(['ok' => false, 'message' => 'Trello is not connected.'], 422);
        }

        try {
            $snap = TrelloApiClient::forIntegration($integration)->boardLiveSnapshot($boardId);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $cards = array_map(fn (array $card) => $this->cardTile($team, $card), $snap['cards']);

        return response()->json([
            'ok' => true,
            'board' => [
                'id' => $snap['board']['id'] ?? $boardId,
                'name' => $snap['board']['name'] ?? '',
                'dateLastActivity' => $snap['board']['dateLastActivity'] ?? null,
            ],
            'lists' => array_map(fn (array $l) => [
                'id' => $l['id'] ?? '',
                'name' => $l['name'] ?? '',
                'pos' => $l['pos'] ?? null,
            ], $snap['lists']),
            'cards' => $cards,
        ]);
    }

    /**
     * Register Trello board webhook when APP_URL is public.
     * Localhost uses short tick polls (SSE blocked other PHP requests on Laragon).
     */
    public function ensureLive(Team $team, string $boardId): JsonResponse
    {
        abort_unless(TeamTrelloAccounts::userCanUse($team), 403);

        $integration = $team->trelloIntegration;
        if (! $integration?->isReady()) {
            return response()->json(['ok' => false, 'message' => 'Trello is not connected.'], 422);
        }

        $mode = 'tick';
        $webhookOk = false;
        $message = null;

        if (TrelloLive::canReceiveWebhooks()) {
            try {
                $webhookOk = $this->ensureBoardWebhook($team, $integration, $boardId);
                $mode = $webhookOk ? 'webhook' : 'tick';
            } catch (RuntimeException $e) {
                $message = $e->getMessage();
                $mode = 'tick';
            }
        } else {
            $message = 'Public APP_URL required for Trello webhooks; using short tick polls.';
        }

        try {
            $act = TrelloApiClient::forIntegration($integration)->boardDateLastActivity($boardId);
            if ($act) {
                Cache::put(TrelloLive::activityPrevKey($boardId), $act, 3600);
                Cache::put(TrelloLive::activityKey($boardId), $act, 3600);
            }
        } catch (RuntimeException $e) {
            $message = $message ?: $e->getMessage();
        }

        return response()->json([
            'ok' => true,
            'mode' => $mode,
            'webhook' => $webhookOk,
            'tick_url' => url('admin/trello/teams/'.$team->id.'/boards/'.$boardId.'/tick'),
            'message' => $message,
        ]);
    }

    /**
     * Tiny change-check for live board sync (returns immediately — no long-lived connection).
     */
    public function tick(Team $team, string $boardId): JsonResponse
    {
        abort_unless(TeamTrelloAccounts::userCanUse($team), 403);

        $integration = $team->trelloIntegration;
        if (! $integration?->isReady()) {
            return response()->json(['ok' => false, 'message' => 'Trello is not connected.'], 422);
        }

        // Release session early so parallel polls (notifications, cards) are not blocked.
        try {
            if (request()->hasSession()) {
                request()->session()->save();
            }
        } catch (\Throwable) {
            // ignore
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $client = TrelloApiClient::forIntegration($integration);
        $changed = false;
        $activity = null;

        try {
            $activity = Cache::remember(TrelloLive::activityKey($boardId), 2, function () use ($client, $boardId) {
                return $client->boardDateLastActivity($boardId);
            });
            $prev = Cache::get(TrelloLive::activityPrevKey($boardId));
            if (is_string($activity) && $activity !== '' && $activity !== $prev) {
                Cache::put(TrelloLive::activityPrevKey($boardId), $activity, 3600);
                TrelloLive::bump($boardId, $activity);
                $changed = true;
            }
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $version = TrelloLive::version($boardId);

        return response()->json([
            'ok' => true,
            'v' => $version,
            'activity' => $activity,
            'changed' => $changed,
        ]);
    }

    private function ensureBoardWebhook(Team $team, TeamTrelloIntegration $integration, string $boardId): bool
    {
        $client = TrelloApiClient::forIntegration($integration);
        $row = TrelloBoardWebhook::query()->firstOrNew([
            'team_id' => $team->id,
            'board_id' => $boardId,
        ]);
        if (! $row->exists || ! filled($row->secret)) {
            $row->secret = Str::random(40);
        }
        $row->active = true;
        // Save before asking Trello: while creating the webhook Trello immediately sends a HEAD
        // to the callback URL, and TrelloWebhookController only answers 200 for a secret it can
        // find in the database. Saving afterwards made every webhook creation fail.
        $row->save();

        $callback = url('/webhooks/trello/'.$team->id.'/'.$row->secret);

        // Reuse existing Trello webhook with same callback if present.
        try {
            $existing = $client->tokenWebhooks();
        } catch (RuntimeException $e) {
            throw $e;
        }

        foreach ($existing as $hook) {
            $cb = (string) ($hook['callbackURL'] ?? '');
            $model = (string) ($hook['idModel'] ?? '');
            if ($model === $boardId && $cb === $callback && ! empty($hook['id'])) {
                $row->webhook_id = (string) $hook['id'];
                $row->save();

                return true;
            }
        }

        // Delete stale CRM-tracked webhook id if Trello no longer has it.
        if (filled($row->webhook_id)) {
            try {
                $client->deleteWebhook((string) $row->webhook_id);
            } catch (RuntimeException) {
                // already gone
            }
            $row->webhook_id = null;
        }

        $created = $client->createWebhook($boardId, $callback, 'Staffshaw CRM '.$team->name);
        $row->webhook_id = isset($created['id']) ? (string) $created['id'] : null;
        $row->save();

        return filled($row->webhook_id);
    }

    public function cover(Team $team, string $cardId, string $attachmentId, string $previewId): Response
    {
        abort_unless(TeamTrelloAccounts::userCanUse($team), 403);

        $integration = $team->trelloIntegration;
        abort_unless($integration?->isReady(), 404);

        try {
            $file = TrelloApiClient::forIntegration($integration)
                ->downloadCoverPreview($cardId, $attachmentId, $previewId);
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        return response($file['body'], 200, [
            'Content-Type' => $file['content_type'],
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function moveCard(Request $request, Team $team, string $cardId): JsonResponse
    {
        abort_unless(TeamTrelloAccounts::userCanUse($team), 403);

        $integration = $team->trelloIntegration;
        if (! $integration?->isReady()) {
            return response()->json(['ok' => false, 'message' => 'Trello is not connected.'], 422);
        }

        $data = $request->validate([
            'list_id' => ['required', 'string', 'max:64'],
            'board_id' => ['nullable', 'string', 'max:64'],
            'from_list_id' => ['nullable', 'string', 'max:64'],
            'pos' => ['nullable'],
        ]);

        $pos = $data['pos'] ?? 'bottom';
        if (is_numeric($pos)) {
            $pos = 0 + $pos;
        } else {
            $pos = in_array((string) $pos, ['top', 'bottom'], true) ? (string) $pos : 'bottom';
        }

        try {
            $card = TrelloApiClient::forIntegration($integration)
                ->moveCard($cardId, $data['list_id'], $pos, $data['board_id'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        // Reordering inside the same list isn't worth an activity line
        if (($data['from_list_id'] ?? null) !== $data['list_id'] || ! empty($data['board_id'])) {
            TrelloActivityRecorder::record($team, 'card_moved', [
                'card_id' => $cardId,
                'list_id' => $data['list_id'],
                'board_id' => $data['board_id'] ?? null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'card' => [
                'id' => $card['id'] ?? $cardId,
                'idBoard' => $card['idBoard'] ?? ($data['board_id'] ?? null),
                'idList' => $card['idList'] ?? $data['list_id'],
                'pos' => $card['pos'] ?? $pos,
            ],
        ]);
    }

    /**
     * Title + favicon for a URL in a card comment (Trello-style smart link chip). Cached for a day.
     * Only public http(s) hosts are fetched, so this can't be used to reach internal addresses.
     */
    public function linkPreview(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'url:http,https', 'max:2048']]);
        $url = $data['url'];

        $preview = Cache::remember('trello.link-preview.v5.'.md5($url), now()->addDay(), function () use ($url) {
            $fallback = ['title' => null, 'icon' => null, 'description' => null, 'site_name' => null];
            // Head for meta tags, plus the start of the body for a fallback description (max 256 KB).
            $html = self::fetchPublic($url, 'html');
            if ($html === null) {
                return $fallback;
            }

            // <meta property|name="X" content="..."> in either attribute order
            $meta = function (string $key) use ($html): ?string {
                $k = preg_quote($key, '/');
                foreach (["/<meta[^>]+(?:property|name)=[\"']{$k}[\"'][^>]+content=[\"']([^\"']*)/i", "/<meta[^>]+content=[\"']([^\"']*)[\"'][^>]+(?:property|name)=[\"']{$k}[\"']/i"] as $re) {
                    if (preg_match($re, $html, $m)) {
                        $v = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
                        if ($v !== '') {
                            return $v;
                        }
                    }
                }

                return null;
            };

            $title = $meta('og:title');
            if ($title === null && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
                $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5));
            }
            $description = $meta('og:description') ?? $meta('description');
            $siteName = $meta('og:site_name');

            // oEmbed (what Trello uses): page title + provider name, e.g. "Home" / "Mike & Carey".
            if (preg_match('/<link[^>]+type=["\']application\/json\+oembed["\'][^>]*>/i', $html, $m)
                && preg_match('/href=["\']([^"\']+)/i', $m[0], $h)) {
                $oembed = json_decode((string) self::fetchPublic(html_entity_decode($h[1]), 'json'), true);
                if (is_array($oembed)) {
                    $title = trim(html_entity_decode((string) ($oembed['title'] ?? ''), ENT_QUOTES | ENT_HTML5)) ?: $title;
                    $siteName ??= trim(html_entity_decode((string) ($oembed['provider_name'] ?? ''), ENT_QUOTES | ENT_HTML5)) ?: null;
                }
            }

            // No meta description: first real paragraph of the page (like Trello's preview).
            if ($description === null && preg_match_all('/<p(?:\s[^>]*)?>(.*?)<\/p>/is', $html, $paras)) {
                foreach ($paras[1] as $p) {
                    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($p), ENT_QUOTES | ENT_HTML5)));
                    if (mb_strlen($text) >= 60) {
                        $description = $text;
                        break;
                    }
                }
            }

            $parts = parse_url($url);
            $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            $icon = $origin.'/favicon.ico';
            if (preg_match('/<link[^>]+rel=["\'](?:shortcut icon|icon|apple-touch-icon)["\'][^>]*>/i', $html, $m)
                && preg_match('/href=["\']([^"\']+)/i', $m[0], $h)) {
                $href = html_entity_decode($h[1]);
                $icon = match (true) {
                    str_starts_with($href, 'http://'), str_starts_with($href, 'https://') => $href,
                    str_starts_with($href, '//') => $parts['scheme'].':'.$href,
                    str_starts_with($href, '/') => $origin.$href,
                    default => $origin.'/'.$href,
                };
            }

            return [
                'title' => $title ? Str::limit($title, 120) : null,
                'icon' => $icon,
                'description' => $description ? Str::limit($description, 300) : null,
                'site_name' => $siteName ? Str::limit($siteName, 80) : null,
            ];
        });

        return response()->json(['ok' => true] + $preview);
    }

    /**
     * GET a public URL (redirects re-checked), reading at most 256 KB or until $stopAt appears.
     * Returns null on any failure or when the content type doesn't contain $type.
     */
    private static function fetchPublic(string $url, string $type, ?string $stopAt = null): ?string
    {
        if (! self::isPublicUrl($url)) {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; StaffShawCRM link preview)'])
                ->withOptions([
                    'stream' => true,
                    'allow_redirects' => [
                        'max' => 3,
                        'on_redirect' => function ($req, $res, $uri) {
                            if (! self::isPublicUrl((string) $uri)) {
                                throw new RuntimeException('Redirect to a non-public address.');
                            }
                        },
                    ],
                ])
                ->get($url);
            if (! $response->successful() || ! str_contains(strtolower($response->header('Content-Type')), $type)) {
                return null;
            }
            $stream = $response->toPsrResponse()->getBody();
            $body = '';
            while (! $stream->eof() && strlen($body) < 262144 && ($stopAt === null || ! str_contains($body, $stopAt))) {
                $body .= $stream->read(16384);
            }

            return $body;
        } catch (\Throwable) {
            return null;
        }
    }

    /** True when the URL's host resolves only to public IPs (blocks localhost / private ranges). */
    private static function isPublicUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }
        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * "Move card" popover data: the team's open boards, plus lists (with card positions) of one board.
     */
    public function moveTargets(Request $request, Team $team): JsonResponse
    {
        abort_unless(TeamTrelloAccounts::userCanUse($team), 403);

        $integration = $team->trelloIntegration;
        if (! $integration?->isReady()) {
            return response()->json(['ok' => false, 'message' => 'Trello is not connected.'], 422);
        }

        $data = $request->validate([
            'board_id' => ['required', 'string', 'max:64'],
            'with_boards' => ['nullable', 'boolean'],
        ]);

        try {
            $client = TrelloApiClient::forIntegration($integration);
            $boards = $request->boolean('with_boards')
                ? array_map(fn (array $b) => ['id' => $b['id'] ?? '', 'name' => $b['name'] ?? ''], $client->boards())
                : null;
            $lists = $client->listsWithCardPositions($data['board_id']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'boards' => $boards, 'lists' => $lists]);
    }

    public function connections(): View
    {
        abort_unless(AuthScope::isAdmin(), 403);

        // Main teams and sub-teams: a sub-team can have its own Trello account
        $teams = Team::query()
            ->with(['trelloIntegration', 'company:id,name', 'parentTeam:id,name'])
            ->orderBy('name')
            ->get();

        $suggestedEmails = [];
        foreach ($teams as $t) {
            $suggestedEmails[$t->id] = TeamTrelloAccounts::suggestedEmail($t);
        }

        return view('admin.trello.connections', compact('teams', 'suggestedEmails'));
    }

    public function edit(Team $team): View
    {
        $this->assertCanManage($team);

        $integration = $team->trelloIntegration;

        return view('admin.trello.connect', [
            'team' => $team,
            'integration' => $integration,
            'suggestedEmail' => TeamTrelloAccounts::suggestedEmail($team),
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->assertCanManage($team);

        $existing = $team->trelloIntegration;
        // Saved credentials that can't be decrypted (other APP_KEY) count as missing: ask for new ones
        $reuse = $existing?->hasReadableCredentials() ?? false;
        $rules = [
            'account_email' => ['nullable', 'email', 'max:190'],
            'api_key' => [$reuse ? 'nullable' : 'required', 'string', 'max:255'],
            'api_token' => [$reuse ? 'nullable' : 'required', 'string', 'max:255'],
        ];

        $data = $request->validate($rules);

        $apiKey = filled($data['api_key'] ?? null) ? trim($data['api_key']) : ($reuse ? $existing->api_key : null);
        $apiToken = filled($data['api_token'] ?? null) ? trim($data['api_token']) : ($reuse ? $existing->api_token : null);

        if (! filled($apiKey) || ! filled($apiToken)) {
            return back()->withInput()->with('error', 'API key and token are required.');
        }

        try {
            $member = (new TrelloApiClient($apiKey, $apiToken))->me();
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $payload = [
            'api_key' => $apiKey,
            'api_token' => $apiToken,
            'account_email' => $data['account_email'] ?: ($member['email'] ?? null),
            'trello_member_id' => $member['id'] ?? null,
            'trello_username' => $member['username'] ?? null,
            'trello_full_name' => $member['fullName'] ?? null,
            'is_active' => true,
            'connected_by' => Auth::id(),
            'connected_at' => now(),
            'last_verified_at' => now(),
        ];

        if ($existing && ! $reuse) {
            // Old values can't be decrypted (other APP_KEY). Eloquent decrypts the original value to
            // compare it with the new one, which crashes — so forget the unreadable originals and
            // just write the new encrypted credentials.
            $existing->setRawAttributes(
                array_merge($existing->getAttributes(), ['api_key' => null, 'api_token' => null]),
                true
            );
            $existing->fill($payload)->save();
        } else {
            TeamTrelloIntegration::query()->updateOrCreate(
                ['team_id' => $team->id],
                $payload
            );
        }

        return redirect()
            ->route('admin.trello.index', ['team' => $team->id])
            ->with('success', 'Trello connected for '.$team->name.'. Boards will load from this account.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->assertCanManage($team);

        $integration = $team->trelloIntegration;
        if ($integration?->isReady()) {
            try {
                $client = TrelloApiClient::forIntegration($integration);
                $hooks = TrelloBoardWebhook::query()->where('team_id', $team->id)->get();
                foreach ($hooks as $hook) {
                    if (filled($hook->webhook_id)) {
                        try {
                            $client->deleteWebhook((string) $hook->webhook_id);
                        } catch (RuntimeException) {
                            // ignore
                        }
                    }
                }
            } catch (RuntimeException) {
                // ignore remote cleanup errors
            }
        }
        TrelloBoardWebhook::query()->where('team_id', $team->id)->delete();
        $integration?->delete();

        return redirect()
            ->route('admin.trello.connections')
            ->with('success', 'Trello disconnected for '.$team->name.'.');
    }

    public function verify(Team $team): RedirectResponse
    {
        $this->assertCanManage($team);
        $integration = $team->trelloIntegration;

        if (! $integration?->isReady()) {
            return back()->with('error', 'No Trello credentials saved for this team.');
        }

        try {
            $member = TrelloApiClient::forIntegration($integration)->me();
            $integration->update([
                'trello_member_id' => $member['id'] ?? $integration->trello_member_id,
                'trello_username' => $member['username'] ?? $integration->trello_username,
                'trello_full_name' => $member['fullName'] ?? $integration->trello_full_name,
                'account_email' => $integration->account_email ?: ($member['email'] ?? null),
                'last_verified_at' => now(),
                'is_active' => true,
            ]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Connection verified for '.$team->name.'.');
    }

    public function syncCustomers(Team $team): RedirectResponse
    {
        abort_unless(AuthScope::isAdmin(), 403);

        try {
            $stats = app(TrelloCardToClientSync::class)->syncTeam($team);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.trello.index', ['team' => $team->id])
                ->with('error', $e->getMessage());
        }

        $boardLabel = ! empty($stats['filtered_boards'])
            ? implode(', ', $stats['filtered_boards'])
            : 'all boards';

        $msg = sprintf(
            'Trello → Clients for %s (%s): %d created, %d updated, %d skipped (%d cards).',
            $team->name,
            $boardLabel,
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
            $stats['cards']
        );

        if (! empty($stats['errors'])) {
            $msg .= ' Some boards failed: '.implode(' | ', array_slice($stats['errors'], 0, 3));
        }

        return redirect()
            ->route('admin.clients.index')
            ->with('success', $msg);
    }

    /** @return \Illuminate\Support\Collection<int, Team> */
    private function accessibleParentTeams()
    {
        // Own Trello per team, sub-teams without one use their parent's (see TeamTrelloAccounts)
        return TeamTrelloAccounts::teamsForUser();
    }

    /**
     * Team opened when none is picked: a connected main team first (e.g. Staffshaw), so Admin /
     * Manager don't land on an unconnected department just because it sorts first by name.
     *
     * @param  \Illuminate\Support\Collection<int, Team>  $teams
     */
    private function defaultTeam($teams): ?Team
    {
        return $teams->first(fn (Team $t) => $t->parent_team_id === null && $t->trelloIntegration?->isReady())
            ?? $teams->first(fn (Team $t) => $t->trelloIntegration?->isReady())
            ?? $teams->first();
    }

    private function canManageTeam(Team $team): bool
    {
        // Credentials connect/update/disconnect: Admin only (not Team Lead / Manager).
        return AuthScope::isAdmin();
    }

    private function assertCanManage(Team $team): void
    {
        abort_unless($this->canManageTeam($team), 403);
    }
}
