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
                @if($brand->image)
                <div class="col-12">
                    <small class="text-muted d-block">Image</small>
                    <img src="{{ asset('storage/' . $brand->image) }}"
                         alt="{{ $brand->name }}"
                         style="height:120px;width:120px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6;">
                </div>
                @endif
                <div class="col-md-6">
                    <small class="text-muted d-block">Brand Name</small>
                    <strong>{{ $brand->name }}</strong>
                </div>
                <div class="col-md-6">
                    <small class="text-muted d-block">URL</small>
                    <a href="{{ $brand->website }}" target="_blank" rel="noopener">{{ $brand->website }}</a>
                </div>
                @if($brand->brief_document)
                <div class="col-md-6">
                    <small class="text-muted d-block">Brief Document</small>
                    <a href="{{ asset('storage/' . $brand->brief_document) }}" target="_blank" rel="noopener">
                        {{ $brand->brief_document_name ?? basename($brand->brief_document) }}
                    </a>
                </div>
                @endif
                <div class="col-md-6">
                    <small class="text-muted d-block">Created</small>
                    {{ $brand->created_at->format('d M Y, h:i A') }}
                </div>
            </div>
        </div>
    </div>
@endsection
