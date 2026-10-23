<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Team;
use App\Models\TeamTarget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmOverview
{
    public function snapshot(?User $user): array
    {
        if (! $user || ! Schema::hasTable('expenses')) {
            return ['ready' => false];
        }

        if (! $user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return [
                'ready' => true,
                'limited' => true,
            ];
        }

        // Standalone AR uses sale_balance as open amount. Sale-linked invoices are receipts
        // (sale_balance is a sale snapshot at issue) — count them as paid, never as open AR.
        $issuedStandalone = Invoice::query()
            ->where('status', Invoice::STATUS_ISSUED)
            ->whereNull('sale_id');
        $issuedSaleLinked = Invoice::query()
            ->where('status', Invoice::STATUS_ISSUED)
            ->whereNotNull('sale_id');

        $openInvoices = (clone $issuedStandalone)->where('sale_balance', '>', 0)->count();
        $invoiceTotal = Invoice::query()->where('status', '!=', Invoice::STATUS_VOID)->count();
        $outstanding = (float) (clone $issuedStandalone)->where('sale_balance', '>', 0)->sum('sale_balance');
        $paidInvoices = (clone $issuedStandalone)->where('sale_balance', '<=', 0)->count()
            + (clone $issuedSaleLinked)->count();
        $overdueInvoices = (clone $issuedStandalone)
            ->where('sale_balance', '>', 0)
            ->whereDate('due_date', '<', today())
            ->count();
        $openNotOverdue = max(0, $openInvoices - $overdueInvoices);

        $leadTotal = Lead::query()->count();
        $converted = Lead::query()->where('status', 'customer')->count();
        $activeLeads = max(0, $leadTotal - $converted);

        $month = now()->month;
        $year = now()->year;
        \App\Support\TargetCarryForward::ensureMonth($month, $year);
        $monthRevenue = (float) Sale::query()
            ->where('approval_status', Sale::APPROVAL_APPROVED)
            ->where('status', 'completed')
            ->whereMonth('sale_date', $month)
            ->whereYear('sale_date', $year)
            ->sum('amount');
        $monthTarget = (float) TeamTarget::query()->where('month', $month)->where('year', $year)->sum('target_amount');

        $trendStart = now()->subMonths(5)->startOfMonth();
        $trendRows = Sale::query()
            ->where('approval_status', Sale::APPROVAL_APPROVED)
            ->where('status', 'completed')
            ->where('sale_date', '>=', $trendStart->toDateString())
            ->toBase()
            ->select(DB::raw('YEAR(sale_date) as y'), DB::raw('MONTH(sale_date) as m'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('YEAR(sale_date)'), DB::raw('MONTH(sale_date)'))
            ->get()
            ->keyBy(fn ($row) => $row->y . '-' . $row->m);
        $revenueLabels = [];
        $revenueValues = [];
        for ($i = 5; $i >= 0; $i--) {
            $point = now()->subMonths($i);
            $revenueLabels[] = $point->format('M');
            $key = $point->year . '-' . $point->month;
            $revenueValues[] = round((float) ($trendRows[$key]->total ?? 0), 2);
        }

        $recentSales = Sale::query()
            ->with('user:id,name')
            ->where('approval_status', Sale::APPROVAL_APPROVED)
            ->latest('sale_date')
            ->limit(5)
            ->get(['id', 'title', 'client_name', 'amount', 'status', 'sale_date', 'user_id']);
        $teams = Team::query()->sales()->with(['teamHead', 'leaders'])->whereNull('parent_team_id')->orderBy('name')->get();
        $teamCards = [];
        foreach ($teams as $team) {
            $target = (float) TeamTarget::query()
                ->where('team_id', $team->id)
                ->where('month', $month)
                ->where('year', $year)
                ->value('target_amount');
            $achieved = (float) Sale::query()
                ->where('team_id', $team->id)
                ->where('approval_status', Sale::APPROVAL_APPROVED)
                ->where('status', 'completed')
                ->whereMonth('sale_date', $month)
                ->whereYear('sale_date', $year)
                ->sum('amount');
            $teamCards[] = [
                'name' => $team->name,
                'lead' => $team->headNames() ?: null,
                'target' => $target,
                'achieved' => $achieved,
                'percent' => $target > 0 ? round(($achieved / $target) * 100, 1) : 0,
            ];
        }
        usort($teamCards, fn ($a, $b) => $b['achieved'] <=> $a['achieved']);

        $leadCounts = Lead::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'ready' => true,
            'limited' => false,
            'open_invoices' => $openInvoices,
            'invoice_total' => $invoiceTotal,
            'outstanding' => $outstanding,
            'converted' => $converted,
            'lead_total' => $leadTotal,
            'active_leads' => $activeLeads,
            'month_revenue' => $monthRevenue,
            'month_target' => $monthTarget,
            'month_label' => Carbon::createFromDate($year, $month, 1)->format('F'),
            'target_percent' => $monthTarget > 0 ? round(min(100, ($monthRevenue / $monthTarget) * 100), 1) : 0,
            'revenue_labels' => $revenueLabels,
            'revenue_values' => $revenueValues,
            'lead_counts' => $leadCounts,
            'invoice_chart' => [
                'labels' => ['Paid', 'Open', 'Overdue'],
                'values' => [$paidInvoices, $openNotOverdue, $overdueInvoices],
            ],
            'team_cards' => array_slice($teamCards, 0, 6),
            'recent_sales' => $recentSales,
        ];
    }
}
