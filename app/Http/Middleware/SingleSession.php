<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\AuthController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            $user->refresh();
            $storedToken  = $user->session_token;
            $currentToken = session('session_token');

            if (! $storedToken) {
                AuthController::bindSession($user, $request);

                return $next($request);
            }

            if ($currentToken === null) {
                $request->session()->put('session_token', $storedToken);

                return $next($request);
            }

            if (! hash_equals($storedToken, $currentToken)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('admin.login')
                    ->withErrors(['email' => 'You have been logged out because your account was accessed from another device.']);
            }
        }

        return $next($request);
    }
}
