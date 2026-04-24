@extends('admin.layout')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-icon', 'speedometer2')

@section('content')

@php $curMonth = DateTime::createFromFormat('!m', $month)->format('F') . ' ' . $year; @endphp

{{-- Total spending card --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">Total PPC Spending — {{ $curMonth }}</div>
                <div class="display-6 fw-bold text-danger">${{ number_format($totalSpending, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">Teams Tracked</div>
                <div class="display-6 fw-bold text-primary">{{ $teams->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">Teams with Spending</div>
                <div class="display-6 fw-bold text-warning">{{ $teams->where('month_spending', '>', 0)->count() }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Per-team spending cards --}}
<div class="row g-3 mb-4">
    @forelse($teams as $team)
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-semibold">{{ $team->name }}</div>
                        <div class="text-muted small">{{ $team->company?->name ?? '—' }}</div>
                    </div>
                    @if($team->month_spending > 0)
                        <span class="badge bg-danger">${{ number_format($team->month_spending, 0) }}</span>
                    @else
                        <span class="badge bg-light text-muted">No spending</span>
                    @endif
                </div>
                @if($totalSpending > 0 && $team->month_spending > 0)
                @php $pct = round(($team->month_spending / $totalSpending) * 100, 1); @endphp
                <div class="progress rounded-pill mt-2" style="height:6px">
                    <div class="progress-bar bg-danger rounded-pill" style="width:{{ $pct }}%"></div>
                </div>
                <div class="text-muted small mt-1">{{ $pct }}% of total</div>
                @endif
                <div class="mt-2">
                    <a href="{{ route('admin.teams.show', $team) }}?month={{ $month }}&year={{ $year }}"
                       class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-plus-circle"></i> Add / View
                    </a>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-info">No teams found.</div>
    </div>
    @endforelse
</div>

{{-- My recent entries --}}
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> My Recent Entries</span>
        <a href="{{ route('admin.ppc.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Team</th>
                        <th>Notes</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentSpendings as $entry)
                    <tr>
                        <td class="text-muted small">{{ $entry->created_at->format('d M Y') }}</td>
                        <td>{{ $entry->team?->name ?? '-' }}</td>
                        <td class="text-muted small">{{ $entry->notes ?: '-' }}</td>
                        <td class="text-end fw-semibold text-danger">${{ number_format($entry->amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted text-center py-4">No entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
