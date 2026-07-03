<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\TeamTarget;
use App\Models\UserActivityLog;
use App\Notifications\TeamJoinDecisionNotification;
use App\Notifications\TeamJoinRequestedNotification;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class TeamMembershipController extends Controller
{
    /**
     * Team_Directory — browse all teams and their join status (Project Manager only).
     */
    public function index(): View
    {
        $this->ensureProjectManager();

        $user = Auth::user();

        $teams = Team::query()
            ->with('company')
            ->orderBy('name')
            ->get();

        $memberships = TeamMembership::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('team_id');

        $month = now()->month;
        $year  = now()->year;
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $monthEnd = (clone $monthStart)->endOfMonth();

        $teams = $teams->map(function (Team $team) use ($memberships, $month, $year, $monthStart, $monthEnd) {
            $membership = $memberships->get($team->id);
            $team->membership_status = $membership?->status; // null | pending | approved | rejected

            // Only compute target/revenue for teams the PM has actually joined (approved).
            if ($membership?->status === TeamMembership::STATUS_APPROVED) {
                $team->current_target = (float) (TeamTarget::where('team_id', $team->id)
                    ->where('month', $month)->where('year', $year)
                    ->value('target_amount') ?? 0);

                $team->current_revenue = (float) Sale::where('team_id', $team->id)
                    ->where('approval_status', Sale::APPROVAL_APPROVED)
                    ->where('status', 'completed')
                    ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->sum('amount');

                $team->target_month_label = $monthStart->format('F Y');
            }

            return $team;
        });

        return view('admin.team-memberships.index', compact('teams'));
    }

    /**
     * Requirement 3 — request to join a team.
     */
    public function join(Team $team): RedirectResponse
    {
        $this->ensureProjectManager();

        $user = Auth::user();

        $existing = TeamMembership::query()
            ->where('user_id', $user->id)
            ->where('team_id', $team->id)
            ->first();

        if ($existing && in_array($existing->status, [TeamMembership::STATUS_PENDING, TeamMembership::STATUS_APPROVED], true)) {
            return back()->with('success', 'You have already requested or joined this team.');
        }

        if ($existing) {
            // Previously rejected — allow a new request, reset to pending.
            $existing->update([
                'status'     => TeamMembership::STATUS_PENDING,
                'decided_by' => null,
                'decided_at' => null,
            ]);
            $membership = $existing;
        } else {
            $membership = TeamMembership::query()->create([
                'user_id' => $user->id,
                'team_id' => $team->id,
                'status'  => TeamMembership::STATUS_PENDING,
            ]);
        }

        $this->notifyApprovers($team, $membership);

        return redirect()
            ->route('admin.team-memberships.index')
            ->with('success', "Join request sent for \"{$team->name}\". Waiting for Team Head approval.");
    }

    /**
     * Requirement 5 — leave a joined team, or cancel a pending request.
     */
    public function leave(Team $team): RedirectResponse
    {
        $this->ensureProjectManager();

        $user = Auth::user();

        $membership = TeamMembership::query()
            ->where('user_id', $user->id)
            ->where('team_id', $team->id)
            ->first();

        if (! $membership || $membership->status === TeamMembership::STATUS_REJECTED) {
            return back()->withErrors(['team' => 'You have not joined this team.']);
        }

        $wasApproved = $membership->isApproved();
        $membership->delete();

        return redirect()
            ->route('admin.team-memberships.index')
            ->with('success', $wasApproved
                ? "You have left \"{$team->name}\"."
                : "Your join request for \"{$team->name}\" was cancelled.");
    }

    /**
     * Requirement 4 — Team Head's list of pending join requests for teams they head
     * (Admin/Manager can also see all, in case a team has no head).
     */
    public function requestsIndex(): View
    {
        $user = Auth::user();

        $query = TeamMembership::query()
            ->where('status', TeamMembership::STATUS_PENDING)
            ->with(['user', 'team']);

        if (! $user->hasRole([Role::ADMIN, Role::MANAGER])) {
            $headedTeamIds = Team::where('team_head_id', $user->id)->pluck('id');
            $query->whereIn('team_id', $headedTeamIds);
        }

        $requests = $query->latest()->get();

        return view('admin.team-memberships.requests', compact('requests'));
    }

    public function approve(TeamMembership $membership): RedirectResponse
    {
        $this->authorizeDecision($membership);

        $membership->update([
            'status'     => TeamMembership::STATUS_APPROVED,
            'decided_by' => Auth::id(),
            'decided_at' => now(),
        ]);

        Notification::send($membership->user, new TeamJoinDecisionNotification($membership, true));

        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_TEAM_UPDATED,
            "Approved Project Manager join request: {$membership->user?->name} → {$membership->team?->name}"
        );

        return back()->with('success', 'Join request approved.');
    }

    public function reject(TeamMembership $membership): RedirectResponse
    {
        $this->authorizeDecision($membership);

        $membership->update([
            'status'     => TeamMembership::STATUS_REJECTED,
            'decided_by' => Auth::id(),
            'decided_at' => now(),
        ]);

        Notification::send($membership->user, new TeamJoinDecisionNotification($membership, false));

        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_TEAM_UPDATED,
            "Rejected Project Manager join request: {$membership->user?->name} → {$membership->team?->name}"
        );

        return back()->with('success', 'Join request rejected.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function ensureProjectManager(): void
    {
        if (! Auth::user()->hasRole(Role::PROJECT_MANAGER)) {
            abort(403);
        }
    }

    private function authorizeDecision(TeamMembership $membership): void
    {
        $user = Auth::user();

        if ($user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return;
        }

        $membership->loadMissing('team');
        $isHead = $membership->team && (int) $membership->team->team_head_id === (int) $user->id;
        $teamHasNoHead = $membership->team && $membership->team->team_head_id === null;

        if ($isHead || $teamHasNoHead) {
            return;
        }

        abort(403);
    }

    private function notifyApprovers(Team $team, TeamMembership $membership): void
    {
        $recipients = collect();

        if ($team->team_head_id) {
            $head = \App\Models\User::find($team->team_head_id);
            if ($head) {
                $recipients->push($head);
            }
        } else {
            // No team head assigned — fall back to notifying Admins.
            $recipients = $recipients->merge(
                \App\Models\User::whereHas('role', fn ($q) => $q->where('name', Role::ADMIN))->get()
            );
        }

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new TeamJoinRequestedNotification($membership));
    }
}
