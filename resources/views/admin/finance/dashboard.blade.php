@extends('admin.layout')

@section('title', 'Dashboard')
@section('page-title', 'Finance Dashboard')
@section('page-icon', 'cash-coin')

@section('content')
@php $m = fn ($v) => '$'.number_format((float) $v, 2); @endphp
<style>
    .fin-tile .num { font-size: 1.6rem; font-weight: 700; line-height: 1.15; }
    .fin-tile .sub { font-size: 12px; opacity: .7; }
    .fin-tile a { color: inherit; text-decoration: none; }
    .fin-list li { font-size: 13px; padding: 8px 0; border-bottom: 1px solid var(--border-color, rgba(0,0,0,.08)); }
    .fin-list li:last-child { border-bottom: 0; }
    .fin-list a { color: inherit; text-decoration: none; }
    .fin-list a:hover .name { text-decoration: underline; }
    .fin-list .meta { font-size: 11px; opacity: .65; }
    .fin-list .amt { font-weight: 700; white-space: nowrap; }
    .fin-net-pos { color: #16a34a; }
    .fin-net-neg { color: #dc2626; }
</style>

<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <form method="GET" action="{{ route('admin.dashboard') }}" class="d-flex gap-2 align-items-center">
        <select name="month" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            @for($i = 1; $i <= 12; $i++)
                <option value="{{ $i }}" @selected($i === $month)>{{ \DateTime::createFromFormat('!m', $i)->format('F') }}</option>
            @endfor
        </select>
        <select name="year" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            @for($y = now()->year + 1; $y >= now()->year - 4; $y--)
                <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
            @endfor
        </select>
    </form>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('admin.invoices.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-receipt"></i> New invoice</a>
        <a href="{{ route('admin.expenses.create') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-wallet2"></i> Add expense</a>
    </div>
</div>

<h6 class="text-muted text-uppercase small mb-2">{{ $monthLabel }}</h6>
<div class="row g-3 mb-4">
    @foreach([
        ['Sales booked', $m($stats['booked']), $stats['sales_count'].' approved sale'.($stats['sales_count'] === 1 ? '' : 's'), route('admin.sales.index'), ''],
        ['Cash received', $m($stats['received']), 'Sale receipts + invoice payments', route('admin.invoices.index'), 'text-success'],
        ['Expenses', \App\Models\Expense::money($stats['expenses']), 'Daily expenses (in rupees)', route('admin.expenses.index'), ''],
        ['PPC spend', $m($stats['ppc']), 'All teams', route('admin.ppc.index'), ''],
        ['Net cash flow (USD)', $m($stats['net']), 'Received − PPC · rupee expenses not included', null, $stats['net'] >= 0 ? 'fin-net-pos' : 'fin-net-neg'],
        ['Refunds', $m($stats['refunds']), $stats['refund_count'].' sale'.($stats['refund_count'] === 1 ? '' : 's').' refunded', route('admin.sales.index'), $stats['refunds'] > 0 ? 'text-danger' : ''],
    ] as [$label, $value, $sub, $url, $class])
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 fin-tile"><div class="card-body">
                @if($url)<a href="{{ $url }}">@endif
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="num {{ $class }}">{{ $value }}</div>
                    <div class="sub">{{ $sub }}</div>
                @if($url)</a>@endif
            </div></div>
        </div>
    @endforeach
</div>

<h6 class="text-muted text-uppercase small mb-2">Money owed (all time)</h6>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 fin-tile"><div class="card-body">
            <div class="text-muted small">Unpaid sale balances</div>
            <div class="num">{{ $m($stats['sale_balance']) }}</div>
            <div class="sub">Approved sales not fully received</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 fin-tile"><div class="card-body">
            <div class="text-muted small">Open invoices</div>
            <div class="num">{{ $m($stats['invoice_balance']) }}</div>
            <div class="sub">Issued invoices with a balance due</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 fin-tile"><div class="card-body">
            <div class="text-muted small">Overdue invoices</div>
            <div class="num {{ $stats['overdue_total'] > 0 ? 'text-danger' : '' }}">{{ $m($stats['overdue_total']) }}</div>
            <div class="sub">Past their due date</div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Overdue invoices</div>
            <div class="card-body">
                @if($overdue->isEmpty())
                    <div class="text-muted small">Nothing overdue 🎉</div>
                @else
                    <ul class="list-unstyled mb-0 fin-list">
                        @foreach($overdue as $inv)
                            <li><a href="{{ route('admin.invoices.show', $inv) }}" class="d-flex justify-content-between gap-2">
                                <span><span class="name">{{ $inv->client_name }}</span>
                                    <div class="meta">{{ $inv->invoice_number }} · due {{ $inv->due_date->format('M d') }} ({{ $inv->due_date->diffForHumans() }})</div></span>
                                <span class="amt text-danger">{{ $m($inv->amountDue()) }}</span>
                            </a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Largest unpaid sale balances</div>
            <div class="card-body">
                @if($topOpenSales->isEmpty())
                    <div class="text-muted small">Every approved sale is fully received.</div>
                @else
                    <ul class="list-unstyled mb-0 fin-list">
                        @foreach($topOpenSales as $sale)
                            <li><a href="{{ route('admin.sales.show', $sale) }}" class="d-flex justify-content-between gap-2">
                                <span><span class="name">{{ $sale->client_name ?: $sale->title }}</span>
                                    <div class="meta">{{ $sale->brand?->name ?? '—' }} · {{ $sale->user?->name ?? '—' }} · {{ $m($sale->received_amount) }} of {{ $m($sale->amount) }}</div></span>
                                <span class="amt">{{ $m($sale->remainingAmount()) }}</span>
                            </a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Recent payments in</div>
            <div class="card-body">
                @if($recent->isEmpty())
                    <div class="text-muted small">No payments recorded yet.</div>
                @else
                    <ul class="list-unstyled mb-0 fin-list">
                        @foreach($recent as $r)
                            <li><a href="{{ $r['url'] }}" class="d-flex justify-content-between gap-2">
                                <span><span class="name">{{ $r['who'] }}</span>
                                    <div class="meta">{{ $r['what'] }} · {{ optional($r['when'])->format('M d, Y') }}</div></span>
                                <span class="amt text-success">{{ $m($r['amount']) }}</span>
                            </a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
