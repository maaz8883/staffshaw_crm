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
use App\Support\CompanyContext;
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
        if ($request->filled('status')) {
            $query->where('account_status', $request->status);
        }

        return DataTables::eloquent($query)
            ->addColumn('role_name', fn (User $user) => e($user->role?->name ?? '-'))
            ->addColumn('team_name', fn (User $user) => e($user->team?->name ?? '-'))
            ->addColumn('company_name', fn (User $user) => e($user->company?->name ?? '-'))
            ->addColumn('status_badge', function (User $user) {
                [$label, $color] = match ($user->account_status) {
                    User::ACCOUNT_ACTIVE => ['Active', 'success'],
                    User::ACCOUNT_BANNED => ['Banned', 'danger'],
                    User::ACCOUNT_PENDING => ['Pending', 'warning'],
                    User::ACCOUNT_REJECTED => ['Rejected', 'secondary'],
                    default => [ucfirst((string) $user->account_status), 'secondary'],
                };

                return '<span class="badge text-bg-'.$color.'">'.e($label).'</span>';
            })
            ->addColumn('actions', function (User $user) {
                $showUrl = route('admin.users.show', $user);
                $editUrl = route('admin.users.edit', $user);
                $deleteUrl = route('admin.users.destroy', $user);
                $csrf = csrf_field();
                $method = method_field('DELETE');

                $canTouch = $this->canManageAccount($user);

                // Ban / unban (never yourself)
                $ban = '';
                if ($user->id !== Auth::id() && $canTouch) {
                    if ($user->isBanned()) {
                        $ban = '<form action="'.route('admin.users.unban', $user).'" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Unban '.e($user->name).'?" data-swal-text="They will be able to sign in again." data-swal-confirm="Yes, unban" data-swal-color="#198754">'
                            .$csrf.'<button type="submit" class="btn btn-sm btn-outline-success">Unban</button></form>';
                    } elseif ($user->isAccountActive()) {
                        $ban = '<form action="'.route('admin.users.ban', $user).'" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Ban '.e($user->name).'?" data-swal-text="They will be signed out and won&#39;t be able to sign in." data-swal-confirm="Yes, ban">'
                            .$csrf.'<button type="submit" class="btn btn-sm btn-outline-dark">Ban</button></form>';
                    }
                }

                $edit = $canTouch ? '<a href="'.$editUrl.'" class="btn btn-sm btn-outline-warning">Edit</a>' : '';
                // Deleting accounts stays with Admins
                $delete = Auth::user()->hasRole(Role::ADMIN)
                    ? '<form action="'.$deleteUrl.'" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this user?">'.$csrf.$method.'<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button></form>'
                    : '';

                return '<a href="'.$showUrl.'" class="btn btn-sm btn-outline-info">View</a> '.$edit.' '.$ban.' '.$delete;
            })
            ->rawColumns(['actions', 'status_badge'])
            ->toJson();
    }

    /** Block the account: signs the user out everywhere and stops password / OTP sign-in. */
    public function ban(User $user): RedirectResponse
    {
        abort_if($user->id === Auth::id(), 403, 'You cannot ban yourself.');
        $this->guardAdminAccount($user);
        abort_unless($user->isAccountActive(), 422, 'Only active accounts can be banned.');

        $user->forceFill([
            'account_status' => User::ACCOUNT_BANNED,
            'session_token' => null,
            'remember_token' => \Illuminate\Support\Str::random(60), // kills "remember me" cookies
        ])->save();

        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_USER_UPDATED, "Banned user: {$user->name} ({$user->email})");

        return back()->with('success', "{$user->name} has been banned and can no longer sign in.");
    }

    public function unban(User $user): RedirectResponse
    {
        $this->guardAdminAccount($user);
        abort_unless($user->isBanned(), 422, 'This account is not banned.');

        $user->forceFill(['account_status' => User::ACCOUNT_ACTIVE])->save();

        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_USER_UPDATED, "Unbanned user: {$user->name} ({$user->email})");

        return back()->with('success', "{$user->name} has been unbanned and can sign in again.");
    }

    /** Roles the signed-in user may give: IT can't make anyone an Admin. */
    private function assignableRoles()
    {
        return Role::query()
            ->when(! Auth::user()->hasRole(Role::ADMIN), fn ($q) => $q->where('name', '!=', Role::ADMIN))
            ->orderBy('name')
            ->get();
    }

    /** IT manages every account except Admin ones. */
    private function canManageAccount(User $user): bool
    {
        return Auth::user()->hasRole(Role::ADMIN) || ! $user->hasRole(Role::ADMIN);
    }

    private function guardAdminAccount(User $user): void
    {
        abort_unless($this->canManageAccount($user), 403, 'Only an Admin can change an Admin account.');
    }

    public function create(): View
    {
        $roles     = $this->assignableRoles();
        $teams     = Team::query()->orderBy('name')->get();
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
            'role_id'          => ['nullable', Rule::in($this->assignableRoles()->pluck('id')->all())],
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
            'company_id'       => CompanyContext::idForCreate($validated['company_id'] ?? null),
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
        $this->guardAdminAccount($user);
        $roles     = $this->assignableRoles();
        $teams     = Team::query()->orderBy('name')->get();
        $companies = AuthScope::isAgent()
            ? \App\Models\Company::where('id', auth()->user()->company_id)->get()
            : Company::query()->orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'teams', 'companies'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guardAdminAccount($user);
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'         => 'nullable|string|min:8|confirmed',
            'role_id'          => ['nullable', Rule::in($this->assignableRoles()->pluck('id')->all())],
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
            'company_id'       => CompanyContext::idForUpdate($user->company_id, $validated['company_id'] ?? null),
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
