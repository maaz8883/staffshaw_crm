<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SubTeamHead;
use App\Models\Team;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TeamMemberController extends Controller
{
    public function index(Team $team): View
    {
        $this->authorizeView($team);

        $canManageMembers = Auth::user()?->hasRole(Role::ADMIN) ?? false;

        $team->load(['users.role', 'teamHead', 'subTeamHeads']);
        $availableUsers = $canManageMembers
            ? $this->availableUsersQuery($team)->get()
            : collect();

        return view('admin.teams.members', [
            'team' => $team,
            'availableUsers' => $availableUsers,
            'members' => $team->users()->with('role')->orderBy('name')->get(),
            'canManageMembers' => $canManageMembers,
        ]);
    }

    public function storeExisting(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeManage($team);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::query()->findOrFail((int) $validated['user_id']);

        if (! $this->availableUsersQuery($team)->where('users.id', $user->id)->exists()) {
            return back()->with('error', 'That user cannot be added to this team.');
        }

        $oldTeamId = $user->team_id ? (int) $user->team_id : null;
        $user->update([
            'team_id' => $team->id,
            'company_id' => $team->company_id ?: $user->company_id,
            'sub_team_id' => null,
            'sub_team_head_id' => null,
        ]);
        \App\Models\TeamMembership::syncProjectManagerTeam($user->fresh(), $oldTeamId, Auth::id());

        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_TEAM_UPDATED,
            "Added {$user->name} to team {$team->name}"
        );

        return back()->with('success', "{$user->name} added to the team.");
    }

    public function storeNew(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeManage($team);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $agentRoleId = Role::query()->where('name', Role::AGENT)->value('id');

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $agentRoleId,
            'team_id' => $team->id,
            'company_id' => $team->company_id,
            'account_status' => User::ACCOUNT_ACTIVE,
            'sub_team_id' => null,
            'sub_team_head_id' => null,
        ]);

        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_USER_CREATED,
            "Team head created agent {$user->name} on team {$team->name}"
        );

        return back()->with('success', "New agent {$user->name} created and added to the team.");
    }

    public function destroy(Team $team, User $user): RedirectResponse
    {
        $this->authorizeManage($team);

        if ((int) $user->team_id !== (int) $team->id) {
            abort(404);
        }

        if ($team->isHeadedBy($user->id)) {
            return back()->with('error', 'You cannot remove the team lead from the team.');
        }

        // If this user is a sub-team head seat, clear seat + members under it.
        $subHead = SubTeamHead::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->first();

        if ($subHead) {
            User::query()->where('sub_team_head_id', $subHead->id)->update(['sub_team_head_id' => null]);
            $subHead->delete();
        }

        $user->update([
            'team_id' => null,
            'sub_team_id' => null,
            'sub_team_head_id' => null,
        ]);
        \App\Models\TeamMembership::syncProjectManagerTeam($user->fresh(), (int) $team->id, Auth::id());

        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_TEAM_UPDATED,
            "Removed {$user->name} from team {$team->name}"
        );

        return back()->with('success', "{$user->name} removed from the team.");
    }

    /** Team Lead may view their own team roster; Admin may view any. */
    private function authorizeView(Team $team): void
    {
        $user = Auth::user();

        if ($user?->hasRole(Role::ADMIN)) {
            return;
        }

        if ($user && $team->isHeadedBy($user->id)) {
            return;
        }

        abort(403, 'You do not have permission to view this team roster.');
    }

    private function authorizeManage(Team $team): void
    {
        if (Auth::user()?->hasRole(Role::ADMIN)) {
            return;
        }

        abort(403, 'Only an admin can manage team members.');
    }

    /** Active users with no team (or not already on another team) who can join this team. */
    private function availableUsersQuery(Team $team)
    {
        $otherHeadIds = Team::query()
            ->where('id', '!=', $team->id)
            ->get(['team_head_id', 'co_head_id'])
            ->flatMap->headIds();

        $q = User::query()
            ->accountActive()
            ->where(function ($q) {
                $q->whereNull('team_id')->orWhere('team_id', 0);
            })
            ->whereDoesntHave('role', fn ($q) => $q->whereIn('name', [Role::ADMIN, Role::MANAGER]))
            ->orderBy('name');

        if ($team->company_id) {
            $q->where(function ($q) use ($team) {
                $q->where('company_id', $team->company_id)
                    ->orWhereNull('company_id');
            });
        }

        if ($otherHeadIds->isNotEmpty()) {
            $q->whereNotIn('id', $otherHeadIds);
        }

        return $q;
    }
}
