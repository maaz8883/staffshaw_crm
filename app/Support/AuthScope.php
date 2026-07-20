<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Central scoping helper.
 * Admin / Manager  → no restrictions (full data)
 * Agent            → scoped to own company + own team
 */
class AuthScope
{
    public static function isAgent(): bool
    {
        return auth()->user()?->hasRole('Agent') ?? false;
    }

    public static function isManager(): bool
    {
        return auth()->user()?->hasRole('Manager') ?? false;
    }

    public static function isProjectManager(): bool
    {
        return auth()->user()?->hasRole('Project Manager') ?? false;
    }

    /** Team IDs the current Project Manager has approved access to (empty collection for non-PMs). */
    public static function projectManagerTeamIds(): \Illuminate\Support\Collection
    {
        if (! self::isProjectManager()) {
            return collect();
        }

        return auth()->user()->approvedTeamIds();
    }

    /** Apply company_id scope to any query that has a company_id column */
    public static function scopeByCompany(Builder $query, string $column = 'company_id'): Builder
    {
        if (self::isAgent()) {
            $query->where($column, auth()->user()->company_id);
        }

        return $query;
    }

    /** Apply team_id scope to any query that has a team_id column */
    public static function scopeByTeam(Builder $query, string $column = 'team_id'): Builder
    {
        if (self::isAgent()) {
            $query->where($column, auth()->user()->team_id);
        }

        return $query;
    }

    /** Scope users — agent sees only teammates */
    public static function scopeUsers(Builder $query): Builder
    {
        if (self::isAgent()) {
            $query->where('team_id', auth()->user()->team_id)
                  ->where('company_id', auth()->user()->company_id);
        }

        return $query;
    }

    /** Scope leads — agent sees only leads assigned to their team */
    public static function scopeLeads(Builder $query): Builder
    {
        if (self::isAgent()) {
            $query->where('team_id', auth()->user()->team_id);
        }

        return $query;
    }

    /** For dropdowns — teams list scoped to agent's own team, or a Project Manager's joined teams */
    public static function teamsForDropdown()
    {
        $q = \App\Models\Team::query()->orderBy('name');

        if (self::isAgent()) {
            $q->where('id', auth()->user()->team_id);
        } elseif (self::isProjectManager()) {
            $q->whereIn('id', self::projectManagerTeamIds());
        }

        return $q->get();
    }

    /** For dropdowns — users scoped to agent's team, or a Project Manager's joined teams */
    public static function usersForDropdown()
    {
        $q = User::query()->orderBy('name');

        if (self::isAgent()) {
            $q->where('team_id', auth()->user()->team_id)
              ->where('company_id', auth()->user()->company_id);
        } elseif (self::isProjectManager()) {
            $q->whereIn('team_id', self::projectManagerTeamIds());
        }

        return $q->get();
    }
}
