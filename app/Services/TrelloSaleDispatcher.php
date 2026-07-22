<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrelloSaleDispatcher
{
    public static function dispatch(Sale $sale): array
    {
        $url = config('services.trello.sale_webhook_url');
        $token = config('services.trello.sale_webhook_token');

        if (
            ! is_string($url) || $url === ''
            || ! is_string($token) || $token === ''
        ) {
            return [
                'status' => 'skipped',
                'message' => 'CRM sender is not configured.',
                'url' => $url,
                'payload' => null,
                'http_status' => null,
                'response' => null,
            ];
        }

        $sale->loadMissing(['user', 'team', 'company', 'client']);

        if (! $sale->client_id || ! $sale->client) {
            return [
                'status' => 'skipped',
                'message' => 'The sale is not linked to a CRM client.',
                'url' => $url,
                'payload' => null,
                'http_status' => null,
                'response' => null,
            ];
        }

        $payload = [
            'crm_client_id' => (string) $sale->client_id,
            'crm_sale_id' => (string) $sale->id,
            'crm_source' => self::crmSourceForSale($sale),
            'name' => $sale->client->name ?: $sale->client_name,
            'email' => $sale->client->email ?: $sale->client_email,
            'phone' => $sale->client->phone ?: $sale->client_phone,
            'sale' => [
                'id' => $sale->id,
                'title' => $sale->title,
                'amount' => $sale->amount,
                'sale_date' => $sale->sale_date?->toDateString(),
                'sale_type' => $sale->sale_type,
                'status' => $sale->status,
            ],
            'seller' => [
                'id' => $sale->user?->id,
                'name' => $sale->user?->name,
                'email' => $sale->user?->email,
            ],
            'team' => $sale->team?->name,
            'company' => $sale->company?->name,
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
                self::log('warning', 'Trello sale webhook returned an error.', [
                    'sale_id' => $sale->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'status' => 'failed',
                    'message' => "CRM sent the JSON, but receiver returned HTTP {$response->status()}.",
                    'url' => $url,
                    'payload' => $payload,
                    'http_status' => $response->status(),
                    'response' => $response->json() ?? $response->body(),
                ];
            }

            self::log('info', 'Trello sale webhook sent successfully.', [
                'sale_id' => $sale->id,
                'status' => $response->status(),
            ]);

            return [
                'status' => 'sent',
                'url' => $url,
                'payload' => $payload,
                'http_status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ];
        } catch (\Throwable $exception) {
            self::log('warning', 'Trello sale webhook could not be sent.', [
                'sale_id' => $sale->id,
                'message' => $exception->getMessage(),
            ]);

            return [
                'status' => 'connection_error',
                'message' => 'CRM could not connect to the receiver: '.$exception->getMessage(),
                'url' => $url,
                'payload' => $payload,
                'http_status' => null,
                'response' => null,
            ];
        }
    }

    private static function log(string $level, string $message, array $context): void
    {
        try {
            Log::log($level, $message, $context);
        } catch (\Throwable) {
            // Webhook diagnostics must never break sale creation.
        }
    }

    private static function crmSourceForSale(Sale $sale): string
    {
        $source = Str::of((string) $sale->company?->name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();

        return $source !== '' ? $source : 'staffshaw';
    }
}
