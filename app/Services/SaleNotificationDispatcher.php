<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SaleCreatedNotification;
use App\Notifications\SaleDecisionNotification;
use App\Notifications\SaleUpdatedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class SaleNotificationDispatcher
{
    /** Agent or team head creates a sale → admins (+ team head if seller is a plain agent) (+ sub-team head if seller is in a sub-team). */
    public static function dispatchSaleCreated(Sale $sale): void
    {
        $sale->loadMissing('user');
        $seller = $sale->user;
        if (! $seller) {
            return;
        }

        // Only agents and team heads should trigger this; skip admins creating sales
        if ($seller->hasRole(Role::ADMIN)) {
            return;
        }

        $recipients = self::adminsForCompany($sale->company_id);

        // Also the team leads (both, when there are two) — a lead's own sale goes to the other lead
        if ($sale->team_id) {
            foreach (Team::query()->find($sale->team_id)?->heads() ?? [] as $head) {
                if ($head->id !== $seller->id) {
                    $recipients->push($head);
                }
            }
        }

        // If seller belongs to a sub-team, notify their sub-team head
        if ($seller->sub_team_head_id) {
            $subTeamHead = \App\Models\SubTeamHead::find($seller->sub_team_head_id);
            if ($subTeamHead && (int) $subTeamHead->user_id !== (int) $seller->id) {
                $subTeamHeadUser = User::find($subTeamHead->user_id);
                if ($subTeamHeadUser) {
                    $recipients->push($subTeamHeadUser);
                }
            }
        }

        $recipients = self::uniqueRecipients($recipients, $seller->id);
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SaleCreatedNotification($sale));
    }

    /** Approve / reject → agent; if decider is not Admin, also all relevant admins + sub-team head. */
    public static function dispatchSaleDecision(Sale $sale, User $decider, bool $approved): void
    {
        $sale->loadMissing('user');
        $recipients = collect();

        $agent = $sale->user;
        if ($agent && $agent->id !== $decider->id) {
            $recipients->push($agent);
        }

        if (! $decider->hasRole(Role::ADMIN)) {
            foreach (self::adminsForCompany($sale->company_id) as $admin) {
                if ($admin->id !== $decider->id) {
                    $recipients->push($admin);
                }
            }
        }

        // If agent belongs to a sub-team and decider is not the sub-team head, notify the sub-team head
        if ($agent && $agent->sub_team_head_id) {
            $subTeamHead = \App\Models\SubTeamHead::find($agent->sub_team_head_id);
            if ($subTeamHead && (int) $subTeamHead->user_id !== (int) $decider->id) {
                $subTeamHeadUser = User::find($subTeamHead->user_id);
                if ($subTeamHeadUser) {
                    $recipients->push($subTeamHeadUser);
                }
            }
        }

        $recipients = self::uniqueRecipients($recipients, $decider->id);
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SaleDecisionNotification($sale, $decider, $approved));
    }

    /** Sale edited → admins + team lead + sub-team head (not the editor). */
    public static function dispatchSaleUpdated(Sale $sale, User $editor): void
    {
        $recipients = collect();

        foreach (self::adminsForCompany($sale->company_id) as $admin) {
            if ($admin->id !== $editor->id) {
                $recipients->push($admin);
            }
        }

        if ($sale->team_id) {
            foreach (Team::query()->find($sale->team_id)?->heads() ?? [] as $head) {
                if ($head->id !== $editor->id) {
                    $recipients->push($head);
                }
            }
        }

        // If editor belongs to a sub-team, notify their sub-team head
        $sale->loadMissing('user');
        if ($sale->user && $sale->user->sub_team_head_id) {
            $subTeamHead = \App\Models\SubTeamHead::find($sale->user->sub_team_head_id);
            if ($subTeamHead && (int) $subTeamHead->user_id !== (int) $editor->id) {
                $subTeamHeadUser = User::find($subTeamHead->user_id);
                if ($subTeamHeadUser) {
                    $recipients->push($subTeamHeadUser);
                }
            }
        }

        $recipients = self::uniqueRecipients($recipients, $editor->id);
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SaleUpdatedNotification($sale, $editor));
    }

    /** Admins scoped to company (null company_id on admin = all companies). */
    private static function adminsForCompany(?int $companyId): Collection
    {
        $q = User::query()
            ->whereHas('role', fn ($r) => $r->where('name', Role::ADMIN));

        if ($companyId !== null) {
            $q->where(function ($w) use ($companyId) {
                $w->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            });
        }

        return $q->get();
    }

    private static function uniqueRecipients(Collection $users, int $excludeUserId): Collection
    {
        return $users->unique('id')->filter(fn (User $u) => $u->id !== $excludeUserId)->values();
    }
}
