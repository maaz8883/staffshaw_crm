<?php

namespace App\Support;

use App\Http\Controllers\Admin\ProductionDashboardController;
use App\Models\Client;
use App\Models\Team;
use App\Services\Trello\TrelloCardToClientSync;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Links a CRM client to the Trello card whose title carries the same name
 * ("Mike & Carey Barnes - Website Development" → client "Mike & Carey Barnes"), so nobody has
 * to run the Trello → Clients sync. Runs when a client is saved and when a PM opens the dashboard.
 */
class ClientTrelloLink
{
    /** Name key of a card title, comparable with clientKey(). */
    public static function cardKey(string $title): string
    {
        $name = TrelloCardToClientSync::canonicalizeDisplayName(TrelloCardToClientSync::parseCardTitle($title)['name']);

        return $name === '' ? '' : TrelloCardToClientSync::normalizeNameKey($name);
    }

    public static function clientKey(string $name): string
    {
        $name = TrelloCardToClientSync::canonicalizeDisplayName($name);

        return $name === '' ? '' : TrelloCardToClientSync::normalizeNameKey($name);
    }

    /** The team whose Trello holds this team's cards: its own connection, else the parent's. */
    public static function trelloTeamFor(?Team $team): ?Team
    {
        foreach ([$team, $team?->parentTeam] as $t) {
            if ($t && $t->trelloIntegration?->isReady()) {
                return $t;
            }
        }

        return null;
    }

    /**
     * Link clients that have no card yet, using a board snapshot's card index.
     * Several cards with the name → an open one wins over a completed one.
     *
     * @param  iterable<Client>  $clients
     * @param  array<string, array<string, mixed>>  $index  card id → card (ProductionDashboardController card_index)
     * @return int linked count
     */
    public static function linkFromIndex(iterable $clients, array $index): int
    {
        $byKey = [];
        foreach ($index as $cardId => $card) {
            $key = $card['key'] ?? '';
            if ($key === '') {
                continue;
            }
            $current = $byKey[$key] ?? null;
            if (! $current || ($current['done'] && ! $card['done'])) {
                $byKey[$key] = $card + ['id' => $cardId];
            }
        }

        $linked = 0;
        foreach ($clients as $client) {
            if ($client->trello_card_id) {
                continue;
            }
            $card = $byKey[self::clientKey((string) $client->name)] ?? null;
            if (! $card) {
                continue;
            }
            $client->forceFill([
                'trello_card_id' => $card['id'],
                'trello_board_id' => $card['board_id'],
                'trello_list_id' => $card['list_id'] ?? null,
                'trello_board_name' => Str::limit((string) $card['board'], 190, ''),
                'trello_list_name' => Str::limit((string) $card['list'], 190, ''),
                'trello_card_url' => $card['url'] ?? null,
                'trello_synced_at' => now(),
            ])->save();
            $linked++;
        }

        return $linked;
    }

    /** Link one client right after it is saved (runs after the response, never blocks the save). */
    public static function linkLater(Client $client): void
    {
        if ($client->trello_card_id) {
            return;
        }
        $id = $client->id;
        dispatch(function () use ($id) {
            try {
                $client = Client::query()->with('team.parentTeam', 'team.trelloIntegration')->find($id);
                $team = self::trelloTeamFor($client?->team);
                if (! $client || $client->trello_card_id || ! $team) {
                    return;
                }
                self::linkFromIndex([$client], ProductionDashboardController::overviewData($team)['card_index'] ?? []);
            } catch (\Throwable $e) {
                Log::warning('Client → Trello card link failed', ['client_id' => $id, 'message' => $e->getMessage()]);
            }
        })->afterResponse();
    }
}
