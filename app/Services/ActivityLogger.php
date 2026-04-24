<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
    }

    private static function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
