@extends('admin.layout')

@section('title', 'Team Directory')
@section('page-title', 'Team Directory')
@section('page-icon', 'diagram-3')

@section('content')

<p class="text-muted">Browse teams and request to join. The team's Team Head must approve your request before you gain access to that team's data.</p>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Team</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>This Month's Target</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teams as $team)
                    <tr>
                        <td class="fw-semibold">{{ $team->name }}</td>
                        <td class="text-muted small">{{ $team->company?->name ?? '-' }}</td>
                        <td>
                            @switch($team->membership_status)
                                @case('approved')
                                    <span class="badge bg-success">Joined</span>
                                    @break
                                @case('pending')
                                    <span class="badge bg-warning text-dark">Pending Approval</span>
                                    @break
                                @case('rejected')
                                    <span class="badge bg-danger">Rejected</span>
                                    @break
                                @default
                                    <span class="text-muted">Not joined</span>
                            @endswitch
                        </td>
                        <td>
                            @if($team->membership_status === 'approved')
                                @if($team->current_target > 0)
                                    <div class="small">
                                        <span class="text-muted">{{ $team->target_month_label }}:</span>
                                        <span class="fw-semibold text-primary">${{ number_format($team->current_target, 0) }}</span>
                                        <span class="text-muted">/ achieved</span>
                                        <span class="fw-semibold text-success">${{ number_format($team->current_revenue, 0) }}</span>
                                    </div>
                                @else
                                    <span class="text-muted small">No target set</span>
                                @endif
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($team->membership_status === 'approved')
                                <a href="{{ route('admin.teams.show', $team) }}" class="btn btn-sm btn-outline-primary">View Members</a>
                                <form action="{{ route('admin.team-memberships.leave', $team) }}" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Leave this team?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Leave</button>
                                </form>
                            @elseif($team->membership_status === 'pending')
                                <form action="{{ route('admin.team-memberships.leave', $team) }}" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Cancel this join request?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Cancel Request</button>
                                </form>
                            @else
                                <form action="{{ route('admin.team-memberships.join', $team) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        {{ $team->membership_status === 'rejected' ? 'Request Again' : 'Request to Join' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted text-center py-3">No teams available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
