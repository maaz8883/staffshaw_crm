@extends('admin.layout')

@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-icon', 'graph-up-arrow')

@section('content')
@include('admin.reports._subnav')

@php
    $canViewOrgReports = $canViewOrgReports ?? false;
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h5 class="mb-1">{{ $canViewOrgReports ? 'Business reports' : 'Team reports' }}</h5>
        <p class="text-muted small mb-0">
            @if($canViewOrgReports)
                Full overview for {{ $monthLabel }}. Open any report for filters and detail.
            @else
                Your team overview for {{ $monthLabel }}. Figures are limited to teams you lead.
            @endif
        </p>
    </div>
    <a href="{{ route('admin.targets.index') }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-bullseye"></i> Sales Performance / Targets
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Revenue (MTD)</div>
                <div class="fs-4 fw-semibold">${{ number_format($kpis['revenue'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Received (MTD sales)</div>
                <div class="fs-4 fw-semibold">${{ number_format($kpis['received'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">{{ $canViewOrgReports ? 'Open AR (invoices)' : 'Sale balance open' }}</div>
                <div class="fs-4 fw-semibold text-warning">${{ number_format($kpis['open_ar'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Active leads</div>
                <div class="fs-4 fw-semibold">{{ number_format($kpis['active_leads']) }}</div>
            </div>
        </div>
    </div>
</div>

@php
    $salesCards = [
        ['route' => 'admin.reports.sales', 'icon' => 'cash-stack', 'title' => 'Sales report', 'desc' => 'Revenue trends, Front/Upsell, approvals & status'],
        ['route' => 'admin.reports.team', 'icon' => 'people', 'title' => 'Team report', 'desc' => 'Revenue and volume by team'],
        ['route' => 'admin.reports.user', 'icon' => 'person-badge', 'title' => 'Agent report', 'desc' => 'Agent leaderboard by revenue'],
        ['route' => 'admin.reports.brands', 'icon' => 'tags', 'title' => 'Brand report', 'desc' => 'Sales performance by brand'],
    ];
    if ($canViewOrgReports && ! ($singleCompany ?? false)) {
        $salesCards[] = ['route' => 'admin.reports.company', 'icon' => 'buildings', 'title' => 'Company report', 'desc' => 'Revenue broken down by company'];
    }
    $financeCards = [
        ['route' => 'admin.reports.invoices', 'icon' => 'receipt', 'title' => 'Invoices & payments', 'desc' => $canViewOrgReports ? 'AR invoices, receipts, paid vs open' : 'Team sale receipts & open balances'],
    ];
    if (auth()->user()?->hasRole('Admin')) {
        $financeCards[] = ['route' => 'admin.reports.expenses', 'icon' => 'wallet2', 'title' => 'Expenses report', 'desc' => 'Spend by category, billable vs not'];
    }
    $groups = [
        'Sales & revenue' => $salesCards,
        'Pipeline & clients' => [
            ['route' => 'admin.reports.leads', 'icon' => 'funnel', 'title' => 'Leads report', 'desc' => 'Pipeline by status, source & conversion'],
            ['route' => 'admin.reports.clients', 'icon' => 'people-fill', 'title' => 'Clients report', 'desc' => 'New clients and linked sales'],
        ],
        'Finance' => $financeCards,
    ];
@endphp

@foreach($groups as $groupTitle => $cards)
    <h6 class="text-muted text-uppercase small mb-2 mt-2">{{ $groupTitle }}</h6>
    <div class="row g-3 mb-4">
        @foreach($cards as $card)
        <div class="col-md-6 col-xl-3">
            <a href="{{ route($card['route']) }}" class="text-decoration-none text-body">
                <div class="card border-0 shadow-sm h-100 report-hub-card">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <div class="report-hub-icon flex-shrink-0">
                                <i class="bi bi-{{ $card['icon'] }}"></i>
                            </div>
                            <div>
                                <div class="fw-semibold mb-1">{{ $card['title'] }}</div>
                                <p class="text-muted small mb-0">{{ $card['desc'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
    </div>
@endforeach
@endsection

@push('styles')
<style>
    .report-hub-card {
        transition: transform .15s ease, border-color .15s ease;
        border: 1px solid var(--border-color) !important;
        background: var(--bg-card);
    }
    .report-hub-card:hover {
        transform: translateY(-2px);
        border-color: var(--brand-blue) !important;
    }
    .report-hub-icon {
        width: 48px;
        height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: var(--bg-hover);
        border: 1px solid var(--border-color);
        color: var(--brand-blue);
    }
    .report-hub-icon .bi {
        font-size: 1.35rem;
        color: var(--brand-blue);
        line-height: 1;
    }
</style>
@endpush
