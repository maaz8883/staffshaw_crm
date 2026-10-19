<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\UserActivityLog;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ActivityLogger;
use App\Support\AuthScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    public function index(): View
    {
        return view('admin.invoices.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = $this->baseQuery();

        if ($request->filled('status')) {
            $query->where('invoices.status', $request->status);
        }

        return DataTables::eloquent($query)
            ->editColumn('invoice_number', fn (Invoice $invoice) => e($invoice->invoice_number))
            ->editColumn('amount', fn (Invoice $invoice) => '$' . number_format($invoice->amount, 2))
            ->editColumn('sale_balance', function (Invoice $invoice) {
                if ($invoice->sale_id) {
                    return '<span class="text-muted">—</span>';
                }

                return '$' . number_format($invoice->amountDue(), 2);
            })
            ->editColumn('issued_at', fn (Invoice $invoice) => $invoice->issued_at->format('d M Y'))
            ->addColumn('sale_link', function (Invoice $invoice) {
                $title = e($invoice->title);
                if (! $invoice->sale_id) {
                    return $title !== '' ? $title : '—';
                }
                $url = route('admin.sales.show', $invoice->sale_id);

                return '<a href="' . $url . '">#' . $invoice->sale_id . ' — ' . $title . '</a>';
            })
            ->addColumn('status_badge', function (Invoice $invoice) {
                return '<span class="badge bg-' . $invoice->statusColor() . '">' . e($invoice->statusLabel()) . '</span>';
            })
            ->addColumn('actions', function (Invoice $invoice) {
                $showUrl = route('admin.invoices.show', $invoice);
                $html = '<a href="' . $showUrl . '" class="btn btn-sm btn-outline-info">View</a>';

                if ($invoice->isStandalone() && $invoice->isDraft() && ! $invoice->isVoid()) {
                    $html .= ' <a href="' . route('admin.invoices.edit', $invoice) . '" class="btn btn-sm btn-outline-primary">Edit</a>';
                }

                if ($this->canVoid() && ! $invoice->isVoid()) {
                    $voidUrl = route('admin.invoices.void', $invoice);
                    $csrf = csrf_field();
                    $html .= '
                    <form action="' . $voidUrl . '" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Void this invoice?">
                        ' . $csrf . '
                        <button type="submit" class="btn btn-sm btn-outline-danger">Void</button>
                    </form>';
                }

                return $html;
            })
            ->rawColumns(['sale_link', 'status_badge', 'actions', 'sale_balance'])
            ->toJson();
    }

    public function create(): View
    {
        $clients = ClientController::forDropdown();
        $brands = AuthScope::brandsForDropdown();

        return view('admin.invoices.create', compact('clients', 'brands'));
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorizeDraftEdit($invoice);
        $invoice->load('items');
        $clients = ClientController::forDropdown();
        $brands = AuthScope::brandsForDropdown();

        return view('admin.invoices.create', compact('clients', 'brands', 'invoice'));
    }

    public function storeStandalone(Request $request): RedirectResponse
    {
        $validated = $this->validateStandalone($request);
        $client = $this->findAccessibleClient((int) $validated['client_id']);
        [$lines, $tax, $discount, $adjustment, $total] = $this->buildStandaloneTotals($validated);

        if ($lines === []) {
            return back()->withInput()->with('error', 'Add at least one invoice item.');
        }

        $draft = $request->boolean('save_as_draft');

        $invoice = DB::transaction(function () use ($validated, $client, $lines, $tax, $discount, $adjustment, $total, $draft) {
            $invoice = Invoice::query()->create($this->standalonePayload(
                $validated, $client, $lines, $tax, $discount, $adjustment, $total,
                $draft ? Invoice::STATUS_DRAFT : Invoice::STATUS_ISSUED
            ));

            foreach ($lines as $line) {
                $invoice->items()->create($line);
            }

            return $invoice;
        });

        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_INVOICE_CREATED,
            'Created '.($draft ? 'draft ' : '')."invoice {$invoice->invoice_number} for {$invoice->client_name} (\$".number_format((float) $invoice->amount, 2).')',
            ['invoice_id' => $invoice->id, 'amount' => (float) $invoice->amount]);

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Invoice ' . $invoice->invoice_number . ' created.');
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeDraftEdit($invoice);

        $validated = $this->validateStandalone($request);
        $client = $this->findAccessibleClient((int) $validated['client_id']);
        [$lines, $tax, $discount, $adjustment, $total] = $this->buildStandaloneTotals($validated);

        if ($lines === []) {
            return back()->withInput()->with('error', 'Add at least one invoice item.');
        }

        $issue = $request->boolean('issue') || ! $request->boolean('save_as_draft');
        $status = $issue ? Invoice::STATUS_ISSUED : Invoice::STATUS_DRAFT;

        DB::transaction(function () use ($invoice, $validated, $client, $lines, $tax, $discount, $adjustment, $total, $status) {
            $invoice->update($this->standalonePayload(
                $validated, $client, $lines, $tax, $discount, $adjustment, $total, $status, false
            ));
            $invoice->items()->delete();
            foreach ($lines as $line) {
                $invoice->items()->create($line);
            }
        });

        $invoice->refresh();
        ActivityLogger::log(Auth::user(), $status === Invoice::STATUS_ISSUED ? UserActivityLog::TYPE_INVOICE_ISSUED : UserActivityLog::TYPE_INVOICE_UPDATED,
            ($status === Invoice::STATUS_ISSUED ? 'Updated and issued' : 'Updated draft')." invoice {$invoice->invoice_number} (\$".number_format((float) $invoice->amount, 2).')',
            ['invoice_id' => $invoice->id, 'amount' => (float) $invoice->amount]);

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Invoice ' . $invoice->invoice_number . ' updated.');
    }

    public function issue(Invoice $invoice): RedirectResponse
    {
        $this->authorizeDraftEdit($invoice);

        if ($invoice->items()->count() === 0) {
            return back()->with('error', 'Add at least one item before issuing.');
        }

        $invoice->update(['status' => Invoice::STATUS_ISSUED]);
        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_INVOICE_ISSUED,
            "Issued invoice {$invoice->invoice_number} (\$".number_format((float) $invoice->amount, 2).')',
            ['invoice_id' => $invoice->id, 'amount' => (float) $invoice->amount]);

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Invoice ' . $invoice->invoice_number . ' issued.');
    }

    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->sale_id || $invoice->isVoid() || $invoice->status === Invoice::STATUS_DRAFT) {
            abort(403);
        }
        $this->authorizeStandalone($invoice);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'paid_at' => 'required|date',
            'payment_mode' => 'nullable|string|max:100',
            'note' => 'nullable|string',
        ]);

        $due = $invoice->amountDue();
        if ((float) $validated['amount'] > $due + 0.001) {
            return back()->with('error', 'Payment cannot exceed the open amount of $' . number_format($due, 2) . '.');
        }

        DB::transaction(function () use ($invoice, $validated) {
            InvoicePayment::query()->create([
                'invoice_id' => $invoice->id,
                'amount' => $validated['amount'],
                'paid_at' => $validated['paid_at'],
                'payment_mode' => $validated['payment_mode'] ?? null,
                'note' => $validated['note'] ?? null,
                'created_by' => Auth::id(),
            ]);
            $paid = (float) $invoice->payments()->sum('amount');
            $invoice->update([
                'sale_received' => $paid,
                'sale_balance' => max(0, round((float) $invoice->amount - $paid, 2)),
            ]);
        });

        ActivityLogger::log(Auth::user(), UserActivityLog::TYPE_PAYMENT_RECORDED,
            'Recorded payment of $'.number_format((float) $validated['amount'], 2)." on invoice {$invoice->invoice_number}"
                .(! empty($validated['payment_mode']) ? ' ('.$validated['payment_mode'].')' : ''),
            ['invoice_id' => $invoice->id, 'amount' => (float) $validated['amount']]);

        return back()->with('success', 'Payment recorded.');
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['sale', 'brand', 'creator', 'items', 'payments', 'client']);
        if ($invoice->sale) {
            $this->authorizeSaleAccess($invoice->sale);
        } else {
            $this->authorizeStandalone($invoice);
        }

        $canVoid = $this->canVoid() && ! $invoice->isVoid();
        $canEditDraft = $invoice->isStandalone() && $invoice->isDraft() && ! $invoice->isVoid()
            && $this->userCanAccessStandalone($invoice);

        return view('admin.invoices.show', compact('invoice', 'canVoid', 'canEditDraft'));
    }

    public function downloadPdf(Invoice $invoice): Response
    {
        $invoice->load(['sale', 'brand', 'creator', 'items', 'payments']);
        if ($invoice->sale) {
            $this->authorizeSaleAccess($invoice->sale);
        } else {
            $this->authorizeStandalone($invoice);
        }

        $filename = Str::slug($invoice->invoice_number, '-') . '.pdf';

        return Pdf::loadView('admin.invoices.pdf', compact('invoice'))
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    public function store(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorizeSaleAccess($sale);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $invoice = $this->invoiceService->createFromSale(
            $sale,
            (float) $validated['amount'],
            Auth::user()
        );

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('success', 'Invoice ' . $invoice->invoice_number . ' generated successfully.');
    }

    public function void(Invoice $invoice): RedirectResponse
    {
        if (! $this->canVoid()) {
            abort(403);
        }

        $invoice->load('sale');
        if ($invoice->sale) {
            $this->authorizeSaleAccess($invoice->sale);
        } else {
            $this->authorizeStandalone($invoice);
        }

        $this->invoiceService->void($invoice, Auth::user());

        if ($invoice->sale_id) {
            return redirect()
                ->route('admin.sales.show', $invoice->sale_id)
                ->with('success', 'Invoice ' . $invoice->invoice_number . ' has been voided.');
        }

        return redirect()
            ->route('admin.invoices.index')
            ->with('success', 'Invoice ' . $invoice->invoice_number . ' has been voided.');
    }

    private function authorizeStandalone(Invoice $invoice): void
    {
        if (! $this->userCanAccessStandalone($invoice)) {
            abort(403);
        }
    }

    private function userCanAccessStandalone(Invoice $invoice): bool
    {
        $user = Auth::user();

        if (AuthScope::seesAllMoney()) {
            return true;
        }

        return (int) $invoice->created_by === (int) $user->id;
    }

    private function authorizeDraftEdit(Invoice $invoice): void
    {
        if (! $invoice->isStandalone() || ! $invoice->isDraft() || $invoice->isVoid()) {
            abort(403);
        }

        $this->authorizeStandalone($invoice);
    }

    private function validateStandalone(Request $request): array
    {
        return $request->validate([
            'client_id' => 'required|exists:clients,id',
            'brand_id' => AuthScope::brandIdRule(required: true),
            'issued_at' => 'required|date',
            'due_date' => 'nullable|date',
            'currency' => 'nullable|string|max:10',
            'discount' => 'nullable|numeric|min:0',
            'adjustment' => 'nullable|numeric',
            'client_note' => 'nullable|string',
            'terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.long_description' => 'nullable|string',
            'items.*.qty' => 'nullable|numeric|min:0',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: float, 2: float, 3: float, 4: float} */
    private function buildStandaloneTotals(array $validated): array
    {
        $subtotal = 0.0;
        $tax = 0.0;
        $lines = [];

        foreach ($validated['items'] as $item) {
            if (trim((string) ($item['description'] ?? '')) === '') {
                continue;
            }
            $qty = (float) ($item['qty'] ?? 1);
            $rate = (float) ($item['rate'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $taxRate = (float) ($item['tax_rate'] ?? 0);
            $line = round($qty * $rate, 2);
            $lineTax = round($line * ($taxRate / 100), 2);
            $subtotal += $line;
            $tax += $lineTax;
            $lines[] = [
                'description' => $item['description'],
                'long_description' => $item['long_description'] ?? null,
                'qty' => $qty,
                'rate' => $rate,
                'tax_rate' => $taxRate,
                'amount' => $line + $lineTax,
            ];
        }

        $discount = (float) ($validated['discount'] ?? 0);
        $adjustment = (float) ($validated['adjustment'] ?? 0);
        $total = max(0, round($subtotal + $tax - $discount + $adjustment, 2));

        return [$lines, $tax, $discount, $adjustment, $total];
    }

    private function standalonePayload(
        array $validated,
        Client $client,
        array $lines,
        float $tax,
        float $discount,
        float $adjustment,
        float $total,
        string $status,
        bool $includeNumber = true
    ): array {
        $brand = Brand::query()->find((int) $validated['brand_id']);

        $payload = [
            'sale_id' => null,
            'client_id' => $client->id,
            'issued_at' => $validated['issued_at'],
            'due_date' => $validated['due_date'] ?? null,
            'amount' => $total,
            'discount' => $discount,
            'adjustment' => $adjustment,
            'tax_total' => $tax,
            'currency' => ($validated['currency'] ?? null) ?: 'USD',
            'client_name' => $client->name,
            'client_email' => $client->email,
            'client_phone' => $client->phone,
            'title' => $lines[0]['description'],
            'notes' => $validated['notes'] ?? null,
            'client_note' => $validated['client_note'] ?? null,
            'terms' => $validated['terms'] ?? null,
            'brand_id' => $brand?->id,
            'brand_name' => $brand?->name,
            'agent_name' => Auth::user()->name,
            'team_name' => $client->team?->name,
            'company_name' => $client->company_name,
            'sale_total' => $total,
            'sale_received' => 0,
            'sale_balance' => $total,
            'status' => $status,
        ];

        if ($includeNumber) {
            $payload['invoice_number'] = $this->invoiceService->generateNumber();
            $payload['created_by'] = Auth::id();
        }

        return $payload;
    }

    private function findAccessibleClient(int $clientId): Client
    {
        $allowed = ClientController::forDropdown()->pluck('id')->map(fn ($id) => (int) $id);
        if (! $allowed->contains($clientId)) {
            abort(403, 'You cannot invoice this client.');
        }

        return Client::query()->with(['brand', 'team'])->findOrFail($clientId);
    }

    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Invoice::query()->with(['sale', 'payments']);
        $user = Auth::user();

        if (AuthScope::seesAllMoney()) {
            return $query;
        }

        if ($user->hasRole(Role::PROJECT_MANAGER)) {
            $joinedTeamIds = $user->approvedTeamIds();

            return $query->where(function ($q) use ($joinedTeamIds, $user) {
                $q->whereHas('sale', fn ($saleQuery) => $saleQuery->whereIn('team_id', $joinedTeamIds))
                    ->orWhere('invoices.created_by', $user->id);
            });
        }

        return $query->where(function ($outer) use ($user) {
            $outer->where('invoices.created_by', $user->id)
                ->orWhereHas('sale', function ($saleQuery) use ($user) {
            if (Team::where('team_head_id', $user->id)->exists()) {
                $teamIds = Team::where('team_head_id', $user->id)->pluck('id');
                $saleQuery->whereIn('team_id', $teamIds);

                return;
            }

            $subTeamHead = \App\Models\SubTeamHead::where('user_id', $user->id)->first();

            if ($subTeamHead) {
                $memberIds = User::where('sub_team_head_id', $subTeamHead->id)->pluck('id');
                $memberIds->push($user->id);
                $saleQuery->whereIn('user_id', $memberIds);

                return;
            }

            $saleQuery->where('user_id', $user->id);
                });
        });
    }

    private function authorizeSaleAccess(Sale $sale): void
    {
        $user = Auth::user();

        if (AuthScope::seesAllMoney()) {
            return;
        }

        // Requirement 7.4 — Project Manager: only sales/invoices from their joined teams
        if ($user->hasRole(Role::PROJECT_MANAGER)) {
            if ($sale->team_id && $user->approvedTeamIds()->contains((int) $sale->team_id)) {
                return;
            }
            abort(403);
        }

        if ((int) $sale->user_id === (int) $user->id) {
            return;
        }

        if ($sale->team_id && Team::where('team_head_id', $user->id)->where('id', $sale->team_id)->exists()) {
            return;
        }

        $subTeamHead = \App\Models\SubTeamHead::where('user_id', $user->id)->first();

        if ($subTeamHead) {
            $saleUser = User::find($sale->user_id);

            if ($saleUser && $saleUser->sub_team_head_id === $subTeamHead->id) {
                return;
            }
        }

        abort(403);
    }

    private function canVoid(): bool
    {
        return AuthScope::seesAllMoney(); // Admin, Manager, Finance
    }
}
