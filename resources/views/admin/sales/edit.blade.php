@extends('admin.layout')

@section('title', 'Edit Sale')
@section('page-title', 'Edit Sale')
@section('page-icon', 'pencil-square')

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.sales.update', $sale) }}" method="POST">
            @method('PUT')
            @include('admin.sales._form')
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="{{ route('admin.sales.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
