@extends('admin.layout')

@section('title', 'Edit Brand')
@section('page-title', 'Edit Brand')
@section('page-icon', 'pencil-square')

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.brands.update', $brand) }}" method="POST">
                @method('PUT')
                @include('admin.brands._form')
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('admin.brands.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
