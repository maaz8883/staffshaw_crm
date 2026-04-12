@extends('admin.layout')

@section('title', 'Sale Details')
@section('page-title', 'Sale Details')
@section('page-icon', 'cash-stack')

@section('content')
<div class="mb-3">
    @if(auth()->user()->hasRole('Admin') || $sale->user_id === auth()->id())
    <a href="{{ route('admin.sales.edit', $sale) }}" class="btn btn-outline-warning btn-sm">Edit</a>
    @endif
    <a href="{{ route('admin.sales.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <small class="text-muted d-block">Title</small>
                <strong>{{ $sale->title }}</strong>
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Status</small>
                @php
                    $colors = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger',\App\Models\Sale::STATUS_REFUNDED=>'dark'];
                @endphp
                <span class="badge bg-{{ $colors[$sale->status] ?? 'secondary' }}">{{ $sale->statusLabel() }}</span>
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Approval</small>
                @php
                    $ac = ['approved'=>'success','rejected'=>'danger','pending_approval'=>'warning'];
                    $al = ['approved'=>'Approved','rejected'=>'Rejected','pending_approval'=>'Pending Approval'];
                @endphp
                <span class="badge bg-{{ $ac[$sale->approval_status] ?? 'secondary' }}">
                    {{ $al[$sale->approval_status] ?? $sale->approval_status }}
                </span>
                @if($sale->approvedBy)
                    <span class="text-muted small ms-2">by {{ $sale->approvedBy->name }}
                        {{ $sale->approved_at?->format('d M Y') }}</span>
                @endif
            </div>
            @if($sale->approval_note)
            <div class="col-12">
                <small class="text-muted d-block">Rejection Reason</small>
                <div class="alert alert-danger py-2 mb-0">{{ $sale->approval_note }}</div>
            </div>
            @endif
            <div class="col-md-6">
                <small class="text-muted d-block">Sale type</small>
                @php $tc = ['front'=>'primary','upsell'=>'info']; @endphp
                <span class="badge bg-{{ $tc[$sale->sale_type ?? 'front'] ?? 'secondary' }}">{{ $sale->saleTypeLabel() }}</span>
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Client Name</small>
                {{ $sale->client_name }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Amount</small>
                <span class="fw-bold fs-5 {{ $sale->is_refunded ? 'text-danger' : 'text-success' }}">${{ number_format($sale->amount, 2) }}</span>
                @if($sale->is_refunded)
                    <span class="badge bg-danger ms-2">Refunded</span>
                    @if($sale->refunded_at)
                        <span class="text-muted small ms-1">{{ $sale->refunded_at->format('d M Y H:i') }}</span>
                    @endif
                    @if($sale->refundedBy)
                        <span class="text-muted small">· {{ $sale->refundedBy->name }}</span>
                    @endif
                @endif
            </div>
            @if($canToggleRefund ?? false)
            <div class="col-12">
                <small class="text-muted d-block mb-1">Refund (admin / team lead)</small>
                <form action="{{ route('admin.sales.toggle-refund', $sale) }}" method="POST" class="d-inline-flex align-items-center gap-2">
                    @csrf
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="refund-switch"
                            {{ $sale->is_refunded ? 'checked' : '' }}
                            onchange="this.form.submit()">
                        <label class="form-check-label" for="refund-switch">Mark as refunded</label>
                    </div>
                </form>
            </div>
            @endif
            <div class="col-md-6">
                <small class="text-muted d-block">Sale Date</small>
                {{ $sale->sale_date->format('d M Y') }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Agent</small>
                {{ $sale->user?->name ?? '-' }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Team</small>
                {{ $sale->team?->name ?? '-' }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Company</small>
                {{ $sale->company?->name ?? '-' }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Created</small>
                {{ $sale->created_at->format('d M Y, h:i A') }}
            </div>
            <div class="col-12">
                <small class="text-muted d-block">Notes</small>
                {{ $sale->notes ?: '-' }}
            </div>
        </div>
    </div>
</div>
@endsection
