@extends('admin.layout')

@section('title', 'Sales report')
@section('page-title', 'Sales report')
@section('page-icon', 'cash-stack')

@section('content')
@include('admin.reports._subnav')

<form method="get" action="{{ route('admin.reports.sales') }}" class="card border-0 shadow-sm mb-4">
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
            @if(!$isAgent && $users->isNotEmpty())
            <div class="col-12 col-md-auto">
                <label class="form-label small text-muted mb-0">Agent</label>
                <select name="user_id" class="form-select form-select-sm" style="min-width: 140px">
                    <option value="">All agents</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <a href="{{ route('admin.reports.sales') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
        <div class="text-muted small mt-2 mb-0">
            Showing <strong>{{ $dateFrom->format('M j, Y') }}</strong> — <strong>{{ $dateTo->format('M j, Y') }}</strong>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 flex-shrink-0">
                    <i class="bi bi-currency-dollar fs-5 text-success"></i>
                </div>
                <div>
                    <div class="text-muted small">Revenue (approved & completed)</div>
                    <div class="fw-bold fs-4">${{ number_format($revenue, 0) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 flex-shrink-0">
                    <i class="bi bi-check-circle fs-5 text-primary"></i>
                </div>
                <div>
                    <div class="text-muted small">Completed sales</div>
                    <div class="fw-bold fs-4">{{ number_format($completedCount) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 flex-shrink-0">
                    <i class="bi bi-hourglass-split fs-5 text-warning"></i>
                </div>
                <div>
                    <div class="text-muted small">Pending approval</div>
                    <div class="fw-bold fs-4">{{ number_format($pendingApprovalCount) }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-bar-chart-line text-primary"></i> Daily revenue (completed & approved)
            </div>
            <div class="card-body">
                <canvas id="dailyRevenueChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-pie-chart text-primary"></i> Sales by status (approved)
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="salesStatusChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-shield-check text-secondary"></i> Approval breakdown
            </div>
            <div class="card-body ">
                <table class="table table-sm table-hover mb-0">
                    <tbody>
                        <tr>
                            <td>Approved</td>
                            <td class="text-end fw-semibold text-success">{{ number_format($approvalBreakdown[\App\Models\Sale::APPROVAL_APPROVED]) }}</td>
                        </tr>
                        <tr>
                            <td>Pending approval</td>
                            <td class="text-end fw-semibold text-warning">{{ number_format($approvalBreakdown[\App\Models\Sale::APPROVAL_PENDING]) }}</td>
                        </tr>
                        <tr>
                            <td>Rejected</td>
                            <td class="text-end fw-semibold text-danger">{{ number_format($approvalBreakdown[\App\Models\Sale::APPROVAL_REJECTED]) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const dailyCtx = document.getElementById('dailyRevenueChart');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: @json($dailyLabels),
            datasets: [{
                label: 'Revenue ($)',
                data: @json($dailyValues),
                fill: true,
                borderColor: '#2E86C1',
                backgroundColor: 'rgba(46, 134, 193, 0.12)',
                tension: 0.25,
                pointRadius: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    const statusCtx = document.getElementById('salesStatusChart');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: @json($salesByStatus->keys()->map(fn($s) => ucfirst($s))),
            datasets: [{
                data: @json($salesByStatus->values()),
                backgroundColor: ['#ffc107', '#198754', '#dc3545'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
            cutout: '60%'
        }
    });
</script>
@endsection
