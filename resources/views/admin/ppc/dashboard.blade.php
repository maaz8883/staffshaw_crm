@extends('admin.layout')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-icon', 'speedometer2')

@section('content')

@php $curMonth = $currentMonthLabel ?? (\Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y')); @endphp

<form method="GET" action="{{ route('admin.dashboard') }}" class="d-flex flex-wrap gap-2 align-items-end mb-4">
    <div>
        <label class="form-label mb-1">Month</label>
        <select name="month" class="form-select form-select-sm">
            @foreach(range(1, 12) as $m)
                <option value="{{ $m }}" @selected($m == $month)>
                    {{ \Carbon\Carbon::createFromDate(null, $m, 1)->format('F') }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label mb-1">Year</label>
        <select name="year" class="form-select form-select-sm">
            @foreach(range(now()->year - 1, now()->year + 1) as $y)
                <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="{{ route('admin.ppc.index', ['month' => $month, 'year' => $year]) }}" class="btn btn-outline-secondary btn-sm">
        Full PPC log
    </a>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">PPC spend — {{ $curMonth }}</div>
                <div class="display-6 fw-bold text-danger">${{ number_format($totalSpending, 0) }}</div>
                <div class="text-muted small mt-1">
                    @if($spendChangePct === null && $totalSpending > 0)
                        No spend last month
                    @elseif($spendChangePct !== null)
                        @if($spendChangePct > 0)
                            <span class="text-danger">↑ {{ $spendChangePct }}%</span> vs prior month
                        @elseif($spendChangePct < 0)
                            <span class="text-success">↓ {{ abs($spendChangePct) }}%</span> vs prior month
                        @else
                            Same as prior month
                        @endif
                        <span class="text-muted">(${{ number_format($prevTotalSpending, 0) }})</span>
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">Prior month spend</div>
                <div class="display-6 fw-bold text-primary">${{ number_format($prevTotalSpending, 0) }}</div>
                <div class="text-muted small mt-1">Previous period</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">Entries logged</div>
                <div class="display-6 fw-bold text-info">{{ $entriesCount }}</div>
                <div class="text-muted small mt-1">{{ $curMonth }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    @forelse($teams as $team)
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="fw-semibold fs-5">{{ $team->name }}</div>
                        @unless($singleCompany)
                        <div class="text-muted small">{{ $team->company?->name ?? '—' }}</div>
                        @endunless
                    </div>
                    <span class="badge bg-danger px-3 py-2">${{ number_format($team->month_spending, 0) }} PPC</span>
                </div>

                <div class="row g-2 small mb-3">
                    <div class="col-6">
                        <div class="text-muted">Vs prior month</div>
                        <div class="fw-semibold">
                            @if($team->spend_change_pct === null && $team->month_spending > 0)
                                New
                            @elseif($team->spend_change_pct !== null)
                                {{ $team->spend_change_pct > 0 ? '+' : '' }}{{ $team->spend_change_pct }}%
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Prior month spend</div>
                        <div class="fw-semibold">${{ number_format($team->prev_month_spending, 0) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Entries</div>
                        <div class="fw-semibold">{{ $team->entries_count }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">This month</div>
                        <div class="fw-semibold">${{ number_format($team->month_spending, 0) }}</div>
                    </div>
                </div>

                <a href="{{ route('admin.teams.show', $team) }}?month={{ $month }}&year={{ $year }}"
                   class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-plus-circle"></i> Add / View spending
                </a>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-info mb-0">No team assigned. Ask Admin to set your team.</div>
    </div>
    @endforelse
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> Recent entries — {{ $curMonth }}</span>
        <a href="{{ route('admin.ppc.index', ['month' => $month, 'year' => $year]) }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Period</th>
                        <th>Team</th>
                        <th>Added by</th>
                        <th>Notes</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentSpendings as $entry)
                    <tr>
                        <td class="text-muted small">{{ \Carbon\Carbon::createFromDate($entry->year, $entry->month, 1)->format('M Y') }}</td>
                        <td>{{ $entry->team?->name ?? '—' }}</td>
                        <td class="text-muted small">{{ $entry->user?->name ?? '—' }}</td>
                        <td class="text-muted small">{{ $entry->notes ?: '—' }}</td>
                        <td class="text-end fw-semibold text-danger">${{ number_format($entry->amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted text-center py-4">No entries for {{ $curMonth }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
