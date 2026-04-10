@extends('admin.layout')

@section('title', 'Add Company')
@section('page-title', 'Add Company')
@section('page-icon', 'plus-circle')

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.companies.store') }}" method="POST" enctype="multipart/form-data">
                @include('admin.companies._form', ['company' => null])
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.companies.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
