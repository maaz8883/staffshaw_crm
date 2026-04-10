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
                @php $colors = ['completed'=>'success','pending'=>'warning','cancelled'=>'danger']; @endphp
                <span class="badge bg-{{ $colors[$sale->status] ?? 'secondary' }}">{{ ucfirst($sale->status) }}</span>
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
                <small class="text-muted d-block">Client Name</small>
                {{ $sale->client_name }}
            </div>
            <div class="col-md-6">
                <small class="text-muted d-block">Amount</small>
                <span class="fw-bold text-success fs-5">${{ number_format($sale->amount, 2) }}</span>
            </div>
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
