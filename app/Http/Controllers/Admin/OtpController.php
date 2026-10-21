<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\AuthController;
use App\Models\LoginOtp;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class OtpController extends Controller
{
    /** Show OTP request form */
    public function showRequestForm()
    {
        return view('admin.otp.request');
    }

    /** Send OTP to configured emails */
    public function sendOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;
        // Max 3 codes per email and 10 per IP in 10 minutes (stops email flooding / guessing)
        foreach (['otp-send:'.mb_strtolower($email) => 3, 'otp-send-ip:'.$request->ip() => 10] as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return back()->withErrors(['email' => 'Too many OTP requests. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minutes.'])->withInput();
            }
            RateLimiter::hit($key, 600);
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            // Same answer as for a real account, so the form can't be used to find which emails exist
            return redirect()->route('admin.otp.verify.form', ['email' => $email])
                ->with('info', 'OTP sent. Check the configured email(s).');
        }

        // Banned / pending / rejected accounts don't get a code
        if ($blocked = $user?->signInBlockedMessage()) {
            return back()->withErrors(['email' => $blocked])->withInput();
        }

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Invalidate old OTPs for this email
        LoginOtp::where('email', $email)->update(['used' => true]);

        // Store new OTP
        LoginOtp::create([
            'email'      => $email,
            'otp'        => $otp,
            'expires_at' => now()->addMinutes(5),
        ]);

        // Send to configured recipient emails (fallback: user's own email)
        $recipients = Setting::otpEmails();
        if (empty($recipients)) {
            $recipients = [$email];
        }

        foreach ($recipients as $recipient) {
            Mail::send('emails.otp', [
                'userName'  => $user->name,
                'userEmail' => $user->email,
                'otpCode'   => $otp,
            ], fn ($msg) => $msg
                ->to($recipient)
                ->subject("CRM Login OTP — {$user->name}")
            );
        }

        return redirect()->route('admin.otp.verify.form', ['email' => $email])
            ->with('info', 'OTP sent. Check the configured email(s).');
    }

    /** Resend OTP */
    public function resendOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Reuse sendOtp logic
        return $this->sendOtp($request);
    }

    /** Show OTP verify form */
    public function showVerifyForm(Request $request)
    {
        $email = $request->query('email', '');

        // Get the actual OTP creation time from DB — fixed, not now()
        $otp = LoginOtp::where('email', $email)
            ->where('used', false)
            ->latest()
            ->first();

        // sentAt in milliseconds for JS
        $sentAt = $otp ? $otp->created_at->timestamp * 1000 : now()->timestamp * 1000;

        return view('admin.otp.verify', compact('email', 'sentAt'));
    }

    /** Verify OTP and login */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|digits:6',
        ]);

        // 5 wrong codes → every open code for this email is cancelled; a new one must be requested
        $key = 'otp-verify:'.mb_strtolower($request->email);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            LoginOtp::where('email', $request->email)->where('used', false)->update(['used' => true]);

            return back()->withErrors(['otp' => 'Too many wrong codes. Request a new OTP.'])->withInput();
        }

        $record = LoginOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('used', false)
            ->latest()
            ->first();

        if (! $record || ! $record->isValid()) {
            RateLimiter::hit($key, 900);

            return back()->withErrors(['otp' => 'Invalid or expired OTP.'])->withInput();
        }
        RateLimiter::clear($key);

        // Mark used
        $record->update(['used' => true]);

        $user = User::where('email', $request->email)->first();
        if (! $user || ($blocked = $user->signInBlockedMessage())) {
            return redirect()->route('admin.login')->withErrors(['email' => $blocked ?? 'Account not found.']);
        }
        Auth::login($user, true);
        $request->session()->regenerate();
        AuthController::bindSession($user, $request);

        return redirect()->route('admin.dashboard');
    }
}
