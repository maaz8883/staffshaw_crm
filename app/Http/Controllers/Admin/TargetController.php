<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
use App\Models\TeamTarget;
use App\Models\UserTarget;
use App\Models\SubTeamHeadTarget;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
use App\Support\TargetCarryForward;
use Carbon\Carbon;
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

        if ($user->hasRole(['Admin', 'Manager'])) {
            return Team::with(['company', 'users'])->sales()->orderBy('name')->get();
        }

        // Team head: only teams where they are the head
        return Team::with(['company', 'users'])
            ->headedBy($user->id)
            ->orderBy('name')
            ->get();
    }

    private function authorizeTeam(Team $team): void
    {
        $user = Auth::user();

        if ($user->hasRole(['Admin', 'Manager'])) {
            return;
        }

        if (! $team->isHeadedBy($user->id)) {
            abort(403, 'You can only manage targets for your own team.');
        }
    }

    // ─── Overview ───────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $month    = (int) $request->get('month', now()->month);
        $year     = (int) $request->get('year', now()->year);
        $authUser = Auth::user();
        TargetCarryForward::ensureMonth($month, $year);

        $isAdmin    = $authUser->hasRole(['Admin', 'Manager']);
        $isTeamHead = Team::headedBy($authUser->id)->exists();
        $isSubTeamHead = \App\Models\SubTeamHead::where('user_id', $authUser->id)->exists();

        $withRelations = ['company', 'leaders', 'teamHead.role', 'users.role', 'users.subTeamHead', 'subTeamHeads.user', 'subTeamHeads.members', 'approvedProjectManagers.role'];

        if ($isAdmin) {
            $teams = Team::with($withRelations)->sales()->orderBy('name')->get();
        } elseif ($isTeamHead) {
            $teams = Team::with($withRelations)
                ->headedBy($authUser->id)
                ->orderBy('name')
                ->get();
        } else {
            $teams = Team::with($withRelations)
                ->where('id', $authUser->team_id)
                ->get();
        }

        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth();
        $agentIds = $teams->flatMap(function (Team $team) {
            return $team->users
                ->filter(fn ($u) => $u->role?->name === Role::AGENT)
                ->pluck('id')
                ->merge($team->headIds());
        })->filter()->unique()->values();

        $achievedByUser = collect();
        if ($agentIds->isNotEmpty()) {
            $achievedByUser = Sale::query()
                ->where('approval_status', Sale::APPROVAL_APPROVED)
                ->where('status', 'completed')
                ->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()])
                ->whereIn('user_id', $agentIds)
                ->selectRaw('user_id, SUM(amount) as total')
                ->groupBy('user_id')
                ->pluck('total', 'user_id');
        }

        $todayQuery = Sale::query()
            ->where('approval_status', Sale::APPROVAL_APPROVED)
            ->where('status', 'completed')
            ->whereDate('sale_date', now()->toDateString());
        if ($isAdmin) {
            // full org today
        } elseif ($isTeamHead) {
            $teamIds = Team::headedBy($authUser->id)->pluck('id');
            $memberIds = \App\Models\User::whereIn('team_id', $teamIds)->pluck('id')
                ->merge(Team::headedBy($authUser->id)->get()->flatMap->headIds());
            $todayQuery->whereIn('user_id', $memberIds->unique());
        } else {
            $todayQuery->where('user_id', $authUser->id);
        }
        $today = (float) $todayQuery->sum('amount');

        $teams->each(function (Team $team) use ($month, $year, $authUser, $isSubTeamHead, $achievedByUser) {
            // Target in force: this month's, else carried from the latest earlier month
            $team->currentTarget = TargetCarryForward::effective(TeamTarget::class, ['team_id' => $team->id], $month, $year);

            if ($isSubTeamHead) {
                $mySubHeadIds = \App\Models\SubTeamHead::where('user_id', $authUser->id)
                    ->where('team_id', $team->id)
                    ->pluck('id');

                if ($mySubHeadIds->isNotEmpty()) {
                    $team->setRelation('subTeamHeads', $team->subTeamHeads->filter(function ($sh) use ($mySubHeadIds) {
                        return $mySubHeadIds->contains($sh->id);
                    }));

                    $team->setRelation('users', $team->users->filter(function ($u) use ($mySubHeadIds, $authUser) {
                        return $mySubHeadIds->contains($u->sub_team_head_id) || (int) $u->id === (int) $authUser->id;
                    }));
                }
            }

            $team->subTeamHeads->each(function ($subHead) use ($team, $month, $year) {
                $subHead->currentTarget = TargetCarryForward::effective(SubTeamHeadTarget::class, ['sub_team_head_id' => $subHead->id, 'team_id' => $team->id], $month, $year);
            });

            $team->users->each(function ($user) use ($team, $month, $year, $achievedByUser) {
                $user->currentTarget = TargetCarryForward::effective(UserTarget::class, ['user_id' => $user->id, 'team_id' => $team->id], $month, $year);
                $user->month_achieved = (float) ($achievedByUser[$user->id] ?? 0);
            });

            if ($team->teamHead) {
                $team->teamHead->month_achieved = (float) ($achievedByUser[$team->teamHead->id] ?? 0);
                if (! isset($team->teamHead->currentTarget)) {
                    $team->teamHead->currentTarget = TargetCarryForward::effective(UserTarget::class, ['user_id' => $team->teamHead->id, 'team_id' => $team->id], $month, $year);
                }
            }
        });

        $visibleAgentIds = $teams->flatMap(function (Team $team) {
            return $team->users
                ->filter(fn ($u) => $u->role?->name === Role::AGENT)
                ->pluck('id');
        })->unique();

        $summary = [
            'today' => $today,
            'month' => (float) $visibleAgentIds->sum(fn ($id) => (float) ($achievedByUser[$id] ?? 0)),
            'target' => (float) $teams->sum(function (Team $team) {
                return $team->users
                    ->filter(fn ($u) => $u->role?->name === Role::AGENT)
                    ->sum(fn ($u) => (float) ($u->currentTarget?->target_amount ?? 0));
            }),
        ];

        return view('admin.targets.index', compact('teams', 'month', 'year', 'isAdmin', 'isTeamHead', 'isSubTeamHead', 'summary'));
    }

    // ─── Team Target ────────────────────────────────────────────────────────────

    public function setTeamTarget(Request $request, Team $team): RedirectResponse
    {
        $authUser = Auth::user();

        if (! $authUser->hasRole(['Admin', 'Manager']) && ! $team->isHeadedBy($authUser->id)) {
            abort(403, 'Only admins and the assigned team head can set this team target.');
        }

        $validated = $request->validate([
            'month'         => 'required|integer|min:1|max:12',
            'year'          => 'required|integer|min:2020|max:2100',
            'target_amount' => 'required|numeric|min:0|max:9999999999999.99',
            'notes'         => 'nullable|string',
        ], [
            'target_amount.max' => 'Target amount is too large. Maximum allowed is 9,999,999,999,999.99.',
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
        if (! $authUser->hasRole(['Admin', 'Manager']) && ! $team->isHeadedBy($authUser->id)) {
            abort(403, 'Only admins and team heads can set sub-team targets.');
        }

        $validated = $request->validate([
            'sub_team_head_id' => 'required|exists:sub_team_heads,id',
            'month'            => 'required|integer|min:1|max:12',
            'year'             => 'required|integer|min:2020|max:2100',
            'target_amount'    => 'required|numeric|min:0|max:9999999999999.99',
            'notes'            => 'nullable|string',
        ], [
            'target_amount.max' => 'Target amount is too large. Maximum allowed is 9,999,999,999,999.99.',
        ]);

        // Make sure the sub-team head belongs to this team
        $subTeamHead = \App\Models\SubTeamHead::find($validated['sub_team_head_id']);
        if ((int) $subTeamHead->team_id !== (int) $team->id) { // ids can arrive as strings from MySQL
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
        
        // Authorization: Admin/Manager, Team Head, or Sub-Team Head
        if (!$authUser->hasRole(['Admin', 'Manager']) && ! $team->isHeadedBy($authUser->id)) {
            $mySubHeadIds = \App\Models\SubTeamHead::where('user_id', $authUser->id)
                ->where('team_id', $team->id)
                ->pluck('id');

            if ($mySubHeadIds->isEmpty()) {
                abort(403, 'You can only manage targets for your team.');
            }

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);

            $targetUser = \App\Models\User::find($validated['user_id']);

            $isInMySubTeam = $targetUser
                && (
                    $mySubHeadIds->contains((int) $targetUser->sub_team_head_id)
                    || (int) $targetUser->id === (int) $authUser->id
                );

            if (!$isInMySubTeam) {
                abort(403, 'You can only set targets for members of your sub-team.');
            }
        }

        $validated = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'month'         => 'required|integer|min:1|max:12',
            'year'          => 'required|integer|min:2020|max:2100',
            'target_amount' => 'required|numeric|min:0|max:9999999999999.99',
            'notes'         => 'nullable|string',
        ], [
            'target_amount.max' => 'Target amount is too large. Maximum allowed is 9,999,999,999,999.99.',
        ]);

        // Make sure the user actually belongs to this team — either via users.team_id,
        // as the team head (whose own team_id need not point at the team they head),
        // or as an approved Project Manager (joined via Team_Membership).
        $team->load(['users', 'approvedProjectManagers']);
        $belongsToTeam = $team->users->contains('id', $validated['user_id'])
            || $team->isHeadedBy($validated['user_id'])
            || $team->approvedProjectManagers->contains('id', $validated['user_id']);

        if (! $belongsToTeam) {
            return back()->withErrors(['user_id' => 'This user does not belong to the selected team.']);
        }

        $targetUser = $team->users->firstWhere('id', $validated['user_id'])
            ?? \App\Models\User::find($validated['user_id']);
        if (! $targetUser || ! $targetUser->hasRole(Role::AGENT)) {
            return back()->withErrors(['user_id' => 'Sales targets are only for sales agents.']);
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

    // ─── Bulk User Targets (one button updates all members at once) ────────────

    public function bulkSetUserTargets(Request $request, Team $team): RedirectResponse
    {
        $authUser = Auth::user();

        $isAdmin    = $authUser->hasRole(['Admin', 'Manager']);
        $isTeamHead = $team->isHeadedBy($authUser->id);
        $mySubHeadIds = collect();

        if (! $isAdmin && ! $isTeamHead) {
            $mySubHeadIds = \App\Models\SubTeamHead::where('user_id', $authUser->id)
                ->where('team_id', $team->id)
                ->pluck('id');

            if ($mySubHeadIds->isEmpty()) {
                abort(403, 'You can only manage targets for your team.');
            }
        }

        $validated = $request->validate([
            'month'                    => 'required|integer|min:1|max:12',
            'year'                     => 'required|integer|min:2020|max:2100',
            'targets'                  => 'required|array',
            'targets.*.target_amount' => 'nullable|numeric|min:0|max:9999999999999.99',
            'targets.*.notes'         => 'nullable|string',
        ], [
            'targets.*.target_amount.max' => 'One of the target amounts is too large. Maximum allowed is 9,999,999,999,999.99.',
        ]);

        $team->load(['users', 'approvedProjectManagers']);

        $updated = 0;
        $skipped = 0;

        foreach ($validated['targets'] as $userId => $row) {
            // Skip rows the user left blank — bulk save should only touch filled-in fields.
            if (! isset($row['target_amount']) || $row['target_amount'] === '' || $row['target_amount'] === null) {
                continue;
            }

            $targetUser = \App\Models\User::find($userId);
            if (! $targetUser || ! $targetUser->hasRole(Role::AGENT)) {
                $skipped++;
                continue;
            }

            // Sub-team heads may only bulk-update members of their own sub-team.
            if ($mySubHeadIds->isNotEmpty()) {
                $isInMySubTeam = $mySubHeadIds->contains((int) $targetUser->sub_team_head_id)
                    || (int) $targetUser->id === (int) $authUser->id;

                if (! $isInMySubTeam) {
                    $skipped++;
                    continue;
                }
            }

            // Must actually belong to this team (member, team head, or approved Project Manager).
            $belongsToTeam = $team->users->contains('id', $targetUser->id)
                || $team->isHeadedBy($targetUser->id)
                || $team->approvedProjectManagers->contains('id', $targetUser->id);

            if (! $belongsToTeam) {
                $skipped++;
                continue;
            }

            UserTarget::updateOrCreate(
                [
                    'user_id' => $targetUser->id,
                    'team_id' => $team->id,
                    'month'   => $validated['month'],
                    'year'    => $validated['year'],
                ],
                [
                    'target_amount' => $row['target_amount'],
                    'notes'         => $row['notes'] ?? null,
                ]
            );

            $updated++;
        }

        if ($updated > 0) {
            $monthName = \DateTime::createFromFormat('!m', $validated['month'])->format('F');
            ActivityLogger::log(
                Auth::user(),
                UserActivityLog::TYPE_USER_TARGET_SET,
                "Bulk updated {$updated} user target(s) for {$team->name} ({$monthName} {$validated['year']})"
            );
        }

        $message = "{$updated} target(s) updated.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (not in your team/sub-team).";
        }

        return back()->with('success', $message);
    }
}
