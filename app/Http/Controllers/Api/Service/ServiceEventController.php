<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Models\OutboxEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * GET /api/v1/service/events?after=<last id seen>&limit=100
 * Returns events in id order. CompanyOS stores meta.next_after and sends it on the next call.
 * The event id is unique and stable, so the consumer can use it to ignore duplicates.
 */
class ServiceEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'after' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => [
                'code' => 'VALIDATION_FAILED',
                'message' => 'Invalid query parameters.',
                'details' => $validator->errors()->toArray(),
            ]], 422);
        }

        $after = (int) $request->query('after', 0);
        $limit = (int) $request->query('limit', 100);

        $rows = OutboxEvent::query()
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();

        return response()->json([
            'data' => $rows->map(fn (OutboxEvent $e) => [
                'id' => $e->id,
                'source' => 'crm',
                'type' => $e->type,
                'aggregate_type' => $e->aggregate_type,
                'aggregate_id' => $e->aggregate_id,
                'context' => $e->context,
                'payload' => $e->payload,
                'occurred_at' => optional($e->occurred_at)->toIso8601String(),
            ]),
            'meta' => [
                'next_after' => $rows->isNotEmpty() ? $rows->last()->id : $after,
                'has_more' => $hasMore,
            ],
        ]);
    }
}
