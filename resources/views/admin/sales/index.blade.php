@extends('admin.layout')

@section('title', 'Sales')
@section('page-title', 'Sales')
@section('page-icon', 'cash-stack')

@section('content')

@if($pendingCount > 0 && !$isAgent)
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-hourglass-split fs-5"></i>
    <span><strong>{{ $pendingCount }}</strong> sale(s) pending your approval.</span>
</div>
@endif

<div class="mb-3 d-flex gap-2 flex-wrap align-items-end">
    @if(!auth()->user()->hasRole('Admin'))
    <a href="{{ route('admin.sales.create') }}" class="btn btn-primary">Add Sale</a>
    @endif

    @if(!$isAgent)
    <select id="filter-team" class="form-select form-select-sm" style="width:auto">
        <option value="">All Teams</option>
        @foreach($teams as $team)
            <option value="{{ $team->id }}">{{ $team->name }}</option>
        @endforeach
    </select>

    <select id="filter-user" class="form-select form-select-sm" style="width:auto">
        <option value="">All Agents</option>
        @foreach($users as $user)
            <option value="{{ $user->id }}">{{ $user->name }}</option>
        @endforeach
    </select>
    @endif

    <select id="filter-status" class="form-select form-select-sm" style="width:auto">
        <option value="">All Statuses</option>
        <option value="completed">Completed</option>
        <option value="pending">Pending</option>
        <option value="cancelled">Cancelled</option>
    </select>

    <select id="filter-approval" class="form-select form-select-sm" style="width:auto">
        <option value="">All Approvals</option>
        <option value="pending_approval">Pending Approval</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
    </select>

    <button id="btn-reset" class="btn btn-outline-secondary btn-sm">Reset</button>
</div>

<div class="card">
    <div class="card-body">
        <table id="sales-table" class="table table-striped w-100">
            <thead>
            <tr>
                <th>Title</th>
                <th>Client</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Agent</th>
                <th>Team</th>
                <th>Status</th>
                <th>Approval</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject Sale</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label for="approval_note" class="form-label">Reason for rejection <span class="text-danger">*</span></label>
                    <textarea name="approval_note" id="approval_note" class="form-control" rows="3" required
                        placeholder="Explain why this sale is being rejected..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Sale</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endpush

@section('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function () {
    var table = $('#sales-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: @json(route('admin.sales.datatable')),
            data: function (d) {
                d.team_id         = $('#filter-team').val();
                d.user_id         = $('#filter-user').val();
                d.status          = $('#filter-status').val();
                d.approval_status = $('#filter-approval').val();
            }
        },
        columns: [
            {data: 'title',          name: 'title'},
            {data: 'client_name',    name: 'client_name'},
            {data: 'amount',         name: 'amount'},
            {data: 'sale_date',      name: 'sale_date'},
            {data: 'agent_name',     name: 'user.name',    searchable: false},
            {data: 'team_name',      name: 'team.name',    searchable: false},
            {data: 'status',         name: 'status'},
            {data: 'approval_badge', name: 'approval_status'},
            {data: 'actions',        name: 'actions', orderable: false, searchable: false, className: 'text-end'}
        ]
    });

    $('#filter-team, #filter-user, #filter-status, #filter-approval').on('change', function () {
        table.draw();
    });

    $('#btn-reset').on('click', function () {
        $('#filter-team, #filter-user, #filter-status, #filter-approval').val('');
        table.draw();
    });

    // Reject modal
    $(document).on('click', '.btn-reject', function () {
        var url = $(this).data('url');
        $('#rejectForm').attr('action', url);
        $('#approval_note').val('');
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    });
});
</script>
@endsection
