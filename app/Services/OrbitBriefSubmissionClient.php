<?php

namespace App\Services;

use App\Models\BriefSubmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrbitBriefSubmissionClient
{
    /** @return Collection<string, BriefSubmission> */
    public function forSale(int $saleId): Collection
    {
        $baseUrl = rtrim((string) config('services.orbit_brief.url'), '/');
        $apiKey  = (string) config('services.orbit_brief.api_key');
        $timeout = (int) config('services.orbit_brief.timeout', 10);

        if ($baseUrl === '' || $apiKey === '') {
            Log::warning('Orbit brief API is not configured.', ['sale_id' => $saleId]);

            return collect();
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout($timeout)
                ->get("{$baseUrl}/sales/{$saleId}/brief-submissions");

            if (! $response->successful()) {
                Log::warning('Orbit brief API request failed.', [
                    'sale_id' => $saleId,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);

                return collect();
            }

            $submissions = $response->json('submissions');

            if (! is_array($submissions)) {
                return collect();
            }

            return collect($submissions)
                ->map(static fn (array $row) => BriefSubmission::fromApi($row))
                ->filter(static fn (BriefSubmission $submission) => $submission->brief_type !== '')
                ->keyBy(static fn (BriefSubmission $submission) => $submission->brief_type);
        } catch (\Throwable $e) {
            Log::warning('Orbit brief API request error.', [
                'sale_id' => $saleId,
                'message' => $e->getMessage(),
            ]);

            return collect();
        }
    }
}
