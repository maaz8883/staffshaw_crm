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

        if ($user->hasRole('Agent')) {
            return $this->agentDashboard($user, $month, $year);
        }

        return $this->adminDashboard($month, $year);
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
            'total_users' => User::count(),
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
        $salesByStatus = collect(Sale::STATUSES)->mapWithKeys(fn ($s) => [
            $s => self::approved()->where('status', $s)->count(),
        ]);

        // Recent approved sales
        $recentSales = self::approved()
            ->with(['user', 'team'])
            ->latest()
            ->limit(8)
            ->get();

        // Per-company breakdown — only approved
        $companies = Company::withCount(['users', 'teams'])
            ->with(['teams' => fn ($q) => $q->withCount('users')])
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

        return view('admin.dashboard', compact(
            'stats', 'salesByStatus', 'recentSales', 'companies', 'topAgents', 'month', 'year',
            'revenueTrendLabels', 'revenueTrendValues', 'companyBarLabels', 'companyBarValues', 'approvalMix',
            'targetAchievement'
        ));
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

        // Team members — only approved sales count
        $teamMembers = $team
            ? User::where('team_id', $team->id)
                ->with('role')
                ->get()
                ->map(function ($member) use ($month, $year) {
                    $member->sales_count = self::approved()->where('user_id', $member->id)->where('status', 'completed')->count();
                    $member->sale_amount = self::approved()->where('user_id', $member->id)->where('status', 'completed')->sum('amount');
                    $member->month_target = UserTarget::where('user_id', $member->id)
                        ->where('month', $month)->where('year', $year)
                        ->value('target_amount') ?? 0;
                    $member->month_revenue = self::monthlyCompletedRevenue($year, $month, fn (Builder $q) => $q->where('user_id', $member->id));
                    $member->target_achievement_pct = self::achievementPercent($member->month_revenue, (float) $member->month_target);

                    return $member;
                })
            : collect();

        // Company info — only approved
        $companyStats = $company ? [
            'teams_count' => Team::where('company_id', $company->id)->count(),
            'users_count' => User::where('company_id', $company->id)->count(),
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
        ));
    }
}
