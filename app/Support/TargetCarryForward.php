<?php

namespace App\Support;

use App\Models\SubTeamHead;
use App\Models\SubTeamHeadTarget;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\TeamTarget;
use App\Models\User;
use App\Models\UserTarget;
use Illuminate\Database\Eloquent\Model;

/**
 * Monthly targets carry forward: a target set for a month applies to every later month until
 * someone sets a new one, so team leads don't have to re-enter it each month.
 *
 * - Viewing / reporting any month uses the latest target set on or before that month.
 * - When a month starts (or an earlier month is opened), the carried value is saved as that
 *   month's own row ({@see ensureMonth}). Past months are then fixed: changing a target later
 *   only affects that month and the months after it that have no target of their own.
 */
class TargetCarryForward
{
    /** @var array<string, bool> months already materialised in this request */
    private static array $done = [];

    private static function ym(int $month, int $year): int
    {
        return $year * 100 + $month;
    }

    /**
     * The target in force for a month: that month's row, else the most recent earlier one.
     *
     * @param  class-string<Model>  $model  TeamTarget, UserTarget or SubTeamHeadTarget
     * @param  array<string, int>  $keys  e.g. ['team_id' => 5] or ['user_id' => 3, 'team_id' => 5]
     */
    public static function effective(string $model, array $keys, int $month, int $year): ?Model
    {
        return $model::query()
            ->where($keys)
            ->whereRaw('(year * 100 + month) <= ?', [self::ym($month, $year)])
            ->orderByRaw('(year * 100 + month) desc')
            ->first();
    }

    /**
     * Save the carried targets as real rows for a month that has started (current or past),
     * so every report, dashboard and target page reads the right amount. Future months are left
     * alone so a change made now still flows into them.
     */
    public static function ensureMonth(int $month, int $year): void
    {
        $ym = self::ym($month, $year);
        if ($month < 1 || $month > 12 || $ym > self::ym((int) now()->month, (int) now()->year) || isset(self::$done[$ym])) {
            return;
        }
        self::$done[$ym] = true;

        self::carry(TeamTarget::class, ['team_id'], $month, $year,
            fn ($row) => Team::query()->whereKey($row->team_id)->exists());

        self::carry(SubTeamHeadTarget::class, ['sub_team_head_id', 'team_id'], $month, $year,
            fn ($row) => SubTeamHead::query()->whereKey($row->sub_team_head_id)->where('team_id', $row->team_id)->exists());

        // Only for people still active in that team (member, team head or joined project manager)
        self::carry(UserTarget::class, ['user_id', 'team_id'], $month, $year, function ($row) {
            $user = User::query()->find($row->user_id);
            if (! $user || ! $user->isAccountActive()) {
                return false;
            }

            return (int) $user->team_id === (int) $row->team_id
                || Team::query()->whereKey($row->team_id)->headedBy($user->id)->exists()
                || TeamMembership::query()->where('user_id', $user->id)->where('team_id', $row->team_id)
                    ->where('status', TeamMembership::STATUS_APPROVED)->exists();
        });
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $keyCols
     */
    private static function carry(string $model, array $keyCols, int $month, int $year, callable $stillValid): void
    {
        $ym = self::ym($month, $year);

        // Latest earlier row per key, for keys that have no row in this month yet
        $earlier = $model::query()
            ->whereRaw('(year * 100 + month) < ?', [$ym])
            ->orderByRaw('(year * 100 + month) desc')
            ->get()
            ->unique(fn ($r) => implode('|', array_map(fn ($c) => $r->{$c}, $keyCols)));

        foreach ($earlier as $row) {
            $keys = [];
            foreach ($keyCols as $c) {
                $keys[$c] = $row->{$c};
            }
            $exists = $model::query()->where($keys)->where('month', $month)->where('year', $year)->exists();
            if ($exists || (float) $row->target_amount <= 0 || ! $stillValid($row)) {
                continue;
            }
            $model::query()->insertOrIgnore([$keys + [
                'month' => $month,
                'year' => $year,
                'target_amount' => $row->target_amount,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]]);
        }
    }
}
