<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession as Middleware;

/**
 * Signs a device out once the account's password was changed somewhere else (Laravel's
 * AuthenticateSession); sends it to our login page, as the app has no route named "login".
 */
class AuthenticateSession extends Middleware
{
    protected function redirectTo(Request $request)
    {
        return $request->expectsJson() ? null : route('admin.login');
    }
}
