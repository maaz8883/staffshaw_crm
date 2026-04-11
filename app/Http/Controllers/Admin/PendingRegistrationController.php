<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class PendingRegistrationController extends Controller
{
    private function isTeamHead(): bool
    {
        return Team::where('team_head_id', auth()->id())->exists();
    }

    private function pendingUsersQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = User::query()
            ->where('account_status', User::ACCOUNT_PENDING)
            ->whereHas('role', fn ($q) => $q->where('name', Role::AGENT))
            ->with(['team', 'company', 'role'])
            ->orderByDesc('created_at');

        if (auth()->user()->hasRole(Role::ADMIN)) {
            return $query;
        }

        if ($this->isTeamHead()) {
            $teamIds = Team::where('team_head_id', auth()->id())->pluck('id');

            return $query->whereIn('team_id', $teamIds);
        }

        abort(403);
    }

    private function canManage(User $user): bool
    {
        if (! $user->isPendingApproval()) {
            return false;
        }
        if (! $user->hasRole(Role::AGENT)) {
            return false;
        }

        $auth = auth()->user();
        if ($auth->hasRole(Role::ADMIN)) {
            return true;
        }

        if ($this->isTeamHead() && $user->team_id) {
            return Team::where('team_head_id', $auth->id)->where('id', $user->team_id)->exists();
        }

        return false;
    }

    public function index(): View
    {
        $users = $this->pendingUsersQuery()->get();

        return view('admin.pending-registrations.index', compact('users'));
    }

    public function approve(User $user): RedirectResponse
    {
        if (! $this->canManage($user)) {
            abort(403);
        }

        $user->update([
            'account_status' => User::ACCOUNT_ACTIVE,
            'rejection_note'   => null,
        ]);

        Notification::send($user, new AccountApprovedNotification());

        return redirect()
            ->route('admin.pending-registrations.index')
            ->with('success', "Approved {$user->name}. They can sign in now.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        if (! $this->canManage($user)) {
            abort(403);
        }

        $request->validate([
            'rejection_note' => 'nullable|string|max:500',
        ]);

        $user->update([
            'account_status'  => User::ACCOUNT_REJECTED,
            'rejection_note'    => $request->rejection_note,
        ]);

        return redirect()
            ->route('admin.pending-registrations.index')
            ->with('success', "Registration for {$user->name} was rejected.");
    }
}
