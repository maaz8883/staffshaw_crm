@extends('admin.layout')

@section('title', 'Brand report')
@section('page-title', 'Brand report')
@section('page-icon', 'tags')

@section('content')
@include('admin.reports._subnav')
@include('admin.reports._filters', [
    'action' => route('admin.reports.brands'),
    'showAgent' => true,
])

<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold">Revenue by brand (approved & completed)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Brand</th>
                        <th class="text-end">Sales</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Received</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($brandRows as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['brand']->name }}</td>
                        <td class="text-end">{{ number_format($row['count']) }}</td>
                        <td class="text-end">${{ number_format($row['revenue'], 2) }}</td>
                        <td class="text-end">${{ number_format($row['received'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted text-center py-4">No brand data.</td></tr>
                @endforelse
                @if($unassignedCount > 0)
                    <tr class="text-muted">
                        <td>No brand</td>
                        <td class="text-end">{{ number_format($unassignedCount) }}</td>
                        <td class="text-end">${{ number_format($unassignedRevenue, 2) }}</td>
                        <td class="text-end">—</td>
                    </tr>
                @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
