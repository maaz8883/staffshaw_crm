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
            'email' => 'required|email|exists:users,email',
        ]);

        $email = $request->email;
        $user  = User::where('email', $email)->first();

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
            'email' => 'required|email|exists:users,email',
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
            'email' => 'required|email|exists:users,email',
            'otp'   => 'required|digits:6',
        ]);

        $record = LoginOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('used', false)
            ->latest()
            ->first();

        if (! $record || ! $record->isValid()) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP.'])->withInput();
        }

        // Mark used
        $record->update(['used' => true]);

        $user = User::where('email', $request->email)->first();
        Auth::login($user, true);
        $request->session()->regenerate();
        AuthController::bindSession($user);

        return redirect()->route('admin.dashboard');
    }
}
