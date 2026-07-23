<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrelloClientDispatcher
{
    public static function dispatch(Client $client): array
    {
        $url = config('services.trello.sale_webhook_url');
        $token = config('services.trello.sale_webhook_token');

        if (! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            return [
                'status' => 'skipped',
                'message' => 'CRM sender is not configured.',
                'http_status' => null,
            ];
        }

        $client->loadMissing(['team.company', 'createdBy.company']);

        $payload = [
            'crm_client_id' => (string) $client->id,
            'crm_sale_id' => null,
            'crm_source' => self::crmSource($client),
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($token)
                ->timeout(5)
                ->retry(
                    2,
                    200,
                    static fn ($exception) => $exception instanceof ConnectionException,
                    false
                )
                ->post($url, $payload);

            if ($response->failed()) {
                self::log('warning', 'Trello client webhook returned an error.', [
                    'client_id' => $client->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'status' => 'failed',
                    'message' => "Trello returned HTTP {$response->status()}.",
                    'payload' => $payload,
                    'http_status' => $response->status(),
                    'response' => $response->json() ?? $response->body(),
                ];
            }

            self::log('info', 'CRM client sent to Trello successfully.', [
                'client_id' => $client->id,
                'status' => $response->status(),
            ]);

            return [
                'status' => 'sent',
                'payload' => $payload,
                'http_status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ];
        } catch (\Throwable $exception) {
            self::log('warning', 'CRM client could not be sent to Trello.', [
                'client_id' => $client->id,
                'message' => $exception->getMessage(),
            ]);

            return [
                'status' => 'connection_error',
                'message' => $exception->getMessage(),
                'payload' => $payload,
                'http_status' => null,
            ];
        }
    }

    private static function crmSource(Client $client): string
    {
        $companyName = $client->team?->company?->name
            ?: $client->createdBy?->company?->name;

        $source = Str::of((string) $companyName)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();

        return $source !== '' ? $source : 'staffshaw';
    }

    private static function log(string $level, string $message, array $context): void
    {
        try {
            Log::log($level, $message, $context);
        } catch (\Throwable) {
            // Client sync diagnostics must never block CRM client changes.
        }
    }
}
