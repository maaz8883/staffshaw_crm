@extends('admin.layout')

@php $canManageMembers = $canManageMembers ?? false; @endphp
@section('title', $canManageMembers ? 'Manage Team Members' : 'Team Members')
@section('page-title', $canManageMembers ? 'Manage Team Members' : 'Team Members')
@section('page-icon', 'people')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-0">{{ $team->name }}</h5>
        <p class="text-muted small mb-0">
            @if($canManageMembers)
                Add new agents, assign existing users, or remove members from this team.
            @else
                Read-only roster. Only an admin can add or remove members.
            @endif
        </p>
    </div>
    <a href="{{ route('admin.teams.show', $team) }}" class="btn btn-outline-secondary btn-sm">Back to team</a>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($canManageMembers)
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold">Create new agent</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.teams.members.create', $team) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm password <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Create &amp; add to team</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold">Add existing user</div>
            <div class="card-body">
                @if($availableUsers->isEmpty())
                    <p class="text-muted mb-0">No unassigned users available to add.</p>
                @else
                <form method="POST" action="{{ route('admin.teams.members.store', $team) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">User <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-select js-select2" required>
                            <option value="">-- Select user --</option>
                            @foreach($availableUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                        <div class="form-text">Shows active users who are not already on a team.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Add to team</button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span>Current members</span>
        <span class="badge bg-secondary">{{ $members->count() }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        @if($canManageMembers)
                        <th class="text-end">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @forelse($members as $member)
                    @php $isHead = $team->isHeadedBy($member->id); @endphp
                    <tr>
                        <td>
                            {{ $member->name }}
                            @if($isHead)
                                <span class="badge bg-warning text-dark">Team Lead</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $member->email }}</td>
                        <td>{{ $isHead ? 'Team Lead' : ($member->role?->name ?? '—') }}</td>
                        @if($canManageMembers)
                        <td class="text-end">
                            @if($isHead)
                                <span class="text-muted small">Cannot remove</span>
                            @else
                                <form method="POST" action="{{ route('admin.teams.members.destroy', [$team, $member]) }}"
                                      class="d-inline js-admin-delete-form" data-swal-title="Remove {{ $member->name }} from this team?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            @endif
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canManageMembers ? 4 : 3 }}" class="text-muted text-center py-4">No members on this team.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
