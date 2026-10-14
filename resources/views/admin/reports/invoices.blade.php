@extends('admin.layout')

@section('title', 'Invoices & payments')
@section('page-title', 'Invoices & payments')
@section('page-icon', 'receipt')

@section('content')
@include('admin.reports._subnav')
@include('admin.reports._filters', [
    'action' => route('admin.reports.invoices'),
    'showCompany' => false,
    'showTeam' => false,
    'showAgent' => false,
])

@php $org = $canViewOrgReports ?? false; @endphp
<div class="row g-3 mb-4">
    @if($org)
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">AR invoices issued</div>
            <div class="fs-4 fw-semibold">${{ number_format($arIssued, 2) }}</div>
            <div class="text-muted small">{{ $standaloneCount }} invoice(s)</div>
        </div></div>
    </div>
    @endif
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">{{ $org ? 'Open AR balance' : 'Open sale balance' }}</div>
            <div class="fs-4 fw-semibold text-warning">${{ number_format($arOpen, 2) }}</div>
        </div></div>
    </div>
    @if($org)
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Overdue AR</div>
            <div class="fs-4 fw-semibold text-danger">${{ number_format($arOverdue, 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">AR payments received</div>
            <div class="fs-4 fw-semibold text-success">${{ number_format($paymentsInPeriod, 2) }}</div>
        </div></div>
    </div>
    @endif
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold">Sale payment receipts (period)</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted small">Receipts recorded</div>
                <div class="fs-5 fw-semibold">{{ number_format($saleReceiptsCount) }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Total amount received via receipts</div>
                <div class="fs-5 fw-semibold">${{ number_format($saleReceiptsTotal, 2) }}</div>
            </div>
        </div>
        <p class="text-muted small mb-0 mt-3">
            Standalone invoices = accounts receivable. Sale-linked rows = payment receipts on sales.
        </p>
    </div>
</div>
@endsection
