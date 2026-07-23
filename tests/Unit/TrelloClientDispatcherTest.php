<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Company;
use App\Models\Team;
use App\Services\TrelloClientDispatcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrelloClientDispatcherTest extends TestCase
{
    public function test_it_sends_a_directly_created_crm_client_to_trello(): void
    {
        config([
            'services.trello.sale_webhook_url' => 'https://example.test/api/crm/clients',
            'services.trello.sale_webhook_token' => 'sync-secret',
        ]);
        Http::fake(['https://example.test/*' => Http::response([], 201)]);

        $team = new Team(['name' => 'Sales']);
        $team->setRelation('company', new Company(['name' => 'Staff Shaw']));

        $client = new Client([
            'name' => 'Direct CRM Client',
            'email' => 'direct@example.com',
            'phone' => '+923001234567',
        ]);
        $client->id = 91;
        $client->setRelation('team', $team);

        $result = TrelloClientDispatcher::dispatch($client);

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://example.test/api/crm/clients'
            && $request->hasHeader('Authorization', 'Bearer sync-secret')
            && $request['crm_client_id'] === '91'
            && $request['crm_sale_id'] === null
            && $request['crm_source'] === 'staffshaw'
            && $request['name'] === 'Direct CRM Client'
        );
        $this->assertSame('sent', $result['status']);
        $this->assertSame(201, $result['http_status']);
    }
}
