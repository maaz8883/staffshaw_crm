@extends('admin.layout')

@section('title', 'Activity Logs')
@section('page-title', 'Activity Logs')
@section('page-icon', 'clock-history')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form id="filter-form" class="row g-2 align-items-end">
            <div class="col-sm-4">
                <label class="form-label small mb-1">User</label>
                <select name="user_id" id="filter-user" class="form-select form-select-sm">
                    <option value="">All Users</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small mb-1">Type</label>
                <select name="type" id="filter-type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}">{{ str_replace('_', ' ', ucfirst($t)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <button type="button" id="btn-filter" class="btn btn-sm btn-primary">Filter</button>
                <button type="button" id="btn-reset" class="btn btn-sm btn-outline-secondary">Reset</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table id="activity-table" class="table table-hover mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>User</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>IP</th>
                    <th>Country / City</th>
                    <th>Browser / OS</th>
                    <th>Time</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(function () {
    var table = $('#activity-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('admin.activity-logs.datatable') }}',
            data: function (d) {
                d.user_id = $('#filter-user').val();
                d.type = $('#filter-type').val();
            }
        },
        columns: [
            { data: 'user_name', name: 'user.name', orderable: true },
            { data: 'type_badge', name: 'type', orderable: true },
            { data: 'description', name: 'description', orderable: false },
            { data: 'ip_address', name: 'ip_address', orderable: false, 
              render: function(data) { return data ? '<span class="font-monospace">' + data + '</span>' : '-'; }
            },
            { data: 'location', name: 'country', orderable: false },
            { data: 'user_agent', name: 'user_agent', orderable: false },
            { data: 'created_at', name: 'created_at', orderable: true }
        ],
        order: [[6, 'desc']], // Latest first
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            emptyTable: 'No activity logs found.',
            zeroRecords: 'No matching records found.'
        }
    });

    $('#btn-filter').on('click', function () {
        table.draw();
    });

    $('#btn-reset').on('click', function () {
        $('#filter-form')[0].reset();
        table.draw();
    });
});
</script>
@endsection
