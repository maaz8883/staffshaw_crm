@extends('admin.layout')

@section('title', 'Clients')
@section('page-title', 'Clients')
@section('page-icon', 'person-lines-fill')

@section('content')

<div class="mb-3">
    <a href="{{ route('admin.clients.create') }}" class="btn btn-primary">Add Client</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="clients-table" class="table table-striped w-100">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Company</th>
                    <th>Team</th>
                    <th>Sales</th>
                    <th>Added By</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
            </table>
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
    $('#clients-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: @json(route('admin.clients.datatable')),
        columns: [
            {data: 'name', name: 'name'},
            {data: 'email', name: 'email'},
            {data: 'phone', name: 'phone'},
            {data: 'company_name', name: 'company_name'},
            {data: 'team_name', name: 'team.name', searchable: false},
            {data: 'sales_count', name: 'sales_count', orderable: false, searchable: false},
            {data: 'created_by_name', name: 'createdBy.name', searchable: false},
            {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end'}
        ]
    });
});
</script>
@endsection
