@extends('admin.layout')

@section('title', 'Customers report')
@section('page-title', 'Customers report')
@section('page-icon', 'people-fill')

@section('content')
@include('admin.reports._subnav')
@include('admin.reports._filters', [
    'action' => route('admin.reports.clients'),
    'showCompany' => false,
    'showAgent' => false,
])

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">New customers (period)</div>
            <div class="fs-4 fw-semibold">{{ number_format($newClients) }}</div>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Active customers (all time)</div>
            <div class="fs-4 fw-semibold">{{ number_format($activeClients) }}</div>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold">Top customers by period revenue</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th class="text-end">Period revenue</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($topClients as $client)
                    <tr>
                        <td>
                            <a href="{{ route('admin.clients.show', $client) }}">{{ $client->name }}</a>
                        </td>
                        <td class="text-muted">{{ $client->email ?: '—' }}</td>
                        <td class="text-end fw-semibold">${{ number_format((float) ($client->period_revenue ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted text-center py-4">No customers found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
