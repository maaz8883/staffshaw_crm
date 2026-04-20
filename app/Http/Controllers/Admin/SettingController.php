<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $otpEmails = Setting::get('otp_emails', '');

        $smtp = [
            'host'       => env('MAIL_HOST', ''),
            'port'       => env('MAIL_PORT', '587'),
            'username'   => env('MAIL_USERNAME', ''),
            'password'   => env('MAIL_PASSWORD', ''),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'from_address' => env('MAIL_FROM_ADDRESS', ''),
            'from_name'    => env('MAIL_FROM_NAME', ''),
        ];

        return view('admin.settings.index', compact('otpEmails', 'smtp'));
    }

    public function updateOtp(Request $request): RedirectResponse
    {
        $request->validate(['otp_emails' => 'nullable|string']);

        $lines  = array_filter(array_map('trim', explode("\n", $request->otp_emails ?? '')));
        $errors = [];
        foreach ($lines as $line) {
            if (! filter_var($line, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email: {$line}";
            }
        }

        if ($errors) {
            return back()->withErrors(['otp_emails' => implode(', ', $errors)])->withFragment('otp');
        }

        Setting::set('otp_emails', implode("\n", $lines));

        return back()->with('success', 'OTP settings saved.')->withFragment('otp');
    }

    public function updateSmtp(Request $request): RedirectResponse
    {
        $request->validate([
            'mail_host'         => 'required|string|max:255',
            'mail_port'         => 'required|integer',
            'mail_username'     => 'nullable|string|max:255',
            'mail_password'     => 'nullable|string|max:255',
            'mail_encryption'   => 'nullable|in:tls,ssl,starttls,',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name'    => 'required|string|max:255',
        ]);

        $this->setEnvValues([
            'MAIL_MAILER'       => 'smtp',
            'MAIL_HOST'         => $request->mail_host,
            'MAIL_PORT'         => $request->mail_port,
            'MAIL_USERNAME'     => $request->mail_username ?? '',
            'MAIL_PASSWORD'     => $request->mail_password ?? '',
            'MAIL_ENCRYPTION'   => $request->mail_encryption ?? 'tls',
            'MAIL_FROM_ADDRESS' => '"' . $request->mail_from_address . '"',
            'MAIL_FROM_NAME'    => '"' . $request->mail_from_name . '"',
        ]);

        Artisan::call('config:clear');

        return back()->with('success', 'SMTP settings saved.')->withFragment('smtp');
    }

    private function setEnvValues(array $values): void
    {
        $envPath    = base_path('.env');
        $envContent = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            $escaped = preg_quote($key, '/');

            if (preg_match("/^{$escaped}=/m", $envContent)) {
                // Update existing key
                $envContent = preg_replace(
                    "/^{$escaped}=.*/m",
                    "{$key}={$value}",
                    $envContent
                );
            } else {
                // Append new key
                $envContent .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $envContent);
    }
}
