<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\AuthScope;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadImportService
{
    /** @var list<string> */
    public const TEMPLATE_HEADERS = [
        'Name',
        'Company',
        'Email',
        'Phone',
        'Value',
        'Source',
        'Status',
        'Assigned To',
        'Brand',
        'Team',
        'Follow-up',
        'Notes',
    ];

    /**
     * @return array{
     *   created: int,
     *   skipped: int,
     *   errors: list<array{row: int, message: string}>,
     *   warnings: list<array{row: int, message: string}>
     * }
     */
    public function import(UploadedFile $file, User $actor): array
    {
        $rows = $this->parseFile($file);
        if ($rows === []) {
            return [
                'created' => 0,
                'skipped' => 0,
                'errors' => [['row' => 0, 'message' => 'No data rows found. Download the template and fill at least one lead.']],
                'warnings' => [],
            ];
        }

        $brands = $this->brandLookup();
        $users = $this->userLookup($actor);
        $teams = $this->teamLookup();
        $canAssignOthers = $this->canAssignOtherAgents($actor);

        $created = 0;
        $skipped = 0;
        $errors = [];
        $warnings = [];

        DB::transaction(function () use ($rows, $actor, $brands, $users, $teams, $canAssignOthers, &$created, &$skipped, &$errors, &$warnings) {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;
                $result = $this->importRow($row, $actor, $brands, $users, $teams, $canAssignOthers);
                if ($result['ok']) {
                    $created++;
                    foreach ($result['warnings'] ?? [] as $w) {
                        $warnings[] = ['row' => $rowNum, 'message' => $w];
                    }
                } else {
                    $skipped++;
                    $errors[] = ['row' => $rowNum, 'message' => $result['message']];
                }
            }
        });

        return compact('created', 'skipped', 'errors', 'warnings');
    }

    public function templateCsv(): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, self::TEMPLATE_HEADERS);
        fputcsv($out, [
            'Jane Doe',
            'Acme Inc',
            'jane@example.com',
            '+1 555 0100',
            '500',
            'website',
            'New Lead',
            '',
            'Example Brand',
            '',
            '2026-12-01',
            'Interested in logo package',
        ]);
        rewind($out);
        $csv = stream_get_contents($out) ?: '';
        fclose($out);

        return $csv;
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (! $path) {
            return [];
        }
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $rawHeader = fgetcsv($handle);
        if (! is_array($rawHeader) || $rawHeader === []) {
            fclose($handle);

            return [];
        }
        if (isset($rawHeader[0])) {
            $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeader[0]) ?? (string) $rawHeader[0];
        }

        $map = $this->mapHeaders($rawHeader);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (! is_array($data) || $this->rowIsEmpty($data)) {
                continue;
            }
            $assoc = [];
            foreach ($map as $field => $colIndex) {
                $assoc[$field] = isset($data[$colIndex]) ? trim((string) $data[$colIndex]) : '';
            }
            $rows[] = $assoc;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function mapHeaders(array $header): array
    {
        $aliases = [
            'name' => ['name', 'customer name', 'contact name', 'lead name'],
            'company' => ['company', 'company name', 'business'],
            'email' => ['email', 'customer email', 'e-mail'],
            'phone' => ['phone', 'phone number', 'mobile', 'contact'],
            'lead_value' => ['value', 'lead value', 'amount', 'budget'],
            'source' => ['source', 'lead source'],
            'status' => ['status', 'stage'],
            'assigned_to' => ['assigned to', 'assigned', 'sales agent', 'agent', 'owner'],
            'brand' => ['brand'],
            'team' => ['team'],
            'follow_up_at' => ['follow-up', 'follow up', 'follow_up', 'next follow-up', 'followup'],
            'notes' => ['notes', 'comments', 'description'],
        ];

        $normalized = [];
        foreach ($header as $i => $label) {
            $key = Str::lower(trim((string) $label));
            $normalized[$i] = preg_replace('/\s+/', ' ', $key) ?? $key;
        }

        $map = [];
        foreach ($aliases as $field => $names) {
            foreach ($normalized as $i => $label) {
                if (in_array($label, $names, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    /** @param  list<string|null>  $data */
    private function rowIsEmpty(array $data): bool
    {
        foreach ($data as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<string, Brand>  $brands
     * @param  Collection<string, User>  $users
     * @param  Collection<string, Team>  $teams
     * @return array{ok: bool, message?: string, warnings?: list<string>}
     */
    private function importRow(
        array $row,
        User $actor,
        Collection $brands,
        Collection $users,
        Collection $teams,
        bool $canAssignOthers
    ): array {
        $warnings = [];
        $name = trim($row['name'] ?? '');
        if ($name === '') {
            return ['ok' => false, 'message' => 'Name is required.'];
        }

        $email = trim($row['email'] ?? '') ?: null;
        if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $warnings[] = "Invalid email \"{$email}\" — saved without email.";
            $email = null;
        }
        $phone = trim($row['phone'] ?? '') ?: null;

        if ($dup = $this->duplicateMessage($email, $phone)) {
            return ['ok' => false, 'message' => $dup];
        }

        $status = $this->parseStatus($row['status'] ?? '');
        if ($status === null) {
            $raw = trim($row['status'] ?? '');
            if ($raw !== '') {
                $warnings[] = "Status \"{$raw}\" not recognized — saved as New Lead.";
            }
            $status = 'new_lead';
        }

        $source = $this->parseSource($row['source'] ?? '');
        if (trim($row['source'] ?? '') !== '' && $source === null) {
            $warnings[] = 'Source "'.trim($row['source']).'" not recognized — left blank.';
        }

        $value = $this->parseMoney($row['lead_value'] ?? '0');
        if ($value === null) {
            $warnings[] = 'Value was invalid — saved as $0.';
            $value = 0.0;
        }

        $assigneeRaw = trim($row['assigned_to'] ?? '');
        $assignee = $assigneeRaw !== '' ? $this->resolveUser($assigneeRaw, $users) : null;
        if ($assigneeRaw !== '' && ! $assignee) {
            $warnings[] = "Assigned To \"{$assigneeRaw}\" not found — lead saved Unassigned (assign later).";
        } elseif ($assigneeRaw === '') {
            // leave unassigned — do not force importer
        }
        if ($assignee && ! $canAssignOthers && (int) $assignee->id !== (int) $actor->id) {
            $warnings[] = 'Assigned To skipped (you can only assign yourself) — left Unassigned.';
            $assignee = null;
        }
        if (AuthScope::seesOnlyOwnLeads() && ! $canAssignOthers) {
            // Agents/PMs who only see own leads: if they put someone else, already cleared;
            // blank stays unassigned so they can assign later from Edit (or keep blank).
        }

        $brandId = null;
        $brandRaw = trim($row['brand'] ?? '');
        if ($brandRaw !== '') {
            $brand = $brands->get($this->norm($brandRaw));
            if ($brand) {
                $brandId = $brand->id;
            } else {
                $warnings[] = "Brand \"{$brandRaw}\" not found — left blank.";
            }
        }

        $teamId = $actor->team_id;
        $teamRaw = trim($row['team'] ?? '');
        if ($teamRaw !== '') {
            $team = $teams->get($this->norm($teamRaw));
            if ($team) {
                $teamId = $team->id;
            } else {
                $warnings[] = "Team \"{$teamRaw}\" not found — using importer’s team.";
            }
        }
        if (! $teamId && $assignee?->team_id) {
            $teamId = $assignee->team_id;
        }
        if (! $teamId && $canAssignOthers) {
            $teamId = Team::headedBy($actor->id)->value('id')
                ?: AuthScope::accessibleTeamIds()->first();
        }

        $followUp = null;
        $followRaw = trim($row['follow_up_at'] ?? '');
        if ($followRaw !== '') {
            $followUp = $this->parseDate($followRaw);
            if (! $followUp) {
                $warnings[] = "Follow-up date \"{$followRaw}\" invalid — left blank.";
            }
        }

        Lead::query()->create([
            'name' => Str::limit($name, 255, ''),
            'company' => trim($row['company'] ?? '') ?: null,
            'email' => $email,
            'phone' => $phone,
            'lead_value' => $value,
            'source' => $source,
            'status' => $status,
            'assigned_to' => $assignee?->id,
            'brand_id' => $brandId,
            'team_id' => $teamId,
            'follow_up_at' => $followUp,
            'notes' => trim($row['notes'] ?? '') ?: null,
        ]);

        return ['ok' => true, 'warnings' => $warnings];
    }

    private function duplicateMessage(?string $email, ?string $phone): ?string
    {
        if ($email) {
            $exists = Lead::query()->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower($email)])->exists();
            if ($exists) {
                return 'A lead with this email already exists.';
            }
        }
        if ($phone) {
            $digits = preg_replace('/\D+/', '', $phone);
            $key = strlen($digits) >= 7 ? substr($digits, -10) : null;
            if ($key) {
                $hit = false;
                Lead::query()->whereNotNull('phone')->where('phone', '!=', '')
                    ->select(['id', 'phone'])
                    ->lazyById(500)
                    ->each(function (Lead $lead) use ($key, &$hit) {
                        $d = preg_replace('/\D+/', '', (string) $lead->phone);
                        if (strlen($d) >= 7 && substr($d, -10) === $key) {
                            $hit = true;

                            return false;
                        }
                    });
                if ($hit) {
                    return 'A lead with this phone already exists.';
                }
            }
        }

        return null;
    }

    /** @return Collection<string, Brand> */
    private function brandLookup(): Collection
    {
        $ids = AuthScope::accessibleBrandIds();

        return Brand::query()
            ->whereIn('id', $ids->isEmpty() ? [0] : $ids->all())
            ->get()
            ->keyBy(fn (Brand $b) => $this->norm($b->name));
    }

    /** @return Collection<string, User> */
    private function userLookup(User $actor): Collection
    {
        $q = User::query()
            ->where('account_status', User::ACCOUNT_ACTIVE)
            ->orderBy('name');
        if (! AuthScope::seesAllMoney()) {
            $teamIds = AuthScope::accessibleTeamIds();
            $q->where(function ($inner) use ($teamIds, $actor) {
                $inner->whereIn('team_id', $teamIds->isEmpty() ? [0] : $teamIds->all())
                    ->orWhere('id', $actor->id);
            });
        }

        $map = collect();
        foreach ($q->get() as $user) {
            $map[$this->norm($user->name)] = $user;
            if ($user->email) {
                $map[$this->norm($user->email)] = $user;
            }
            $first = Str::of($user->name)->trim()->before(' ')->toString();
            if ($first !== '' && ! $map->has($this->norm($first))) {
                $map[$this->norm($first)] = $user;
            }
        }

        return $map;
    }

    /** @return Collection<string, Team> */
    private function teamLookup(): Collection
    {
        return AuthScope::teamsForDropdown()->keyBy(fn (Team $t) => $this->norm($t->name));
    }

    private function canAssignOtherAgents(User $actor): bool
    {
        if ($actor->hasRole([Role::ADMIN, Role::MANAGER])) {
            return true;
        }
        if (Team::headedBy($actor->id)->exists()) {
            return true;
        }

        return \App\Models\SubTeamHead::where('user_id', $actor->id)->exists();
    }

    private function resolveUser(string $raw, Collection $users): ?User
    {
        $raw = trim($raw);

        return $raw === '' ? null : $users->get($this->norm($raw));
    }

    private function parseStatus(string $raw): ?string
    {
        $v = Str::lower(trim($raw));
        if ($v === '') {
            return 'new_lead';
        }
        $map = [
            'new' => 'new_lead',
            'new lead' => 'new_lead',
            'new_lead' => 'new_lead',
            'contacted' => 'contacted',
            'follow up' => 'follow_up',
            'follow-up' => 'follow_up',
            'follow_up' => 'follow_up',
            'proposal' => 'proposal',
            'client' => 'customer',
            'customer' => 'customer',
            'won' => 'customer',
            'lost' => 'lost',
        ];

        return $map[$v] ?? (in_array($v, Lead::STATUSES, true) ? $v : null);
    }

    private function parseSource(string $raw): ?string
    {
        $v = Str::lower(trim(str_replace(['-', ' '], '_', $raw)));
        if ($v === '') {
            return null;
        }
        $aliases = [
            'social' => 'social_media',
            'socialmedia' => 'social_media',
            'coldcall' => 'cold_call',
            'call' => 'cold_call',
            'web' => 'website',
        ];
        $v = $aliases[$v] ?? $v;

        return in_array($v, Lead::SOURCES, true) ? $v : null;
    }

    private function parseMoney(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0.0;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $raw));
        if ($clean === null || $clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return round((float) $clean, 2);
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        foreach (['Y-m-d', 'm/d/Y', 'n/j/Y', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $raw);
                if ($dt !== false) {
                    return $dt->format('Y-m-d');
                }
            } catch (\Throwable) {
                // next
            }
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function norm(string $value): string
    {
        return Str::lower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }
}
