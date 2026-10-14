<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class TrelloLive
{
    public static function bump(string $boardId, ?string $activity = null): void
    {
        $boardId = trim($boardId);
        if ($boardId === '') {
            return;
        }

        Cache::put(self::versionKey($boardId), microtime(true), 3600);

        if ($activity !== null && $activity !== '') {
            Cache::put(self::activityKey($boardId), $activity, 3600);
            Cache::put(self::activityPrevKey($boardId), $activity, 3600);
        } else {
            Cache::forget(self::activityKey($boardId));
        }
    }

    public static function version(string $boardId): float
    {
        return (float) Cache::get(self::versionKey($boardId), 0);
    }

    public static function versionKey(string $boardId): string
    {
        return 'trello.live.'.$boardId;
    }

    public static function activityKey(string $boardId): string
    {
        return 'trello.act.'.$boardId;
    }

    public static function activityPrevKey(string $boardId): string
    {
        return 'trello.act.prev.'.$boardId;
    }

    /** True when APP_URL is reachable by Trello (not local/dev hosts). */
    public static function canReceiveWebhooks(): bool
    {
        $url = (string) config('app.url');
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?: ''));

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (str_ends_with($host, '.test') || str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
            return false;
        }

        // Trello requires a publicly reachable callback; prefer https in production.
        return in_array($scheme, ['http', 'https'], true);
    }
}
