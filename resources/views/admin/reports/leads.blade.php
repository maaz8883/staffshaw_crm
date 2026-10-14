@extends('admin.layout')

@section('title', 'Leads report')
@section('page-title', 'Leads report')
@section('page-icon', 'funnel')

@section('content')
@include('admin.reports._subnav')
@include('admin.reports._filters', [
    'action' => route('admin.reports.leads'),
    'showCompany' => false,
    'showAgent' => true,
])

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">New leads (period)</div>
            <div class="fs-4 fw-semibold">{{ number_format($total) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Converted to customer</div>
            <div class="fs-4 fw-semibold text-success">{{ number_format($converted) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Conversion rate</div>
            <div class="fs-4 fw-semibold">{{ $conversionRate }}%</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Open pipeline value</div>
            <div class="fs-4 fw-semibold">${{ number_format($pipelineValue, 2) }}</div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold">By status</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>Status</th><th class="text-end">Count</th></tr></thead>
                        <tbody>
                        @foreach($byStatus as $status => $count)
                            <tr>
                                <td>{{ \App\Models\Lead::STATUS_LABELS[$status] ?? $status }}</td>
                                <td class="text-end">{{ $count }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold">By source</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>Source</th><th class="text-end">Count</th></tr></thead>
                        <tbody>
                        @forelse($bySource as $row)
                            <tr>
                                <td class="text-capitalize">{{ str_replace('_', ' ', $row->src) }}</td>
                                <td class="text-end">{{ $row->cnt }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted text-center py-4">No leads in this period.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
