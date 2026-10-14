<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Sale;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function invoicedTotal(Sale $sale): float
    {
        return round((float) Invoice::query()
            ->where('sale_id', $sale->id)
            ->where('status', '!=', Invoice::STATUS_VOID)
            ->sum('amount'), 2);
    }

    /** Align legacy sales where invoices exist but received_amount was not updated. */
    public function syncReceivedAmount(Sale $sale): Sale
    {
        $invoiced = $this->invoicedTotal($sale);

        if (round((float) $sale->received_amount, 2) < $invoiced) {
            $sale->update(['received_amount' => $invoiced]);
            $sale->refresh();
        }

        return $sale;
    }

    public function maxReceivableAmount(Sale $sale): float
    {
        return round(max(0, min($sale->remainingAmount(), $this->billableRemaining($sale))), 2);
    }

    public function billableRemaining(Sale $sale): float
    {
        return round(max(0, (float) $sale->amount - $this->invoicedTotal($sale)), 2);
    }

    public function generateNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "INV-{$year}-";

        return DB::transaction(function () use ($prefix) {
            $lastNumber = Invoice::query()
                ->where('invoice_number', 'like', $prefix . '%')
                ->orderByDesc('invoice_number')
                ->lockForUpdate()
                ->value('invoice_number');

            $seq = 1;

            if (is_string($lastNumber) && preg_match('/-(\d+)$/', $lastNumber, $matches)) {
                $seq = (int) $matches[1] + 1;
            }

            return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        });
    }

    public function canGenerateForSale(Sale $sale): bool
    {
        if ($sale->is_draft) {
            return false;
        }

        if (! $sale->isApproved()) {
            return false;
        }

        if ($sale->is_refunded || $sale->status === Sale::STATUS_REFUNDED) {
            return false;
        }

        return $sale->remainingAmount() > 0 && $this->billableRemaining($sale) > 0;
    }

    public function createFromSale(Sale $sale, float $amount, User $creator): Invoice
    {
        $sale = $this->syncReceivedAmount($sale->fresh());

        if (! $this->canGenerateForSale($sale)) {
            throw ValidationException::withMessages([
                'amount' => 'Cannot generate an invoice for this sale.',
            ]);
        }

        $amount = round($amount, 2);
        $maxAmount = $this->maxReceivableAmount($sale);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Received amount must be greater than zero.',
            ]);
        }

        // Compare cents (round to 2dp) — raw float can make exact remaining fail (e.g. 963.95).
        if ($amount > $maxAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Received amount cannot exceed $' . number_format($maxAmount, 2) . '.',
            ]);
        }

        return DB::transaction(function () use ($sale, $amount, $creator) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $sale->loadMissing(['user', 'team', 'company', 'brand']);

            $newReceived = round((float) $sale->received_amount + $amount, 2);
            $newBalance = round(max(0, (float) $sale->amount - $newReceived), 2);

            $invoice = Invoice::query()->create([
                'sale_id'         => $sale->id,
                'client_id'       => $sale->client_id,
                'invoice_number'  => $this->generateNumber(),
                'issued_at'       => now()->toDateString(),
                'amount'          => $amount,
                'currency'        => 'USD',
                'client_name'     => $sale->client_name,
                'client_email'    => $sale->client_email,
                'client_phone'    => $sale->client_phone,
                'title'           => $sale->title,
                'notes'           => $sale->notes,
                'brand_id'        => $sale->brand_id,
                'brand_name'      => $sale->brand?->name,
                'agent_name'      => $sale->user?->name,
                'team_name'       => $sale->team?->name,
                'company_name'    => $sale->company?->name,
                'sale_total'      => $sale->amount,
                'sale_received'   => $newReceived,
                'sale_balance'    => $newBalance,
                'status'          => Invoice::STATUS_ISSUED,
                'created_by'      => $creator->id,
            ]);

            $sale->update(['received_amount' => $newReceived]);

            ActivityLogger::log($creator, UserActivityLog::TYPE_INVOICE_CREATED,
                "Created invoice {$invoice->invoice_number} for sale #{$sale->id} (received \${$amount})",
                ['invoice_id' => $invoice->id, 'sale_id' => $sale->id, 'amount' => $amount]
            );

            return $invoice;
        });
    }

    public function void(Invoice $invoice, User $user): Invoice
    {
        if ($invoice->isVoid()) {
            throw ValidationException::withMessages([
                'invoice' => 'This invoice is already void.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $user) {
            $sale = Sale::query()->lockForUpdate()->find($invoice->sale_id);

            if ($sale !== null) {
                $newReceived = round(max(0, (float) $sale->received_amount - (float) $invoice->amount), 2);
                $sale->update(['received_amount' => $newReceived]);
            } else {
                // Standalone: drop payments so voided invoices do not keep AR/payment history active
                $invoice->payments()->delete();
                $invoice->update([
                    'sale_received' => 0,
                    'sale_balance' => 0,
                ]);
            }

            $invoice->update(['status' => Invoice::STATUS_VOID]);

            ActivityLogger::log($user, UserActivityLog::TYPE_INVOICE_VOIDED,
                "Voided invoice {$invoice->invoice_number}" . ($invoice->sale_id ? " (sale #{$invoice->sale_id})" : ''),
                ['invoice_id' => $invoice->id, 'sale_id' => $invoice->sale_id, 'amount' => $invoice->amount]
            );

            return $invoice->fresh();
        });
    }
}
