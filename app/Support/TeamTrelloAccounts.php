<?php

namespace App\Support;

use App\Models\Team;

/**
 * Suggested Trello login email per CRM team (separate account per team).
 */
class TeamTrelloAccounts
{
    /** @var array<string, string> team-name needle (lowercase) => email */
    private const MAP = [
        'staffshaw' => 'sales.trello@staffshaw.com',
        'nextbyte' => 'sales.nexbyte@staffshaw.com',
        'nexbyte' => 'sales.nexbyte@staffshaw.com',
    ];

    /**
     * Optional board-name allowlist for customer sync (exact Trello board title).
     * null = import from all open boards.
     *
     * @var array<string, list<string>>
     */
    private const SYNC_BOARDS = [
        'staffshaw' => ['Team Design - SS'],
    ];

    public static function suggestedEmail(?Team $team): ?string
    {
        if (! $team) {
            return null;
        }

        $name = strtolower(trim((string) $team->name));

        foreach (self::MAP as $needle => $email) {
            if ($needle !== '' && str_contains($name, $needle)) {
                return $email;
            }
        }

        return null;
    }

    /** @return list<string>|null */
    public static function syncBoardNames(?Team $team): ?array
    {
        if (! $team) {
            return null;
        }

        $name = strtolower(trim((string) $team->name));
        foreach (self::SYNC_BOARDS as $needle => $boards) {
            if ($needle !== '' && str_contains($name, $needle)) {
                return $boards;
            }
        }

        return null;
    }
}
