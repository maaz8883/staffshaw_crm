@extends('admin.layout')

@section('title', 'Invoice ' . $invoice->invoice_number)
@section('page-title', 'Invoice ' . $invoice->invoice_number)
@section('page-icon', 'receipt')

@section('content')
<div class="mb-3 d-print-none">
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
        <i class="bi bi-printer"></i> Print
    </button>
    <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-file-earmark-pdf"></i> Download PDF
    </a>
    @if($invoice->sale_id)
    <a href="{{ route('admin.sales.show', $invoice->sale_id) }}" class="btn btn-outline-secondary btn-sm">Back to Sale</a>
    @endif
    <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary btn-sm">All Invoices</a>
    @if($canEditDraft ?? false)
    <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn btn-outline-primary btn-sm">Edit Draft</a>
    <form action="{{ route('admin.invoices.issue', $invoice) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-success btn-sm">Issue Invoice</button>
    </form>
    @endif
    @if($canVoid)
    <form action="{{ route('admin.invoices.void', $invoice) }}" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Void this invoice?">
        @csrf
        <button type="submit" class="btn btn-outline-danger btn-sm">Void Invoice</button>
    </form>
    @endif
</div>

@if(! $invoice->sale_id && ! $invoice->isVoid() && $invoice->status !== 'draft' && $invoice->amountDue() > 0)
<div class="card mb-3 d-print-none">
    <div class="card-body">
        <h6>Record Payment</h6>
        <form method="POST" action="{{ route('admin.invoices.payment', $invoice) }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" min="0.01" max="{{ $invoice->amountDue() }}" name="amount" class="form-control" value="{{ $invoice->amountDue() }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date</label>
                <input type="date" name="paid_at" class="form-control" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Payment mode</label>
                <select name="payment_mode" class="form-select">
                    <option>Bank Wire</option>
                    <option>Cash</option>
                    <option>Card</option>
                    <option>Other</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Note</label>
                <input type="text" name="note" class="form-control" placeholder="Optional">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary">Save Payment</button>
            </div>
        </form>
    </div>
</div>
@endif

@if(! $invoice->sale_id && $invoice->payments->isNotEmpty())
<div class="card mb-3 d-print-none">
    <div class="card-header bg-white">
        <strong>Payment History</strong>
        <span class="text-muted small ms-2">Paid ${{ number_format($invoice->amountPaid(), 2) }} · Open ${{ number_format($invoice->amountDue(), 2) }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Mode</th>
                        <th>Note</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->payments->sortByDesc('paid_at') as $payment)
                    <tr>
                        <td>{{ $payment->paid_at?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $payment->payment_mode ?: '—' }}</td>
                        <td class="text-muted">{{ $payment->note ?: '—' }}</td>
                        <td class="text-end fw-semibold">${{ number_format($payment->amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="card invoice-print-card">
    <div class="card-body p-4">
        @include('admin.invoices._body')
    </div>
</div>
@endsection

@push('styles')
<style>
@media print {
    .sidebar, main > .page-header, .d-print-none, .alert, .toast-container, .live-notification-toast-wrap {
        display: none !important;
    }
    main {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .invoice-print-card {
        border: none !important;
        box-shadow: none !important;
    }
    .invoice-print-card .card-body {
        padding: 0 !important;
    }
}
</style>
@endpush
