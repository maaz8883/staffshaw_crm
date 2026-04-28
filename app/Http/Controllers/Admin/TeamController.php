<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
use App\Support\AuthScope;
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
            ->with(['company', 'teamHead'])
            ->withCount('users');

        AuthScope::scopeByTeam($query, 'id')
                 ->when(AuthScope::isAgent(), fn ($q) => $q->where('company_id', auth()->user()->company_id));

        return DataTables::eloquent($query)
            ->addColumn('company_name', fn (Team $team) => e($team->company?->name ?? '-'))
            ->addColumn('team_head_name', fn (Team $team) => e($team->teamHead?->name ?? '-'))
            ->editColumn('description', fn (Team $team) => e($team->description ?: '-'))
            ->addColumn('actions', function (Team $team) {
                $showUrl = route('admin.teams.show', $team);
                $editUrl = route('admin.teams.edit', $team);
                $deleteUrl = route('admin.teams.destroy', $team);
                $csrf = csrf_field();
                $method = method_field('DELETE');
                $isAgent = auth()->user()?->hasRole('Agent');
                $isPpc   = auth()->user()?->hasRole('PPC');

                $html = '<a href="'.$showUrl.'" class="btn btn-sm btn-outline-info">View</a>';
                if (!$isAgent && !$isPpc) {
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
        $companies = Company::query()->orderBy('name')->get();
        $users     = User::query()->orderBy('name')->get();

        return view('admin.teams.create', compact('companies', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id'   => 'nullable|exists:companies,id',
            'team_head_id' => 'nullable|exists:users,id',
            'name'         => 'required|string|max:255|unique:teams,name',
            'description'  => 'nullable|string',
        ]);

        $team = Team::query()->create($validated);

        // Log activity
        $companyName = $team->company?->name ?? 'No company';
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_TEAM_CREATED,
            "Created team: {$team->name} (Company: {$companyName})"
        );

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team created successfully.');
    }

    public function show(Team $team): View
    {
        $team->load(['company', 'teamHead', 'users']);

        $month = (int) request()->get('month', now()->month);
        $year  = (int) request()->get('year', now()->year);

        $isPpc = auth()->user()->hasRole('PPC');

        $spendings = \App\Models\TeamPpcSpending::where('team_id', $team->id)
            ->where('month', $month)
            ->where('year', $year)
            ->with('user')
            ->latest()
            ->get();

        $totalSpending = $spendings->sum('amount');

        return view('admin.teams.show', compact('team', 'spendings', 'totalSpending', 'month', 'year', 'isPpc'));
    }

    public function edit(Team $team): View
    {
        $companies = Company::query()->orderBy('name')->get();
        $users     = User::query()->orderBy('name')->get();

        return view('admin.teams.edit', compact('team', 'companies', 'users'));
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'company_id'   => 'nullable|exists:companies,id',
            'team_head_id' => 'nullable|exists:users,id',
            'name'         => 'required|string|max:255|unique:teams,name,' . $team->id,
            'description'  => 'nullable|string',
        ]);

        $oldName = $team->name;
        $team->update($validated);

        // Log activity
        $team->load('company');
        $companyName = $team->company?->name ?? 'No company';
        $nameChanged = $oldName !== $team->name ? " (renamed from {$oldName})" : '';
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_TEAM_UPDATED,
            "Updated team: {$team->name}{$nameChanged} (Company: {$companyName})"
        );

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team updated successfully.');
    }

    public function destroy(Team $team): RedirectResponse
    {
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
}
