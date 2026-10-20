<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Read the status fresh, so a ban takes effect on the very next request
        if ($user instanceof User) {
            $user->account_status = User::query()->whereKey($user->getKey())->value('account_status') ?? $user->account_status;
        }

        if ($user instanceof User && ! $user->isAccountActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Also ends the session of a user banned while signed in
            return redirect()->route('admin.login')->withErrors(['email' => $user->signInBlockedMessage() ?? 'Your session has ended.']);
        }

        return $next($request);
    }
}
