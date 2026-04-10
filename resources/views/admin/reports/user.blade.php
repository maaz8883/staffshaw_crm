@extends('admin.layout')

@section('title', 'User report')
@section('page-title', 'User report')
@section('page-icon', 'person-badge')

@section('content')
@include('admin.reports._subnav')

<form method="get" action="{{ route('admin.reports.user') }}" class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-auto">
                <label class="form-label small text-muted mb-0">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="{{ $filters['date_from'] ?? $dateFrom->toDateString() }}">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small text-muted mb-0">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="{{ $filters['date_to'] ?? $dateTo->toDateString() }}">
            </div>
            @if(!$isAgent && $companies->isNotEmpty())
            <div class="col-12 col-md-auto">
                <label class="form-label small text-muted mb-0">Company</label>
                <select name="company_id" class="form-select form-select-sm" style="min-width: 160px">
                    <option value="">All companies</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected(($filters['company_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-12 col-md-auto">
                <label class="form-label small text-muted mb-0">Team</label>
                <select name="team_id" class="form-select form-select-sm" style="min-width: 140px">
                    <option value="">All teams</option>
                    @foreach($teams as $t)
                        <option value="{{ $t->id }}" @selected(($filters['team_id'] ?? '') == $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <a href="{{ route('admin.reports.user') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
        <div class="text-muted small mt-2 mb-0">
            Period: <strong>{{ $dateFrom->format('M j, Y') }}</strong> — <strong>{{ $dateTo->format('M j, Y') }}</strong>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-people-fill text-primary"></i> Users &amp; revenue (completed &amp; approved sales)
    </div>
    <div class="card-body ">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Team</th>
                        <th>Company</th>
                        <th class="text-center">Completed sales</th>
                        <th class="text-end">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($userRows as $i => $row)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td class="fw-semibold">{{ $row['user']->name }}</td>
                        <td class="text-muted small">{{ $row['user']->team?->name ?? '—' }}</td>
                        <td class="text-muted small">{{ $row['user']->company?->name ?? '—' }}</td>
                        <td class="text-center">{{ number_format($row['count']) }}</td>
                        <td class="text-end text-success fw-semibold">${{ number_format($row['revenue'], 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted text-center py-4">No users in scope.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
