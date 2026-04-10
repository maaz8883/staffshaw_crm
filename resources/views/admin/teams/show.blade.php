@extends('admin.layout')

@section('title', 'Team Details')
@section('page-title', 'Team Details')
@section('page-icon', 'people-fill')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="mb-1">{{ $team->name }}</h5>
            <div class="text-muted mb-2">
                {{ $team->company?->name ?? 'No company' }}
            </div>
            <div class="mb-2">
                <small class="text-muted">Team Head:</small>
                <strong>{{ $team->teamHead?->name ?? 'Not assigned' }}</strong>
            </div>
            <p class="mb-0">{{ $team->description ?: 'No description provided.' }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h6>Users in team</h6>
            <ul class="mb-0">
                @forelse($team->users as $user)
                    <li>{{ $user->name }} ({{ $user->email }})</li>
                @empty
                    <li>No users assigned.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
