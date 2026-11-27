<?php

namespace App\Support;

use App\Models\OutboxEvent;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records business events for CompanyOS to pull.
 *
 * Recording never breaks the business action that triggered it: if the insert fails,
 * the error is logged and the request carries on. Events written inside a DB transaction
 * are committed or rolled back together with the business row.
 */
class Outbox
{
    /** 'live' for normal use; 'import' while a CSV import runs so automations can skip historical rows. */
    private static string $context = 'live';

    public static function record(string $type, string $aggregateType, int $aggregateId, array $payload): void
    {
        try {
            OutboxEvent::query()->create([
                'type' => $type,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'context' => self::$context,
                'payload' => $payload,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Outbox event not recorded', [
                'type' => $type,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Run $callback with every event it records tagged with $context.
     *
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function withContext(string $context, Closure $callback): mixed
    {
        $previous = self::$context;
        self::$context = $context;

        try {
            return $callback();
        } finally {
            self::$context = $previous;
        }
    }
}
