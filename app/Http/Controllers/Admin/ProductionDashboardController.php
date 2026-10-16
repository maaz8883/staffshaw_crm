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

        $cacheKey = 'production.overview.'.$team->id;
        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey); // "Refresh" button: always fetch from Trello
        }

        try {
            $data = Cache::remember($cacheKey, 120, function () use ($integration, $team) {
                return $this->buildOverview(TrelloApiClient::forIntegration($integration), $team);
            });
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'team' => ['id' => $team->id, 'name' => $team->name]] + $data);
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

        foreach ($boards as $board) {
            $set = $byBoard[$board['id']] ?? ['cards' => [], 'lists' => []];
            $listNames = array_column($set['lists'], 'name', 'id');
            $openCards += count($set['cards']);

            foreach ($set['cards'] as $card) {
                if (empty($card['due']) || ! empty($card['dueComplete'])) {
                    continue;
                }
                $due = Carbon::parse($card['due']);
                $group = $due->lt($now) ? 'overdue' : ($due->lte($endOfToday) ? 'today' : ($due->lte($endOfWeek) ? 'week' : null));
                if (! $group) {
                    continue;
                }
                $groups[$group][] = [
                    'id' => $card['id'],
                    'name' => $card['name'] ?? '',
                    'due' => $card['due'],
                    'board' => $board['name'] ?? '',
                    'list' => $listNames[$card['idList'] ?? ''] ?? '',
                    'labels' => array_map(fn ($l) => ['name' => $l['name'] ?? '', 'color' => $l['color'] ?? null], $card['labels'] ?? []),
                    // Opens the card in the CRM board
                    'url' => route('admin.trello.board', ['boardId' => $board['id'], 'team' => $team->id, 'card' => $card['id']]),
                ];
            }
        }
        foreach ($groups as &$cards) {
            usort($cards, fn ($a, $b) => strcmp($a['due'], $b['due']));
        }
        unset($cards);

        return [
            'counts' => [
                'overdue' => count($groups['overdue']),
                'today' => count($groups['today']),
                'week' => count($groups['week']),
                'boards' => count($boards),
                'open_cards' => $openCards,
            ],
            'cards' => $groups,
            'boards' => array_map(fn ($b) => [
                'name' => $b['name'] ?? '',
                'url' => route('admin.trello.board', ['boardId' => $b['id'], 'team' => $team->id]),
            ], $boards),
            'generated_at' => $now->toIso8601String(),
        ];
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
