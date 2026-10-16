<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Suggested Trello login email per CRM team (separate account per team).
 */
class TeamTrelloAccounts
{
    /** @var array<string, string> team-name needle (lowercase) => email */
    private const MAP = [
        'staffshaw' => 'sales.trello@staffshaw.com',
        'nextbyte' => 'sales.nexbyte@staffshaw.com',
        'nexbyte' => 'sales.nexbyte@staffshaw.com',
    ];

    /**
     * Optional board-name allowlist for customer sync (exact Trello board title).
     * null = import from all open boards.
     *
     * @var array<string, list<string>>
     */
    private const SYNC_BOARDS = [
        'staffshaw' => ['Team Design - SS'],
    ];

    /**
     * Teams whose Trello the logged-in user works in.
     *
     * A team uses its own Trello connection when it has one; a sub-team without one falls back
     * to its parent's. So "Production" (trello@, all boards) can have sub-teams Design / SEO /
     * SMM… each on their own account: the production head (head of the parent) gets only
     * trello@, a designer gets only design@.
     * Admin / Manager get every team (main and sub) so any of them can be connected.
     *
     * @return Collection<int, Team>
     */
    public static function teamsForUser(): Collection
    {
        if (AuthScope::isAdminOrManager()) {
            return Team::query()->with(['trelloIntegration', 'parentTeam'])->orderBy('name')->get();
        }

        $own = Team::query()->with('trelloIntegration')->whereIn('id', AuthScope::accessibleTeamIds())->get();
        // Production departments never fall back to the parent (trello@ = every board): a member
        // whose department isn't connected yet sees "Trello not connected" for it instead.
        $noFallback = AuthScope::isProduction();
        $userTeamId = (int) auth()->user()?->team_id;
        $ids = $own->map(function (Team $t) use ($noFallback, $userTeamId) {
            if ($t->trelloIntegration || $t->parent_team_id === null) {
                return $t->id;
            }

            return $noFallback ? ((int) $t->id === $userTeamId ? $t->id : null) : $t->parent_team_id;
        })
            ->filter()
            ->unique()
            ->values();

        return Team::query()->with(['trelloIntegration', 'parentTeam'])
            ->whereIn('id', $ids->isEmpty() ? [0] : $ids)
            ->orderBy('name')
            ->get();
    }

    /** Whether the logged-in user may use this team's Trello (pages, cards, live updates). */
    public static function userCanUse(Team $team): bool
    {
        return self::teamsForUser()->contains('id', $team->id);
    }

    /** "Production › Design" for sub-teams, plain name for main teams. */
    public static function label(Team $team): string
    {
        return $team->parentTeam ? $team->parentTeam->name.' › '.$team->name : $team->name;
    }

    public static function suggestedEmail(?Team $team): ?string
    {
        if (! $team) {
            return null;
        }

        $name = strtolower(trim((string) $team->name));

        // Production → trello@, its departments → <department>@ (e.g. development@)
        if ($team->parent_team_id === null && $name === 'production') {
            return 'trello@staffshaw.com';
        }
        if ($team->parentTeam && strtolower(trim((string) $team->parentTeam->name)) === 'production') {
            return preg_replace('/[^a-z0-9]+/', '', $name).'@staffshaw.com';
        }

        foreach (self::MAP as $needle => $email) {
            if ($needle !== '' && str_contains($name, $needle)) {
                return $email;
            }
        }

        return null;
    }

    /** @return list<string>|null */
    public static function syncBoardNames(?Team $team): ?array
    {
        if (! $team) {
            return null;
        }

        $name = strtolower(trim((string) $team->name));
        foreach (self::SYNC_BOARDS as $needle => $boards) {
            if ($needle !== '' && str_contains($name, $needle)) {
                return $boards;
            }
        }

        return null;
    }
}
