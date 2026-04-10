@extends('admin.layout')

@section('title', 'Lead Details')
@section('page-title', 'Lead Details')
@section('page-icon', 'funnel')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.leads.edit', $lead) }}" class="btn btn-outline-warning btn-sm">Edit</a>
        <a href="{{ route('admin.leads.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <small class="text-muted d-block">Name</small>
                    <strong>{{ $lead->name }}</strong>
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Status</small>
                    @php
                        $colors = ['new'=>'primary','contacted'=>'info','proposal'=>'warning','won'=>'success','lost'=>'danger'];
                    @endphp
                    <span class="badge bg-{{ $colors[$lead->status] ?? 'secondary' }}">{{ ucfirst($lead->status) }}</span>
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Email</small>
                    {{ $lead->email ?: '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Phone</small>
                    {{ $lead->phone ?: '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Source</small>
                    {{ $lead->source ? ucwords(str_replace('_', ' ', $lead->source)) : '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Assigned To</small>
                    {{ $lead->assignedUser?->name ?? '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Team</small>
                    {{ $lead->team?->name ?? '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Created</small>
                    {{ $lead->created_at->format('d M Y, h:i A') }}
                </div>
                <div class="col-12">
                    <small class="text-muted d-block">Notes</small>
                    {{ $lead->notes ?: '-' }}
                </div>
            </div>
        </div>
    </div>
@endsection
