@extends('admin.layout')

@section('title', 'Edit Brief Form Type')
@section('page-title', 'Edit Brief Form Type')
@section('page-icon', 'pencil-square')

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.brief-form-types.update', $briefFormType) }}" method="POST">
                @method('PUT')
                @include('admin.brief-form-types._form')
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('admin.brief-form-types.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
