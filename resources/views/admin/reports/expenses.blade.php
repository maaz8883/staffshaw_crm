@extends('admin.layout')

@section('title', 'Expenses report')
@section('page-title', 'Expenses report')
@section('page-icon', 'wallet2')

@section('content')
@include('admin.reports._subnav')
@include('admin.reports._filters', [
    'action' => route('admin.reports.expenses'),
    'showCompany' => false,
    'showAgent' => false,
])

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Total spend</div>
            <div class="fs-4 fw-semibold">${{ number_format($total, 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Tax</div>
            <div class="fs-4 fw-semibold">${{ number_format($tax, 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Billable</div>
            <div class="fs-4 fw-semibold text-info">${{ number_format($billable, 2) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Not billable</div>
            <div class="fs-4 fw-semibold">${{ number_format($nonBillable, 2) }}</div>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold">By category</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="text-end">Entries</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Tax</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($byCategory as $row)
                    <tr>
                        <td>{{ $row->category }}</td>
                        <td class="text-end">{{ $row->cnt }}</td>
                        <td class="text-end">${{ number_format((float) $row->total, 2) }}</td>
                        <td class="text-end">${{ number_format((float) $row->tax, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted text-center py-4">No expenses in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
