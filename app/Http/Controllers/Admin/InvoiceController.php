<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
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
            ->editColumn('sale_balance', fn (Invoice $invoice) => '$' . number_format($invoice->sale_balance, 2))
            ->editColumn('issued_at', fn (Invoice $invoice) => $invoice->issued_at->format('d M Y'))
            ->addColumn('sale_link', function (Invoice $invoice) {
                $title = e($invoice->title);
                $url = route('admin.sales.show', $invoice->sale_id);

                return '<a href="' . $url . '">#' . $invoice->sale_id . ' — ' . $title . '</a>';
            })
            ->addColumn('status_badge', function (Invoice $invoice) {
                $class = $invoice->isVoid() ? 'secondary' : 'success';

                return '<span class="badge bg-' . $class . '">' . e($invoice->statusLabel()) . '</span>';
            })
            ->addColumn('actions', function (Invoice $invoice) {
                $showUrl = route('admin.invoices.show', $invoice);
                $html = '<a href="' . $showUrl . '" class="btn btn-sm btn-outline-info">View</a>';

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
            ->rawColumns(['sale_link', 'status_badge', 'actions'])
            ->toJson();
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['sale', 'brand', 'creator']);
        $this->authorizeSaleAccess($invoice->sale);

        $canVoid = $this->canVoid() && ! $invoice->isVoid();

        return view('admin.invoices.show', compact('invoice', 'canVoid'));
    }

    public function downloadPdf(Invoice $invoice): Response
    {
        $invoice->load(['sale', 'brand', 'creator']);
        $this->authorizeSaleAccess($invoice->sale);

        $filename = Str::slug($invoice->invoice_number, '-') . '.pdf';

        return app('dompdf.wrapper')
            ->loadView('admin.invoices.pdf', compact('invoice'))
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
        $this->authorizeSaleAccess($invoice->sale);

        $this->invoiceService->void($invoice, Auth::user());

        return redirect()
            ->route('admin.sales.show', $invoice->sale_id)
            ->with('success', 'Invoice ' . $invoice->invoice_number . ' has been voided.');
    }

    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Invoice::query()->with(['sale']);
        $user = Auth::user();

        if ($user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return $query;
        }

        return $query->whereHas('sale', function ($saleQuery) use ($user) {
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
    }

    private function authorizeSaleAccess(Sale $sale): void
    {
        $user = Auth::user();

        if ($user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return;
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
        return Auth::user()->hasRole([Role::ADMIN, Role::MANAGER]);
    }
}
