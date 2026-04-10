@extends('admin.layout')

@section('title', 'Create Team')
@section('page-title', 'Create Team')
@section('page-icon', 'plus-circle')

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.teams.store') }}" method="POST">
                @include('admin.teams._form', ['team' => null])
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.teams.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
