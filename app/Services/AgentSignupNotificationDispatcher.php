<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Notifications\AgentSignupPendingNotification;
use Illuminate\Support\Facades\Notification;

class AgentSignupNotificationDispatcher
{
    public static function dispatch(User $newUser): void
    {
        $newUser->loadMissing('team');

        $recipients = self::adminsForCompany($newUser->company_id);

        if ($newUser->team_id) {
            $head = Team::query()->find($newUser->team_id)?->teamHead;
            if ($head && $head->id !== $newUser->id) {
                $recipients->push($head);
            }
        }

        $recipients = $recipients->unique('id')->filter(fn (User $u) => $u->id !== $newUser->id)->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new AgentSignupPendingNotification($newUser));
    }

    private static function adminsForCompany(?int $companyId): \Illuminate\Support\Collection
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
}
