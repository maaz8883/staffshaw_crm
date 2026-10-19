<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Role;
use App\Models\SubTeamHead;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Central scoping helper for org hierarchy.
 *
 * Admin / Manager → full data
 * Team Head       → headed teams
 * Sub-Team Head   → own sub-team members / those teams
 * Project Manager → approved joined teams
 * Agent / PPC     → own team
 */
class AuthScope
{
    public static function isAgent(): bool
    {
        return auth()->user()?->hasRole(Role::AGENT) ?? false;
    }

    public static function isManager(): bool
    {
        return auth()->user()?->hasRole(Role::MANAGER) ?? false;
    }

    public static function isAdmin(): bool
    {
        return auth()->user()?->hasRole(Role::ADMIN) ?? false;
    }

    public static function isAdminOrManager(): bool
    {
        return auth()->user()?->hasRole([Role::ADMIN, Role::MANAGER]) ?? false;
    }

    public static function isProjectManager(): bool
    {
        return auth()->user()?->hasRole(Role::PROJECT_MANAGER) ?? false;
    }

    public static function isPpc(): bool
    {
        return auth()->user()?->hasRole(Role::PPC) ?? false;
    }

    public static function isHr(): bool
    {
        return auth()->user()?->hasRole(Role::HR) ?? false;
    }

    public static function isFinance(): bool
    {
        return auth()->user()?->hasRole(Role::FINANCE) ?? false;
    }

    /** Company-wide sales, invoices, clients and money reports: Admin, Manager, Finance. */
    public static function seesAllMoney(): bool
    {
        return auth()->user()?->hasRole([Role::ADMIN, Role::MANAGER, Role::FINANCE]) ?? false;
    }

    public static function isIt(): bool
    {
        return auth()->user()?->hasRole(Role::IT) ?? false;
    }

    public static function isProduction(): bool
    {
        return auth()->user()?->hasRole(Role::PRODUCTION) ?? false;
    }

    /** Team IDs where the user is Team Head. */
    public static function headedTeamIds(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        return Team::where('team_head_id', $user->id)->pluck('id');
    }

    /** SubTeamHead row IDs where the user is a sub-team head. */
    public static function subTeamHeadIds(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        return SubTeamHead::where('user_id', $user->id)->pluck('id');
    }

    /** Team IDs for sub-team head seats. */
    public static function subHeadTeamIds(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        return SubTeamHead::where('user_id', $user->id)->pluck('team_id')->unique()->values();
    }

    /** Team IDs the current Project Manager has approved access to. */
    public static function projectManagerTeamIds(): Collection
    {
        if (! self::isProjectManager()) {
            return collect();
        }

        return auth()->user()->approvedTeamIds();
    }

    /**
     * Team IDs the current user may access across modules.
     */
    public static function accessibleTeamIds(): Collection
    {
        if (self::isAdminOrManager()) {
            return Team::query()->pluck('id');
        }

        if (self::isProjectManager()) {
            return self::projectManagerTeamIds();
        }

        $headed = self::headedTeamIds();
        if ($headed->isNotEmpty()) {
            return $headed->merge(self::subHeadTeamIds())->unique()->values();
        }

        $subTeams = self::subHeadTeamIds();
        if ($subTeams->isNotEmpty()) {
            return $subTeams;
        }

        $teamId = auth()->user()?->team_id;
        return $teamId ? collect([(int) $teamId]) : collect();
    }

    public static function canAccessTeam(int $teamId): bool
    {
        if (self::isAdminOrManager()) {
            return true;
        }

        return self::accessibleTeamIds()->contains($teamId);
    }

    /** Apply company_id scope */
    public static function scopeByCompany(Builder $query, string $column = 'company_id'): Builder
    {
        if (self::isAdminOrManager()) {
            return $query;
        }

        $user = auth()->user();
        if ($user?->company_id) {
            $query->where($column, $user->company_id);
        }

        return $query;
    }

    /** Apply team_id scope (column may be teams.id when listing teams) */
    public static function scopeByTeam(Builder $query, string $column = 'team_id'): Builder
    {
        if (self::isAdminOrManager()) {
            return $query;
        }

        $ids = self::accessibleTeamIds();
        if ($ids->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $ids);
    }

    /** Scope users list */
    public static function scopeUsers(Builder $query): Builder
    {
        if (self::isAdminOrManager() || self::isIt() || self::isHr() || self::isFinance()) { // IT / HR manage every account; Finance reports on everyone
            return $query;
        }

        if (self::isProjectManager()) {
            return $query->whereIn('team_id', self::projectManagerTeamIds());
        }

        $headed = self::headedTeamIds();
        if ($headed->isNotEmpty()) {
            return $query->whereIn('team_id', $headed);
        }

        $subHeadIds = self::subTeamHeadIds();
        if ($subHeadIds->isNotEmpty()) {
            return $query->where(function (Builder $q) use ($subHeadIds) {
                $q->whereIn('sub_team_head_id', $subHeadIds)
                    ->orWhere('id', auth()->id());
            });
        }

        $user = auth()->user();
        if ($user?->team_id) {
            return $query->where('team_id', $user->team_id)
                ->where('company_id', $user->company_id);
        }

        return $query->whereRaw('1 = 0');
    }

    /** Scope leads by org seat */
    public static function scopeLeads(Builder $query): Builder
    {
        if (self::isAdminOrManager()) {
            return $query;
        }

        if (self::isProjectManager()) {
            $ids = self::projectManagerTeamIds();
            return $ids->isEmpty()
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('team_id', $ids);
        }

        $headed = self::headedTeamIds();
        if ($headed->isNotEmpty()) {
            return $query->whereIn('team_id', $headed);
        }

        $subHeadIds = self::subTeamHeadIds();
        if ($subHeadIds->isNotEmpty()) {
            $memberIds = User::whereIn('sub_team_head_id', $subHeadIds)
                ->pluck('id')
                ->push(auth()->id())
                ->unique()
                ->values();

            return $query->where(function (Builder $q) use ($memberIds) {
                $q->whereIn('assigned_to', $memberIds)
                    ->orWhereIn('team_id', self::subHeadTeamIds());
            });
        }

        $user = auth()->user();
        if ($user?->team_id) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('team_id', $user->team_id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        return $query->where('assigned_to', auth()->id());
    }

    public static function canAccessLead(\App\Models\Lead $lead): bool
    {
        if (self::isAdminOrManager()) {
            return true;
        }

        return self::scopeLeads(\App\Models\Lead::query()->whereKey($lead->id))->exists();
    }

    /** Teams for dropdowns */
    public static function teamsForDropdown()
    {
        $q = Team::query()->sales()->orderBy('name');

        if (! self::seesAllMoney()) {
            $ids = self::accessibleTeamIds();
            $q->whereIn('id', $ids->isEmpty() ? [0] : $ids);
        }

        return $q->get();
    }

    /** Users for dropdowns */
    public static function usersForDropdown()
    {
        $q = User::query()->orderBy('name');
        self::scopeUsers($q);
        // Sales dropdowns only (leads, sales, reports): no production people
        $q->whereDoesntHave('role', fn ($r) => $r->whereIn('name', [Role::PRODUCTION, Role::PPC]))
            ->whereDoesntHave('team', fn ($t) => $t->where('is_production', true));

        return $q->get();
    }

    /**
     * Brand IDs the current user may select.
     * Admin/Manager → all. Others → brands assigned to their accessible teams.
     */
    public static function accessibleBrandIds(): Collection
    {
        if (self::seesAllMoney()) { // Finance invoices any brand
            return Brand::query()->pluck('id');
        }

        $teamIds = self::accessibleTeamIds();
        if ($teamIds->isEmpty()) {
            return collect();
        }

        return Brand::query()
            ->whereHas('teams', fn (Builder $q) => $q->whereIn('teams.id', $teamIds))
            ->pluck('id');
    }

    public static function canAccessBrand(?int $brandId): bool
    {
        if ($brandId === null) {
            return true;
        }

        if (self::seesAllMoney()) {
            return true;
        }

        return self::accessibleBrandIds()->contains($brandId);
    }

    /** Brands for dropdowns (scoped by team assignment). */
    public static function brandsForDropdown()
    {
        $q = Brand::query()->orderBy('name');

        if (! self::isAdminOrManager()) {
            $ids = self::accessibleBrandIds();
            $q->whereIn('id', $ids->isEmpty() ? [0] : $ids);
        }

        return $q->get(['id', 'name']);
    }

    /**
     * Validation rule for brand_id fields.
     *
     * @return array<int, mixed>
     */
    public static function brandIdRule(bool $required = false): array
    {
        // Include int + string forms so HTML select values pass Rule::in.
        $ids = self::accessibleBrandIds()
            ->flatMap(fn ($id) => [(int) $id, (string) $id])
            ->unique()
            ->values()
            ->all();

        return [
            $required ? 'required' : 'nullable',
            Rule::in($ids),
        ];
    }
}
