@extends('admin.layout')

@section('title', 'Sales report')
@section('page-title', 'Sales report')
@section('page-icon', 'cash-stack')

@section('content')
@include('admin.reports._subnav')

@include('admin.reports._filters', ['action' => route('admin.reports.sales')])

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Revenue</div>
            <div class="fw-bold fs-4">${{ number_format($revenue, 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Received</div>
            <div class="fw-bold fs-4 text-success">${{ number_format($received, 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Outstanding</div>
            <div class="fw-bold fs-4 text-warning">${{ number_format($outstanding, 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Completed / Pending / Refunded</div>
            <div class="fw-bold fs-5">{{ $completedCount }} / {{ $pendingApprovalCount }} / {{ $refundedCount }}</div>
        </div></div>
    </div>
    <div class="col-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Front revenue</div>
            <div class="fw-bold fs-4">${{ number_format($frontRevenue, 2) }}</div>
        </div></div>
    </div>
    <div class="col-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Upsell revenue</div>
            <div class="fw-bold fs-4">${{ number_format($upsellRevenue, 2) }}</div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-bar-chart-line text-primary"></i> Daily revenue (completed & approved)
            </div>
            <div class="card-body">
                <canvas id="dailyRevenueChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold">
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
            <div class="card-header fw-semibold">
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
