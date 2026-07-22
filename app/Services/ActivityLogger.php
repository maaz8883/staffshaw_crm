<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActivityLogger
{
    public static function log(
        User $user,
        string $type,
        string $description,
        array $meta = [],
        ?Request $request = null
    ): void {
        $request ??= app(Request::class);

        $ip      = $request->ip();
        $country = null;
        $city    = null;

        if ($ip && ! self::isPrivateIp($ip)) {
            try {
                $geo = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,country,city',
                ])->json();

                if (($geo['status'] ?? '') === 'success') {
                    $country = $geo['country'] ?? null;
                    $city    = $geo['city'] ?? null;
                }
            } catch (\Throwable) {
                // geo is best-effort, never block the request
            }
        }

        try {
            UserActivityLog::create([
                'user_id'     => $user->id,
                'type'        => $type,
                'description' => $description,
                'ip_address'  => $ip,
                'country'     => $country,
                'city'        => $city,
                'user_agent'  => $request->userAgent(),
                'meta'        => $meta ?: null,
            ]);
        } catch (\Throwable $exception) {
            // Audit logging must never prevent the action being audited.
            Log::warning('Unable to write user activity log.', [
                'user_id' => $user->id,
                'type' => $type,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private static function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
