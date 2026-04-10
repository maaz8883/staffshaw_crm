@extends('admin.layout')

@section('title', 'Create User')
@section('page-title', 'Create User')
@section('page-icon', 'person-plus')

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.users.store') }}" method="POST">
                @include('admin.users._form', ['user' => null])
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
