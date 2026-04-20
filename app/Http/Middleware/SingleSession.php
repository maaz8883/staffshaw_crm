<?php

namespace App\Http\Middleware;

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
            $storedToken  = $user->session_token;
            $currentToken = session('session_token');

            // If tokens don't match — another device logged in
            if ($storedToken && $currentToken !== $storedToken) {
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
