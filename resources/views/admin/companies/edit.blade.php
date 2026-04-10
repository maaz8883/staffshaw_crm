@extends('admin.layout')

@section('title', 'Edit Company')
@section('page-title', 'Edit Company')
@section('page-icon', 'pencil-square')

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.companies.update', $company) }}" method="POST" enctype="multipart/form-data">
                @method('PUT')
                @include('admin.companies._form')
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('admin.companies.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
