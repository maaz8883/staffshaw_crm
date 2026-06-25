<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        if (Auth::attempt($credentials, true)) {
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
            static::bindSession($user, $request);

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

        return redirect($this->buildCrossAppLogoutUrl());
    }

    public function globalLogout(Request $request)
    {
        if ($user = Auth::user()) {
            $user->update(['session_token' => null]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($this->safeRedirect($request, $this->hrmLoginUrl()));
    }

    public function hrmSso(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'Invalid SSO link. Please open CRM from HRM again.',
            ]);
        }

        $hrmAppUrl = rtrim(env('HRM_APP_URL', ''), '/');
        $ssoSecret = env('SSO_SHARED_SECRET');

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

            if (!$user->isAccountActive()) {
                if ($user->isPendingApproval()) {
                    return redirect()->route('admin.login')->withErrors([
                        'email' => 'Your account is pending approval.',
                    ]);
                }
                if ($user->isAccountRejected()) {
                    return redirect()->route('admin.login')->withErrors([
                        'email' => 'Your registration was not approved.',
                    ]);
                }
                return redirect()->route('admin.login')->withErrors([
                    'email' => 'Your account cannot sign in.',
                ]);
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
        return rtrim(env('HRM_APP_URL', ''), '/') . '/login';
    }

    private function buildCrossAppLogoutUrl(): string
    {
        $hrm = rtrim(env('HRM_APP_URL', ''), '/');
        $trello = rtrim(env('TRELLO_APP_URL', ''), '/');

        $next = $hrm ? "{$hrm}/api/auth/global-logout?redirect=/login" : '/admin/login';

        if ($trello) {
            $next = "{$trello}/auth/global-logout?redirect=" . urlencode($next);
        }

        return $next;
    }

    private function safeRedirect(Request $request, string $default): string
    {
        $target = $request->query('redirect', $default);
        $hrm = rtrim(env('HRM_APP_URL', ''), '/');
        $trello = rtrim(env('TRELLO_APP_URL', ''), '/');

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
