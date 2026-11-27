<?php

namespace App\Services\CompanyOs;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\Team;
use App\Models\TeamPpcSpending;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Company-wide figures for CompanyOS. Definitions mirror the CRM finance dashboard
 * (Admin\DashboardController::financeDashboard) so both show the same numbers:
 * - booked: approved, not refunded sales by sale_date (USD)
 * - received: sale-linked receipts by issued_at + invoice payments by paid_at, void invoices excluded (USD)
 * - ppc: team PPC spend for every month the range touches (USD)
 * - expenses: office expenses by expense_date (PKR)
 */
class ServiceSummary
{
    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $range = [$from->toDateString(), $to->toDateString()];
        $notVoid = fn ($q) => $q->where('status', '!=', Invoice::STATUS_VOID);

        $bookedQuery = $this->approved()->whereBetween('sale_date', $range)
            ->where('is_refunded', false)->where('is_chargeback', false);
        $booked = (float) (clone $bookedQuery)->sum('amount');
        $salesCount = (clone $bookedQuery)->count();

        $completedRevenue = (float) $this->approved()
            ->where('status', 'completed')
            ->whereBetween('sale_date', $range)
            ->sum('amount');

        $receiptsIn = (float) Invoice::query()->whereNotNull('sale_id')->tap($notVoid)
            ->whereBetween('issued_at', $range)->sum('amount');
        $paymentsIn = (float) InvoicePayment::query()
            ->whereBetween('paid_at', $range)
            ->whereHas('invoice', $notVoid)
            ->sum('amount');
        $received = round($receiptsIn + $paymentsIn, 2);

        $refunds = Sale::query()->where('is_refunded', true)
            ->whereBetween('refunded_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        $chargebacks = Sale::query()->where('is_chargeback', true)
            ->whereBetween('chargeback_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $ppc = (float) TeamPpcSpending::query()
            ->whereRaw('(year * 100 + month) between ? and ?', [
                $from->year * 100 + $from->month,
                $to->year * 100 + $to->month,
            ])
            ->sum('amount');

        $openSales = $this->approved()->where('is_refunded', false)->where('is_chargeback', false)
            ->whereColumn('received_amount', '<', 'amount');
        $openInvoices = Invoice::query()->whereNull('sale_id')
            ->where('status', Invoice::STATUS_ISSUED)
            ->with('payments')->get()
            ->filter(fn (Invoice $i) => $i->amountDue() > 0);
        $overdue = $openInvoices->filter(fn (Invoice $i) => $i->due_date && $i->due_date->isPast());

        $newLeads = Lead::query()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        return [
            'period' => ['from' => $range[0], 'to' => $range[1]],
            'currency' => ['sales' => 'USD', 'ppc' => 'USD', 'expenses' => 'PKR'],
            'sales' => [
                'booked' => round($booked, 2),
                'count' => $salesCount,
                'completed_revenue' => round($completedRevenue, 2),
                'received' => $received,
                'refunds_total' => round((float) (clone $refunds)->sum('amount'), 2),
                'refunds_count' => (clone $refunds)->count(),
                'chargebacks_total' => round((float) (clone $chargebacks)->sum('amount'), 2),
                'chargebacks_count' => (clone $chargebacks)->count(),
                'awaiting_approval_count' => Sale::query()
                    ->where('approval_status', Sale::APPROVAL_PENDING)
                    ->where('is_draft', false)
                    ->count(),
            ],
            'ppc' => [
                'spend' => round($ppc, 2),
                'net_cash' => round($received - $ppc, 2),
            ],
            'receivables' => [
                'open_sale_balance' => round((float) (clone $openSales)->sum(DB::raw('amount - received_amount')), 2),
                'open_sales_count' => (clone $openSales)->count(),
                'open_invoices_count' => $openInvoices->count(),
                'open_invoices_amount' => round($openInvoices->sum(fn (Invoice $i) => $i->amountDue()), 2),
                'overdue_invoices_count' => $overdue->count(),
                'overdue_invoices_amount' => round($overdue->sum(fn (Invoice $i) => $i->amountDue()), 2),
            ],
            'leads' => [
                'new_count' => (clone $newLeads)->count(),
                'new_by_status' => (clone $newLeads)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
                'new_by_source' => (clone $newLeads)->selectRaw('source, COUNT(*) as total')->groupBy('source')->pluck('total', 'source'),
                'follow_ups_due_count' => Lead::query()
                    ->whereNotNull('follow_up_at')
                    ->whereDate('follow_up_at', '<=', now()->toDateString())
                    ->whereNotIn('status', ['customer', 'lost'])
                    ->count(),
            ],
            'expenses' => [
                'total' => round((float) Expense::query()->whereBetween('expense_date', $range)->sum('amount'), 2),
                'count' => Expense::query()->whereBetween('expense_date', $range)->count(),
            ],
            'by_team' => $this->byTeam($range),
        ];
    }

    private function byTeam(array $range): array
    {
        $rows = $this->approved()
            ->whereBetween('sale_date', $range)
            ->where('is_refunded', false)
            ->where('is_chargeback', false)
            ->selectRaw('team_id, COUNT(*) as sales_count, SUM(amount) as booked, SUM(received_amount) as received')
            ->groupBy('team_id')
            ->get();

        $names = Team::query()->whereIn('id', $rows->pluck('team_id')->filter())->pluck('name', 'id');

        return $rows->map(fn ($r) => [
            'team_id' => $r->team_id !== null ? (int) $r->team_id : null,
            'team_name' => $r->team_id !== null ? ($names[$r->team_id] ?? null) : null,
            'sales_count' => (int) $r->sales_count,
            'booked' => round((float) $r->booked, 2),
            'received' => round((float) $r->received, 2),
        ])->sortByDesc('booked')->values()->all();
    }

    private function approved(): Builder
    {
        return Sale::query()->where('approval_status', Sale::APPROVAL_APPROVED);
    }
}
