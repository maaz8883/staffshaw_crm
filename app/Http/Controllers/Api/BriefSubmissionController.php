<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BriefSubmission;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BriefSubmissionController extends Controller
{
    public function show(Sale $sale, string $briefType): JsonResponse
    {
        if (! preg_match('/^[a-z0-9_-]+$/', $briefType)) {
            return response()->json(['error' => 'Invalid brief type.'], 422);
        }

        $submission = BriefSubmission::query()
            ->where('sale_id', $sale->id)
            ->where('brief_type', $briefType)
            ->first();

        if ($submission === null || ! $submission->isSubmitted()) {
            return response()->json(['error' => 'Brief submission not found.'], 404);
        }

        return response()->json([
            'sale_id'    => $sale->id,
            'submission' => $this->serializeSubmission($submission),
        ]);
    }

    public function store(Request $request, Sale $sale): JsonResponse
    {
        $validated = $request->validate([
            'brief_type'    => 'required|string|max:20|regex:/^[a-z0-9_-]+$/',
            'form_path'     => 'required|string|max:100',
            'data'          => 'required|array',
            'attachments'   => 'nullable|array',
            'status'        => 'nullable|string|max:20|in:' . BriefSubmission::STATUS_PENDING . ',' . BriefSubmission::STATUS_SUBMITTED,
            'client_name'   => 'nullable|string|max:255',
            'client_email'  => 'nullable|email|max:255',
            'client_ip'     => 'nullable|string|max:45',
        ]);

        $now = now();
        $status = $validated['status'] ?? BriefSubmission::STATUS_SUBMITTED;

        $existing = BriefSubmission::query()
            ->where('sale_id', $sale->id)
            ->where('brief_type', $validated['brief_type'])
            ->first();

        if ($existing !== null && $existing->isSubmitted()) {
            return response()->json(['error' => 'This brief has already been submitted.'], 409);
        }

        $submission = BriefSubmission::query()->updateOrCreate(
            [
                'sale_id'    => $sale->id,
                'brief_type' => $validated['brief_type'],
            ],
            [
                'form_path'     => $validated['form_path'],
                'data'          => $validated['data'],
                'attachments'   => $validated['attachments'] ?? null,
                'status'        => $status,
                'submitted_at'  => $status === BriefSubmission::STATUS_SUBMITTED ? $now : null,
                'client_name'   => $validated['client_name'] ?? null,
                'client_email'  => $validated['client_email'] ?? null,
                'client_ip'     => $validated['client_ip'] ?? $request->ip(),
            ]
        );

        return response()->json([
            'sale_id'    => $sale->id,
            'submission' => $this->serializeSubmission($submission),
        ], $submission->wasRecentlyCreated ? 201 : 200);
    }

    /** @return array<string, mixed> */
    private function serializeSubmission(BriefSubmission $submission): array
    {
        return [
            'id'            => $submission->id,
            'sale_id'       => $submission->sale_id,
            'brief_type'    => $submission->brief_type,
            'form_path'     => $submission->form_path,
            'status'        => $submission->status,
            'submitted_at'  => $submission->submitted_at?->toIso8601String(),
            'client_name'   => $submission->client_name,
            'client_email'  => $submission->client_email,
            'client_ip'     => $submission->client_ip,
            'data'          => $submission->data ?? [],
            'attachments'   => $submission->attachments ?? [],
        ];
    }
}
