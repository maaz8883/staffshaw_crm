@extends('admin.layout')

@section('title', 'Pending agent signups')
@section('page-title', 'Pending agent signups')
@section('page-icon', 'person-check')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <p class="text-muted small mb-4">Approve or reject agents who registered online. Team leads only see requests for their own teams.</p>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Company</th>
                        <th>Team</th>
                        <th class="text-muted small">Requested</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                    <tr>
                        <td class="fw-semibold">{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->company?->name ?? '—' }}</td>
                        <td>{{ $u->team?->name ?? '—' }}</td>
                        <td class="text-muted small">{{ $u->created_at->format('d M Y H:i') }}</td>
                        <td class="text-end text-nowrap">
                            <form action="{{ route('admin.pending-registrations.approve', $u) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">Accept</button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#reject-{{ $u->id }}">Reject</button>
                        </td>
                    </tr>
                    <tr class="collapse border-top-0" id="reject-{{ $u->id }}">
                        <td colspan="6" class="pt-0 pb-3 bg-light">
                            <form action="{{ route('admin.pending-registrations.reject', $u) }}" method="POST" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-md-8">
                                    <label class="form-label small text-muted mb-0">Optional note (shown internally)</label>
                                    <input type="text" name="rejection_note" class="form-control form-control-sm" maxlength="500" placeholder="Reason (optional)">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-sm btn-danger">Confirm reject</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-muted text-center py-4">No pending signups.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
