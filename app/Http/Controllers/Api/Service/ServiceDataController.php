<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\User;
use App\Services\CompanyOs\ServiceSummary;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

/**
 * Read-only data for the CompanyOS hub. Guarded by the service.key middleware.
 * Responses: { "data": ..., "meta": {...} }; errors: { "error": { code, message, details } }.
 */
class ServiceDataController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    public function summary(Request $request, ServiceSummary $summary): JsonResponse
    {
        $errors = $this->validateQuery($request, $this->rangeRules());
        if ($errors) {
            return $errors;
        }

        [$from, $to] = $this->range($request);
        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            return $this->error('VALIDATION_FAILED', 'Date range may not exceed '.self::MAX_RANGE_DAYS.' days.', 422);
        }

        return response()->json([
            'data' => $summary->build($from, $to),
            'meta' => ['generated_at' => now()->toIso8601String()],
        ]);
    }

    public function sales(Request $request): JsonResponse
    {
        $errors = $this->validateQuery($request, $this->rangeRules() + $this->pageRules() + [
            'approval_status' => 'nullable|in:'.implode(',', [Sale::APPROVAL_PENDING, Sale::APPROVAL_APPROVED, Sale::APPROVAL_REJECTED]),
            'status' => 'nullable|in:'.implode(',', [...Sale::STATUSES, Sale::STATUS_REFUNDED]),
            'team_id' => 'nullable|integer',
        ]);
        if ($errors) {
            return $errors;
        }

        [$from, $to] = $this->range($request);
        $query = Sale::query()
            ->with(['team:id,name', 'brand:id,name'])
            ->where('is_draft', false)
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->query('approval_status'), fn ($q, $v) => $q->where('approval_status', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('team_id'), fn ($q, $v) => $q->where('team_id', (int) $v))
            ->orderByDesc('sale_date')->orderByDesc('id');

        return $this->paginated($query->paginate($this->perPage($request)), fn (Sale $s) => [
            'id' => $s->id,
            'title' => $s->title,
            'client_id' => $s->client_id,
            'client_name' => $s->client_name,
            'amount' => (float) $s->amount,
            'received_amount' => (float) $s->received_amount,
            'currency' => 'USD',
            'sale_date' => optional($s->sale_date)->toDateString(),
            'sale_type' => $s->sale_type,
            'status' => $s->status,
            'approval_status' => $s->approval_status,
            'is_refunded' => (bool) $s->is_refunded,
            'is_chargeback' => (bool) $s->is_chargeback,
            'is_ppc' => (bool) $s->is_ppc,
            'agent_id' => $s->user_id,
            'agent_name' => $s->sales_agent_name,
            'team' => $s->team ? ['id' => $s->team->id, 'name' => $s->team->name] : null,
            'brand' => $s->brand ? ['id' => $s->brand->id, 'name' => $s->brand->name] : null,
            'created_at' => optional($s->created_at)->toIso8601String(),
        ]);
    }

    public function leads(Request $request): JsonResponse
    {
        $errors = $this->validateQuery($request, $this->rangeRules() + $this->pageRules() + [
            'status' => 'nullable|in:'.implode(',', Lead::STATUSES),
            'source' => 'nullable|string|max:50',
            'follow_up_due' => 'nullable|boolean',
        ]);
        if ($errors) {
            return $errors;
        }

        $query = Lead::query()->with('team:id,name')->orderByDesc('id');

        if ($request->boolean('follow_up_due')) {
            $query->whereNotNull('follow_up_at')
                ->whereDate('follow_up_at', '<=', now()->toDateString())
                ->whereNotIn('status', ['customer', 'lost']);
        } elseif ($request->filled('from') || $request->filled('to')) {
            [$from, $to] = $this->range($request);
            $query->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        }

        $query->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('source'), fn ($q, $v) => $q->where('source', $v));

        return $this->paginated($query->paginate($this->perPage($request)), fn (Lead $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'company' => $l->company,
            'source' => $l->source,
            'status' => $l->status,
            'lead_value' => $l->lead_value !== null ? (float) $l->lead_value : null,
            'assigned_to' => $l->assigned_to,
            'team' => $l->team ? ['id' => $l->team->id, 'name' => $l->team->name] : null,
            'follow_up_at' => optional($l->follow_up_at)->toDateString(),
            'last_contact_at' => optional($l->last_contact_at)->toDateString(),
            'created_at' => optional($l->created_at)->toIso8601String(),
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $errors = $this->validateQuery($request, $this->pageRules() + [
            'status' => 'nullable|in:unpaid,overdue,all',
        ]);
        if ($errors) {
            return $errors;
        }

        $status = $request->query('status', 'unpaid');
        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->query('page', 1));

        $query = Invoice::query()->with('payments')->orderByDesc('id');

        if ($status === 'all') {
            $paginator = $query->where('status', '!=', Invoice::STATUS_VOID)->paginate($perPage);
        } else {
            // Only standalone invoices carry an open balance (sale-linked rows are receipts).
            $rows = $query->whereNull('sale_id')->where('status', Invoice::STATUS_ISSUED)->get()
                ->filter(fn (Invoice $i) => $i->amountDue() > 0)
                ->when($status === 'overdue', fn ($c) => $c->filter(fn (Invoice $i) => $i->due_date && $i->due_date->isPast()))
                ->values();
            $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);
        }

        return $this->paginated($paginator, fn (Invoice $i) => [
            'id' => $i->id,
            'invoice_number' => $i->invoice_number,
            'sale_id' => $i->sale_id,
            'client_id' => $i->client_id,
            'client_name' => $i->client_name,
            'amount' => (float) $i->amount,
            'amount_paid' => round($i->amountPaid(), 2),
            'amount_due' => round($i->amountDue(), 2),
            'currency' => $i->currency ?: 'USD',
            'status' => $i->status,
            'issued_at' => optional($i->issued_at)->toDateString(),
            'due_date' => optional($i->due_date)->toDateString(),
            'is_overdue' => $i->amountDue() > 0 && $i->due_date && $i->due_date->isPast(),
            'brand_name' => $i->brand_name,
            'team_name' => $i->team_name,
        ]);
    }

    public function expenses(Request $request): JsonResponse
    {
        $errors = $this->validateQuery($request, $this->rangeRules() + $this->pageRules() + [
            'category' => 'nullable|string|max:100',
        ]);
        if ($errors) {
            return $errors;
        }

        [$from, $to] = $this->range($request);
        $query = Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->orderByDesc('expense_date')->orderByDesc('id');

        return $this->paginated($query->paginate($this->perPage($request)), fn (Expense $e) => [
            'id' => $e->id,
            'category' => $e->category,
            'amount' => (float) $e->amount,
            'tax_amount' => $e->tax_amount !== null ? (float) $e->tax_amount : null,
            'currency' => 'PKR',
            'expense_date' => optional($e->expense_date)->toDateString(),
            'note' => $e->note,
            'team_id' => $e->team_id,
            'client_id' => $e->client_id,
            'is_billable' => (bool) $e->is_billable,
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $errors = $this->validateQuery($request, $this->pageRules());
        if ($errors) {
            return $errors;
        }

        $query = User::query()->with(['role:id,name', 'team:id,name'])->orderBy('id');

        return $this->paginated($query->paginate($this->perPage($request)), fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role?->name,
            'team' => $u->team ? ['id' => $u->team->id, 'name' => $u->team->name] : null,
            'account_status' => $u->account_status,
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function rangeRules(): array
    {
        return [
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ];
    }

    private function pageRules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    /** Defaults to the current calendar month. */
    private function range(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::createFromFormat('Y-m-d', $request->query('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::createFromFormat('Y-m-d', $request->query('to'))->startOfDay()
            : now()->endOfMonth()->startOfDay();

        return [$from, $to];
    }

    private function perPage(Request $request): int
    {
        return (int) $request->query('per_page', 25);
    }

    private function validateQuery(Request $request, array $rules): ?JsonResponse
    {
        $validator = Validator::make($request->query(), $rules);

        return $validator->fails()
            ? $this->error('VALIDATION_FAILED', 'Invalid query parameters.', 422, $validator->errors()->toArray())
            : null;
    }

    private function paginated(LengthAwarePaginator $paginator, callable $map): JsonResponse
    {
        return response()->json([
            'data' => collect($paginator->items())->map($map)->values(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    private function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json(['error' => array_filter([
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ])], $status);
    }
}
