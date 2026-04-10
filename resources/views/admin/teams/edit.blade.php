@extends('admin.layout')

@section('title', 'Edit Team')
@section('page-title', 'Edit Team')
@section('page-icon', 'pencil-square')

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.teams.update', $team) }}" method="POST">
                @method('PUT')
                @include('admin.teams._form', ['team' => $team])
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('admin.teams.index') }}" class="btn btn-outline-secondary">Back</a>
            </form>
        </div>
    </div>
@endsection
