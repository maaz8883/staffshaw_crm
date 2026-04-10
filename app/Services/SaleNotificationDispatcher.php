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
    /** Agent (non–team-head) creates a sale → team lead + admins. */
    public static function dispatchSaleCreated(Sale $sale): void
    {
        $sale->loadMissing('user');
        $seller = $sale->user;
        if (! $seller || ! self::isAgentOnly($seller)) {
            return;
        }

        $recipients = self::adminsForCompany($sale->company_id);

        if ($sale->team_id) {
            $head = Team::query()->find($sale->team_id)?->teamHead;
            if ($head && $head->id !== $seller->id) {
                $recipients->push($head);
            }
        }

        $recipients = self::uniqueRecipients($recipients, $seller->id);
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SaleCreatedNotification($sale));
    }

    /** Approve / reject → agent; if decider is not Admin, also all relevant admins. */
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

        $recipients = self::uniqueRecipients($recipients, $decider->id);
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SaleDecisionNotification($sale, $decider, $approved));
    }

    /** Sale edited → admins + team lead (not the editor). */
    public static function dispatchSaleUpdated(Sale $sale, User $editor): void
    {
        $recipients = collect();

        foreach (self::adminsForCompany($sale->company_id) as $admin) {
            if ($admin->id !== $editor->id) {
                $recipients->push($admin);
            }
        }

        if ($sale->team_id) {
            $head = Team::query()->find($sale->team_id)?->teamHead;
            if ($head && $head->id !== $editor->id) {
                $recipients->push($head);
            }
        }

        $recipients = self::uniqueRecipients($recipients, $editor->id);
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SaleUpdatedNotification($sale, $editor));
    }

    private static function isAgentOnly(User $user): bool
    {
        if (! $user->hasRole(Role::AGENT)) {
            return false;
        }

        return ! Team::where('team_head_id', $user->id)->exists();
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
