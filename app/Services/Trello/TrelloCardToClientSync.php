<?php

namespace App\Services\Trello;

use App\Models\Client;
use App\Models\Team;
use App\Models\TeamTrelloIntegration;
use App\Support\TeamTrelloAccounts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

class TrelloCardToClientSync
{
    /**
     * Import open Trello cards into CRM customers for this team.
     * Optional board allowlist (e.g. Staffshaw → only "Team Design - SS").
     * Customer name is detected from card title. Same name (per team) = one customer.
     *
     * @param  list<string>|null  $onlyBoardNames  exact board titles; null = TeamTrelloAccounts / all
     * @return array{created:int, updated:int, skipped:int, boards:int, cards:int, filtered_boards:list<string>, errors:list<string>}
     */
    public function syncTeam(Team $team, ?int $createdBy = null, ?array $onlyBoardNames = null): array
    {
        $integration = $team->trelloIntegration;
        if (! $integration instanceof TeamTrelloIntegration || ! $integration->isReady()) {
            throw new RuntimeException('Trello is not connected for '.$team->name.'.');
        }

        $api = TrelloApiClient::forIntegration($integration);
        $boards = $api->boards();
        $createdBy = $createdBy ?: (Auth::id() ?: $integration->connected_by);

        $onlyBoardNames ??= TeamTrelloAccounts::syncBoardNames($team);
        $allow = null;
        if (is_array($onlyBoardNames) && $onlyBoardNames !== []) {
            $allow = array_fill_keys(array_map(
                fn ($n) => mb_strtolower(trim((string) $n)),
                $onlyBoardNames
            ), true);
        }

        $filteredBoards = [];
        foreach ($boards as $board) {
            $boardName = (string) ($board['name'] ?? '');
            if ($allow !== null && ! isset($allow[mb_strtolower(trim($boardName))])) {
                continue;
            }
            $filteredBoards[] = $board;
        }

        if ($allow !== null && $filteredBoards === []) {
            throw new RuntimeException(
                'No matching Trello board found. Expected: '.implode(', ', $onlyBoardNames)
            );
        }

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'boards' => count($filteredBoards),
            'cards' => 0,
            'filtered_boards' => array_values(array_map(fn ($b) => (string) ($b['name'] ?? ''), $filteredBoards)),
            'errors' => [],
        ];

        // Unique customers by normalized name (Mike/Michael, and/&).
        /** @var array<string, Client> $clientsByKey */
        $clientsByKey = [];
        foreach (Client::query()->where('team_id', $team->id)->get() as $existingClient) {
            $clientsByKey[self::normalizeNameKey($existingClient->name)] = $existingClient;
        }

        foreach ($filteredBoards as $board) {
            $boardId = (string) ($board['id'] ?? '');
            $boardName = (string) ($board['name'] ?? 'Board');
            if ($boardId === '') {
                continue;
            }

            try {
                $lists = $api->lists($boardId);
                $listNames = [];
                foreach ($lists as $list) {
                    $listNames[(string) $list['id']] = (string) ($list['name'] ?? 'List');
                }

                $cards = $api->cards($boardId);
                $stats['cards'] += count($cards);

                foreach ($cards as $card) {
                    $result = $this->upsertCard(
                        $team,
                        $card,
                        $boardId,
                        $boardName,
                        $listNames,
                        $createdBy,
                        $clientsByKey
                    );
                    $stats[$result]++;
                }
            } catch (RuntimeException $e) {
                $stats['errors'][] = $boardName.': '.$e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * Pick the person/customer name from a Trello card title.
     * Most titles: "Steve Masterson - Hampton Publisher" → Steve Masterson
     * Some titles: "Video Trailer - Carey Barnes" → Carey Barnes
     *
     * @return array{name: string, company: null, raw: string}
     */
    public static function parseCardTitle(string $raw): array
    {
        $raw = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
        if ($raw === '') {
            return ['name' => '', 'company' => null, 'raw' => $raw];
        }

        if (! preg_match('/^(.*?)\s*[-|]\s*(.+)$/u', $raw, $m)) {
            return [
                'name' => self::cleanPersonName($raw),
                'company' => null,
                'raw' => $raw,
            ];
        }

        $left = trim($m[1]);
        $right = trim($m[2]);
        // If right still has more segments, only use first segment for scoring vs left.
        $rightFirst = trim((string) ((preg_split('/\s*[-|]\s*/u', $right, 2) ?: [$right])[0] ?? $right));

        $leftScore = self::personNameScore(self::cleanPersonName($left) ?: $left);
        $rightScore = self::personNameScore(self::cleanPersonName($rightFirst) ?: $rightFirst);

        // Prefer the side that looks more like a person. Tie → left (majority pattern).
        if ($rightScore > $leftScore) {
            $name = $rightFirst !== '' ? $rightFirst : $left;
        } else {
            $name = $left !== '' ? $left : $rightFirst;
        }

        $name = self::cleanPersonName($name);

        return [
            'name' => $name !== '' ? $name : self::cleanPersonName($raw),
            'company' => null,
            'raw' => $raw,
        ];
    }

    /**
     * Strip junk from a person label:
     * "Angela Bennett (owais) Press Release" → "Angela Bennett"
     * "Angela Bennett Video Trailer" → "Angela Bennett"
     */
    public static function cleanPersonName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '') {
            return '';
        }

        // Drop parenthetical notes: (French), (owais), (Owais)
        $name = preg_replace('/\([^)]*\)/u', ' ', $name) ?? $name;
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        static $suffixPhrases = [
            'press release', 'video trailer', 'video trailers', 'author website', "author's website",
            'book cover', 'book covers', 'social media', 'website design', 'website content',
            'video animation', 'cover design', 'logo design',
        ];
        static $suffixWords = [
            'trailer', 'trailers', 'audiobook', 'audiobooks', 'manuscript', 'manuscripts',
            'marketing', 'website', 'websites', 'content', 'animation', 'animations',
            'design', 'designs', 'cover', 'covers', 'seo', 'branding', 'package', 'packages',
            'video', 'press', 'release', 'logo', 'development',
        ];

        // Strip known multi-word suffixes from the end (repeat).
        $lower = mb_strtolower($name);
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($suffixPhrases as $phrase) {
                if (str_ends_with($lower, ' '.$phrase) || $lower === $phrase) {
                    $name = trim(mb_substr($name, 0, mb_strlen($name) - mb_strlen($phrase)));
                    $name = rtrim($name, " \t-–—|");
                    $lower = mb_strtolower($name);
                    $changed = true;
                }
            }
        }

        // Strip trailing single project words (keep at least first+last when possible).
        $words = preg_split('/\s+/u', $name) ?: [];
        while (count($words) > 2) {
            $last = mb_strtolower(rtrim((string) end($words), '.,'));
            if (in_array($last, $suffixWords, true)) {
                array_pop($words);
                $name = implode(' ', $words);
                continue;
            }
            break;
        }

        // If still "Name Video" style with exactly 2 words and second is project → drop it.
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        if (count($words) === 2) {
            $last = mb_strtolower(rtrim($words[1], '.,'));
            if (in_array($last, $suffixWords, true)) {
                $name = $words[0];
            }
        }

        return trim($name);
    }

    /**
     * Higher = more likely a customer/person name than a service/project label.
     */
    public static function personNameScore(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return -100;
        }

        $score = 0;
        $lower = mb_strtolower($text);
        $words = preg_split('/\s+/u', $text) ?: [];
        $wordCount = count($words);

        // Leading number / qty → project ("3 Website Design")
        if (preg_match('/^\d+/u', $text)) {
            $score -= 8;
        }

        // Typical person: 2–4 words
        if ($wordCount >= 2 && $wordCount <= 4) {
            $score += 4;
        } elseif ($wordCount === 1) {
            $score += 0;
        } elseif ($wordCount > 5) {
            $score -= 3;
        }

        // "Mike & Carey Barnes"
        if (str_contains($text, '&')) {
            $score += 3;
        }

        // Looks like capitalized name tokens
        $nameLike = 0;
        foreach ($words as $w) {
            $w = trim($w, " \t.,");
            if ($w === '' || $w === '&') {
                continue;
            }
            if (preg_match("/^[A-Z][A-Za-z'.]+$/u", $w) || preg_match("/^[A-Z]\.$/u", $w)) {
                $nameLike++;
            }
        }
        if ($nameLike >= 2) {
            $score += 3;
        }

        static $projectWords = [
            'website', 'websites', 'web', 'design', 'designs', 'video', 'trailer', 'trailers',
            'audiobook', 'audiobooks', 'manuscript', 'manuscripts', 'marketing', 'cover', 'covers',
            'content', 'animation', 'animations', 'publisher', 'publishers', 'project', 'projects',
            'book', 'books', 'porch', 'talk', 'month', 'months', 'seo', 'logo', 'branding',
            'social', 'media', 'package', 'packages', 'development', 'dev', 'ss', 'hp',
            'author', 'authors', 'global', 'hampton', 'timesquare', 'trailer', 'completed',
            'compeled', 'description', 'team', 'design',
        ];

        $hits = 0;
        foreach ($words as $w) {
            $token = mb_strtolower(trim($w, " \t.,"));
            if (in_array($token, $projectWords, true)) {
                $hits++;
            }
        }
        $score -= $hits * 4;

        // Whole-phrase project cues
        if (preg_match('/\b(website|video|trailer|audiobook|manuscript|marketing|design|content|animation)\b/iu', $lower)) {
            $score -= 3;
        }

        return $score;
    }

    /**
     * Canonical key so "Mike & Carey Barnes" / "Michael and Carey Barnes" match.
     */
    public static function normalizeNameKey(string $name): string
    {
        $name = self::canonicalizeDisplayName($name);
        $parts = preg_split('/\s*&\s*/u', $name) ?: [$name];
        $normParts = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $words = preg_split('/\s+/u', mb_strtolower($part)) ?: [];
            $expanded = [];
            foreach ($words as $i => $word) {
                $expanded[] = $i === 0 ? self::canonicalFirstName($word) : $word;
            }
            $normParts[] = implode(' ', $expanded);
        }
        sort($normParts);

        return implode(' & ', $normParts);
    }

    /** Clean display form: strip junk, "and" → "&", expand common nicknames. */
    public static function canonicalizeDisplayName(string $name): string
    {
        $name = self::cleanPersonName($name);
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        $name = preg_replace('/\s+\band\b\s+/iu', ' & ', $name) ?? $name;
        $name = preg_replace('/\s*&\s*/u', ' & ', $name) ?? $name;

        $parts = preg_split('/\s*&\s*/u', $name) ?: [$name];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $words = preg_split('/\s+/u', $part) ?: [];
            if ($words !== []) {
                $first = mb_strtolower($words[0]);
                $canon = self::canonicalFirstName($first);
                if ($canon !== $first) {
                    $words[0] = self::titleCaseWord($canon);
                } else {
                    $words[0] = self::titleCaseWord($words[0]);
                }
            }
            $out[] = implode(' ', $words);
        }

        return implode(' & ', $out);
    }

    public static function canonicalFirstName(string $word): string
    {
        $word = mb_strtolower(trim($word, " \t.,"));
        static $map = [
            'mike' => 'michael',
            'mikey' => 'michael',
            'mick' => 'michael',
            'bob' => 'robert',
            'bobby' => 'robert',
            'rob' => 'robert',
            'robbie' => 'robert',
            'bill' => 'william',
            'billy' => 'william',
            'will' => 'william',
            'willy' => 'william',
            'jim' => 'james',
            'jimmy' => 'james',
            'joe' => 'joseph',
            'tony' => 'anthony',
            'chris' => 'christopher',
            'dan' => 'daniel',
            'danny' => 'daniel',
            'tom' => 'thomas',
            'tommy' => 'thomas',
            'dick' => 'richard',
            'rick' => 'richard',
            'rich' => 'richard',
            'alex' => 'alexander',
            'sam' => 'samuel',
            'ben' => 'benjamin',
            'josh' => 'joshua',
            'nick' => 'nicholas',
            'matt' => 'matthew',
            'jon' => 'jonathan',
            'johnathan' => 'jonathan',
        ];

        return $map[$word] ?? $word;
    }

    private static function titleCaseWord(string $word): string
    {
        $word = trim($word);
        if ($word === '') {
            return $word;
        }
        if (str_contains($word, '-')) {
            return implode('-', array_map(
                fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)).mb_strtolower(mb_substr($p, 1)),
                explode('-', $word)
            ));
        }

        return mb_strtoupper(mb_substr($word, 0, 1)).mb_strtolower(mb_substr($word, 1));
    }

    private static function preferDisplayName(string $current, string $candidate): string
    {
        // Prefer longer / more formal looking label once keys match.
        if (mb_strlen($candidate) > mb_strlen($current)) {
            return $candidate;
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  array<string, string>  $listNames
     * @param  array<string, Client>  $clientsByKey
     * @return 'created'|'updated'|'skipped'
     */
    private function upsertCard(
        Team $team,
        array $card,
        string $boardId,
        string $boardName,
        array $listNames,
        ?int $createdBy,
        array &$clientsByKey,
    ): string {
        $cardId = (string) ($card['id'] ?? '');
        $rawTitle = trim((string) ($card['name'] ?? ''));
        if ($cardId === '' || $rawTitle === '') {
            return 'skipped';
        }

        $parsed = self::parseCardTitle($rawTitle);
        $name = self::canonicalizeDisplayName($parsed['name']);
        if ($name === '') {
            return 'skipped';
        }

        $nameKey = self::normalizeNameKey($name);
        $listId = (string) ($card['idList'] ?? '');
        $listName = $listNames[$listId] ?? null;
        $desc = trim((string) ($card['desc'] ?? ''));
        $url = (string) ($card['url'] ?? $card['shortUrl'] ?? '');
        $email = $this->extractEmail($desc."\n".$rawTitle);

        $existing = $this->findExistingClient($clientsByKey, $nameKey);

        // Already have this unique customer in this sync → only merge notes/email.
        if ($existing) {
            $betterName = self::preferDisplayName($existing->name, $name);
            $updates = ['trello_synced_at' => now(), 'company_name' => null];
            if ($betterName !== $existing->name) {
                $updates['name'] = Str::limit($betterName, 190, '');
            }
            if ($email && blank($existing->email)) {
                $updates['email'] = $email;
            }
            $existing->update($updates);
            $this->mergeExtraCardIntoNotes($existing->fresh(), $boardName, $listName, $url, $rawTitle, $email);
            $clientsByKey[$nameKey] = $existing->fresh();

            return 'skipped';
        }

        $notes = $this->buildNotes($boardName, $listName, $desc, $url, $rawTitle);
        $created = Client::query()->create([
            'name' => Str::limit($name, 190, ''),
            'company_name' => null,
            'email' => $email,
            'notes' => $notes,
            'is_active' => true,
            'team_id' => $team->id,
            'trello_card_id' => $cardId,
            'trello_board_id' => $boardId,
            'trello_list_id' => $listId ?: null,
            'trello_board_name' => Str::limit($boardName, 190, ''),
            'trello_list_name' => $listName ? Str::limit($listName, 190, '') : null,
            'trello_card_url' => $url !== '' ? Str::limit($url, 500, '') : null,
            'trello_synced_at' => now(),
            'created_by' => $createdBy,
            'currency' => 'USD',
        ]);
        $clientsByKey[$nameKey] = $created;
        // Keep fuzzy key pointing at same record for later cards.
        $clientsByKey[self::normalizeNameKey($created->name)] = $created;

        return 'created';
    }

    /**
     * @param  array<string, Client>  $clientsByKey
     */
    private function findExistingClient(array &$clientsByKey, string $nameKey): ?Client
    {
        if (isset($clientsByKey[$nameKey])) {
            return $clientsByKey[$nameKey];
        }

        $tokens = $this->nameKeyTokens($nameKey);

        // First-name only → merge into fuller "First Last" (Angela → Angela Bennett)
        if (count($tokens) === 1 && ! str_contains($nameKey, '&')) {
            $first = $tokens[0];
            $best = null;
            $bestLen = 0;
            foreach ($clientsByKey as $key => $client) {
                if (str_contains($key, '&')) {
                    continue;
                }
                $other = $this->nameKeyTokens($key);
                if (count($other) >= 2 && ($other[0] ?? '') === $first && count($other) > $bestLen) {
                    $best = $client;
                    $bestLen = count($other);
                }
            }
            if ($best) {
                $clientsByKey[$nameKey] = $best;

                return $best;
            }
        }

        // Fuller name arrives after first-name-only was created
        if (count($tokens) >= 2 && ! str_contains($nameKey, '&')) {
            $first = $tokens[0];
            if (isset($clientsByKey[$first])) {
                $clientsByKey[$nameKey] = $clientsByKey[$first];

                return $clientsByKey[$first];
            }
        }

        // Soft-merge joint names: "michael & carey" ⊂ "carey barnes & michael"
        if (! str_contains($nameKey, '&')) {
            return null;
        }

        if (count($tokens) < 2) {
            return null;
        }

        foreach ($clientsByKey as $key => $client) {
            if (! str_contains($key, '&')) {
                continue;
            }
            $other = $this->nameKeyTokens($key);
            if (count($other) < 2) {
                continue;
            }
            $smaller = count($tokens) <= count($other) ? $tokens : $other;
            $larger = count($tokens) <= count($other) ? $other : $tokens;
            if ($smaller !== [] && array_diff($smaller, $larger) === []) {
                $clientsByKey[$nameKey] = $client;

                return $client;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function nameKeyTokens(string $nameKey): array
    {
        $flat = str_replace(' & ', ' ', $nameKey);

        return array_values(array_filter(preg_split('/\s+/u', $flat) ?: []));
    }

    private function mergeExtraCardIntoNotes(
        Client $client,
        string $boardName,
        ?string $listName,
        string $url,
        string $rawTitle,
        ?string $email,
    ): void {
        $line = 'Also: '.$rawTitle;
        if ($boardName !== '') {
            $line .= ' @ '.$boardName;
        }
        if ($listName) {
            $line .= ' / '.$listName;
        }
        if ($url !== '') {
            $line .= ' — '.$url;
        }

        $notes = (string) $client->notes;
        if (! str_contains($notes, $rawTitle) && ! ($url !== '' && str_contains($notes, $url))) {
            $client->notes = trim($notes."\n".$line);
        }

        $updates = ['trello_synced_at' => now()];
        if ($email && blank($client->email)) {
            $updates['email'] = $email;
        }
        if ($client->isDirty('notes')) {
            $updates['notes'] = $client->notes;
        }
        $client->update($updates);
    }

    private function extractEmail(string $text): ?string
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)) {
            return Str::lower($m[0]);
        }

        return null;
    }

    private function buildNotes(?string $board, ?string $list, string $desc, string $url, string $rawTitle): string
    {
        $parts = [];
        $parts[] = 'Imported from Trello';
        $parts[] = 'Card title: '.$rawTitle;
        if ($board) {
            $parts[] = 'Board: '.$board;
        }
        if ($list) {
            $parts[] = 'List: '.$list;
        }
        if ($url !== '') {
            $parts[] = 'Card: '.$url;
        }
        if ($desc !== '') {
            $parts[] = '';
            $parts[] = Str::limit($desc, 2000);
        }

        return implode("\n", $parts);
    }
}
