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
    <a href="{{ route('admin.sales.show', $invoice->sale_id) }}" class="btn btn-outline-secondary btn-sm">Back to Sale</a>
    <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary btn-sm">All Invoices</a>
    @if($canVoid)
    <form action="{{ route('admin.invoices.void', $invoice) }}" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Void this invoice?">
        @csrf
        <button type="submit" class="btn btn-outline-danger btn-sm">Void Invoice</button>
    </form>
    @endif
</div>

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
