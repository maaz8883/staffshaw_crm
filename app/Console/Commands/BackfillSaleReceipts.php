<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Console\Command;

/**
 * Approved sales whose "Received amount" (typed on the sale form) never got a receipt:
 * shows them, and with --apply issues the missing receipt for each.
 */
class BackfillSaleReceipts extends Command
{
    protected $signature = 'crm:backfill-sale-receipts {--apply : Create the receipts (default only lists them)}';

    protected $description = 'Issue receipts for money received on approved sales that has no receipt yet';

    public function handle(InvoiceService $invoices): int
    {
        $rows = [];
        $sales = Sale::query()
            ->where('approval_status', Sale::APPROVAL_APPROVED)
            ->where('received_amount', '>', 0)
            ->where('is_draft', false)
            ->where(fn ($q) => $q->whereNull('is_refunded')->orWhere('is_refunded', false))
            ->orderBy('id')
            ->get();

        foreach ($sales as $sale) {
            $missing = round(min((float) $sale->received_amount, (float) $sale->amount) - $invoices->invoicedTotal($sale), 2);
            if ($missing <= 0) {
                continue;
            }
            $receipt = null;
            if ($this->option('apply')) {
                // Issued by whoever approved the sale (else its agent), dated the approval day
                $creator = User::find($sale->approved_by) ?? User::find($sale->user_id);
                if ($creator) {
                    $receipt = $invoices->receiptForReceivedOnSale($sale, $creator, optional($sale->approved_at)->toDateString());
                }
            }
            $rows[] = ['#'.$sale->id, $sale->title, number_format((float) $sale->amount, 2), number_format((float) $sale->received_amount, 2),
                number_format($missing, 2), $receipt?->invoice_number ?? ($this->option('apply') ? 'skipped' : '—')];
        }

        if (! $rows) {
            $this->info('Every approved sale already has receipts for the money received.');

            return self::SUCCESS;
        }
        $this->table(['Sale', 'Title', 'Total', 'Received', 'No receipt for', 'Receipt'], $rows);
        $this->line($this->option('apply')
            ? count($rows).' sale(s) processed.'
            : 'Nothing was changed. Run again with --apply to create these receipts.');

        return self::SUCCESS;
    }
}
