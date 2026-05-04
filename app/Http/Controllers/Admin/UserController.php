<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
use App\Support\AuthScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function index(): View
    {
        $companies = Company::query()->orderBy('name')->get();
        $teams     = Team::query()->orderBy('name')->get();
        $roles     = Role::query()->orderBy('name')->get();

        return view('admin.users.index', compact('companies', 'teams', 'roles'));
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['role', 'team', 'company'])
            ->select('users.*');

        AuthScope::scopeUsers($query);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        return DataTables::eloquent($query)
            ->addColumn('role_name', fn (User $user) => e($user->role?->name ?? '-'))
            ->addColumn('team_name', fn (User $user) => e($user->team?->name ?? '-'))
            ->addColumn('company_name', fn (User $user) => e($user->company?->name ?? '-'))
            ->addColumn('actions', function (User $user) {
                $showUrl = route('admin.users.show', $user);
                $editUrl = route('admin.users.edit', $user);
                $deleteUrl = route('admin.users.destroy', $user);
                $csrf = csrf_field();
                $method = method_field('DELETE');

                return '
                    <a href="'.$showUrl.'" class="btn btn-sm btn-outline-info">View</a>
                    <a href="'.$editUrl.'" class="btn btn-sm btn-outline-warning">Edit</a>
                    <form action="'.$deleteUrl.'" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this user?">
                        '.$csrf.$method.'
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                ';
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function create(): View
    {
        $roles     = Role::query()->orderBy('name')->get();
        $teams     = AuthScope::teamsForDropdown();
        $companies = AuthScope::isAgent()
            ? \App\Models\Company::where('id', auth()->user()->company_id)->get()
            : Company::query()->orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'teams', 'companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|max:255|unique:users,email',
            'password'         => 'required|string|min:8|confirmed',
            'role_id'          => 'nullable|exists:roles,id',
            'company_id'       => 'nullable|exists:companies,id',
            'team_id'          => 'nullable|exists:teams,id',
            'sub_team_id'      => 'nullable|exists:teams,id',
            'sub_team_head_id' => 'nullable|exists:sub_team_heads,id',
        ]);

        $user = User::query()->create([
            'name'             => $validated['name'],
            'email'            => $validated['email'],
            'password'         => Hash::make($validated['password']),
            'role_id'          => $validated['role_id'] ?? null,
            'company_id'       => $validated['company_id'] ?? null,
            'team_id'          => $validated['team_id'] ?? null,
            'sub_team_id'      => $validated['sub_team_id'] ?? null,
            'sub_team_head_id' => $validated['sub_team_head_id'] ?? null,
            'account_status'   => User::ACCOUNT_ACTIVE,
        ]);

        // Log activity
        $user->load(['role', 'team', 'subTeam', 'subTeamHead', 'company']);
        $roleName = $user->role?->name ?? 'No role';
        $teamName = $user->team?->name ?? 'No team';
        $subTeamName = $user->subTeam ? " / {$user->subTeam->name}" : '';
        $subTeamHeadName = $user->subTeamHead ? " (Sub-Team Head: {$user->subTeamHead->title})" : '';
        $companyName = $user->company?->name ?? 'No company';
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_USER_CREATED,
            "Created user: {$user->name} ({$user->email}) - Role: {$roleName}, Team: {$teamName}{$subTeamName}{$subTeamHeadName}, Company: {$companyName}"
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $user->load(['role', 'team']);
        $activityLogs = $user->activityLogs()->paginate(20);

        return view('admin.users.show', compact('user', 'activityLogs'));
    }

    public function edit(User $user): View
    {
        $roles     = Role::query()->orderBy('name')->get();
        $teams     = AuthScope::teamsForDropdown();
        $companies = AuthScope::isAgent()
            ? \App\Models\Company::where('id', auth()->user()->company_id)->get()
            : Company::query()->orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'teams', 'companies'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'         => 'nullable|string|min:8|confirmed',
            'role_id'          => 'nullable|exists:roles,id',
            'company_id'       => 'nullable|exists:companies,id',
            'team_id'          => 'nullable|exists:teams,id',
            'sub_team_id'      => 'nullable|exists:teams,id',
            'sub_team_head_id' => 'nullable|exists:sub_team_heads,id',
        ]);

        $oldName = $user->name;
        $oldEmail = $user->email;

        $payload = [
            'name'             => $validated['name'],
            'email'            => $validated['email'],
            'role_id'          => $validated['role_id'] ?? null,
            'company_id'       => $validated['company_id'] ?? null,
            'team_id'          => $validated['team_id'] ?? null,
            'sub_team_id'      => $validated['sub_team_id'] ?? null,
            'sub_team_head_id' => $validated['sub_team_head_id'] ?? null,
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);

        // Log activity
        $user->load(['role', 'team', 'subTeam', 'subTeamHead', 'company']);
        $roleName = $user->role?->name ?? 'No role';
        $teamName = $user->team?->name ?? 'No team';
        $subTeamName = $user->subTeam ? " / {$user->subTeam->name}" : '';
        $subTeamHeadName = $user->subTeamHead ? " (Sub-Team Head: {$user->subTeamHead->title})" : '';
        $companyName = $user->company?->name ?? 'No company';
        $changes = [];
        if ($oldName !== $user->name) $changes[] = "name changed from '{$oldName}'";
        if ($oldEmail !== $user->email) $changes[] = "email changed from '{$oldEmail}'";
        if (!empty($validated['password'])) $changes[] = "password updated";
        $changeText = !empty($changes) ? ' (' . implode(', ', $changes) . ')' : '';
        
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_USER_UPDATED,
            "Updated user: {$user->name} ({$user->email}){$changeText} - Role: {$roleName}, Team: {$teamName}{$subTeamName}{$subTeamHeadName}, Company: {$companyName}"
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $userName = $user->name;
        $userEmail = $user->email;
        $roleName = $user->role?->name ?? 'No role';
        $teamName = $user->team?->name ?? 'No team';
        $companyName = $user->company?->name ?? 'No company';

        $user->delete();

        // Log activity
        ActivityLogger::log(
            Auth::user(),
            UserActivityLog::TYPE_USER_DELETED,
            "Deleted user: {$userName} ({$userEmail}) - Role: {$roleName}, Team: {$teamName}, Company: {$companyName}"
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
