<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\AgentSignupNotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View
    {
        $teams = Team::query()->with('company')->orderBy('name')->get();

        return view('register', compact('teams'));
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'team_id'  => 'required|exists:teams,id',
        ]);

        $team = Team::query()->findOrFail($validated['team_id']);
        $agentRole = Role::query()->where('name', Role::AGENT)->firstOrFail();

        $user = User::query()->create([
            'name'           => $validated['name'],
            'email'          => $validated['email'],
            'password'       => Hash::make($validated['password']),
            'role_id'        => $agentRole->id,
            'team_id'        => $team->id,
            'company_id'     => $team->company_id,
            'account_status' => User::ACCOUNT_PENDING,
        ]);

        AgentSignupNotificationDispatcher::dispatch($user);

        return redirect()
            ->route('admin.login')
            ->with('success', 'Your account was created. Please wait for an admin or team lead to approve it before you can sign in.');
    }
}
