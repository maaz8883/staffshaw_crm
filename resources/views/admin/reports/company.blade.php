@extends('admin.layout')

@section('title', 'Company report')
@section('page-title', 'Company report')
@section('page-icon', 'buildings')

@section('content')
@include('admin.reports._subnav')

<form method="get" action="{{ route('admin.reports.company') }}" class="card border-0 shadow-sm mb-4">
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
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <a href="{{ route('admin.reports.company') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
        <div class="text-muted small mt-2 mb-0">
            Period: <strong>{{ $dateFrom->format('M j, Y') }}</strong> — <strong>{{ $dateTo->format('M j, Y') }}</strong>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-buildings text-primary"></i> Companies
    </div>
    <div class="card-body ">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Company</th>
                        <th class="text-center">Teams</th>
                        <th class="text-center">Users</th>
                        <th class="text-center">Sales in period</th>
                        <th class="text-center">Pending approval</th>
                        <th class="text-center">Completed</th>
                        <th class="text-end">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($companyRows as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['company']->name }}</td>
                        <td class="text-center">{{ number_format($row['company']->teams_count) }}</td>
                        <td class="text-center">{{ number_format($row['company']->users_count) }}</td>
                        <td class="text-center">{{ number_format($row['total_in_period']) }}</td>
                        <td class="text-center">{{ number_format($row['pending_approval']) }}</td>
                        <td class="text-center">{{ number_format($row['completed_count']) }}</td>
                        <td class="text-end text-success fw-semibold">${{ number_format($row['revenue'], 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted text-center py-4">No companies found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
