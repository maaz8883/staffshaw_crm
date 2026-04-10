@extends('admin.layout')

@section('title', 'Brand Details')
@section('page-title', 'Brand Details')
@section('page-icon', 'building')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.brands.edit', $brand) }}" class="btn btn-outline-warning btn-sm">Edit</a>
        <a href="{{ route('admin.brands.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <small class="text-muted d-block">Brand Name</small>
                    <strong>{{ $brand->name }}</strong>
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Industry</small>
                    {{ $brand->industry ?: '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Email</small>
                    {{ $brand->email ?: '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Phone</small>
                    {{ $brand->phone ?: '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Website</small>
                    @if($brand->website)
                        <a href="{{ $brand->website }}" target="_blank">{{ $brand->website }}</a>
                    @else
                        -
                    @endif
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Assigned To</small>
                    {{ $brand->assignedUser?->name ?? '-' }}
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Status</small>
                    <span class="badge bg-{{ $brand->status === 'active' ? 'success' : 'secondary' }}">
                        {{ ucfirst($brand->status) }}
                    </span>
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">Created</small>
                    {{ $brand->created_at->format('d M Y, h:i A') }}
                </div>
                <div class="col-12">
                    <small class="text-muted d-block">Address</small>
                    {{ $brand->address ?: '-' }}
                </div>
                <div class="col-12">
                    <small class="text-muted d-block">Notes</small>
                    {{ $brand->notes ?: '-' }}
                </div>
            </div>
        </div>
    </div>
@endsection
