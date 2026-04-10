@extends('admin.layout')

@section('title', 'Team report')
@section('page-title', 'Team report')
@section('page-icon', 'people')

@section('content')
@include('admin.reports._subnav')

<form method="get" action="{{ route('admin.reports.team') }}" class="card border-0 shadow-sm mb-4">
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
                <a href="{{ route('admin.reports.team') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
        <div class="text-muted small mt-2 mb-0">
            Period: <strong>{{ $dateFrom->format('M j, Y') }}</strong> — <strong>{{ $dateTo->format('M j, Y') }}</strong>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-bar-chart-fill text-success"></i> Revenue by team (completed &amp; approved)
            </div>
            <div class="card-body">
                <canvas id="teamRevenueChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-table"></i> Team breakdown
            </div>
            <div class="card-body ">
                <div class="table-responsive" style="max-height: 420px;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light sticky-top bg-white">
                            <tr>
                                <th>Team</th>
                                <th class="text-center">Completed</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($revenueByTeam as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['team']->name }}</td>
                                <td class="text-center">{{ number_format($row['count']) }}</td>
                                <td class="text-end text-success">${{ number_format($row['revenue'], 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">No data for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const ctx = document.getElementById('teamRevenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($teamChartLabels),
                datasets: [{
                    label: 'Revenue ($)',
                    data: @json($teamChartValues),
                    backgroundColor: 'rgba(46, 134, 193, 0.65)',
                    borderColor: '#2E86C1',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true },
                    x: { ticks: { maxRotation: 45, minRotation: 0 } }
                }
            }
        });
    }
</script>
@endsection
