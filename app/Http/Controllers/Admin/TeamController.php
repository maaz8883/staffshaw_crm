<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
use App\Support\AuthScope;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('admin.teams.index');
    }

    public function datatable(): JsonResponse
    {
        $query = Team::query()
            ->select('teams.*')
            ->with(['company', 'teamHead', 'coHead'])
            ->withCount('users');

        AuthScope::scopeByTeam($query, 'id');

        return DataTables::eloquent($query)
            ->addColumn('company_name', fn (Team $team) => e($team->company?->name ?? '-'))
            ->addColumn('team_head_name', fn (Team $team) => e($team->headNames() ?: '-'))
            ->editColumn('description', fn (Team $team) => e($team->description ?: '-'))
            ->addColumn('actions', function (Team $team) {
                $showUrl = route('admin.teams.show', $team);
                $editUrl = route('admin.teams.edit', $team);
                $deleteUrl = route('admin.teams.destroy', $team);
                $csrf = csrf_field();
                $method = method_field('DELETE');
                $canManage = AuthScope::isAdminOrManager();

                $html = '<a href="'.$showUrl.'" class="btn btn-sm btn-outline-info">View</a>';
                if ($canManage) {
                    $html .= '
                    <a href="'.$editUrl.'" class="btn btn-sm btn-outline-warning">Edit</a>
                    <form action="'.$deleteUrl.'" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this team?">
                        '.$csrf.$method.'
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>';
                }
                return $html;
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorizeManage();

        $companies = Company::query()->orderBy('name')->get();
        $allUsers  = User::query()->orderBy('name')->get();
        $teamUsers = collect(); // Empty for create
        $brands    = Brand::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.teams.create', compact('companies', 'allUsers', 'teamUsers', 'brands'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'company_id'       => 'nullable|exists:companies,id',
            'team_head_id'     => 'nullable|exists:users,id',
            'co_head_id'       => 'nullable|different:team_head_id|exists:users,id',
            'name'             => 'required|string|max:255|unique:teams,name',
            'description'      => 'nullable|string',
            'brand_ids'        => 'nullable|array',
            'brand_ids.*'      => 'integer|exists:brands,id',
            'sub_heads'        => 'nullable|array',
            'sub_heads.*.id'   => 'nullable|integer',
            'sub_heads.*.title' => 'required|string|max:255',
            'sub_heads.*.user_id' => 'required|exists:users,id',
        ]);

        $team = Team::query()->create([
            'company_id'   => CompanyContext::idForCreate($validated['company_id'] ?? null),
            'team_head_id' => $validated['team_head_id'] ?? null,
            'co_head_id'   => $validated['co_head_id'] ?? null,
            'name'         => $validated['name'],
            'description'  => $validated['description'] ?? null,
            'is_production' => $request->boolean('is_production'),
        ]);

        $team->brands()->sync($validated['brand_ids'] ?? []);
        $this->syncSubTeamHeads($team, $validated['sub_heads'] ?? []);
        $this->syncTeamHeadMembership($team);

        // Log activity
        $companyName = $team->company?->name ?? 'No company';
        $subHeadsCount = $team->subTeamHeads()->count();
        $subHeadsInfo = $subHeadsCount > 0 ? " with {$subHeadsCount} sub-team heads" : '';
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_TEAM_CREATED,
            "Created team: {$team->name} (Company: {$companyName}){$subHeadsInfo}"
        );

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team created successfully.');
    }

    public function show(Team $team): View
    {
        $user = Auth::user();

        // Requirement 10.1/10.4 — Project Manager can only view teams they have an approved membership for.
        if ($user->hasRole(\App\Models\Role::PROJECT_MANAGER) && ! $user->approvedTeamIds()->contains($team->id)) {
            abort(403);
        }

        if (! AuthScope::canAccessTeam((int) $team->id) && ! $user->hasRole(\App\Models\Role::PROJECT_MANAGER)) {
            abort(403);
        }

        // Production teams have no sales targets: show members + their Trello work instead
        if ($user->hasRole(\App\Models\Role::PRODUCTION)) {
            return $this->productionTeamView($team);
        }

        $team->load([
            'company', 
            'teamHead', 
            'coHead',
            'users' => function($query) {
                $query->with(['role', 'subTeamHead.user'])->orderBy('name');
            },
            'subTeamHeads.user',
            'approvedProjectManagers.role',
            'brands',
        ]);

        $month = (int) request()->get('month', now()->month);
        $year  = (int) request()->get('year', now()->year);
        \App\Support\TargetCarryForward::ensureMonth($month, $year);

        // Current month's target for the team, its team head, every member, and each Project Manager.
        $team->currentTarget = \App\Models\TeamTarget::where('team_id', $team->id)
            ->where('month', $month)->where('year', $year)
            ->first();

        $targetLookup = function (int $userId) use ($team, $month, $year) {
            return \App\Models\UserTarget::where('user_id', $userId)
                ->where('team_id', $team->id)
                ->where('month', $month)->where('year', $year)
                ->value('target_amount');
        };

        foreach ([$team->teamHead, $team->coHead] as $lead) {
            if ($lead) {
                $lead->current_target_amount = $targetLookup($lead->id);
            }
        }
        $team->users->each(function ($member) use ($targetLookup) {
            $member->current_target_amount = $targetLookup($member->id);
        });
        $team->approvedProjectManagers->each(function ($pm) use ($targetLookup) {
            $pm->current_target_amount = $targetLookup($pm->id);
        });

        $isPpc = auth()->user()->hasRole('PPC');

        $spendings = \App\Models\TeamPpcSpending::where('team_id', $team->id)
            ->where('month', $month)
            ->where('year', $year)
            ->with('user')
            ->latest()
            ->get();

        $totalSpending = $spendings->sum('amount');

        $canManageMembers = $user->hasRole(\App\Models\Role::ADMIN);
        // PPC spend is a business cost: Admin, Manager, Finance, PPC and this team's lead only
        $canSeePpc = $user->hasRole([\App\Models\Role::ADMIN, \App\Models\Role::MANAGER, \App\Models\Role::FINANCE, \App\Models\Role::PPC])
            || $team->isHeadedBy($user->id);

        return view('admin.teams.show', compact(
            'team', 'spendings', 'totalSpending', 'month', 'year', 'isPpc', 'canManageMembers', 'canSeePpc'
        ));
    }

    public function edit(Team $team): View
    {
        $this->authorizeManage();

        $companies = Company::query()->orderBy('name')->get();
        $allUsers  = User::query()->orderBy('name')->get();
        $teamUsers = $team->users()->with(['role', 'subTeamHead'])->orderBy('name')->get(); // Only users in this team
        $team->load(['subTeamHeads.user', 'brands']);
        $brands = Brand::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.teams.edit', compact('team', 'companies', 'allUsers', 'teamUsers', 'brands'));
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'company_id'       => 'nullable|exists:companies,id',
            'team_head_id'     => 'nullable|exists:users,id',
            'co_head_id'       => 'nullable|different:team_head_id|exists:users,id',
            'name'             => 'required|string|max:255|unique:teams,name,' . $team->id,
            'description'      => 'nullable|string',
            'brand_ids'        => 'nullable|array',
            'brand_ids.*'      => 'integer|exists:brands,id',
            'sub_heads'        => 'nullable|array',
            'sub_heads.*.id'   => 'nullable|integer',
            'sub_heads.*.title' => 'required|string|max:255',
            'sub_heads.*.user_id' => 'required|exists:users,id',
        ]);

        $oldName = $team->name;
        
        $team->update([
            'company_id'   => CompanyContext::idForUpdate($team->company_id, $validated['company_id'] ?? null),
            'team_head_id' => $validated['team_head_id'] ?? null,
            'co_head_id'   => $validated['co_head_id'] ?? null,
            'name'         => $validated['name'],
            'description'  => $validated['description'] ?? null,
            'is_production' => $request->boolean('is_production'),
        ]);

        $team->brands()->sync($validated['brand_ids'] ?? []);

        // Preserve sub-head IDs so users.sub_team_head_id stays intact
        $this->syncSubTeamHeads($team, $validated['sub_heads'] ?? []);
        $this->syncTeamHeadMembership($team);

        // Log activity
        $team->load('company');
        $companyName = $team->company?->name ?? 'No company';
        $nameChanged = $oldName !== $team->name ? " (renamed from {$oldName})" : '';
        $subHeadsCount = $team->subTeamHeads()->count();
        $subHeadsInfo = $subHeadsCount > 0 ? " with {$subHeadsCount} sub-team heads" : '';
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_TEAM_UPDATED,
            "Updated team: {$team->name}{$nameChanged} (Company: {$companyName}){$subHeadsInfo}"
        );

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team updated successfully.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorizeManage();

        $teamName = $team->name;
        $companyName = $team->company?->name ?? 'No company';
        $membersCount = $team->users()->count();

        $team->delete();

        // Log activity
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_TEAM_DELETED,
            "Deleted team: {$teamName} (Company: {$companyName}, {$membersCount} members)"
        );

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team deleted successfully.');
    }

    /**
     * Get sub-teams for a given team (AJAX endpoint)
     */
    public function subTeams(Team $team): JsonResponse
    {
        $subTeams = Team::query()
            ->where('parent_team_id', $team->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($subTeams);
    }

    /**
     * Get sub-team heads for a given team (AJAX endpoint)
     */
    public function subTeamHeads(Team $team): JsonResponse
    {
        $subTeamHeads = $team->subTeamHeads()
            ->with('user:id,name')
            ->orderBy('title')
            ->get(['id', 'title', 'user_id'])
            ->map(function ($subHead) {
                return [
                    'id' => $subHead->id,
                    'title' => $subHead->title,
                    'user_id' => $subHead->user_id,
                    'user_name' => $subHead->user->name,
                ];
            });

        return response()->json($subTeamHeads);
    }

    private function authorizeManage(): void
    {
        if (! AuthScope::isAdminOrManager()) {
            abort(403, 'Only Admin or Manager can manage teams.');
        }
    }

    /**
     * Update-or-create sub-team heads without wiping member FKs.
     * Only rows removed from the form are deleted.
     *
     * @param  array<int, array{id?: mixed, title: string, user_id: mixed}>  $subHeads
     */
    private function syncSubTeamHeads(Team $team, array $subHeads): void
    {
        $keptIds = [];

        foreach ($subHeads as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $userId = (int) ($row['user_id'] ?? 0);
            if ($title === '' || $userId < 1) {
                continue;
            }

            $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $existing = $id
                ? $team->subTeamHeads()->where('id', $id)->first()
                : null;

            if ($existing) {
                $existing->update([
                    'title' => $title,
                    'user_id' => $userId,
                ]);
                $keptIds[] = $existing->id;
            } else {
                $created = $team->subTeamHeads()->create([
                    'title' => $title,
                    'user_id' => $userId,
                ]);
                $keptIds[] = $created->id;
            }

            // Keep the sub-head user on this team
            User::where('id', $userId)->update(['team_id' => $team->id]);
        }

        $query = $team->subTeamHeads();
        if ($keptIds === []) {
            $query->delete();
        } else {
            $query->whereNotIn('id', $keptIds)->delete();
        }
    }

    /** Ensure both team leads' users.team_id matches the team they lead. */
    private function syncTeamHeadMembership(Team $team): void
    {
        if (! $team->headIds()) {
            return;
        }

        $attrs = ['team_id' => $team->id];
        if ($team->company_id) {
            $attrs['company_id'] = $team->company_id;
        }

        User::whereIn('id', $team->headIds())->update($attrs);
    }

    /**
     * "My Team" for production teams: members and a small work report built from the
     * CRM's Trello activity log (who moved / created / completed / commented how much).
     */
    private function productionTeamView(Team $team): View
    {
        $periods = [
            'week' => ['Last 7 days', now()->subDays(7)],
            'month' => ['This month', now()->startOfMonth()],
            '30d' => ['Last 30 days', now()->subDays(30)],
        ];
        $period = array_key_exists(request('period'), $periods) ? request('period') : 'week';
        [$periodLabel, $since] = $periods[$period];

        $teamIds = $team->subTeams()->pluck('id')->push($team->id);
        $members = User::query()
            ->with('role')
            ->where(fn ($q) => $q->whereIn('team_id', $teamIds)->orWhereIn('id', $team->headIds() ?: [0]))
            ->orderBy('name')
            ->get();

        // One row per member per action kind, then pivot in PHP
        $counts = \App\Models\TrelloActivityLog::query()
            ->selectRaw('user_id, action, COUNT(*) as total, MAX(occurred_at) as last_at')
            ->whereIn('user_id', $members->pluck('id'))
            ->where('occurred_at', '>=', $since)
            ->groupBy('user_id', 'action')
            ->get()
            ->groupBy('user_id');

        $columns = [
            'created' => ['card_created'],
            'moved' => ['card_moved'],
            'completed' => ['card_completed', 'checkitem_completed'],
            'comments' => ['comment_added'],
            'files' => ['attachment_added'],
        ];
        $report = $members->map(function (User $member) use ($counts, $columns, $team) {
            $rows = $counts->get($member->id, collect());
            $row = [
                'user' => $member,
                'is_head' => $team->isHeadedBy($member->id),
                'total' => (int) $rows->sum('total'),
                'last_at' => $rows->max('last_at'),
            ];
            foreach ($columns as $key => $actions) {
                $row[$key] = (int) $rows->whereIn('action', $actions)->sum('total');
            }

            return $row;
        })->sortByDesc('total')->values();

        $recent = \App\Models\TrelloActivityLog::query()
            ->with('user:id,name')
            ->whereIn('user_id', $members->pluck('id'))
            ->where('occurred_at', '>=', $since)
            ->latest('occurred_at')
            ->limit(30)
            ->get();

        $team->load('teamHead');

        return view('admin.teams.production-show', compact('team', 'report', 'recent', 'period', 'periods', 'periodLabel'));
    }
}
