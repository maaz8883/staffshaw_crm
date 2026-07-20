@extends('admin.layout')

@section('title', 'Join Requests')
@section('page-title', 'Project Manager Join Requests')
@section('page-icon', 'person-plus')

@section('content')

<p class="text-muted">Pending requests from Project Managers who want to join your team(s). Approving grants immediate access to that team's sales, invoices, and briefs.</p>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Project Manager</th>
                        <th>Team</th>
                        <th>Requested</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $request)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $request->user?->name }}</div>
                            <div class="text-muted small">{{ $request->user?->email }}</div>
                        </td>
                        <td>{{ $request->team?->name }}</td>
                        <td class="text-muted small">{{ $request->created_at->diffForHumans() }}</td>
                        <td class="text-end">
                            <form action="{{ route('admin.teams.join-requests.approve', $request) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <form action="{{ route('admin.teams.join-requests.reject', $request) }}" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Reject this join request?">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted text-center py-3">No pending join requests.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
