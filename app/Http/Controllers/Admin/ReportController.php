<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
use App\Models\User;
use App\Support\AuthScope;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    private function isTeamHead(): bool
    {
        return Team::where('team_head_id', Auth::id())->exists();
    }

    private function isAgent(): bool
    {
        return Auth::user()->hasRole('Agent') && ! $this->isTeamHead();
    }

    private function canViewOrgReports(): bool
    {
        return AuthScope::seesAllMoney(); // Admin, Manager, Finance
    }

    /** Admin / Manager / Team Lead only — regular agents use Sales Performance. */
    private function authorizeReportsAccess(): void
    {
        if ($this->canViewOrgReports() || $this->isTeamHead()) {
            return;
        }

        abort(403, 'Reports are available to admins, managers, and team leads.');
    }

    /** @return Collection<int, int> */
    private function headedTeamIds(): Collection
    {
        return Team::where('team_head_id', Auth::id())->pluck('id');
    }

    /** Same visibility rules as {@see SaleController::baseQuery()}. */
    private function salesBaseQuery(): Builder
    {
        $user = Auth::user();
        $query = Sale::query()->select('sales.*');

        if (AuthScope::seesAllMoney()) {
            // all
        } elseif ($this->isTeamHead()) {
            $query->whereIn('team_id', $this->headedTeamIds());
        } else {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    private function applySaleFilters(Builder $query, Request $request): void
    {
        if ($request->filled('company_id') && $this->canViewOrgReports()) {
            $query->where('company_id', $request->integer('company_id'));
        }
        if ($request->filled('team_id')) {
            $teamId = $request->integer('team_id');
            if ($this->isTeamHead() && ! $this->canViewOrgReports() && ! $this->headedTeamIds()->contains($teamId)) {
                abort(403, 'You can only view reports for your own team.');
            }
            $query->where('team_id', $teamId);
        }
        if ($request->filled('user_id') && ! $this->isAgent()) {
            $query->where('user_id', $request->integer('user_id'));
        }
    }

    private function validateReportRequest(Request $request): void
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => [
                'nullable',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if (! $value || ! $request->filled('date_from')) {
                        return;
                    }
                    if (Carbon::parse($value)->startOfDay()->lt(Carbon::parse($request->date_from)->startOfDay())) {
                        $fail('The end date must be on or after the start date.');
                    }
                },
            ],
            'company_id' => 'nullable|exists:companies,id',
            'team_id' => 'nullable|exists:teams,id',
            'user_id' => 'nullable|exists:users,id',
        ]);
    }

    /**
     * @return array{
     *     dateFrom: Carbon,
     *     dateTo: Carbon,
     *     salesInPeriod: Builder,
     *     approvedInPeriod: Builder,
     *     approvedCompleted: Builder,
     *     teams: Collection,
     *     users: Collection,
     *     companies: Collection,
     *     isAgent: bool,
     *     filters: array
     * }
     */
    private function prepareSalesContext(Request $request): array
    {
        $this->authorizeReportsAccess();
        $this->validateReportRequest($request);

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfMonth();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfMonth();

        $salesQ = $this->salesBaseQuery();
        $this->applySaleFilters($salesQ, $request);

        $salesInPeriod = (clone $salesQ)->whereBetween('sale_date', [
            $dateFrom->toDateString(),
            $dateTo->toDateString(),
        ]);

        $approvedInPeriod = (clone $salesInPeriod)->where('approval_status', Sale::APPROVAL_APPROVED);
        $approvedCompleted = (clone $approvedInPeriod)->where('status', 'completed');

        $teams = $this->isTeamHead() && ! $this->canViewOrgReports()
            ? Team::query()->whereIn('id', $this->headedTeamIds())->orderBy('name')->get()
            : AuthScope::teamsForDropdown();

        return [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'salesInPeriod' => $salesInPeriod,
            'approvedInPeriod' => $approvedInPeriod,
            'approvedCompleted' => $approvedCompleted,
            'teams' => $teams,
            'users' => $this->isAgent() ? collect() : AuthScope::usersForDropdown(),
            'companies' => $this->canViewOrgReports() ? Company::query()->orderBy('name')->get() : collect(),
            'isAgent' => $this->isAgent(),
            'isTeamHead' => $this->isTeamHead(),
            'canViewOrgReports' => $this->canViewOrgReports(),
            'filters' => $request->only(['date_from', 'date_to', 'company_id', 'team_id', 'user_id']),
        ];
    }

    public function index(): View
    {
        $this->authorizeReportsAccess();

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        $approvedSales = $this->salesBaseQuery()
            ->where('approval_status', Sale::APPROVAL_APPROVED)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to]);

        $leadQ = Lead::query()->whereNotIn('status', ['customer', 'lost']);
        if ($this->isAgent()) {
            $leadQ->where('assigned_to', Auth::id());
        } elseif ($this->isTeamHead() && ! $this->canViewOrgReports()) {
            $leadQ->whereIn('team_id', $this->headedTeamIds());
        }

        $openAr = 0.0;
        if ($this->canViewOrgReports()) {
            $openAr = (float) Invoice::query()
                ->whereNull('sale_id')
                ->where('status', Invoice::STATUS_ISSUED)
                ->where('sale_balance', '>', 0)
                ->sum('sale_balance');
        } else {
            $saleIds = $this->salesBaseQuery()->pluck('id');
            $openAr = (float) Sale::query()
                ->whereIn('id', $saleIds->isEmpty() ? [0] : $saleIds)
                ->where('approval_status', Sale::APPROVAL_APPROVED)
                ->whereRaw('(amount - received_amount) > 0')
                ->selectRaw('COALESCE(SUM(amount - received_amount), 0) as outstanding')
                ->value('outstanding');
        }

        return view('admin.reports.hub', [
            'monthLabel' => now()->format('F Y'),
            'isTeamHead' => $this->isTeamHead(),
            'canViewOrgReports' => $this->canViewOrgReports(),
            'kpis' => [
                'revenue' => (float) (clone $approvedSales)->sum('amount'),
                'received' => (float) (clone $approvedSales)->sum('received_amount'),
                'open_ar' => (float) $openAr,
                'active_leads' => (clone $leadQ)->count(),
            ],
        ]);
    }

    public function company(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $this->authorizeReportsAccess();

        if (! $this->canViewOrgReports() || CompanyContext::single()) {
            return redirect()->route('admin.reports.index');
        }

        $ctx = $this->prepareSalesContext($request);
        $salesInPeriod = $ctx['salesInPeriod'];

        $companyQuery = Company::query()->orderBy('name')->withCount(['teams', 'users']);
        if (AuthScope::isAgent()) {
            $companyQuery->where('id', Auth::user()->company_id);
        }
        $companies = $companyQuery->get();

        $companyRows = $companies->map(function (Company $company) use ($salesInPeriod) {
            $sq = (clone $salesInPeriod)->where('company_id', $company->id);
            $approved = (clone $sq)->where('approval_status', Sale::APPROVAL_APPROVED);

            return [
                'company' => $company,
                'revenue' => (float) (clone $approved)->where('status', 'completed')->sum('amount'),
                'completed_count' => (clone $approved)->where('status', 'completed')->count(),
                'pending_approval' => (clone $sq)->where('approval_status', Sale::APPROVAL_PENDING)->count(),
                'total_in_period' => (clone $sq)->count(),
            ];
        });

        return view('admin.reports.company', array_merge($ctx, [
            'companyRows' => $companyRows,
        ]));
    }

    public function team(Request $request): View
    {
        $ctx = $this->prepareSalesContext($request);
        $approvedCompleted = $ctx['approvedCompleted'];

        $teams = $ctx['teams'];

        $revenueByTeam = $teams->map(function (Team $team) use ($approvedCompleted) {
            $base = (clone $approvedCompleted)->where('team_id', $team->id);

            return [
                'team' => $team,
                'revenue' => (float) (clone $base)->sum('amount'),
                'count' => (clone $base)->count(),
            ];
        })->values();

        $teamLabels = $revenueByTeam->map(fn ($r) => $r['team']->name)->values()->all();
        $teamValues = $revenueByTeam->map(fn ($r) => round($r['revenue'], 2))->values()->all();

        return view('admin.reports.team', array_merge($ctx, [
            'revenueByTeam' => $revenueByTeam,
            'teamChartLabels' => $teamLabels,
            'teamChartValues' => $teamValues,
        ]));
    }

    public function sales(Request $request): View
    {
        $ctx = $this->prepareSalesContext($request);
        $salesInPeriod = $ctx['salesInPeriod'];
        $approvedInPeriod = $ctx['approvedInPeriod'];
        $approvedCompleted = $ctx['approvedCompleted'];
        $dateFrom = $ctx['dateFrom'];
        $dateTo = $ctx['dateTo'];

        $revenue = (float) (clone $approvedCompleted)->sum('amount');
        $received = (float) (clone $approvedCompleted)->sum('received_amount');
        $outstanding = max(0, $revenue - $received);
        $completedCount = (clone $approvedCompleted)->count();
        $pendingApprovalCount = (clone $salesInPeriod)->where('approval_status', Sale::APPROVAL_PENDING)->count();
        $refundedCount = (clone $salesInPeriod)->where(function ($q) {
            $q->where('is_refunded', true)->orWhere('status', Sale::STATUS_REFUNDED);
        })->count();

        $frontRevenue = (float) (clone $approvedCompleted)->where('sale_type', Sale::TYPE_FRONT)->sum('amount');
        $upsellRevenue = (float) (clone $approvedCompleted)->where('sale_type', Sale::TYPE_UPSELL)->sum('amount');

        $salesByStatus = collect(Sale::allStatuses())->mapWithKeys(fn (string $s) => [
            $s => (clone $approvedInPeriod)->where('status', $s)->count(),
        ]);

        $approvalBreakdown = [
            Sale::APPROVAL_APPROVED => (clone $salesInPeriod)->where('approval_status', Sale::APPROVAL_APPROVED)->count(),
            Sale::APPROVAL_PENDING => (clone $salesInPeriod)->where('approval_status', Sale::APPROVAL_PENDING)->count(),
            Sale::APPROVAL_REJECTED => (clone $salesInPeriod)->where('approval_status', Sale::APPROVAL_REJECTED)->count(),
        ];

        $dailyRows = (clone $approvedCompleted)
            ->toBase()
            ->select(DB::raw('DATE(sale_date) as d'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('DATE(sale_date)'))
            ->orderBy('d')
            ->pluck('total', 'd');

        $dailyLabels = [];
        $dailyValues = [];
        foreach (CarbonPeriod::create($dateFrom->toDateString(), $dateTo->toDateString()) as $date) {
            $key = $date->format('Y-m-d');
            $dailyLabels[] = $date->format('M j');
            $dailyValues[] = round((float) ($dailyRows[$key] ?? 0), 2);
        }

        return view('admin.reports.sales', array_merge($ctx, [
            'revenue' => $revenue,
            'received' => $received,
            'outstanding' => $outstanding,
            'completedCount' => $completedCount,
            'pendingApprovalCount' => $pendingApprovalCount,
            'refundedCount' => $refundedCount,
            'frontRevenue' => $frontRevenue,
            'upsellRevenue' => $upsellRevenue,
            'salesByStatus' => $salesByStatus,
            'approvalBreakdown' => $approvalBreakdown,
            'dailyLabels' => $dailyLabels,
            'dailyValues' => $dailyValues,
        ]));
    }

    public function user(Request $request): View
    {
        $ctx = $this->prepareSalesContext($request);
        $approvedCompleted = $ctx['approvedCompleted'];

        $agg = (clone $approvedCompleted)
            ->toBase()
            ->select(DB::raw('user_id'), DB::raw('SUM(amount) as revenue'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $userList = AuthScope::usersForDropdown();
        $userList->load(['team', 'company']);

        $userRows = $userList->map(function (User $u) use ($agg) {
            $a = $agg->get($u->id);

            return [
                'user' => $u,
                'revenue' => $a ? (float) $a->revenue : 0.0,
                'count' => $a ? (int) $a->cnt : 0,
            ];
        })->sortByDesc('revenue')->values();

        return view('admin.reports.user', array_merge($ctx, [
            'userRows' => $userRows,
        ]));
    }

    public function brands(Request $request): View
    {
        $ctx = $this->prepareSalesContext($request);
        $approvedCompleted = $ctx['approvedCompleted'];

        $agg = (clone $approvedCompleted)
            ->toBase()
            ->select('brand_id', DB::raw('SUM(amount) as revenue'), DB::raw('SUM(received_amount) as received'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('brand_id')
            ->get()
            ->keyBy('brand_id');

        $brands = Brand::query()->orderBy('name')->get(['id', 'name']);
        $brandRows = $brands->map(function (Brand $brand) use ($agg) {
            $a = $agg->get($brand->id);

            return [
                'brand' => $brand,
                'revenue' => $a ? (float) $a->revenue : 0.0,
                'received' => $a ? (float) $a->received : 0.0,
                'count' => $a ? (int) $a->cnt : 0,
            ];
        })->sortByDesc('revenue')->values();

        $unassigned = $agg->get(null) ?? $agg->get('');

        return view('admin.reports.brands', array_merge($ctx, [
            'brandRows' => $brandRows,
            'unassignedRevenue' => $unassigned ? (float) $unassigned->revenue : 0.0,
            'unassignedCount' => $unassigned ? (int) $unassigned->cnt : 0,
        ]));
    }

    public function leads(Request $request): View
    {
        abort_if(AuthScope::isFinance(), 403, 'The leads report is for sales roles.');
        $ctx = $this->prepareSalesContext($request);
        $dateFrom = $ctx['dateFrom'];
        $dateTo = $ctx['dateTo'];

        $query = Lead::query()->whereBetween('created_at', [$dateFrom, $dateTo]);
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->integer('team_id'));
        }
        if ($request->filled('user_id') && ! $this->isAgent()) {
            $query->where('assigned_to', $request->integer('user_id'));
        }
        if ($this->isAgent()) {
            $query->where('assigned_to', Auth::id());
        } elseif ($this->isTeamHead() && ! $this->canViewOrgReports()) {
            $query->whereIn('team_id', $this->headedTeamIds());
        }

        $total = (clone $query)->count();
        $converted = (clone $query)->where('status', 'customer')->count();
        $pipelineValue = (float) (clone $query)->whereNotIn('status', ['customer', 'lost'])->sum('lead_value');

        $byStatus = collect(Lead::STATUSES)->mapWithKeys(function (string $status) use ($query) {
            return [$status => (clone $query)->where('status', $status)->count()];
        });

        $bySource = (clone $query)
            ->toBase()
            ->select(DB::raw("COALESCE(NULLIF(source, ''), 'other') as src"), DB::raw('COUNT(*) as cnt'))
            ->groupBy('src')
            ->orderByDesc('cnt')
            ->get();

        return view('admin.reports.leads', array_merge($ctx, [
            'total' => $total,
            'converted' => $converted,
            'conversionRate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0,
            'pipelineValue' => $pipelineValue,
            'byStatus' => $byStatus,
            'bySource' => $bySource,
        ]));
    }

    public function clients(Request $request): View
    {
        $ctx = $this->prepareSalesContext($request);
        $dateFrom = $ctx['dateFrom'];
        $dateTo = $ctx['dateTo'];
        $teamScope = $this->isTeamHead() && ! $this->canViewOrgReports()
            ? $this->headedTeamIds()
            : null;

        $clientQ = Client::query()->whereBetween('created_at', [$dateFrom, $dateTo]);
        if ($teamScope) {
            $clientQ->whereIn('team_id', $teamScope);
        }
        if ($request->filled('team_id')) {
            $teamId = $request->integer('team_id');
            if ($teamScope && ! $teamScope->contains($teamId)) {
                abort(403);
            }
            $clientQ->where('team_id', $teamId);
        }

        $activeQ = Client::query()->where('is_active', true);
        if ($teamScope) {
            $activeQ->whereIn('team_id', $teamScope);
        }

        $topQ = Client::query();
        if ($teamScope) {
            $topQ->whereIn('team_id', $teamScope);
        }

        $topClients = $topQ
            ->withSum(['sales as period_revenue' => function ($q) use ($dateFrom, $dateTo, $teamScope) {
                $q->where('approval_status', Sale::APPROVAL_APPROVED)
                    ->where('status', 'completed')
                    ->whereBetween('sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
                if ($teamScope) {
                    $q->whereIn('team_id', $teamScope);
                }
            }], 'amount')
            ->orderByDesc('period_revenue')
            ->limit(15)
            ->get();

        return view('admin.reports.clients', array_merge($ctx, [
            'newClients' => (clone $clientQ)->count(),
            'activeClients' => $activeQ->count(),
            'topClients' => $topClients,
        ]));
    }

    public function invoices(Request $request): View
    {
        $ctx = $this->prepareSalesContext($request);
        $dateFrom = $ctx['dateFrom'];
        $dateTo = $ctx['dateTo'];
        $teamScoped = $this->isTeamHead() && ! $this->canViewOrgReports();
        $saleIds = $teamScoped
            ? $this->salesBaseQuery()->pluck('id')
            : null;

        $standalone = Invoice::query()
            ->whereNull('sale_id')
            ->where('status', '!=', Invoice::STATUS_VOID)
            ->whereBetween('issued_at', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        $receipts = Invoice::query()
            ->whereNotNull('sale_id')
            ->where('status', '!=', Invoice::STATUS_VOID)
            ->whereBetween('issued_at', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if ($teamScoped) {
            $standalone->whereRaw('1 = 0'); // team leads do not see org AR invoices
            $receipts->whereIn('sale_id', $saleIds->isEmpty() ? [0] : $saleIds);
        }

        $arIssued = (float) (clone $standalone)->sum('amount');
        $arOpenQ = Invoice::query()
            ->whereNull('sale_id')
            ->where('status', Invoice::STATUS_ISSUED)
            ->where('sale_balance', '>', 0);
        $arOverdueQ = (clone $arOpenQ)->whereDate('due_date', '<', today());
        if ($teamScoped) {
            $arOpen = (float) Sale::query()
                ->whereIn('id', $saleIds->isEmpty() ? [0] : $saleIds)
                ->where('approval_status', Sale::APPROVAL_APPROVED)
                ->whereRaw('(amount - received_amount) > 0')
                ->selectRaw('COALESCE(SUM(amount - received_amount), 0) as outstanding')
                ->value('outstanding');
            $arOverdue = 0.0;
            $paymentsInPeriod = 0.0;
        } else {
            $arOpen = (float) $arOpenQ->sum('sale_balance');
            $arOverdue = (float) $arOverdueQ->sum('sale_balance');
            $paymentsInPeriod = (float) InvoicePayment::query()
                ->whereBetween('paid_at', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->sum('amount');
        }

        return view('admin.reports.invoices', array_merge($ctx, [
            'arIssued' => $arIssued,
            'arOpen' => $arOpen,
            'arOverdue' => $arOverdue,
            'paymentsInPeriod' => $paymentsInPeriod,
            'saleReceiptsTotal' => (float) (clone $receipts)->sum('amount'),
            'saleReceiptsCount' => (clone $receipts)->count(),
            'standaloneCount' => (clone $standalone)->count(),
        ]));
    }

    public function expenses(Request $request): View
    {
        abort_unless(Auth::user()?->hasRole([Role::ADMIN, Role::FINANCE]), 403);

        $ctx = $this->prepareSalesContext($request);
        $dateFrom = $ctx['dateFrom'];
        $dateTo = $ctx['dateTo'];

        $query = Expense::query()->whereBetween('expense_date', [
            $dateFrom->toDateString(),
            $dateTo->toDateString(),
        ]);
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->integer('team_id'));
        }

        $total = (float) (clone $query)->sum('amount');
        $billable = (float) (clone $query)->where('is_billable', true)->sum('amount');
        $nonBillable = (float) (clone $query)->where('is_billable', false)->sum('amount');

        $byCategory = (clone $query)
            ->toBase()
            ->select('category', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return view('admin.reports.expenses', array_merge($ctx, [
            'total' => $total,
            'billable' => $billable,
            'nonBillable' => $nonBillable,
            'byCategory' => $byCategory,
            'showAgent' => false,
        ]));
    }
}
