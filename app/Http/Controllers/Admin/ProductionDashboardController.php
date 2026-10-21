<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TrelloActivityLog;
use App\Services\Trello\TrelloApiClient;
use App\Support\AuthScope;
use App\Support\TeamTrelloAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use RuntimeException;

/**
 * Dashboard for the "Production" role (Design, Development, SEO…): the team's Trello work —
 * late / due today / due this week cards across all its boards — plus who did what.
 */
class ProductionDashboardController extends Controller
{
    public function show(): View
    {
        $teams = $this->trelloTeams();
        $user = Auth::user();

        $myActivity = TrelloActivityLog::query()
            ->where('user_id', $user->id)
            ->latest('occurred_at')
            ->limit(10)
            ->get();
        $teamActivity = TrelloActivityLog::query()
            ->with('user:id,name')
            ->whereIn('team_id', $teams->pluck('id'))
            ->latest('occurred_at')
            ->limit(15)
            ->get();

        return view('admin.production.dashboard', compact('teams', 'myActivity', 'teamActivity'));
    }

    /** Late / today / this week cards for one team's boards (JSON, loaded after the page). */
    public function overview(Request $request): JsonResponse
    {
        $teams = $this->trelloTeams();
        $team = $teams->firstWhere('id', (int) $request->query('team')) ?? $teams->first();
        if (! $team) {
            return response()->json(['ok' => false, 'message' => 'No Trello team for your account.'], 404);
        }
        $integration = $team->trelloIntegration;
        if (! $integration?->isReady()) {
            return response()->json(['ok' => false, 'message' => 'Trello is not connected for '.$team->name.'.'], 422);
        }

        // Don't hold the session lock while waiting on Trello
        if ($request->hasSession()) {
            $request->session()->save();
        }

        $cacheKey = self::overviewCacheKey($team);
        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey); // "Refresh" button: always fetch from Trello
        }

        try {
            $data = Cache::remember($cacheKey, 300, function () use ($integration, $team) {
                return $this->buildOverview(TrelloApiClient::forIntegration($integration), $team);
            });
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        unset($data['card_index']); // only used server-side (PM dashboard)

        return response()->json(['ok' => true, 'team' => ['id' => $team->id, 'name' => $team->name]] + $data);
    }

    private static function overviewCacheKey(Team $team): string
    {
        return 'production.overview.v4.'.$team->id;
    }

    /**
     * Project Manager dashboard: what needs the PM on the team's Trello (late / due today,
     * waiting on client, revisions, stuck) plus where their own clients' projects stand.
     */
    public function projectManager(Request $request): JsonResponse
    {
        $teams = $this->trelloTeams()->filter(fn (Team $t) => $t->trelloIntegration?->isReady())->values();
        $team = $teams->firstWhere('id', (int) $request->query('team')) ?? $teams->first();
        if (! $team) {
            return response()->json(['ok' => false, 'message' => 'Trello is not connected for your team.'], 404);
        }
        if ($request->hasSession()) {
            $request->session()->save();
        }
        $cacheKey = self::overviewCacheKey($team);
        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        try {
            $data = Cache::remember($cacheKey, 300, function () use ($team) {
                return $this->buildOverview(TrelloApiClient::forIntegration($team->trelloIntegration), $team);
            });
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        // Clients this PM sold to or added, that are linked to a Trello card
        $user = Auth::user();
        $clientIds = \App\Models\Sale::query()->where('user_id', $user->id)->whereNotNull('client_id')->pluck('client_id');
        $clients = \App\Models\Client::query()
            ->whereNotNull('trello_card_id')
            ->where(fn ($q) => $q->whereIn('id', $clientIds)->orWhere('created_by', $user->id))
            ->get(['id', 'name', 'trello_card_id']);

        $index = $data['card_index'] ?? [];
        $now = Carbon::now();
        $projects = [];
        foreach ($clients as $client) {
            $c = $index[$client->trello_card_id] ?? null;
            if (! $c) {
                continue; // card archived or on another team's boards
            }
            $late = ! $c['done'] && $c['due'] && Carbon::parse($c['due'])->lt($now);
            $projects[] = [
                'client' => $client->name,
                'client_url' => route('admin.clients.show', $client->id),
                'card' => $c['name'],
                'board' => $c['board'],
                'list' => $c['list'],
                'stage' => $c['done'] ? 'done' : $c['stage'],
                'attention' => $c['done'] ? null : $c['attention'],
                'due' => $c['due'],
                'late' => $late,
                'idle' => $c['idle'],
                'url' => route('admin.trello.board', ['boardId' => $c['board_id'], 'team' => $team->id, 'card' => $client->trello_card_id]),
            ];
        }
        // Late first, then ones needing attention, then open, completed last
        $rank = fn ($p) => $p['late'] ? 0 : ($p['attention'] ? 1 : ($p['stage'] === 'done' ? 3 : 2));
        usort($projects, fn ($a, $b) => [$rank($a), $b['idle']] <=> [$rank($b), $a['idle']]);

        return response()->json([
            'ok' => true,
            'team' => ['id' => $team->id, 'name' => $team->name],
            'counts' => $data['counts'],
            'attention' => $data['attention'],
            'stale_count' => $data['stale']['count'],
            'overdue' => array_slice($data['cards']['overdue'], 0, 40),
            'today' => $data['cards']['today'],
            'week' => array_slice($data['cards']['week'], 0, 40),
            'stale' => array_slice($data['stale']['cards'], 0, 20),
            'projects' => $projects,
            'projects_summary' => [
                'total' => count($projects),
                'late' => count(array_filter($projects, fn ($p) => $p['late'])),
                'attention' => count(array_filter($projects, fn ($p) => $p['attention'] && ! $p['late'])),
                'done' => count(array_filter($projects, fn ($p) => $p['stage'] === 'done')),
            ],
            'generated_at' => $data['generated_at'],
        ]);
    }

    /** @return array<string, mixed> */
    private function buildOverview(TrelloApiClient $client, Team $team): array
    {
        $boards = array_values(array_filter($client->boards(), fn ($b) => empty($b['closed'])));
        $byBoard = $client->boardsCardsAndLists(array_column($boards, 'id'));

        $now = Carbon::now();
        $endOfToday = $now->copy()->endOfDay();
        $endOfWeek = $now->copy()->addDays(7)->endOfDay();
        $groups = ['overdue' => [], 'today' => [], 'week' => []];
        $openCards = 0;
        $stages = ['todo' => 0, 'progress' => 0, 'review' => 0, 'hold' => 0, 'done' => 0];
        $pipeline = [];
        $attention = ['qa' => 0, 'revision' => 0, 'concern' => 0, 'client' => 0, 'hold' => 0];
        $stale = [];
        $staleBuckets = ['14_30' => 0, '30_60' => 0, '60_plus' => 0];
        $members = [];
        $hygiene = ['no_due' => 0, 'no_member' => 0];
        $lateByBoard = [];
        $lateAge = ['1_7' => 0, '8_30' => 0, '31_plus' => 0];
        $cardIndex = []; // every visible card, for the PM dashboard's "my clients" list

        foreach ($boards as $board) {
            $set = $byBoard[$board['id']] ?? ['cards' => [], 'lists' => []];
            $listNames = array_column($set['lists'], 'name', 'id');
            $doneListId = self::doneListId($set['lists'], $set['cards']);
            $listStage = [];
            foreach ($set['lists'] as $l) {
                $listStage[$l['id']] = $l['id'] === $doneListId ? 'done' : self::stageFor((string) ($l['name'] ?? ''));
            }
            $row = ['name' => $board['name'] ?? '', 'url' => route('admin.trello.board', ['boardId' => $board['id'], 'team' => $team->id]),
                'todo' => 0, 'progress' => 0, 'review' => 0, 'hold' => 0, 'done' => 0];

            foreach ($set['cards'] as $card) {
                $listId = $card['idList'] ?? '';
                if (! isset($listStage[$listId])) {
                    continue; // card sits in an archived list: hidden on Trello, so ignore it here too
                }
                $listName = $listNames[$listId] ?? '';
                $stage = $listStage[$listId];
                $cardIndex[$card['id']] = [
                    'name' => $card['name'] ?? '',
                    'board' => $board['name'] ?? '',
                    'board_id' => $board['id'],
                    'list' => $listName,
                    'stage' => $stage,
                    'attention' => self::attentionFor($listName),
                    'due' => $card['due'] ?? null,
                    'done' => ! empty($card['dueComplete']) || $stage === 'done',
                    'idle' => ! empty($card['dateLastActivity']) ? (int) Carbon::parse($card['dateLastActivity'])->diffInDays($now) : 0,
                ];
                // Complete = due date ticked, or moved into the board's Completed / Done list
                if (! empty($card['dueComplete']) || $stage === 'done') {
                    $row['done']++;
                    $stages['done']++;

                    continue;
                }
                $row[$stage]++;
                $stages[$stage]++;
                $openCards++;
                if ($a = self::attentionFor($listName)) {
                    $attention[$a]++;
                }
                $url = route('admin.trello.board', ['boardId' => $board['id'], 'team' => $team->id, 'card' => $card['id']]);
                $isLate = ! empty($card['due']) && Carbon::parse($card['due'])->lt($now);

                // Who has how much open work
                foreach ($card['members'] ?? [] as $m) {
                    $members[$m['id']] ??= ['name' => $m['fullName'] ?? '', 'initials' => $m['initials'] ?? '', 'open' => 0, 'late' => 0];
                    $members[$m['id']]['open']++;
                    $members[$m['id']]['late'] += $isLate ? 1 : 0;
                }
                if ($stage !== 'hold') {
                    $hygiene['no_member'] += empty($card['idMembers']) ? 1 : 0;
                    $hygiene['no_due'] += empty($card['due']) ? 1 : 0;

                    // Stuck: nothing happened on the card for 2+ weeks (on-hold cards are parked on purpose)
                    $idle = ! empty($card['dateLastActivity']) ? (int) Carbon::parse($card['dateLastActivity'])->diffInDays($now) : 0;
                    if ($idle >= 14) {
                        $staleBuckets[$idle >= 60 ? '60_plus' : ($idle >= 30 ? '30_60' : '14_30')]++;
                        $stale[] = ['name' => $card['name'] ?? '', 'board' => $board['name'] ?? '', 'list' => $listName, 'days' => $idle, 'url' => $url];
                    }
                }

                if (empty($card['due'])) {
                    continue;
                }
                $due = Carbon::parse($card['due']);
                $group = $isLate ? 'overdue' : ($due->lte($endOfToday) ? 'today' : ($due->lte($endOfWeek) ? 'week' : null));
                if (! $group) {
                    continue;
                }
                $entry = [
                    'id' => $card['id'],
                    'name' => $card['name'] ?? '',
                    'due' => $card['due'],
                    'board' => $board['name'] ?? '',
                    'board_id' => $board['id'],
                    'list' => $listName,
                    'labels' => array_map(fn ($l) => ['name' => $l['name'] ?? '', 'color' => $l['color'] ?? null], $card['labels'] ?? []),
                    // Opens the card in the CRM board
                    'url' => $url,
                ];
                if ($isLate) {
                    $days = max(1, (int) $due->diffInDays($now));
                    $entry['days_late'] = $days;
                    $lateAge[$days <= 7 ? '1_7' : ($days <= 30 ? '8_30' : '31_plus')]++;
                    $lateByBoard[$board['id']] ??= ['id' => $board['id'], 'name' => $board['name'] ?? '', 'count' => 0];
                    $lateByBoard[$board['id']]['count']++;
                }
                $groups[$group][] = $entry;
            }
            $pipeline[] = $row;
        }
        foreach ($groups as &$cards) {
            usort($cards, fn ($a, $b) => strcmp($a['due'], $b['due']));
        }
        unset($cards);
        usort($stale, fn ($a, $b) => $b['days'] <=> $a['days']);
        usort($pipeline, fn ($a, $b) => ($b['todo'] + $b['progress'] + $b['review'] + $b['hold']) <=> ($a['todo'] + $a['progress'] + $a['review'] + $a['hold']));
        $lateByBoard = array_values($lateByBoard);
        usort($lateByBoard, fn ($a, $b) => $b['count'] <=> $a['count']);
        $members = array_values($members);
        usort($members, fn ($a, $b) => [$b['open'], $b['late']] <=> [$a['open'], $a['late']]);

        return [
            'counts' => [
                'overdue' => count($groups['overdue']),
                'today' => count($groups['today']),
                'week' => count($groups['week']),
                'boards' => count($boards),
                'open_cards' => $openCards,
            ],
            'cards' => $groups,
            'late_by_board' => $lateByBoard,
            'late_age' => $lateAge,
            'stages' => $stages,
            'pipeline' => $pipeline,
            'attention' => $attention,
            'stale' => ['count' => count($stale), 'buckets' => $staleBuckets, 'cards' => array_slice($stale, 0, 60)],
            'members' => array_slice($members, 0, 30),
            'hygiene' => $hygiene,
            'boards' => array_map(fn ($b) => [
                'name' => $b['name'] ?? '',
                'url' => route('admin.trello.board', ['boardId' => $b['id'], 'team' => $team->id]),
            ], $boards),
            'card_index' => $cardIndex,
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * Pipeline stage from a list's name (each board names its lists differently).
     * Non-final "complete" lists ("development completed", "UI Design Complete") count as
     * review: the work is done but the project isn't closed yet.
     */
    private static function stageFor(string $name): string
    {
        $n = mb_strtolower($name);

        return match (true) {
            (bool) preg_match('/hold|refund|reject/', $n) => 'hold',
            (bool) preg_match('/\bqa\b|revision|review|approv|feedback|awaiting|concern|client|deliver|complet|compel|\bdone\b|closed|finali|publish|final files/', $n) => 'review',
            (bool) preg_match('/\bnew\b|to ?do|brief|backlog|to be processed|mockup/', $n) => 'todo',
            default => 'progress',
        };
    }

    /** Lists that need someone's attention: QA, revisions, concerns, waiting on the client, on hold. */
    private static function attentionFor(string $name): ?string
    {
        $n = mb_strtolower($name);

        return match (true) {
            (bool) preg_match('/\bqa\b/', $n) => 'qa',
            (bool) preg_match('/revision/', $n) => 'revision',
            (bool) preg_match('/concern/', $n) => 'concern',
            (bool) preg_match('/feedback|awaiting|to client|deliver|on approval/', $n) => 'client',
            (bool) preg_match('/hold/', $n) => 'hold',
            default => null,
        };
    }

    /**
     * The board's "finished" list, among lists named like Completed / Done / Closed
     * ("Task Compeled", "Project Completed / Closed", "Completed Tasks", "Done"…):
     * - Trello's default trailing "To Do › Doing › Done" is ignored when the board has its own
     *   completed list ("… Project Completed / Closed › To Do › Doing › Done").
     * - Boards with several ("development completed" … "Project Completed") use the last one
     *   that holds cards.
     *
     * @param  array<int, array<string, mixed>>  $lists
     * @param  array<int, array<string, mixed>>  $cards
     */
    public static function doneListId(array $lists, array $cards = []): ?string
    {
        usort($lists, fn ($a, $b) => ($a['pos'] ?? 0) <=> ($b['pos'] ?? 0));
        $name = fn ($l) => mb_strtolower(trim(preg_replace('/[^\pL\pN\s\/]+/u', '', (string) ($l['name'] ?? ''))));

        $done = [];
        $afterDefault = false; // past a "To Do" / "Doing" list
        foreach ($lists as $l) {
            $n = $name($l);
            if (in_array($n, ['to do', 'todo', 'doing'], true)) {
                $afterDefault = true;
            }
            if (preg_match('/complet|compel|\bdone\b|closed/i', $n)) {
                $done[] = $l + ['_default' => $afterDefault && $n === 'done'];
            }
        }
        $own = array_values(array_filter($done, fn ($l) => ! $l['_default']));
        $done = $own ?: $done;
        if (! $done) {
            return null;
        }

        $counts = array_count_values(array_filter(array_column($cards, 'idList')));
        $withCards = array_values(array_filter($done, fn ($l) => ($counts[$l['id']] ?? 0) > 0));

        $pick = $withCards ?: $done;

        return (string) $pick[count($pick) - 1]['id'];
    }

    /**
     * Teams whose Trello the user works in: same rule as the Trello page (own connection first,
     * else the parent's).
     *
     * @return Collection<int, Team>
     */
    private function trelloTeams(): Collection
    {
        // Admin / Manager: only teams that actually have Trello connected
        $teams = TeamTrelloAccounts::teamsForUser();

        return AuthScope::isAdminOrManager() ? $teams->filter(fn (Team $t) => $t->trelloIntegration)->values() : $teams;
    }
}
