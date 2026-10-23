<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\TeamTarget;
use App\Models\User;
use App\Models\UserTarget;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user()->load(['role', 'team.company', 'company']);
        $month = now()->month;
        $year = now()->year;

        // Targets carry forward month to month (save this month's, and a picked month's, carried targets)
        \App\Support\TargetCarryForward::ensureMonth($month, $year);
        \App\Support\TargetCarryForward::ensureMonth((int) request('month', $month), (int) request('year', $year));

        if ($user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return $this->adminDashboard($month, $year);
        }

        // IT / HR: accounts overview (no sales data); HR gets expenses instead of brand setup
        if ($user->hasRole([Role::IT, Role::HR])) {
            return $this->itDashboard();
        }

        // Finance: cash in, money owed, spend and refunds across the company
        if ($user->hasRole(Role::FINANCE)) {
            return $this->financeDashboard($month, $year);
        }

        // Production (Design, Development, SEO…): Trello work only — checked before team head,
        // so a production team head also gets this instead of the sales dashboard
        if ($user->hasRole(Role::PRODUCTION)) {
            return app(ProductionDashboardController::class)->show();
        }

        // Project Manager: scoped to their approved (joined) teams
        if ($user->hasRole(Role::PROJECT_MANAGER)) {
            return $this->projectManagerDashboard($user, $month, $year);
        }

        // Team Head check
        $isTeamHead = Team::headedBy($user->id)->exists();
        if ($isTeamHead) {
            return $this->teamHeadDashboard($user, $month, $year);
        }

        // Sub-Team Head check
        $subTeamHead = \App\Models\SubTeamHead::where('user_id', $user->id)->first();
        if ($subTeamHead) {
            return $this->subTeamHeadDashboard($user, $subTeamHead, $month, $year);
        }

        // PPC user
        if ($user->hasRole('PPC')) {
            return $this->ppcDashboard($user, $month, $year);
        }

        return $this->agentDashboard($user, $month, $year);
    }

    // ── Approved sales base scope ────────────────────────────────────────────────
    private static function approved(): Builder
    {
        return Sale::where('approval_status', Sale::APPROVAL_APPROVED);
    }

    /** Revenue in calendar month (approved + completed). */
    private static function monthlyCompletedRevenue(int $year, int $month, ?callable $scope = null): float
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth();

        $q = self::approved()
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()]);

        if ($scope !== null) {
            $scope($q);
        }

        return (float) $q->sum('amount');
    }

    private static function achievementPercent(float $achieved, float $target): ?float
    {
        if ($target <= 0) {
            return null;
        }

        return round(($achieved / $target) * 100, 1);
    }

    // ── Admin Dashboard ──────────────────────────────────────────────────────────

    /**
     * Finance: for the chosen month — sales booked, cash received (sale receipts + invoice payments),
     * expenses, PPC spend, refunds and net; plus money still owed (sale balances, open invoices).
     */
    private function financeDashboard(int $month, int $year): View
    {
        $month = max(1, min(12, (int) request('month', $month)));
        $year = max(2020, min(2100, (int) request('year', $year)));
        $from = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();
        $range = [$from->toDateString(), $to->toDateString()];

        $invoiceModel = \App\Models\Invoice::class;
        $notVoid = fn ($q) => $q->where('status', '!=', \App\Models\Invoice::STATUS_VOID);

        $booked = (float) self::approved()->whereBetween('sale_date', $range)->where('is_refunded', false)->sum('amount');
        $salesCount = self::approved()->whereBetween('sale_date', $range)->where('is_refunded', false)->count();
        $receiptsIn = (float) $invoiceModel::query()->whereNotNull('sale_id')->tap($notVoid)->whereBetween('issued_at', $range)->sum('amount');
        $paymentsIn = (float) \App\Models\InvoicePayment::query()
            ->whereBetween('paid_at', $range)
            ->whereHas('invoice', $notVoid)
            ->sum('amount');
        $received = round($receiptsIn + $paymentsIn, 2);
        $expenses = (float) \App\Models\Expense::query()->whereBetween('expense_date', $range)->sum('amount');
        $ppc = (float) \App\Models\TeamPpcSpending::query()->where('month', $month)->where('year', $year)->sum('amount');
        $refunds = Sale::query()->where('is_refunded', true)->whereBetween('refunded_at', [$from, $to]);
        $refundTotal = (float) (clone $refunds)->sum('amount');

        // Still owed: approved, not refunded sales not fully received + issued invoices with a balance
        $openSales = self::approved()->where('is_refunded', false)->whereColumn('received_amount', '<', 'amount');
        $saleBalance = (float) (clone $openSales)->sum(DB::raw('amount - received_amount'));
        $topOpenSales = (clone $openSales)->with(['user:id,name', 'brand:id,name'])
            ->orderByRaw('(amount - received_amount) desc')->limit(8)->get();

        $openInvoices = $invoiceModel::query()->whereNull('sale_id')
            ->where('status', \App\Models\Invoice::STATUS_ISSUED)
            ->with('payments')->get()
            ->filter(fn ($i) => $i->amountDue() > 0);
        $overdue = $openInvoices->filter(fn ($i) => $i->due_date && $i->due_date->isPast())->sortBy('due_date')->values();

        $recentReceipts = $invoiceModel::query()->whereNotNull('sale_id')->tap($notVoid)->latest('id')->limit(6)->get()
            ->map(fn ($i) => ['when' => $i->issued_at, 'who' => $i->client_name, 'what' => $i->invoice_number, 'amount' => (float) $i->amount, 'url' => route('admin.invoices.show', $i)]);
        $recentPayments = \App\Models\InvoicePayment::query()->with('invoice')->latest('id')->limit(6)->get()
            ->filter(fn ($p) => $p->invoice)
            ->map(fn ($p) => ['when' => $p->paid_at, 'who' => $p->invoice->client_name, 'what' => $p->invoice->invoice_number.($p->payment_mode ? ' · '.$p->payment_mode : ''), 'amount' => (float) $p->amount, 'url' => route('admin.invoices.show', $p->invoice)]);
        $recent = $recentReceipts->concat($recentPayments)->sortByDesc(fn ($r) => optional($r['when'])->timestamp ?? 0)->take(8)->values();

        return view('admin.finance.dashboard', [
            'month' => $month,
            'year' => $year,
            'monthLabel' => $from->format('F Y'),
            'stats' => [
                'booked' => $booked,
                'sales_count' => $salesCount,
                'received' => $received,
                'expenses' => $expenses,
                'ppc' => $ppc,
                // Expenses are in rupees, sales / PPC in USD: never subtract one from the other
                'net' => round($received - $ppc, 2),
                'refunds' => $refundTotal,
                'refund_count' => (clone $refunds)->count(),
                'sale_balance' => $saleBalance,
                'invoice_balance' => (float) $openInvoices->sum(fn ($i) => $i->amountDue()),
                'overdue_total' => (float) $overdue->sum(fn ($i) => $i->amountDue()),
            ],
            'overdue' => $overdue->take(8),
            'topOpenSales' => $topOpenSales,
            'recent' => $recent,
        ]);
    }

    /** IT: who has access, what needs approval, brand setup gaps, recent sign-ins and account changes. */
    private function itDashboard(): View
    {
        $byStatus = User::query()->select('account_status', DB::raw('COUNT(*) as c'))->groupBy('account_status')->pluck('c', 'account_status');
        $monthRange = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        $roles = Role::query()->withCount('users')->orderBy('name')->get();
        $brands = \App\Models\Brand::query()->withCount('teams')->orderBy('name')->get();

        $recentLogins = \App\Models\UserActivityLog::query()
            ->with('user:id,name,email')
            ->where('type', \App\Models\UserActivityLog::TYPE_LOGIN)
            ->latest()->limit(12)->get();
        $accountChanges = \App\Models\UserActivityLog::query()
            ->with('user:id,name')
            ->whereIn('type', [
                \App\Models\UserActivityLog::TYPE_USER_CREATED,
                \App\Models\UserActivityLog::TYPE_USER_UPDATED,
                \App\Models\UserActivityLog::TYPE_USER_DELETED,
            ])
            ->latest()->limit(12)->get();

        return view('admin.it.dashboard', [
            'stats' => [
                'total' => (int) $byStatus->sum(),
                'active' => (int) ($byStatus[User::ACCOUNT_ACTIVE] ?? 0),
                'pending' => (int) ($byStatus[User::ACCOUNT_PENDING] ?? 0),
                'banned' => (int) ($byStatus[User::ACCOUNT_BANNED] ?? 0),
                'brands' => $brands->count(),
                'expenses_today' => (float) \App\Models\Expense::query()->whereDate('expense_date', now()->toDateString())->sum('amount'),
                'expenses_month' => (float) \App\Models\Expense::query()->whereBetween('expense_date', $monthRange)->sum('amount'),
            ],
            'recentExpenses' => \App\Models\Expense::query()->whereBetween('expense_date', $monthRange)
                ->orderByDesc('expense_date')->orderByDesc('id')->limit(10)->get(),
            'roles' => $roles,
            'brandsMissingLogo' => $brands->filter(fn ($b) => ! $b->image || ! is_file(public_path('storage/'.$b->image)))->values(),
            'brandsWithoutTeam' => $brands->where('teams_count', 0)->values(),
            'recentLogins' => $recentLogins,
            'accountChanges' => $accountChanges,
        ]);
    }

    private function adminDashboard(int $month, int $year): View
    {
        $stats = [
            'total_users' => User::accountActive()->count(),
            'total_teams' => Team::count(),
            'total_companies' => Company::count(),
            'total_sales' => self::approved()->count(),
            'sales_completed' => self::approved()->where('status', 'completed')->count(),
            'sales_pending' => self::approved()->where('status', 'pending')->count(),
            'sales_cancelled' => self::approved()->where('status', 'cancelled')->count(),
            'total_revenue' => self::approved()->where('status', 'completed')->sum('amount'),
            'pending_approval' => Sale::where('approval_status', Sale::APPROVAL_PENDING)->count(),
        ];

        // Approved sales by status for chart
        $salesByStatus = collect(Sale::allStatuses())->mapWithKeys(fn ($s) => [
            $s => self::approved()->where('status', $s)->count(),
        ]);

        // Recent approved sales
        $recentSales = self::approved()
            ->with(['user', 'team'])
            ->latest()
            ->limit(8)
            ->get();

        // Per-company breakdown — only approved
        $companies = Company::withCount([
                'users' => fn ($q) => $q->where('account_status', User::ACCOUNT_ACTIVE),
                'teams',
            ])
            ->with(['teams' => fn ($q) => $q->withCount([
                'users' => fn ($q2) => $q2->where('account_status', User::ACCOUNT_ACTIVE),
            ])])
            ->get()
            ->map(function (Company $company) use ($month, $year) {
                $teamIds = $company->teams->pluck('id');

                $company->sales_count = self::approved()->where('company_id', $company->id)->count();
                $company->revenue = self::approved()
                    ->where('company_id', $company->id)
                    ->where('status', 'completed')
                    ->sum('amount');

                $company->team_target = TeamTarget::whereIn('team_id', $teamIds)
                    ->where('month', $month)->where('year', $year)
                    ->sum('target_amount');

                return $company;
            });

        // Top agents — only approved sales (exclude Admin & Manager roles)
        $topAgents = User::with(['team', 'company', 'role'])
            ->accountActive()
            ->whereDoesntHave('role', fn (Builder $q) => $q->whereIn('name', [Role::ADMIN, Role::MANAGER, Role::PRODUCTION, Role::PPC]))
            ->whereDoesntHave('team', fn (Builder $q) => $q->where('is_production', true))
            ->get()
            ->map(function ($u) use ($month, $year) {
                $u->total_sales = self::approved()->where('user_id', $u->id)->where('status', 'completed')->count();
                $u->total_amount = self::approved()->where('user_id', $u->id)->where('status', 'completed')->sum('amount');
                $u->month_target = UserTarget::where('user_id', $u->id)
                    ->where('month', $month)->where('year', $year)
                    ->value('target_amount') ?? 0;

                return $u;
            })
            ->sortByDesc('total_amount')
            ->take(5)
            ->values();

        // Charts: 30-day revenue trend (approved + completed)
        $trendStart = now()->subDays(29)->startOfDay();
        $trendEnd = now()->endOfDay();
        $dailyRevenueRows = self::approved()
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$trendStart->toDateString(), $trendEnd->toDateString()])
            ->toBase()
            ->select(DB::raw('DATE(sale_date) as d'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('DATE(sale_date)'))
            ->pluck('total', 'd');

        $revenueTrendLabels = [];
        $revenueTrendValues = [];
        foreach (CarbonPeriod::create($trendStart->toDateString(), $trendEnd->toDateString()) as $date) {
            $revenueTrendLabels[] = $date->format('M j');
            $revenueTrendValues[] = round((float) ($dailyRevenueRows[$date->format('Y-m-d')] ?? 0), 2);
        }

        // Top companies by revenue (bar chart)
        $topCompaniesChart = $companies->sortByDesc('revenue')->take(8)->values();
        $companyBarLabels = $topCompaniesChart->pluck('name')->all();
        $companyBarValues = $topCompaniesChart->pluck('revenue')->map(fn ($v) => (float) $v)->all();

        // Approval pipeline
        $approvalMix = [
            'Approved' => Sale::where('approval_status', Sale::APPROVAL_APPROVED)->count(),
            'Pending' => Sale::where('approval_status', Sale::APPROVAL_PENDING)->count(),
            'Rejected' => Sale::where('approval_status', Sale::APPROVAL_REJECTED)->count(),
        ];

        // Target achievement (current month) — org-wide
        $sumUserTargets = (float) UserTarget::where('month', $month)->where('year', $year)->sum('target_amount');
        $sumTeamTargets = (float) TeamTarget::where('month', $month)->where('year', $year)->sum('target_amount');
        $monthlyOrgRevenue = self::monthlyCompletedRevenue($year, $month);

        $targetAchievement = [
            'label' => Carbon::createFromDate($year, $month, 1)->format('F Y'),
            'monthly_revenue' => $monthlyOrgRevenue,
            'user_target_total' => $sumUserTargets,
            'team_target_total' => $sumTeamTargets,
            'user_percent' => self::achievementPercent($monthlyOrgRevenue, $sumUserTargets),
            'team_percent' => self::achievementPercent($monthlyOrgRevenue, $sumTeamTargets),
        ];

        // Per-team cards: monthly target vs revenue + members table
        $teamDashboardCards = $this->buildTeamDashboardCards($month, $year);

        return view('admin.dashboard', compact(
            'stats', 'salesByStatus', 'recentSales', 'companies', 'topAgents', 'month', 'year',
            'revenueTrendLabels', 'revenueTrendValues', 'companyBarLabels', 'companyBarValues', 'approvalMix',
            'targetAchievement', 'teamDashboardCards'
        ));
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function buildTeamDashboardCards(int $month, int $year): \Illuminate\Support\Collection
    {
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $monthEnd = (clone $monthStart)->endOfMonth();

        $monthlyByTeam = self::approved()
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereNotNull('team_id')
            ->toBase()
            ->select('team_id', DB::raw('SUM(amount) as total'))
            ->groupBy('team_id')
            ->pluck('total', 'team_id');

        $targetsByTeam = TeamTarget::query()
            ->where('month', $month)
            ->where('year', $year)
            ->pluck('target_amount', 'team_id');

        // PPC spending per team this month
        $ppcByTeam = \App\Models\TeamPpcSpending::query()
            ->where('month', $month)
            ->where('year', $year)
            ->toBase()
            ->select('team_id', DB::raw('SUM(amount) as total'))
            ->groupBy('team_id')
            ->pluck('total', 'team_id');

        $userTargets = UserTarget::query()
            ->where('month', $month)
            ->where('year', $year)
            ->pluck('target_amount', 'user_id');

        // Member sales/revenue use the same month as targets (not all-time).
        $salesStatsByUser = self::approved()
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->toBase()
            ->select(
                'user_id',
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(amount) as sale_amount')
            )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $usersByTeam = User::query()
            ->accountActive()
            ->whereNotNull('team_id')
            ->with('role')
            ->orderBy('name')
            ->get()
            // PMs belong via Team_Membership, not users.team_id (avoids stale home-team).
            // PPC / Production don't make sales, so they have no place in a sales table.
            ->reject(fn (User $u) => $u->hasRole([Role::PROJECT_MANAGER, Role::PPC, Role::PRODUCTION]))
            ->groupBy('team_id');

        $pmsByTeam = TeamMembership::query()
            ->where('status', TeamMembership::STATUS_APPROVED)
            ->with(['user.role'])
            ->get()
            ->groupBy('team_id')
            ->map(fn ($rows) => $rows->map->user->filter()->unique('id')->values());

        return Team::query()->sales()
            ->with('company')
            ->orderBy('name')
            ->get()
            ->map(function (Team $team) use (
                $year, $month,
                $monthlyByTeam, $targetsByTeam, $ppcByTeam,
                $userTargets,
                $salesStatsByUser, $usersByTeam, $pmsByTeam
            ) {
                $tid     = $team->id;
                $target  = (float) ($targetsByTeam[$tid] ?? 0);
                $revenue = (float) ($monthlyByTeam[$tid] ?? 0);
                $ppc     = (float) ($ppcByTeam[$tid] ?? 0);
                $pct     = self::achievementPercent($revenue, $target);

                $teamUsers = ($usersByTeam->get($tid) ?? collect())
                    ->concat($pmsByTeam->get($tid) ?? collect())
                    ->unique('id')
                    // Team Lead first, then agents, then Project Managers (A–Z within each)
                    ->sortBy(fn (User $u) => [
                        $team->isHeadedBy($u->id) ? 0 : ($u->hasRole(Role::PROJECT_MANAGER) ? 2 : 1),
                        mb_strtolower($u->name),
                    ])
                    ->values();

                $members = $teamUsers
                    ->map(function (User $member) use ($userTargets, $salesStatsByUser, $team) {
                        $mid         = $member->id;
                        $stats       = $salesStatsByUser->get($mid);
                        $monthTarget = (float) ($userTargets[$mid] ?? 0);
                        $monthRev    = $stats ? (float) $stats->sale_amount : 0.0;
                        $isHead      = $team->isHeadedBy($mid);
                        // Project Managers can't be given a target: show "—", not $0
                        $monthTarget = $member->hasRole(Role::PROJECT_MANAGER) ? null : $monthTarget;

                        return [
                            'id'                     => $mid,
                            'name'                   => $member->name,
                            'role_name'              => $isHead ? 'Team Lead' : ($member->role?->name ?? '—'),
                            'is_head'                => $isHead,
                            'sales_count'            => $stats ? (int) $stats->sales_count : 0,
                            'sale_amount'            => $monthRev,
                            'month_target'           => $monthTarget,
                            'month_revenue'          => $monthRev,
                            'target_achievement_pct' => $monthTarget === null ? null : self::achievementPercent($monthRev, $monthTarget),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id'                 => $team->id,
                    'name'               => $team->name,
                    'company_name'       => $team->company?->name,
                    'members_count'      => count($members),
                    'month_label'        => Carbon::createFromDate($year, $month, 1)->format('F Y'),
                    'target'             => $target,
                    'monthly_revenue'    => $revenue,
                    'achievement_percent' => $pct,
                    'ppc_spending'       => $ppc,
                    'members'            => $members,
                ];
            });
    }

    // ── Project Manager Dashboard ────────────────────────────────────────────────

    /**
     * Requirement 9 — Dashboard scoped strictly to the Project Manager's
     * currently approved Team_Memberships (Joined_Teams).
     */
    private function projectManagerDashboard(User $user, int $month, int $year): View
    {
        $joinedTeamIds = $user->approvedTeamIds();

        $teamDashboardCards = $this->buildTeamDashboardCards($month, $year)
            ->filter(fn ($card) => $joinedTeamIds->contains($card['id']))
            ->values();

        $myRecentSales = Sale::where('user_id', $user->id)
            ->with(['team'])->latest()->limit(6)->get();

        return view('admin.dashboard', compact('month', 'year', 'teamDashboardCards', 'myRecentSales'))
            ->with([
                'isProjectManagerView' => true,
                'hasJoinedTeams'       => $joinedTeamIds->isNotEmpty(),
                // Trello section (loaded by AJAX)
                'pmTrelloTeams'        => \App\Support\TeamTrelloAccounts::teamsForUser()->filter(fn ($t) => $t->trelloIntegration?->isReady())->values(),
                'myTrelloActivity'     => \App\Models\TrelloActivityLog::query()->where('user_id', $user->id)->latest('occurred_at')->limit(8)->get(),
            ]);
    }

    // ── Team Head Dashboard ──────────────────────────────────────────────────────

    private function teamHeadDashboard(User $user, int $month, int $year): View
    {
        $teams = Team::with(['company', 'users.role', 'teamHead'])
            ->headedBy($user->id)
            ->get();

        $teamDashboardCards = $this->buildTeamDashboardCards($month, $year)
            ->filter(fn ($card) => $teams->pluck('id')->contains($card['id']))
            ->values();

        $firstTeam = $teams->first();
        $teamIds = $teams->pluck('id');
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $monthEnd = (clone $monthStart)->endOfMonth();
        $monthLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

        $teamTargetAmt = $firstTeam
            ? (float) TeamTarget::where('team_id', $firstTeam->id)->where('month', $month)->where('year', $year)->value('target_amount')
            : 0;
        $teamMonthRev = $firstTeam
            ? self::monthlyCompletedRevenue($year, $month, fn (Builder $q) => $q->where('team_id', $firstTeam->id))
            : 0.0;
        $teamMonthSalesCount = $firstTeam
            ? (int) self::approved()
                ->where('status', 'completed')
                ->where('team_id', $firstTeam->id)
                ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count()
            : 0;

        $teamTargetAchievement = [
            'target' => $teamTargetAmt,
            'achieved' => $teamMonthRev,
            'percent' => self::achievementPercent($teamMonthRev, $teamTargetAmt),
            'label' => $monthLabel,
        ];

        $myMonthRevenue = self::monthlyCompletedRevenue($year, $month, fn (Builder $q) => $q->where('user_id', $user->id));
        $myMonthCompleted = (int) self::approved()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();
        $myMonthPending = (int) Sale::query()
            ->where('user_id', $user->id)
            ->where('approval_status', Sale::APPROVAL_PENDING)
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();
        $myMonthCancelled = (int) Sale::query()
            ->where('user_id', $user->id)
            ->where('status', 'cancelled')
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $myTarget = UserTarget::where('user_id', $user->id)
            ->where('month', $month)->where('year', $year)->first();
        $myTargetAmt = (float) ($myTarget?->target_amount ?? 0);

        $mySales = [
            'total' => $myMonthCompleted + $myMonthPending + $myMonthCancelled,
            'completed' => $myMonthCompleted,
            'pending' => $myMonthPending,
            'cancelled' => $myMonthCancelled,
            'revenue' => $myMonthRevenue,
            'month_revenue' => $myMonthRevenue,
        ];

        $userTargetAchievement = [
            'target' => $myTargetAmt,
            'achieved' => $myMonthRevenue,
            'percent' => self::achievementPercent($myMonthRevenue, $myTargetAmt),
            'label' => $monthLabel,
        ];

        $company = $user->company;
        $companyStats = $company ? [
            'teams_count' => Team::where('company_id', $company->id)->count(),
            'users_count' => User::where('company_id', $company->id)->count(),
            'sales_count' => self::approved()->where('company_id', $company->id)->count(),
            'revenue' => self::approved()->where('company_id', $company->id)->where('status', 'completed')->sum('amount'),
        ] : [];

        $recentSales = Sale::query()
            ->whereIn('team_id', $teamIds->isEmpty() ? [0] : $teamIds)
            ->with(['team', 'user'])
            ->latest('sale_date')
            ->limit(8)
            ->get();

        // Team charts: last 14 days + status mix for headed teams
        $agentTrendStart = now()->subDays(13)->startOfDay();
        $agentTrendEnd = now()->endOfDay();
        $teamDailyRows = self::approved()
            ->whereIn('team_id', $teamIds->isEmpty() ? [0] : $teamIds)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$agentTrendStart->toDateString(), $agentTrendEnd->toDateString()])
            ->toBase()
            ->select(DB::raw('DATE(sale_date) as d'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('DATE(sale_date)'))
            ->pluck('total', 'd');

        $agentRevenueTrendLabels = [];
        $agentRevenueTrendValues = [];
        foreach (CarbonPeriod::create($agentTrendStart->toDateString(), $agentTrendEnd->toDateString()) as $date) {
            $agentRevenueTrendLabels[] = $date->format('M j');
            $agentRevenueTrendValues[] = round((float) ($teamDailyRows[$date->format('Y-m-d')] ?? 0), 2);
        }

        $teamStatusBase = Sale::query()
            ->whereIn('team_id', $teamIds->isEmpty() ? [0] : $teamIds)
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
        $agentSalesByStatus = [
            'labels' => ['Completed', 'Pending', 'Cancelled'],
            'values' => [
                (int) (clone $teamStatusBase)->where('approval_status', Sale::APPROVAL_APPROVED)->where('status', 'completed')->count(),
                (int) (clone $teamStatusBase)->where('approval_status', Sale::APPROVAL_PENDING)->count(),
                (int) (clone $teamStatusBase)->where('status', 'cancelled')->count(),
            ],
        ];

        $subTeamsProgress = collect();
        if ($firstTeam) {
            $subTeamHeads = \App\Models\SubTeamHead::where('team_id', $firstTeam->id)
                ->with('user')
                ->get();

            $subTeamsProgress = $subTeamHeads->map(function ($subHead) use ($month, $year, $monthStart, $monthEnd) {
                $subTeamTarget = \App\Models\SubTeamHeadTarget::where('sub_team_head_id', $subHead->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->first();

                $subTeamMemberIds = User::query()
                    ->where(function ($q) use ($subHead) {
                        $q->where('sub_team_head_id', $subHead->id)
                            ->orWhere('id', $subHead->user_id);
                    })
                    ->pluck('id')
                    ->unique()
                    ->values();

                $subTeamRevenue = self::approved()
                    ->where('status', 'completed')
                    ->whereIn('user_id', $subTeamMemberIds->isEmpty() ? [0] : $subTeamMemberIds)
                    ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->sum('amount');

                $targetAmt = (float) ($subTeamTarget?->target_amount ?? 0);
                $revenueAmt = (float) $subTeamRevenue;

                return [
                    'title' => $subHead->title,
                    'head_name' => $subHead->user?->name ?? '—',
                    'members_count' => $subTeamMemberIds->count(),
                    'target' => $targetAmt,
                    'revenue' => $revenueAmt,
                    'percent' => self::achievementPercent($revenueAmt, $targetAmt),
                ];
            });
        }

        return view('admin.dashboard', compact(
            'user', 'teams', 'teamDashboardCards',
            'mySales', 'myTarget', 'recentSales', 'month', 'year',
            'company', 'companyStats',
            'agentRevenueTrendLabels', 'agentRevenueTrendValues', 'agentSalesByStatus',
            'teamTargetAchievement', 'subTeamsProgress', 'teamMonthSalesCount'
        ))->with([
            'isTeamHeadView' => true,
            'team' => $teams->first(),
            'teamTarget' => null,
            'teamMembers' => collect(),
            'userTargetAchievement' => $userTargetAchievement,
        ]);
    }

    // ── Sub-Team Head Dashboard ─────────────────────────────────────────────────

    private function subTeamHeadDashboard(User $user, \App\Models\SubTeamHead $subTeamHead, int $month, int $year): View
    {
        $team = $subTeamHead->team;
        $company = $user->company;
        $teamTarget = $team
            ? TeamTarget::where('team_id', $team->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first()
            : null;
        $teamTargetAmt = (float) ($teamTarget?->target_amount ?? 0);
        $currentMonthLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $monthEnd = (clone $monthStart)->endOfMonth();

        $subTeamMembers = User::query()
            ->where(function ($q) use ($subTeamHead, $user) {
                $q->where('sub_team_head_id', $subTeamHead->id)
                    ->orWhere('id', $user->id);
            })
            ->with('role')
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();

        $subTeamMemberIds = $subTeamMembers->pluck('id')->values();

        $subTeamTarget = \App\Models\SubTeamHeadTarget::where('sub_team_head_id', $subTeamHead->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $subTeamTargetAmt = (float) ($subTeamTarget?->target_amount ?? 0);

        $subTeamMonthRevenue = self::monthlyCompletedRevenue(
            $year,
            $month,
            fn (Builder $q) => $q->whereIn('user_id', $subTeamMemberIds->isEmpty() ? [0] : $subTeamMemberIds)
        );

        $teamMonthRevenue = $team
            ? self::monthlyCompletedRevenue($year, $month, fn (Builder $q) => $q->where('team_id', $team->id))
            : 0.0;

        $subTeamMonthSalesCount = (int) self::approved()
            ->where('status', 'completed')
            ->whereIn('user_id', $subTeamMemberIds->isEmpty() ? [0] : $subTeamMemberIds)
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $subTeamSales = [
            'total' => $subTeamMonthSalesCount,
            'completed' => $subTeamMonthSalesCount,
            'pending' => (int) Sale::query()
                ->whereIn('user_id', $subTeamMemberIds->isEmpty() ? [0] : $subTeamMemberIds)
                ->where('approval_status', Sale::APPROVAL_PENDING)
                ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count(),
            'revenue' => $subTeamMonthRevenue,
        ];

        $subTeamTargetAchievement = [
            'target' => $subTeamTargetAmt,
            'achieved' => $subTeamMonthRevenue,
            'percent' => self::achievementPercent($subTeamMonthRevenue, $subTeamTargetAmt),
            'label' => $currentMonthLabel,
        ];

        $teamTargetAchievement = [
            'target' => $teamTargetAmt,
            'achieved' => $teamMonthRevenue,
            'percent' => self::achievementPercent($teamMonthRevenue, $teamTargetAmt),
            'label' => $currentMonthLabel,
        ];

        $userTargets = UserTarget::whereIn('user_id', $subTeamMemberIds)
            ->where('month', $month)
            ->where('year', $year)
            ->pluck('target_amount', 'user_id');

        $salesStatsByUser = self::approved()
            ->where('status', 'completed')
            ->whereIn('user_id', $subTeamMemberIds->isEmpty() ? [0] : $subTeamMemberIds)
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->toBase()
            ->select(
                'user_id',
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(amount) as sale_amount')
            )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $memberStats = $subTeamMembers->map(function (User $member) use (
            $userTargets, $salesStatsByUser, $user, $subTeamHead
        ) {
            $mid = $member->id;
            $stats = $salesStatsByUser->get($mid);
            $monthTarget = (float) ($userTargets[$mid] ?? 0);
            $monthRev = $stats ? (float) $stats->sale_amount : 0.0;
            $isSubHead = (int) $mid === (int) $user->id;
            $monthTarget = $member->hasRole(Role::PROJECT_MANAGER) ? null : $monthTarget;

            return [
                'id' => $mid,
                'name' => $member->name,
                'role_name' => $isSubHead
                    ? ('Sub-Team Head · ' . ($subTeamHead->title ?: 'Lead'))
                    : ($member->role?->name ?? 'Agent'),
                'is_sub_team_head' => $isSubHead,
                'sales_count' => $stats ? (int) $stats->sales_count : 0,
                'sale_amount' => $monthRev,
                'month_target' => $monthTarget,
                'month_revenue' => $monthRev,
                'target_achievement_pct' => $monthTarget === null ? null : self::achievementPercent($monthRev, $monthTarget),
            ];
        })->values();

        $myMonthRevenue = self::monthlyCompletedRevenue($year, $month, fn (Builder $q) => $q->where('user_id', $user->id));
        $mySales = [
            'total' => (int) Sale::query()
                ->where('user_id', $user->id)
                ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count(),
            'completed' => (int) self::approved()
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count(),
            'pending' => (int) Sale::query()
                ->where('user_id', $user->id)
                ->where('approval_status', Sale::APPROVAL_PENDING)
                ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count(),
            'cancelled' => (int) Sale::query()
                ->where('user_id', $user->id)
                ->where('status', 'cancelled')
                ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count(),
            'revenue' => $myMonthRevenue,
            'month_revenue' => $myMonthRevenue,
        ];

        $myTarget = UserTarget::where('user_id', $user->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $recentSales = Sale::query()
            ->whereIn('user_id', $subTeamMemberIds->isEmpty() ? [0] : $subTeamMemberIds)
            ->with(['user', 'team'])
            ->latest('sale_date')
            ->latest('id')
            ->limit(8)
            ->get();

        $trendStart = now()->subDays(13)->startOfDay();
        $trendEnd = now()->endOfDay();
        $dailyRows = self::approved()
            ->where('status', 'completed')
            ->whereIn('user_id', $subTeamMemberIds->isEmpty() ? [0] : $subTeamMemberIds)
            ->whereBetween('sale_date', [$trendStart->toDateString(), $trendEnd->toDateString()])
            ->toBase()
            ->select(DB::raw('DATE(sale_date) as d'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('DATE(sale_date)'))
            ->pluck('total', 'd');

        $revenueTrendLabels = [];
        $revenueTrendValues = [];
        foreach (CarbonPeriod::create($trendStart->toDateString(), $trendEnd->toDateString()) as $date) {
            $revenueTrendLabels[] = $date->format('M j');
            $revenueTrendValues[] = round((float) ($dailyRows[$date->format('Y-m-d')] ?? 0), 2);
        }

        return view('admin.dashboard', compact(
            'user', 'team', 'company',
            'subTeamHead', 'subTeamMembers', 'memberStats',
            'subTeamSales', 'subTeamTarget', 'subTeamTargetAchievement',
            'mySales', 'myTarget', 'recentSales',
            'month', 'year',
            'revenueTrendLabels', 'revenueTrendValues',
            'teamTarget', 'teamTargetAmt', 'currentMonthLabel', 'teamTargetAchievement'
        ))->with([
            'isSubTeamHeadView' => true,
            'teamMembers' => collect(),
            'companyStats' => [
                'teams_count' => $company ? Team::where('company_id', $company->id)->count() : 0,
                'users_count' => $company ? User::where('company_id', $company->id)->accountActive()->count() : 0,
                'sales_count' => $company ? self::approved()->where('company_id', $company->id)->count() : 0,
                'revenue' => $company ? self::approved()->where('company_id', $company->id)->where('status', 'completed')->sum('amount') : 0,
            ],
            'teamDashboardCards' => collect(),
            'agentRevenueTrendLabels' => [],
            'agentRevenueTrendValues' => [],
            'agentSalesByStatus' => ['labels' => [], 'values' => []],
            'userTargetAchievement' => [
                'target' => (float) ($myTarget?->target_amount ?? 0),
                'achieved' => $myMonthRevenue,
                'percent' => self::achievementPercent(
                    $myMonthRevenue,
                    (float) ($myTarget?->target_amount ?? 0)
                ),
                'label' => $currentMonthLabel,
            ],
            'teamTargetAchievement' => $teamTargetAchievement,
        ]);
    }

    // ── PPC Dashboard ────────────────────────────────────────────────────────────

    private function ppcDashboard(User $user, int $month, int $year): View
    {
        $month = max(1, min(12, (int) request()->get('month', $month)));
        $year = max(2020, min(2100, (int) request()->get('year', $year)));

        $prev = Carbon::createFromDate($year, $month, 1)->subMonth();
        $prevMonth = (int) $prev->month;
        $prevYear = (int) $prev->year;
        $currentMonthLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

        // PPC users only see their assigned team(s); Admin/Manager see all.
        $teamsQuery = Team::with(['company'])->sales()->orderBy('name');
        if (! $user->hasRole([Role::ADMIN, Role::MANAGER])) {
            $teamIds = \App\Support\AuthScope::accessibleTeamIds();
            $teamsQuery->whereIn('id', $teamIds->isEmpty() ? [0] : $teamIds);
        }

        // PPC employees: spend-only (no team revenue / targets / margin ratios).
        $teams = $teamsQuery->get()
            ->map(function (Team $team) use ($month, $year, $prevMonth, $prevYear) {
                $tid = $team->id;
                $spend = (float) \App\Models\TeamPpcSpending::where('team_id', $tid)
                    ->where('month', $month)->where('year', $year)->sum('amount');
                $prevSpend = (float) \App\Models\TeamPpcSpending::where('team_id', $tid)
                    ->where('month', $prevMonth)->where('year', $prevYear)->sum('amount');
                $entries = (int) \App\Models\TeamPpcSpending::where('team_id', $tid)
                    ->where('month', $month)->where('year', $year)->count();

                $team->month_spending = $spend;
                $team->prev_month_spending = $prevSpend;
                $team->spend_change_pct = $prevSpend > 0
                    ? round((($spend - $prevSpend) / $prevSpend) * 100, 1)
                    : ($spend > 0 ? null : 0.0);
                $team->entries_count = $entries;

                return $team;
            });

        $totalSpending = (float) $teams->sum('month_spending');
        $prevTotalSpending = (float) $teams->sum('prev_month_spending');
        $entriesCount = (int) $teams->sum('entries_count');
        $spendChangePct = $prevTotalSpending > 0
            ? round((($totalSpending - $prevTotalSpending) / $prevTotalSpending) * 100, 1)
            : ($totalSpending > 0 ? null : 0.0);

        $recentQuery = \App\Models\TeamPpcSpending::query()
            ->with(['team', 'user'])
            ->where('month', $month)
            ->where('year', $year)
            ->latest()
            ->limit(10);

        if (! $user->hasRole([Role::ADMIN, Role::MANAGER])) {
            $teamIds = \App\Support\AuthScope::accessibleTeamIds();
            $recentQuery->whereIn('team_id', $teamIds->isEmpty() ? [0] : $teamIds);
        }

        $recentSpendings = $recentQuery->get();

        return view('admin.ppc.dashboard', compact(
            'teams', 'totalSpending', 'prevTotalSpending',
            'entriesCount', 'spendChangePct',
            'recentSpendings', 'month', 'year', 'currentMonthLabel'
        ));
    }

    // ── Agent Dashboard ──────────────────────────────────────────────────────────

    private function agentDashboard(User $user, int $month, int $year): View
    {
        $team = $user->team;
        $company = $user->company;
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $monthEnd = (clone $monthStart)->endOfMonth();
        $currentMonthLabel = $monthStart->format('F Y');

        $myMonthRevenue = self::monthlyCompletedRevenue($year, $month, fn (Builder $q) => $q->where('user_id', $user->id));
        $teamMonthRevenue = $team
            ? self::monthlyCompletedRevenue($year, $month, fn (Builder $q) => $q->where('team_id', $team->id))
            : 0.0;

        $monthBase = Sale::query()
            ->where('user_id', $user->id)
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

        $mySales = [
            'total' => (int) (clone $monthBase)->count(),
            'completed' => (int) (clone $monthBase)
                ->where('approval_status', Sale::APPROVAL_APPROVED)
                ->where('status', 'completed')
                ->count(),
            'pending_approval' => (int) (clone $monthBase)
                ->where('approval_status', Sale::APPROVAL_PENDING)
                ->count(),
            'pending' => (int) (clone $monthBase)
                ->where('approval_status', Sale::APPROVAL_PENDING)
                ->count(),
            'cancelled' => (int) (clone $monthBase)->where('status', 'cancelled')->count(),
            'revenue' => $myMonthRevenue,
            'month_revenue' => $myMonthRevenue,
        ];

        $myTarget = UserTarget::where('user_id', $user->id)
            ->where('month', $month)->where('year', $year)
            ->first();

        $teamTarget = $team ? TeamTarget::where('team_id', $team->id)
            ->where('month', $month)->where('year', $year)
            ->first() : null;

        $myTargetAmt = (float) ($myTarget?->target_amount ?? 0);
        $teamTargetAmt = (float) ($teamTarget?->target_amount ?? 0);

        $userTargetAchievement = [
            'target' => $myTargetAmt,
            'achieved' => $myMonthRevenue,
            'percent' => self::achievementPercent($myMonthRevenue, $myTargetAmt),
            'label' => $currentMonthLabel,
        ];
        $teamTargetAchievement = [
            'target' => $teamTargetAmt,
            'achieved' => $teamMonthRevenue,
            'percent' => self::achievementPercent($teamMonthRevenue, $teamTargetAmt),
            'label' => $currentMonthLabel,
        ];

        $teamMembers = collect();

        $companyStats = $company ? [
            'teams_count' => Team::where('company_id', $company->id)->count(),
            'users_count' => User::where('company_id', $company->id)->accountActive()->count(),
            'sales_count' => self::approved()->where('company_id', $company->id)->count(),
            'revenue' => self::approved()->where('company_id', $company->id)->where('status', 'completed')->sum('amount'),
        ] : [];

        $recentSales = Sale::where('user_id', $user->id)
            ->with(['team'])
            ->latest('sale_date')
            ->latest('id')
            ->limit(8)
            ->get();

        $agentTrendStart = now()->subDays(13)->startOfDay();
        $agentTrendEnd = now()->endOfDay();
        $agentDailyRows = self::approved()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$agentTrendStart->toDateString(), $agentTrendEnd->toDateString()])
            ->toBase()
            ->select(DB::raw('DATE(sale_date) as d'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('DATE(sale_date)'))
            ->pluck('total', 'd');

        $agentRevenueTrendLabels = [];
        $agentRevenueTrendValues = [];
        foreach (CarbonPeriod::create($agentTrendStart->toDateString(), $agentTrendEnd->toDateString()) as $date) {
            $agentRevenueTrendLabels[] = $date->format('M j');
            $agentRevenueTrendValues[] = round((float) ($agentDailyRows[$date->format('Y-m-d')] ?? 0), 2);
        }

        $agentSalesByStatus = [
            'labels' => ['Approved', 'Awaiting approval', 'Cancelled'],
            'values' => [
                $mySales['completed'],
                $mySales['pending_approval'],
                $mySales['cancelled'],
            ],
        ];

        return view('admin.dashboard', compact(
            'user', 'team', 'company',
            'mySales', 'myTarget', 'teamTarget',
            'teamMembers', 'companyStats', 'recentSales',
            'month', 'year', 'currentMonthLabel',
            'agentRevenueTrendLabels', 'agentRevenueTrendValues', 'agentSalesByStatus',
            'userTargetAchievement', 'teamTargetAchievement'
        ))->with('teamDashboardCards', collect());
    }
}
