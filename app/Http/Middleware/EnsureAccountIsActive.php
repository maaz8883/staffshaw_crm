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

        if ($user instanceof User && ! $user->isAccountActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Your session has ended.';
            if ($user->isPendingApproval()) {
                $message = 'Your account is still pending approval.';
            } elseif ($user->isAccountRejected()) {
                $message = 'Your account is not active.';
            }

            return redirect()->route('admin.login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
