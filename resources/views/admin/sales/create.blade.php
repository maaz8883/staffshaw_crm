@extends('admin.layout')

@section('title', 'Add Sale')
@section('page-title', 'Add Sale')
@section('page-icon', 'plus-circle')

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.sales.store') }}" method="POST">
            @include('admin.sales._form', ['sale' => null])
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('admin.sales.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
