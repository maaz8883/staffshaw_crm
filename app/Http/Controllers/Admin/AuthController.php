<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

        // Password guessing: 5 wrong tries per email + IP, then wait a minute
        $key = 'login:'.mb_strtolower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'email' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials, true)) {
            RateLimiter::clear($key);
            $user = Auth::user();

            if ($blocked = $user->signInBlockedMessage()) {
                Auth::logout();
                $request->session()->regenerate();

                return back()->withErrors(['email' => $blocked])->onlyInput('email');
            }

            $request->session()->regenerate();
            static::bindSession($user, $request);

            ActivityLogger::log($user, UserActivityLog::TYPE_LOGIN, 'User logged in', [], $request);

            return redirect()->intended(route('admin.dashboard'));
        }

        RateLimiter::hit($key, 60);

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

        return redirect($this->buildCrossAppLogoutUrl());
    }

    public function globalLogout(Request $request)
    {
        $redirectTo = $this->safeRedirect($request, $this->hrmLoginUrl());

        try {
            if ($user = Auth::user()) {
                $user->update(['session_token' => null]);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\Throwable $e) {
            // Still send user to HRM login if MySQL is saturated (Too many connections)
            report($e);
            try {
                Auth::logout();
            } catch (\Throwable) {
                //
            }
        }

        return redirect($redirectTo);
    }

    /**
     * Authenticated CRM user → open HRM via one-time SSO token.
     */
    public function openHrm(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('admin.login');
        }

        $hrmAppUrl = rtrim((string) config('services.hrm.url'), '/');
        $ssoSecret = config('services.hrm.sso_secret');

        if (!$hrmAppUrl || !$ssoSecret) {
            return redirect()->route('admin.dashboard')->withErrors([
                'email' => 'SSO is not configured. Contact admin.',
            ]);
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->post("{$hrmAppUrl}/api/integrations/crm/sso/issue", [
                'headers' => [
                    'X-SSO-Secret' => $ssoSecret,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => ['email' => $user->email],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!($body['success'] ?? false) || empty($body['redirectUrl'])) {
                return redirect()->route('admin.dashboard')->withErrors([
                    'email' => $body['error'] ?? 'Could not open HRM. Try again.',
                ]);
            }

            return redirect()->away($body['redirectUrl']);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $message = 'Could not open HRM. Ensure your account exists there.';
            $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            if ($status === 404) {
                $message = 'No HRM account for this email. Contact HR.';
            } elseif ($status === 403) {
                $message = 'Your HRM account is deactivated. Contact HR.';
            } elseif ($status === 401) {
                $message = 'SSO authentication failed. Contact admin.';
            }

            return redirect()->route('admin.dashboard')->withErrors(['email' => $message]);
        } catch (\Exception $e) {
            return redirect()->route('admin.dashboard')->withErrors([
                'email' => 'Could not open HRM. Please try again.',
            ]);
        }
    }

    public function hrmSso(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'Invalid SSO link. Please open CRM from HRM again.',
            ]);
        }

        $hrmAppUrl = rtrim((string) config('services.hrm.url'), '/');
        $ssoSecret = config('services.hrm.sso_secret');

        if (!$hrmAppUrl || !$ssoSecret) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'SSO is not configured on CRM. Contact admin.',
            ]);
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->post("{$hrmAppUrl}/api/integrations/crm/verify", [
                'headers' => [
                    'X-SSO-Secret' => $ssoSecret,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => ['token' => $token],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!($body['success'] ?? false) || empty($body['data']['email'])) {
                return redirect()->route('admin.login')->withErrors([
                    'email' => 'SSO verification failed. Please try again from HRM.',
                ]);
            }

            $email = strtolower(trim($body['data']['email']));
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

            if (!$user) {
                return redirect()->route('admin.login')->withErrors([
                    'email' => 'No CRM account linked to this email. Contact admin.',
                ]);
            }

            if ($blocked = $user->signInBlockedMessage()) {
                return redirect()->route('admin.login')->withErrors(['email' => $blocked]);
            }

            Auth::login($user, true);
            $request->session()->regenerate();
            static::bindSession($user, $request);
            $request->session()->save();

            ActivityLogger::log($user, UserActivityLog::TYPE_LOGIN, 'User logged in via HRM SSO', [], $request);

            return redirect()->intended(route('admin.dashboard'));
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $message = 'SSO link expired or already used. Please open CRM from HRM again.';
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 401) {
                $message = 'SSO authentication failed. Contact admin.';
            }

            return redirect()->route('admin.login')->withErrors(['email' => $message]);
        } catch (\Exception $e) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'SSO login failed. Please try again from HRM.',
            ]);
        }
    }

    /** Generate a new session token, store in DB + session */
    public static function bindSession(User $user, ?Request $request = null): void
    {
        $token = Str::random(60);
        $user->update(['session_token' => $token]);
        $user->refresh();

        if ($request) {
            $request->session()->put('session_token', $token);
        } else {
            session(['session_token' => $token]);
        }
    }

    private function hrmLoginUrl(): string
    {
        return rtrim((string) config('services.hrm.url'), '/') . '/login';
    }

    private function buildCrossAppLogoutUrl(): string
    {
        $hrm = rtrim((string) config('services.hrm.url'), '/');
        $trello = rtrim((string) config('services.trello.app_url'), '/');

        $next = $hrm ? "{$hrm}/api/auth/global-logout?redirect=/login" : '/admin/login';

        if ($trello) {
            $next = "{$trello}/auth/global-logout?redirect=" . urlencode($next);
        }

        return $next;
    }

    private function safeRedirect(Request $request, string $default): string
    {
        $target = $request->query('redirect', $default);
        $hrm = rtrim((string) config('services.hrm.url'), '/');
        $trello = rtrim((string) config('services.trello.app_url'), '/');

        if ($trello && str_starts_with($target, $trello)) {
            return $target;
        }

        if ($hrm && str_starts_with($target, $hrm)) {
            return $target;
        }

        if ($hrm && str_starts_with($target, '/')) {
            return $hrm . $target;
        }

        return $default;
    }
}
