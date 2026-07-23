<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\TrelloClientDispatcher;
use Illuminate\Console\Command;

class SyncTrelloClients extends Command
{
    protected $signature = 'trello:sync-clients {--client= : Sync only one CRM client ID}';

    protected $description = 'Send CRM clients to the Trello workspace application';

    public function handle(): int
    {
        $query = Client::query()->with(['team.company', 'createdBy.company']);

        if ($clientId = $this->option('client')) {
            $query->whereKey($clientId);
        }

        $clients = $query->orderBy('id')->get();

        if ($clients->isEmpty()) {
            $this->warn('No matching CRM clients found.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($clients as $client) {
            $result = TrelloClientDispatcher::dispatch($client);
            $status = (string) ($result['status'] ?? 'failed');

            if ($status === 'sent') {
                $this->info("[sent] #{$client->id} {$client->name}");
                continue;
            }

            $failed++;
            $message = (string) ($result['message'] ?? 'Unknown error');
            $this->error("[{$status}] #{$client->id} {$client->name}: {$message}");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
