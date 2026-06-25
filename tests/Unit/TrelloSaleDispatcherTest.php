<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Sale;
use App\Services\TrelloSaleDispatcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrelloSaleDispatcherTest extends TestCase
{
    public function test_it_posts_sale_and_available_client_details(): void
    {
        config([
            'services.trello.sale_webhook_url' => 'https://example.test/trello',
            'services.trello.sale_webhook_token' => 'secret-token',
        ]);
        Http::fake(['https://example.test/trello' => Http::response()]);

        $sale = new Sale([
            'title' => 'Website Package',
            'client_name' => 'Acme',
            'client_email' => 'client@example.com',
            'client_phone' => '+1 555 0100',
            'amount' => 1200,
            'sale_date' => '2026-06-12',
            'sale_type' => Sale::TYPE_FRONT,
            'status' => 'completed',
        ]);
        $sale->id = 42;
        $sale->setRelation('company', new Company(['name' => 'DSS']));

        $result = TrelloSaleDispatcher::dispatch($sale);

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://example.test/trello'
            && $request->hasHeader('Authorization', 'Bearer secret-token')
            && $request['crm_client_id'] === '42'
            && $request['crm_source'] === 'dss'
            && $request['name'] === 'Acme'
            && $request['email'] === 'client@example.com'
            && $request['phone'] === '+1 555 0100'
            && $request['sale']['id'] === 42
        );
        $this->assertSame('sent', $result['status']);
        $this->assertSame(200, $result['http_status']);
        $this->assertSame('Acme', $result['payload']['name']);
        $this->assertSame('dss', $result['payload']['crm_source']);
    }

    public function test_it_does_nothing_when_webhook_url_is_not_configured(): void
    {
        config([
            'services.trello.sale_webhook_url' => null,
            'services.trello.sale_webhook_token' => null,
        ]);
        Http::fake();

        $result = TrelloSaleDispatcher::dispatch(new Sale(['client_name' => 'Acme']));

        Http::assertNothingSent();
        $this->assertSame('skipped', $result['status']);
    }

    public function test_it_returns_receiver_validation_errors_without_reporting_a_connection_error(): void
    {
        config([
            'services.trello.sale_webhook_url' => 'https://example.test/trello',
            'services.trello.sale_webhook_token' => 'secret-token',
        ]);
        Http::fake([
            'https://example.test/trello' => Http::response([
                'message' => 'The email field is required.',
            ], 422),
        ]);

        $sale = new Sale(['client_name' => 'Acme']);
        $sale->id = 43;

        $result = TrelloSaleDispatcher::dispatch($sale);

        $this->assertSame('failed', $result['status']);
        $this->assertSame(422, $result['http_status']);
        $this->assertSame(
            'The email field is required.',
            $result['response']['message']
        );
    }
}
