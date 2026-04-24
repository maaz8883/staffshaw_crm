<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $user = Auth::user();

            if (! $user->isAccountActive()) {
                Auth::logout();
                $request->session()->regenerate();

                if ($user->isPendingApproval()) {
                    return back()->withErrors(['email' => 'Your account is pending approval.'])->onlyInput('email');
                }
                if ($user->isAccountRejected()) {
                    return back()->withErrors(['email' => 'Your registration was not approved.'])->onlyInput('email');
                }
                return back()->withErrors(['email' => 'Your account cannot sign in.'])->onlyInput('email');
            }

            $request->session()->regenerate();
            static::bindSession($user);

            ActivityLogger::log($user, UserActivityLog::TYPE_LOGIN, 'User logged in', [], $request);

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        if ($user = Auth::user()) {
            ActivityLogger::log($user, UserActivityLog::TYPE_LOGOUT, 'User logged out', [], $request);
            $user->update(['session_token' => null]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }

    /** Generate a new session token, store in DB + session */
    public static function bindSession($user): void
    {
        $token = Str::random(60);
        $user->update(['session_token' => $token]);
        session(['session_token' => $token]);
    }
}
