<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Client;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\AuthScope;
use App\Support\ClientTrelloLink;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleImportService
{
    /** @var list<string> */
    public const TEMPLATE_HEADERS = [
        'Sales Type',
        'Service',
        'PPC',
        'Brand',
        'Order Date',
        'Customer Name',
        'Phone Number',
        'Customer Email',
        'Service Purchased',
        'Merchant',
        'Status',
        'Paid Amount',
        'Balance',
        'Sales Agent',
        'Account Manager',
        'Project Manager',
        'Comments',
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
            return ['created' => 0, 'skipped' => 0, 'errors' => [
                ['row' => 0, 'message' => 'No data rows found. Download the template and fill at least one sale.'],
            ], 'warnings' => []];
        }

        $brands = $this->brandLookup($actor);
        $users = $this->userLookup($actor);
        $canAssignOthers = $this->canAssignOtherAgents($actor);

        $created = 0;
        $skipped = 0;
        $errors = [];
        $warnings = [];

        DB::transaction(function () use ($rows, $actor, $brands, $users, $canAssignOthers, &$created, &$skipped, &$errors, &$warnings) {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; // header is row 1
                // Master-sheet footers / team totals have no customer or service — ignore quietly.
                if ($this->rowLacksSaleData($row)) {
                    continue;
                }
                $result = $this->importRow($row, $rowNum, $actor, $brands, $users, $canAssignOthers);
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

        if ($created > 0) {
            ActivityLogger::log(
                $actor,
                UserActivityLog::TYPE_SALE_CREATED,
                "Imported {$created} sale(s) from spreadsheet",
                ['imported' => $created, 'skipped' => $skipped]
            );
        }

        return compact('created', 'skipped', 'errors', 'warnings');
    }

    public function templateCsv(): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, self::TEMPLATE_HEADERS);
        fputcsv($out, [
            'Front',
            'Design',
            'No',
            'Example Brand',
            '2026-11-01',
            'Jane Doe',
            '+1 555 0100',
            'jane@example.com',
            'Logo design package',
            'Stripe',
            'Completed',
            '500',
            '0',
            'agent@example.com',
            '',
            '',
            'Imported sample — replace with real rows',
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

        // Strip UTF-8 BOM on first cell
        if (isset($rawHeader[0])) {
            $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeader[0]) ?? (string) $rawHeader[0];
        }

        $map = $this->mapHeaders($rawHeader);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if (! is_array($data)) {
                continue;
            }
            if ($this->rowIsEmpty($data)) {
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
            'sales_type' => ['sales type', 'sales type (front/upsell)', 'sale type', 'type'],
            'service_category' => ['service', 'service category', 'category', 'ebook/design', 'ebook or design'],
            'is_ppc' => ['ppc'],
            'brand' => ['brand'],
            'sale_date' => ['order date', 'sale date', 'date'],
            'client_name' => ['customer name', 'client name', 'name'],
            'client_phone' => ['phone number', 'phone', 'customer phone'],
            'client_email' => ['customer email', 'client email', 'email'],
            'title' => ['service purchased', 'project title', 'title', 'service'],
            'merchant' => ['merchant'],
            'status' => ['status'],
            'paid' => ['paid amount', 'received amount', 'paid'],
            'balance' => ['balance', 'remaining', 'remaining amount'],
            'sales_agent' => ['sales agent', 'agent', 'seller'],
            'account_manager' => ['account manager', 'am'],
            'project_manager' => ['project manager', 'pm'],
            'notes' => ['comments', 'notes', 'description'],
        ];

        $normalized = [];
        foreach ($header as $i => $label) {
            $key = Str::lower(trim((string) $label));
            $key = preg_replace('/\s+/', ' ', $key) ?? $key;
            $normalized[$i] = $key;
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
     * @return array{ok: bool, message?: string, warnings?: list<string>}
     */
    private function importRow(
        array $row,
        int $rowNum,
        User $actor,
        Collection $brands,
        Collection $users,
        bool $canAssignOthers
    ): array {
        $warnings = [];
        $title = trim($row['title'] ?? '');
        $clientName = trim($row['client_name'] ?? '');
        if ($title === '' || $clientName === '') {
            return ['ok' => false, 'message' => 'Service Purchased and Customer Name are required.'];
        }

        $saleType = $this->parseSaleType($row['sales_type'] ?? '');
        if ($saleType === null) {
            return ['ok' => false, 'message' => 'Sales Type must be Front or Upsell.'];
        }

        // Optional: many master sheets have no Service column — leave blank if missing/invalid.
        $serviceCategory = $this->parseServiceCategory($row['service_category'] ?? '');
        if (trim($row['service_category'] ?? '') !== '' && $serviceCategory === null) {
            $warnings[] = 'Service “'.trim($row['service_category']).'” not recognized — left blank (use Ebook or Design).';
            $serviceCategory = null;
        }

        $brandName = trim($row['brand'] ?? '');
        if ($brandName === '') {
            return ['ok' => false, 'message' => 'Brand is required.'];
        }
        $brand = $this->resolveBrand($brandName, $brands);
        if (! $brand) {
            return ['ok' => false, 'message' => "Unknown brand \"{$brandName}\". Use the CRM brand name or its Short code (set on Brands → Edit)."];
        }

        $saleDate = $this->parseDate($row['sale_date'] ?? '');
        if (! $saleDate) {
            return ['ok' => false, 'message' => 'Order Date is invalid. Use YYYY-MM-DD or MM/DD/YYYY.'];
        }

        $paid = $this->parseMoney($row['paid'] ?? '0');
        $balance = $this->parseMoney($row['balance'] ?? '0');
        if ($paid === null || $balance === null) {
            return ['ok' => false, 'message' => 'Paid Amount and Balance must be numbers.'];
        }
        $amount = round($paid + $balance, 2);
        if ($amount < 0) {
            return ['ok' => false, 'message' => 'Total amount cannot be negative.'];
        }

        $status = $this->parseStatus($row['status'] ?? 'completed');
        if ($status === null) {
            // Don't block the row — blank/unknown status defaults to completed.
            $warnings[] = 'Status “'.trim($row['status'] ?? '').'” not recognized — saved as Completed.';
            $status = 'completed';
        }

        // Sales Agent: link CRM user when found; otherwise keep the sheet name for history.
        // Upsell / AM-only sheets often omit Sales Agent — fall back to Account Manager for ownership.
        $agentRaw = trim($row['sales_agent'] ?? '');
        $amRawForAgent = trim($row['account_manager'] ?? '');
        $usedAmAsAgent = false;
        if ($agentRaw === '' && $amRawForAgent !== '') {
            $agentRaw = $amRawForAgent;
            $usedAmAsAgent = true;
        }
        $agent = $agentRaw !== '' ? $this->resolveUser($agentRaw, $users) : null;
        $agentName = null;
        if ($agentRaw !== '' && ! $agent) {
            $agentName = Str::limit($agentRaw, 255, '');
            $warnings[] = $usedAmAsAgent
                ? "Account Manager \"{$agentRaw}\" used as Sales Agent (no agent column) — name saved for history (not linked)."
                : "Sales Agent \"{$agentRaw}\" not in CRM — name saved on sale for history (not linked to a login).";
        } elseif ($agentRaw === '') {
            $warnings[] = 'No Sales Agent or Account Manager in sheet — sale saved without agent.';
        } elseif ($usedAmAsAgent && $agent) {
            $warnings[] = "No Sales Agent column — used Account Manager \"{$agent->name}\" as sales agent.";
        }
        if ($agent && ! $canAssignOthers && (int) $agent->id !== (int) $actor->id) {
            $agentName = Str::limit($agent->name, 255, '');
            $warnings[] = 'Sales Agent “'.$agent->name.'” skipped (you can only assign yourself) — name kept on sale, not linked.';
            $agent = null;
        }

        // Team/company from matched agent, else from the person importing (team context only).
        $teamId = $agent?->team_id ?: $actor->team_id;
        $companyId = $agent?->company_id ?: $actor->company_id;
        if (! $teamId && $actor->hasRole(Role::PROJECT_MANAGER)) {
            $teamId = $actor->approvedTeamIds()->first();
            if ($teamId) {
                $team = Team::find($teamId);
                $companyId = $team?->company_id;
            }
        }
        if (! $teamId && $canAssignOthers) {
            $teamId = Team::headedBy($actor->id)->value('id')
                ?: AuthScope::accessibleTeamIds()->first();
            if ($teamId) {
                $team = Team::find($teamId);
                $companyId = $companyId ?: $team?->company_id;
            }
        }
        if (! $teamId) {
            return ['ok' => false, 'message' => 'Could not determine team for this sale. Assign the importer to a team, or set a Sales Agent with a team.'];
        }

        $accountManagerId = null;
        $amName = null;
        $amRaw = trim($row['account_manager'] ?? '');
        if ($amRaw !== '') {
            $am = $this->resolveUser($amRaw, $users);
            if ($am) {
                $accountManagerId = $am->id;
                $amName = $am->name;
            } else {
                $amName = Str::limit($amRaw, 255, '');
                $warnings[] = "Account Manager \"{$amRaw}\" not in CRM — name saved for history.";
            }
        }

        $projectManagerId = null;
        $pmName = null;
        $pmRaw = trim($row['project_manager'] ?? '');
        if ($pmRaw !== '') {
            $pm = $this->resolveUser($pmRaw, $users);
            if ($pm) {
                $projectManagerId = $pm->id;
                $pmName = $pm->name;
            } else {
                $pmName = Str::limit($pmRaw, 255, '');
                $warnings[] = "Project Manager \"{$pmRaw}\" not in CRM — name saved for history.";
            }
        }

        $email = trim($row['client_email'] ?? '') ?: null;
        if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $warnings[] = "Invalid customer email \"{$email}\" — saved without email.";
            $email = null;
        }

        $clientId = $this->resolveOrCreateClient([
            'name' => $clientName,
            'email' => $email,
            'phone' => trim($row['client_phone'] ?? '') ?: null,
            'brand_id' => $brand->id,
            'team_id' => (int) $teamId,
            'created_by' => $actor->id,
        ]);

        $isRefunded = false;
        $isChargeback = false;
        $storeStatus = $status;
        if ($status === Sale::STATUS_REFUNDED) {
            $isRefunded = true;
            $storeStatus = 'completed';
        } elseif ($status === Sale::STATUS_CHARGEBACK) {
            $isChargeback = true;
            $storeStatus = 'completed';
        }

        // Team leads / managers / admins importing history: auto-approve so reports count immediately.
        // Regular agents still go through pending approval.
        $autoApprove = $canAssignOthers;
        if ($autoApprove && $storeStatus === 'pending') {
            $storeStatus = 'completed';
        }

        $finalStatus = $storeStatus;
        if ($isChargeback) {
            $finalStatus = Sale::STATUS_CHARGEBACK;
        } elseif ($isRefunded) {
            $finalStatus = Sale::STATUS_REFUNDED;
        }

        $sale = Sale::query()->create([
            'title' => Str::limit($title, 255, ''),
            'client_id' => $clientId,
            'client_name' => Str::limit($clientName, 255, ''),
            'client_email' => $email,
            'client_phone' => trim($row['client_phone'] ?? '') ?: null,
            'amount' => $amount,
            'received_amount' => $paid,
            'sale_date' => $saleDate,
            'user_id' => $agent?->id,
            'sales_agent_name' => $agent?->name ?? $agentName,
            'account_manager_id' => $accountManagerId,
            'account_manager_name' => $amName,
            'project_manager_id' => $projectManagerId,
            'project_manager_name' => $pmName,
            'team_id' => $teamId,
            'company_id' => $companyId,
            'brand_id' => $brand->id,
            'status' => $finalStatus,
            'sale_type' => $saleType,
            'service_category' => $serviceCategory,
            'merchant' => trim($row['merchant'] ?? '') ?: null,
            'is_ppc' => $this->parseYesNo($row['is_ppc'] ?? ''),
            'notes' => trim($row['notes'] ?? '') ?: null,
            'is_refunded' => $isRefunded,
            'refunded_at' => $isRefunded ? now() : null,
            'refunded_by' => $isRefunded ? $actor->id : null,
            'refunded_amount' => $isRefunded ? $amount : null,
            'status_before_refund' => $isRefunded ? 'completed' : null,
            'is_chargeback' => $isChargeback,
            'chargeback_at' => $isChargeback ? now() : null,
            'chargeback_by' => $isChargeback ? $actor->id : null,
            'chargeback_amount' => $isChargeback ? $amount : null,
            'status_before_chargeback' => $isChargeback ? 'completed' : null,
            'approval_status' => $autoApprove ? Sale::APPROVAL_APPROVED : Sale::APPROVAL_PENDING,
            'approved_by' => $autoApprove ? $actor->id : null,
            'approved_at' => $autoApprove ? now() : null,
            'is_draft' => false,
        ]);

        // Same as manual approve: Paid Amount → payment receipt when sale is already approved.
        if ($autoApprove && $paid > 0 && ! $isRefunded && ! $isChargeback) {
            app(InvoiceService::class)->receiptForReceivedOnSale(
                $sale,
                $actor,
                $saleDate instanceof Carbon ? $saleDate->toDateString() : (string) $saleDate
            );
        }

        return ['ok' => true, 'warnings' => $warnings];
    }

    /** @param  array{name: string, email: ?string, phone: ?string, brand_id: int, team_id: int, created_by: int}  $data */
    private function resolveOrCreateClient(array $data): int
    {
        $q = Client::query()->where('team_id', $data['team_id']);
        if (! empty($data['email'])) {
            $existing = (clone $q)->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->first();
            if ($existing) {
                return $existing->id;
            }
        }

        $existing = (clone $q)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->first();
        if ($existing) {
            return $existing->id;
        }

        $client = Client::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'brand_id' => $data['brand_id'],
            'team_id' => $data['team_id'],
            'created_by' => $data['created_by'],
            'is_active' => true,
            'currency' => 'USD',
        ]);
        ClientTrelloLink::linkLater($client);

        return $client->id;
    }

    /**
     * Map of normalized name + short_code → Brand (both work on import).
     *
     * @return Collection<string, Brand>
     */
    private function brandLookup(User $actor): Collection
    {
        $ids = AuthScope::accessibleBrandIds();
        $brands = Brand::query()
            ->whereIn('id', $ids->isEmpty() ? [0] : $ids->all())
            ->get();

        $map = collect();
        foreach ($brands as $brand) {
            $map[$this->norm($brand->name)] = $brand;
            if (filled($brand->short_code)) {
                $codeKey = $this->norm($brand->short_code);
                if ($codeKey !== '' && ! $map->has($codeKey)) {
                    $map[$codeKey] = $brand;
                }
            }
        }

        return $map;
    }

    /**
     * Match CRM brand name or Short code from Brands → Edit.
     *
     * @param  Collection<string, Brand>  $brands
     */
    private function resolveBrand(string $raw, Collection $brands): ?Brand
    {
        $key = $this->norm($raw);
        if ($key === '') {
            return null;
        }

        if ($brands->has($key)) {
            return $brands->get($key);
        }

        // Unique contains match on name keys only (avoid short-code collisions)
        $hits = $brands->filter(function (Brand $brand, string $mapKey) use ($key) {
            $nameKey = $this->norm($brand->name);

            return $mapKey === $nameKey && (str_contains($nameKey, $key) || str_contains($key, $nameKey));
        });

        return $hits->count() === 1 ? $hits->first() : null;
    }

    /** Summary / blank rows from Excel master sheets (no sale fields filled). */
    private function rowLacksSaleData(array $row): bool
    {
        return trim($row['client_name'] ?? '') === ''
            && trim($row['title'] ?? '') === ''
            && trim($row['brand'] ?? '') === '';
    }

    /** @return Collection<string, User> */
    private function userLookup(User $actor): Collection
    {
        // Broader than sales dropdown — match AM/PM/agents by name or email.
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
            // First name only (e.g. sheet says "Manu")
            $first = Str::of($user->name)->trim()->before(' ')->toString();
            if ($first !== '' && ! $map->has($this->norm($first))) {
                $map[$this->norm($first)] = $user;
            }
        }

        return $map;
    }

    private function canAssignOtherAgents(User $actor): bool
    {
        if ($actor->hasRole([Role::ADMIN, Role::MANAGER])) {
            return true;
        }
        if (Team::headedBy($actor->id)->exists()) {
            return true;
        }
        if (\App\Models\SubTeamHead::where('user_id', $actor->id)->exists()) {
            return true;
        }

        return false;
    }

    private function resolveUser(string $raw, Collection $users): ?User
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        return $users->get($this->norm($raw));
    }

    private function parseSaleType(string $raw): ?string
    {
        $v = Str::lower(trim($raw));
        if (in_array($v, ['front', 'f'], true)) {
            return Sale::TYPE_FRONT;
        }
        if (in_array($v, ['upsell', 'up-sell', 'up sell', 'u'], true)) {
            return Sale::TYPE_UPSELL;
        }

        return null;
    }

    private function parseServiceCategory(string $raw): ?string
    {
        $v = Str::lower(trim($raw));
        if ($v === '') {
            return null;
        }
        if (in_array($v, ['ebook', 'e-book', 'e book', 'book'], true)) {
            return Sale::CATEGORY_EBOOK;
        }
        if (in_array($v, ['design', 'designing', 'graphics', 'graphic'], true)) {
            return Sale::CATEGORY_DESIGN;
        }

        return null;
    }

    private function parseStatus(string $raw): ?string
    {
        $v = Str::lower(trim($raw));
        if ($v === '') {
            return 'completed';
        }
        if (in_array($v, ['pending', 'awaiting', 'awaiting approval'], true)) {
            return 'pending';
        }
        if (in_array($v, ['completed', 'complete', 'paid', 'done', 'closed', 'won'], true)) {
            return 'completed';
        }
        if (in_array($v, ['cancelled', 'canceled', 'cancel'], true)) {
            return 'cancelled';
        }
        if (in_array($v, ['refunded', 'refund'], true)) {
            return Sale::STATUS_REFUNDED;
        }
        if (in_array($v, ['chargeback', 'charge back', 'disputed', 'dispute'], true)) {
            return Sale::STATUS_CHARGEBACK;
        }

        return null;
    }

    private function parseYesNo(string $raw): bool
    {
        $v = Str::lower(trim($raw));

        return in_array($v, ['1', 'y', 'yes', 'true', 'ppc', 'paid'], true);
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

        // Prefer US MM/DD/YYYY (sales sheets) before DD/MM/YYYY.
        foreach (['Y-m-d', 'm/d/Y', 'n/j/Y', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $raw);
                if ($dt !== false) {
                    return $dt->format('Y-m-d');
                }
            } catch (\Throwable) {
                // try next
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
