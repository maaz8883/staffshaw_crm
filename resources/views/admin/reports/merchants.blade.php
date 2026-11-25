@extends('admin.layout')

@section('title', 'Merchant report')
@section('page-title', 'Merchant report')
@section('page-icon', 'credit-card')

@section('content')
@include('admin.reports._subnav')
@include('admin.reports._filters', [
    'action' => route('admin.reports.merchants'),
    'showAgent' => true,
])

<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold">Totals by merchant (from sales — not imported summary columns)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Merchant</th>
                        <th class="text-end">No. of transactions</th>
                        <th class="text-end">Gross total</th>
                        <th class="text-end">Refunded</th>
                        <th class="text-end">No. of refunds</th>
                        <th class="text-end">Net total</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($merchantRows as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['merchant'] }}</td>
                        <td class="text-end">{{ number_format($row['transactions']) }}</td>
                        <td class="text-end">${{ number_format($row['gross_total'], 2) }}</td>
                        <td class="text-end text-danger">${{ number_format($row['refunded'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['refund_count']) }}</td>
                        <td class="text-end fw-semibold">${{ number_format($row['net_total'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted text-center py-4">No merchant data yet. Import or create sales with a Merchant value.</td></tr>
                @endforelse
                </tbody>
                @if($merchantRows->isNotEmpty())
                <tfoot>
                    <tr class="fw-semibold">
                        <td>Total</td>
                        <td class="text-end">{{ number_format($merchantRows->sum('transactions')) }}</td>
                        <td class="text-end">${{ number_format($merchantRows->sum('gross_total'), 2) }}</td>
                        <td class="text-end text-danger">${{ number_format($merchantRows->sum('refunded'), 2) }}</td>
                        <td class="text-end">{{ number_format($merchantRows->sum('refund_count')) }}</td>
                        <td class="text-end">${{ number_format($merchantRows->sum('net_total'), 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
<p class="text-muted small mt-2 mb-0">
    Disputed columns from the old spreadsheet are not tracked per sale yet — refunds are included above. Net = Gross − Refunded.
</p>
@endsection
