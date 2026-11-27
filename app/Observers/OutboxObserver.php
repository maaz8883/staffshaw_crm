<?php

namespace App\Observers;

use App\Models\InvoicePayment;
use App\Models\Lead;
use App\Models\Sale;
use App\Support\Outbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns model changes into outbox events. Registered in AppServiceProvider.
 *
 * Note: mass updates (Model::query()->update([...])) skip model events, so they
 * produce no outbox rows. All current create/approve/refund paths save models individually.
 */
class OutboxObserver
{
    public function created(Model $model): void
    {
        $this->safely('created', $model, function () use ($model) {
            match (true) {
                $model instanceof Sale => $this->saleCreated($model),
                $model instanceof Lead => Outbox::record('lead.created', 'lead', $model->id, $this->leadPayload($model)),
                $model instanceof InvoicePayment => $this->paymentCreated($model),
                default => null,
            };
        });
    }

    public function updated(Model $model): void
    {
        $this->safely('updated', $model, function () use ($model) {
            if ($model instanceof Sale) {
                $this->saleUpdated($model);
            } elseif ($model instanceof Lead && $model->wasChanged('status')) {
                Outbox::record('lead.status_changed', 'lead', $model->id, $this->leadPayload($model) + [
                    'previous_status' => $model->getOriginal('status'),
                ]);
            }
        });
    }

    /**
     * Building an event must never stop a sale, lead or payment from saving
     * (e.g. a legacy row with an unparseable date). Any error is logged and ignored.
     */
    private function safely(string $hook, Model $model, \Closure $work): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            Log::error('Outbox observer skipped an event', [
                'hook' => $hook,
                'model' => $model::class,
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function saleCreated(Sale $sale): void
    {
        if (! $sale->is_draft) {
            Outbox::record('sale.submitted', 'sale', $sale->id, $this->salePayload($sale));
        }
    }

    private function saleUpdated(Sale $sale): void
    {
        if ($sale->wasChanged('is_draft') && ! $sale->is_draft) {
            Outbox::record('sale.submitted', 'sale', $sale->id, $this->salePayload($sale));
        }

        if ($sale->wasChanged('approval_status')) {
            $type = match ($sale->approval_status) {
                Sale::APPROVAL_APPROVED => 'sale.approved',
                Sale::APPROVAL_REJECTED => 'sale.rejected',
                default => null,
            };
            if ($type !== null) {
                Outbox::record($type, 'sale', $sale->id, $this->salePayload($sale));
            }
        }

        if ($sale->wasChanged('is_refunded') && $sale->is_refunded) {
            Outbox::record('sale.refunded', 'sale', $sale->id, $this->salePayload($sale));
        }

        if ($sale->wasChanged('is_chargeback') && $sale->is_chargeback) {
            Outbox::record('sale.chargeback', 'sale', $sale->id, $this->salePayload($sale));
        }
    }

    private function paymentCreated(InvoicePayment $payment): void
    {
        $invoice = $payment->invoice;

        Outbox::record('invoice.payment_received', 'invoice', (int) $payment->invoice_id, [
            'payment_id' => $payment->id,
            'invoice_id' => (int) $payment->invoice_id,
            'invoice_number' => $invoice?->invoice_number,
            'client_id' => $invoice?->client_id,
            'client_name' => $invoice?->client_name,
            'amount' => (float) $payment->amount,
            'currency' => $invoice?->currency ?: 'USD',
            'paid_at' => optional($payment->paid_at)->toDateString(),
            'payment_mode' => $payment->payment_mode,
        ]);
    }

    private function salePayload(Sale $sale): array
    {
        return [
            'sale_id' => $sale->id,
            'title' => $sale->title,
            'client_id' => $sale->client_id,
            'client_name' => $sale->client_name,
            'amount' => (float) $sale->amount,
            'received_amount' => (float) $sale->received_amount,
            'currency' => 'USD',
            'sale_date' => optional($sale->sale_date)->toDateString(),
            'sale_type' => $sale->sale_type,
            'status' => $sale->status,
            'approval_status' => $sale->approval_status,
            'is_ppc' => (bool) $sale->is_ppc,
            'user_id' => $sale->user_id,
            'agent_name' => $sale->sales_agent_name,
            'team_id' => $sale->team_id,
            'brand_id' => $sale->brand_id,
        ];
    }

    private function leadPayload(Lead $lead): array
    {
        return [
            'lead_id' => $lead->id,
            'name' => $lead->name,
            'company' => $lead->company,
            'source' => $lead->source,
            'status' => $lead->status,
            'lead_value' => $lead->lead_value !== null ? (float) $lead->lead_value : null,
            'assigned_to' => $lead->assigned_to,
            'team_id' => $lead->team_id,
            'brand_id' => $lead->brand_id,
            'follow_up_at' => optional($lead->follow_up_at)->toDateString(),
        ];
    }
}
