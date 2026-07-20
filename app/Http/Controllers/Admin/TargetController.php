<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamTarget;
use App\Models\UserTarget;
use App\Models\SubTeamHeadTarget;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
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

        if ((int)$team->team_head_id !== (int)$user->id) {
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
        $isSubTeamHead = \App\Models\SubTeamHead::where('user_id', $authUser->id)->exists();

        $withRelations = ['company', 'users.subTeamHead', 'subTeamHeads.user', 'subTeamHeads.members', 'approvedProjectManagers.role'];

        if ($isAdmin) {
            // Admin: all teams
            $teams = Team::with($withRelations)->orderBy('name')->get();
        } elseif ($isTeamHead) {
            // Team head: only their teams
            $teams = Team::with($withRelations)
                ->where('team_head_id', $authUser->id)
                ->orderBy('name')
                ->get();
        } else {
            // Regular agent or Sub-Team Head: apni team dikhao
            $teams = Team::with($withRelations)
                ->where('id', $authUser->team_id)
                ->get();
        }

        $teams->each(function (Team $team) use ($month, $year, $authUser, $isSubTeamHead) {
            $team->currentTarget = TeamTarget::where([
                'team_id' => $team->id,
                'month'   => $month,
                'year'    => $year,
            ])->first();

            // If user is a sub-team head, filter to show only their sub-team
            if ($isSubTeamHead) {
                $userSubTeamHead = \App\Models\SubTeamHead::where('user_id', $authUser->id)
                    ->where('team_id', $team->id)
                    ->first();
                
                if ($userSubTeamHead) {
                    // Filter subTeamHeads to only show the one they manage
                    $team->setRelation('subTeamHeads', $team->subTeamHeads->filter(function($sh) use ($userSubTeamHead) {
                        return $sh->id === $userSubTeamHead->id;
                    }));
                    
                    // Filter users to only show those in their sub-team (including themselves)
                    $team->setRelation('users', $team->users->filter(function($u) use ($userSubTeamHead) {
                        return $u->sub_team_head_id === $userSubTeamHead->id || $u->id === $userSubTeamHead->user_id;
                    }));
                }
            }

            // Load sub-team head targets
            $team->subTeamHeads->each(function ($subHead) use ($team, $month, $year) {
                $subHead->currentTarget = SubTeamHeadTarget::where([
                    'sub_team_head_id' => $subHead->id,
                    'team_id' => $team->id,
                    'month'   => $month,
                    'year'    => $year,
                ])->first();
            });

            $team->users->each(function ($user) use ($team, $month, $year) {
                $user->currentTarget = UserTarget::where([
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'month'   => $month,
                    'year'    => $year,
                ])->first();
            });

            // Project Managers (approved Team_Membership) — not linked via users.team_id.
            $team->approvedProjectManagers->each(function ($pm) use ($team, $month, $year) {
                $pm->currentTarget = UserTarget::where([
                    'user_id' => $pm->id,
                    'team_id' => $team->id,
                    'month'   => $month,
                    'year'    => $year,
                ])->first();
            });
        });

        return view('admin.targets.index', compact('teams', 'month', 'year', 'isAdmin', 'isTeamHead', 'isSubTeamHead'));
    }

    // ─── Team Target ────────────────────────────────────────────────────────────

    public function setTeamTarget(Request $request, Team $team): RedirectResponse
    {
        $authUser = Auth::user();

        if (! $authUser->hasRole('Admin') && (int) $team->team_head_id !== (int) $authUser->id) {
            abort(403, 'Only admins and the team head can set team targets.');
        }

        $validated = $request->validate([
            'month'         => 'required|integer|min:1|max:12',
            'year'          => 'required|integer|min:2020|max:2100',
            'target_amount' => 'required|numeric|min:0',
            'notes'         => 'nullable|string',
        ]);

        $existing = TeamTarget::where([
            'team_id' => $team->id,
            'month'   => $validated['month'],
            'year'    => $validated['year'],
        ])->first();

        TeamTarget::updateOrCreate(
            ['team_id' => $team->id, 'month' => $validated['month'], 'year' => $validated['year']],
            ['target_amount' => $validated['target_amount'], 'notes' => $validated['notes'] ?? null]
        );

        // Log activity
        $monthName = \DateTime::createFromFormat('!m', $validated['month'])->format('F');
        $action = $existing ? 'Updated' : 'Set';
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_TEAM_TARGET_SET,
            "{$action} team target for {$team->name}: \$" . number_format($validated['target_amount'], 2) . " ({$monthName} {$validated['year']})"
        );

        return back()->with('success', "Team target set for {$team->name}.");
    }

    // ─── Sub-Team Head Target ───────────────────────────────────────────────────

    public function setSubTeamHeadTarget(Request $request, Team $team): RedirectResponse
    {
        $authUser = Auth::user();
        
        // Authorization: Only Admin or Team Head can set sub-team targets
        if (!$authUser->hasRole('Admin') && (int)$team->team_head_id !== (int)$authUser->id) {
            abort(403, 'Only admins and team heads can set sub-team targets.');
        }

        $validated = $request->validate([
            'sub_team_head_id' => 'required|exists:sub_team_heads,id',
            'month'            => 'required|integer|min:1|max:12',
            'year'             => 'required|integer|min:2020|max:2100',
            'target_amount'    => 'required|numeric|min:0',
            'notes'            => 'nullable|string',
        ]);

        // Make sure the sub-team head belongs to this team
        $subTeamHead = \App\Models\SubTeamHead::find($validated['sub_team_head_id']);
        if ($subTeamHead->team_id !== $team->id) {
            return back()->withErrors(['sub_team_head_id' => 'This sub-team head does not belong to the selected team.']);
        }

        $existing = SubTeamHeadTarget::where([
            'sub_team_head_id' => $validated['sub_team_head_id'],
            'team_id'          => $team->id,
            'month'            => $validated['month'],
            'year'             => $validated['year'],
        ])->first();

        SubTeamHeadTarget::updateOrCreate(
            [
                'sub_team_head_id' => $validated['sub_team_head_id'],
                'team_id'          => $team->id,
                'month'            => $validated['month'],
                'year'             => $validated['year'],
            ],
            ['target_amount' => $validated['target_amount'], 'notes' => $validated['notes'] ?? null]
        );

        // Log activity
        $monthName = \DateTime::createFromFormat('!m', $validated['month'])->format('F');
        $action = $existing ? 'Updated' : 'Set';
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_TEAM_TARGET_SET,
            "{$action} sub-team target for {$subTeamHead->title} ({$team->name}): \$" . number_format($validated['target_amount'], 2) . " ({$monthName} {$validated['year']})"
        );

        return back()->with('success', 'Sub-team target updated.');
    }

    // ─── User Target ────────────────────────────────────────────────────────────

    public function setUserTarget(Request $request, Team $team): RedirectResponse
    {
        $authUser = Auth::user();
        
        // Authorization: Admin, Team Head, or Sub-Team Head
        if (!$authUser->hasRole('Admin') && (int)$team->team_head_id !== (int)$authUser->id) {
            // Check if user is a sub-team head
            $subTeamHead = \App\Models\SubTeamHead::where('user_id', $authUser->id)
                ->where('team_id', $team->id)
                ->first();
            
            if (!$subTeamHead) {
                abort(403, 'You can only manage targets for your team.');
            }
            
            // Validate that the target user belongs to this sub-team head
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);
            
            $targetUser = \App\Models\User::find($validated['user_id']);
            
            // Check if user is in this sub-team
            $isInMySubTeam = $targetUser->sub_team_head_id === $subTeamHead->id || $targetUser->id === $subTeamHead->user_id;
            
            if (!$isInMySubTeam) {
                abort(403, 'You can only set targets for members of your sub-team.');
            }
        }

        $validated = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'month'         => 'required|integer|min:1|max:12',
            'year'          => 'required|integer|min:2020|max:2100',
            'target_amount' => 'required|numeric|min:0',
            'notes'         => 'nullable|string',
        ]);

        // Make sure the user actually belongs to this team — either via users.team_id,
        // as the team head (whose own team_id need not point at the team they head),
        // or as an approved Project Manager (joined via Team_Membership).
        $team->load(['users', 'approvedProjectManagers']);
        $belongsToTeam = $team->users->contains('id', $validated['user_id'])
            || (int) $team->team_head_id === (int) $validated['user_id']
            || $team->approvedProjectManagers->contains('id', $validated['user_id']);

        if (! $belongsToTeam) {
            return back()->withErrors(['user_id' => 'This user does not belong to the selected team.']);
        }

        $existing = UserTarget::where([
            'user_id' => $validated['user_id'],
            'team_id' => $team->id,
            'month'   => $validated['month'],
            'year'    => $validated['year'],
        ])->first();

        UserTarget::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'team_id' => $team->id,
                'month'   => $validated['month'],
                'year'    => $validated['year'],
            ],
            ['target_amount' => $validated['target_amount'], 'notes' => $validated['notes'] ?? null]
        );

        // Log activity
        $targetUser = $team->users->firstWhere('id', $validated['user_id'])
            ?? \App\Models\User::find($validated['user_id']);
        $monthName = \DateTime::createFromFormat('!m', $validated['month'])->format('F');
        $action = $existing ? 'Updated' : 'Set';
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_USER_TARGET_SET,
            "{$action} user target for {$targetUser->name} ({$team->name}): \$" . number_format($validated['target_amount'], 2) . " ({$monthName} {$validated['year']})"
        );

        return back()->with('success', 'User target updated.');
    }
}
