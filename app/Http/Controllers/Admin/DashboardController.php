<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
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

        if ($user->hasRole('Admin')) {
            return $this->adminDashboard($month, $year);
        }

        // Team Head check
        $isTeamHead = Team::where('team_head_id', $user->id)->exists();
        if ($isTeamHead) {
            return $this->teamHeadDashboard($user, $month, $year);
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

        return min(100.0, round(($achieved / $target) * 100, 1));
    }

    // ── Admin Dashboard ──────────────────────────────────────────────────────────

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
            ->whereDoesntHave('role', fn (Builder $q) => $q->whereIn('name', [Role::ADMIN, Role::MANAGER]))
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

        $userCounts = User::query()
            ->accountActive()
            ->whereNotNull('team_id')
            ->select('team_id', DB::raw('COUNT(*) as c'))
            ->groupBy('team_id')
            ->pluck('c', 'team_id');

        $userTargets = UserTarget::query()
            ->where('month', $month)
            ->where('year', $year)
            ->pluck('target_amount', 'user_id');

        $monthRevByUser = self::approved()
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->toBase()
            ->select('user_id', DB::raw('SUM(amount) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $salesStatsByUser = self::approved()
            ->where('status', 'completed')
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
            ->groupBy('team_id');

        return Team::query()
            ->with('company')
            ->orderBy('name')
            ->get()
            ->map(function (Team $team) use (
                $year,
                $month,
                $monthlyByTeam,
                $targetsByTeam,
                $userCounts,
                $userTargets,
                $monthRevByUser,
                $salesStatsByUser,
                $usersByTeam
            ) {
                $tid = $team->id;
                $target = (float) ($targetsByTeam[$tid] ?? 0);
                $revenue = (float) ($monthlyByTeam[$tid] ?? 0);
                $pct = self::achievementPercent($revenue, $target);

                $members = ($usersByTeam->get($tid) ?? collect())
                    ->map(function (User $member) use ($userTargets, $monthRevByUser, $salesStatsByUser, $team) {
                        $mid = $member->id;
                        $stats = $salesStatsByUser->get($mid);
                        $monthTarget = (float) ($userTargets[$mid] ?? 0);
                        $monthRev = (float) ($monthRevByUser[$mid] ?? 0);

                        return [
                            'id' => $mid,
                            'name' => $member->name,
                            'role_name' => $member->role?->name ?? '—',
                            'is_head' => $team->team_head_id === $mid,
                            'sales_count' => $stats ? (int) $stats->sales_count : 0,
                            'sale_amount' => $stats ? (float) $stats->sale_amount : 0.0,
                            'month_target' => $monthTarget,
                            'month_revenue' => $monthRev,
                            'target_achievement_pct' => self::achievementPercent($monthRev, $monthTarget),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'company_name' => $team->company?->name,
                    'members_count' => (int) ($userCounts[$tid] ?? 0),
                    'month_label' => Carbon::createFromDate($year, $month, 1)->format('F Y'),
                    'target' => $target,
                    'monthly_revenue' => $revenue,
                    'achievement_percent' => $pct,
                    'members' => $members,
                ];
            });
    }

    // ── Team Head Dashboard ──────────────────────────────────────────────────────

    private function teamHeadDashboard(User $user, int $month, int $year): View
    {
        $teams = Team::with(['company', 'users.role', 'teamHead'])
            ->where('team_head_id', $user->id)
            ->get();

        $teamDashboardCards = $this->buildTeamDashboardCards($month, $year)
            ->filter(fn ($card) => $teams->pluck('id')->contains($card['id']))
            ->values();

        // My own sales stats
        $mySales = [
            'total'     => Sale::where('user_id', $user->id)->count(),
            'completed' => self::approved()->where('user_id', $user->id)->where('status', 'completed')->count(),
            'pending'   => Sale::where('user_id', $user->id)->where('status', 'pending')->count(),
            'cancelled' => Sale::where('user_id', $user->id)->where('status', 'cancelled')->count(),
            'revenue'   => self::approved()->where('user_id', $user->id)->where('status', 'completed')->sum('amount'),
        ];

        $myTarget = UserTarget::where('user_id', $user->id)
            ->where('month', $month)->where('year', $year)->first();

        $company = $user->company;
        $companyStats = $company ? [
            'teams_count'  => Team::where('company_id', $company->id)->count(),
            'users_count'  => User::where('company_id', $company->id)->count(),
            'sales_count'  => self::approved()->where('company_id', $company->id)->count(),
            'revenue'      => self::approved()->where('company_id', $company->id)->where('status', 'completed')->sum('amount'),
        ] : [];

        $recentSales = Sale::where('user_id', $user->id)
            ->with(['team'])->latest()->limit(6)->get();

        // Charts: last 14 days revenue trend
        $agentTrendStart = now()->subDays(13)->startOfDay();
        $agentTrendEnd   = now()->endOfDay();
        $agentDailyRows  = self::approved()
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
            'labels' => ['Completed', 'Pending', 'Cancelled'],
            'values' => [$mySales['completed'], $mySales['pending'], $mySales['cancelled']],
        ];

        return view('admin.dashboard', compact(
            'user', 'teams', 'teamDashboardCards',
            'mySales', 'myTarget', 'recentSales', 'month', 'year',
            'company', 'companyStats',
            'agentRevenueTrendLabels', 'agentRevenueTrendValues', 'agentSalesByStatus'
        ))->with([
            'isTeamHeadView' => true,
            'team'           => $teams->first(),
            'teamTarget'     => null,
            'teamMembers'    => collect(),
            'userTargetAchievement' => ['target' => 0, 'achieved' => 0, 'percent' => null, 'label' => ''],
            'teamTargetAchievement' => ['target' => 0, 'achieved' => 0, 'percent' => null, 'label' => ''],
        ]);
    }

    // ── Agent Dashboard ──────────────────────────────────────────────────────────

    private function agentDashboard(User $user, int $month, int $year): View
    {
        $team = $user->team;
        $company = $user->company;

        // My sales stats — only approved count in revenue/completed
        $mySales = [
            'total' => Sale::where('user_id', $user->id)->count(),
            'completed' => self::approved()->where('user_id', $user->id)->where('status', 'completed')->count(),
            'pending' => Sale::where('user_id', $user->id)->where('status', 'pending')->count(),
            'cancelled' => Sale::where('user_id', $user->id)->where('status', 'cancelled')->count(),
            'revenue' => self::approved()->where('user_id', $user->id)->where('status', 'completed')->sum('amount'),
        ];

        // My target this month
        $myTarget = UserTarget::where('user_id', $user->id)
            ->where('month', $month)->where('year', $year)
            ->first();

        // Team target this month
        $teamTarget = $team ? TeamTarget::where('team_id', $team->id)
            ->where('month', $month)->where('year', $year)
            ->first() : null;

        $myTargetAmt = (float) ($myTarget?->target_amount ?? 0);
        $teamTargetAmt = (float) ($teamTarget?->target_amount ?? 0);

        $myMonthRevenue = self::monthlyCompletedRevenue($year, $month, fn (Builder $q) => $q->where('user_id', $user->id));
        $teamMonthRevenue = $team
            ? self::monthlyCompletedRevenue($year, $month, fn (Builder $q) => $q->where('team_id', $team->id))
            : 0.0;

        $userTargetAchievement = [
            'target' => $myTargetAmt,
            'achieved' => $myMonthRevenue,
            'percent' => self::achievementPercent($myMonthRevenue, $myTargetAmt),
            'label' => Carbon::createFromDate($year, $month, 1)->format('F Y'),
        ];
        $teamTargetAchievement = [
            'target' => $teamTargetAmt,
            'achieved' => $teamMonthRevenue,
            'percent' => self::achievementPercent($teamMonthRevenue, $teamTargetAmt),
            'label' => Carbon::createFromDate($year, $month, 1)->format('F Y'),
        ];

        // Team members — Agent ko nahi dikhna chahiye
        $teamMembers = collect();

        // Company info — only approved
        $companyStats = $company ? [
            'teams_count' => Team::where('company_id', $company->id)->count(),
            'users_count' => User::where('company_id', $company->id)->accountActive()->count(),
            'sales_count' => self::approved()->where('company_id', $company->id)->count(),
            'revenue' => self::approved()->where('company_id', $company->id)->where('status', 'completed')->sum('amount'),
        ] : [];

        // My recent sales (all — so agent can see pending ones too)
        $recentSales = Sale::where('user_id', $user->id)
            ->with(['team'])
            ->latest()
            ->limit(6)
            ->get();

        // Agent charts: last 14 days completed revenue
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
            'labels' => ['Completed', 'Pending', 'Cancelled'],
            'values' => [
                $mySales['completed'],
                $mySales['pending'],
                $mySales['cancelled'],
            ],
        ];

        return view('admin.dashboard', compact(
            'user', 'team', 'company',
            'mySales', 'myTarget', 'teamTarget',
            'teamMembers', 'companyStats', 'recentSales',
            'month', 'year',
            'agentRevenueTrendLabels', 'agentRevenueTrendValues', 'agentSalesByStatus',
            'userTargetAchievement', 'teamTargetAchievement'
        ))->with('teamDashboardCards', collect());
    }
}
