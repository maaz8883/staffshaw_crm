<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Sale;
use App\Models\Team;
use App\Models\User;
use App\Support\AuthScope;
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

    /** Same visibility rules as {@see SaleController::baseQuery()}. */
    private function salesBaseQuery(): Builder
    {
        $user = Auth::user();
        $query = Sale::query()->select('sales.*');

        if ($user->hasRole('Admin')) {
            // all
        } elseif ($this->isTeamHead()) {
            $teamIds = Team::where('team_head_id', $user->id)->pluck('id');
            $query->whereIn('team_id', $teamIds);
        } else {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    private function applySaleFilters(Builder $query, Request $request): void
    {
        if ($request->filled('company_id') && ! AuthScope::isAgent()) {
            $query->where('company_id', $request->integer('company_id'));
        }
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->integer('team_id'));
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

        return [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'salesInPeriod' => $salesInPeriod,
            'approvedInPeriod' => $approvedInPeriod,
            'approvedCompleted' => $approvedCompleted,
            'teams' => AuthScope::teamsForDropdown(),
            'users' => $this->isAgent() ? collect() : AuthScope::usersForDropdown(),
            'companies' => AuthScope::isAgent() ? collect() : Company::query()->orderBy('name')->get(),
            'isAgent' => $this->isAgent(),
            'filters' => $request->only(['date_from', 'date_to', 'company_id', 'team_id', 'user_id']),
        ];
    }

    public function index(): View
    {
        return view('admin.reports.hub');
    }

    public function company(Request $request): View
    {
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

        $revenue = (clone $approvedInPeriod)->where('status', 'completed')->sum('amount');
        $completedCount = (clone $approvedInPeriod)->where('status', 'completed')->count();
        $pendingApprovalCount = (clone $salesInPeriod)->where('approval_status', Sale::APPROVAL_PENDING)->count();

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
            'completedCount' => $completedCount,
            'pendingApprovalCount' => $pendingApprovalCount,
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
}
