<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamTarget;
use App\Models\UserTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TargetController extends Controller
{
    // ─── Helpers ────────────────────────────────────────────────────────────────

    /** Teams the logged-in user can manage (admin = all, team head = own team) */
    private function manageableTeams()
    {
        $user = Auth::user();

        if ($user->hasRole('Admin')) {
            return Team::with(['company', 'users'])->orderBy('name')->get();
        }

        // Team head: only teams where they are the head
        return Team::with(['company', 'users'])
            ->where('team_head_id', $user->id)
            ->orderBy('name')
            ->get();
    }

    private function authorizeTeam(Team $team): void
    {
        $user = Auth::user();

        if ($user->hasRole('Admin')) {
            return;
        }

        if ($team->team_head_id !== $user->id) {
            abort(403, 'You can only manage targets for your own team.');
        }
    }

    // ─── Overview ───────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $month    = (int) $request->get('month', now()->month);
        $year     = (int) $request->get('year', now()->year);
        $authUser = Auth::user();

        $isAdmin    = $authUser->hasRole('Admin');
        $isTeamHead = Team::where('team_head_id', $authUser->id)->exists();

        if ($isAdmin) {
            // Admin: all teams
            $teams = Team::with(['company', 'users'])->orderBy('name')->get();
        } elseif ($isTeamHead) {
            // Team head: only their teams
            $teams = Team::with(['company', 'users'])
                ->where('team_head_id', $authUser->id)
                ->orderBy('name')
                ->get();
        } else {
            // Regular agent: apni team dikhao with ALL members (read-only)
            $teams = Team::with(['company', 'users'])
                ->where('id', $authUser->team_id)
                ->get();
        }

        $teams->each(function (Team $team) use ($month, $year) {
            $team->currentTarget = TeamTarget::where([
                'team_id' => $team->id,
                'month'   => $month,
                'year'    => $year,
            ])->first();

            $team->users->each(function ($user) use ($team, $month, $year) {
                $user->currentTarget = UserTarget::where([
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'month'   => $month,
                    'year'    => $year,
                ])->first();
            });
        });

        return view('admin.targets.index', compact('teams', 'month', 'year', 'isAdmin', 'isTeamHead'));
    }

    // ─── Team Target ────────────────────────────────────────────────────────────

    public function setTeamTarget(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeTeam($team);

        $validated = $request->validate([
            'month'         => 'required|integer|min:1|max:12',
            'year'          => 'required|integer|min:2020|max:2100',
            'target_amount' => 'required|numeric|min:0',
            'notes'         => 'nullable|string',
        ]);

        TeamTarget::updateOrCreate(
            ['team_id' => $team->id, 'month' => $validated['month'], 'year' => $validated['year']],
            ['target_amount' => $validated['target_amount'], 'notes' => $validated['notes'] ?? null]
        );

        return back()->with('success', "Team target set for {$team->name}.");
    }

    // ─── User Target ────────────────────────────────────────────────────────────

    public function setUserTarget(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeTeam($team);

        $validated = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'month'         => 'required|integer|min:1|max:12',
            'year'          => 'required|integer|min:2020|max:2100',
            'target_amount' => 'required|numeric|min:0',
            'notes'         => 'nullable|string',
        ]);

        // Make sure the user actually belongs to this team
        $team->load('users');
        if (! $team->users->contains('id', $validated['user_id'])) {
            return back()->withErrors(['user_id' => 'This user does not belong to the selected team.']);
        }

        UserTarget::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'team_id' => $team->id,
                'month'   => $validated['month'],
                'year'    => $validated['year'],
            ],
            ['target_amount' => $validated['target_amount'], 'notes' => $validated['notes'] ?? null]
        );

        return back()->with('success', 'User target updated.');
    }
}
