@extends('admin.layout')

@section('title', 'Client Details')
@section('page-title', 'Client Details')
@section('page-icon', 'person-lines-fill')

@section('content')

<div class="mb-3">
    <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-outline-warning btn-sm">Edit</a>
    <a href="{{ route('admin.clients.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-1">{{ $client->name }}</h5>
        <div class="text-muted mb-2">{{ $client->company_name ?: 'No company' }} · Team: {{ $client->team?->name ?? '-' }}</div>
        <div class="row g-3">
            <div class="col-md-6">
                <small class="text-muted d-block">Email</small>
                {{ $client->email ?: '-' }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Phone</small>
                {{ $client->phone ?: '-' }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Address</small>
                {{ $client->address ?: '-' }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Added By</small>
                {{ $client->createdBy?->name ?? '-' }}
            </div>
            <div class="col-12">
                <small class="text-muted d-block">Notes</small>
                {{ $client->notes ?: '-' }}
            </div>
        </div>
    </div>
</div>

{{-- Summary cards --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Total Sales</div>
                <div class="fs-4 fw-bold text-primary">{{ $sales->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Total Sale Amount</div>
                <div class="fs-4 fw-bold text-success">${{ number_format($totalSaleAmount, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Remaining</div>
                <div class="fs-4 fw-bold {{ $totalRemaining > 0 ? 'text-warning' : 'text-success' }}">${{ number_format($totalRemaining, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Invoices</div>
                <div class="fs-4 fw-bold text-info">{{ $invoices->count() }} <span class="fs-6 text-muted">(${{ number_format($totalInvoiced, 2) }})</span></div>
            </div>
        </div>
    </div>
</div>

{{-- Sales --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-cash-stack text-primary"></i> Sales for this Client
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Agent</th>
                        <th>Team</th>
                        <th>Status</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Received</th>
                        <th class="text-end">Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                    @php $sc = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger', \App\Models\Sale::STATUS_REFUNDED=>'dark']; @endphp
                    <tr>
                        <td><a href="{{ route('admin.sales.show', $sale) }}" class="text-decoration-none">{{ $sale->title }}</a></td>
                        <td class="text-muted small">{{ $sale->user?->name ?? '-' }}</td>
                        <td class="text-muted small">{{ $sale->team?->name ?? '-' }}</td>
                        <td><span class="badge bg-{{ $sc[$sale->status] ?? 'secondary' }}">{{ $sale->statusLabel() }}</span></td>
                        <td class="text-end fw-semibold">${{ number_format($sale->amount, 2) }}</td>
                        <td class="text-end">${{ number_format($sale->received_amount, 2) }}</td>
                        <td class="text-end {{ $sale->remainingAmount() > 0 ? 'text-warning' : 'text-success' }}">${{ number_format($sale->remainingAmount(), 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted text-center py-3">No sales recorded for this client yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Invoices --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-receipt text-primary"></i> Invoices for this Client
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Sale</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->invoice_number }}</td>
                        <td><a href="{{ route('admin.sales.show', $invoice->sale_id) }}" class="text-decoration-none">#{{ $invoice->sale_id }} — {{ $invoice->title }}</a></td>
                        <td>{{ $invoice->issued_at->format('d M Y') }}</td>
                        <td class="text-end fw-semibold">${{ number_format($invoice->amount, 2) }}</td>
                        <td class="text-end"><a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-info">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted text-center py-3">No invoices generated for this client yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
