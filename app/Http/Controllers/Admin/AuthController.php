<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $user = Auth::user();
            if (! $user->isAccountActive()) {
                Auth::logout();
                $request->session()->regenerate();

                if ($user->isPendingApproval()) {
                    return back()->withErrors([
                        'email' => 'Your account is pending approval by an admin or team lead.',
                    ])->onlyInput('email');
                }

                if ($user->isAccountRejected()) {
                    return back()->withErrors([
                        'email' => 'Your registration was not approved.',
                    ])->onlyInput('email');
                }

                return back()->withErrors([
                    'email' => 'Your account cannot sign in.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/admin/login');
    }
}
