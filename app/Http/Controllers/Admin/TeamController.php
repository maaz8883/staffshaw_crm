<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use App\Support\AuthScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

                $html = '<a href="'.$showUrl.'" class="btn btn-sm btn-outline-info">View</a>';
                if (!$isAgent) {
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

        Team::query()->create($validated);

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team created successfully.');
    }

    public function show(Team $team): View
    {
        $team->load(['company', 'teamHead', 'users']);

        return view('admin.teams.show', compact('team'));
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

        $team->update($validated);

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team updated successfully.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team deleted successfully.');
    }
}
