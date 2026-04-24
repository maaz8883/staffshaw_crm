@extends('admin.layout')

@section('title', 'PPC Spending')
@section('page-title', 'PPC Spending')
@section('page-icon', 'graph-up')

@section('content')

@php $curMonth = DateTime::createFromFormat('!m', $month)->format('F') . ' ' . $year; @endphp

{{-- Month / Year Filter --}}
<form method="GET" action="{{ route('admin.ppc.index') }}" class="d-flex gap-2 align-items-end mb-4">
    <div>
        <label class="form-label mb-1">Month</label>
        <select name="month" class="form-select form-select-sm">
            @foreach(range(1,12) as $m)
                <option value="{{ $m }}" @selected($m == $month)>
                    {{ DateTime::createFromFormat('!m', $m)->format('F') }}
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
</form>

{{-- Stat cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-4">
                <div class="text-muted small mb-1">Total Spending — {{ $curMonth }}</div>
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

{{-- Per-team cards --}}
<div class="row g-3 mb-4">
    @foreach($teams as $team)
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
    @endforeach
</div>

{{-- Full history --}}
<div class="card border-0 shadow-sm">
    <div class="card-header">
        <i class="bi bi-clock-history"></i> All Entries — {{ $curMonth }}
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Team</th>
                        <th>Added By</th>
                        <th>Notes</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($spendings as $entry)
                    <tr>
                        <td class="text-muted small">{{ $entry->created_at->format('d M Y') }}</td>
                        <td>{{ $entry->team?->name ?? '-' }}</td>
                        <td class="text-muted small">{{ $entry->user?->name ?? '-' }}</td>
                        <td class="text-muted small">{{ $entry->notes ?: '-' }}</td>
                        <td class="text-end fw-semibold text-danger">${{ number_format($entry->amount, 2) }}</td>
                        <td class="text-end">
                            @if(auth()->user()->hasRole('Admin') || $entry->user_id === auth()->id())
                            <form method="POST"
                                action="{{ route('admin.ppc.destroy', $entry) }}"
                                class="d-inline js-admin-delete-form"
                                data-swal-title="Delete this entry?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-muted text-center py-4">No entries for {{ $curMonth }}.</td>
                    </tr>
                    @endforelse
                </tbody>
                @if($spendings->count() > 0)
                <tfoot class="table-light">
                    <tr>
                        <td colspan="4" class="fw-semibold text-end">Total</td>
                        <td class="text-end fw-bold text-danger">${{ number_format($totalSpending, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

@endsection
