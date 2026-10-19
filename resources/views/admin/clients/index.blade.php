@extends('admin.layout')

@section('title', 'Clients')
@section('page-title', 'Clients')
@section('page-icon', 'person-lines-fill')

@section('content')

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card"><div class="card-body py-3"><div class="text-muted small">Total Clients</div><div class="fs-5 fw-semibold">{{ $stats['total'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-3"><div class="text-muted small">Active Clients</div><div class="fs-5 fw-semibold">{{ $stats['active'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-3"><div class="text-muted small">Inactive Clients</div><div class="fs-5 fw-semibold">{{ $stats['inactive'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-3"><div class="text-muted small">Contacts</div><div class="fs-5 fw-semibold">{{ $stats['contacts'] }}</div></div></div></div>
</div>

<div class="mb-3">
    @unless(auth()->user()->hasRole('Finance'))<a href="{{ route('admin.clients.create') }}" class="btn btn-primary">New Client</a>@endunless
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
                    <th>Brand</th>
                    <th>Active</th>
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
            {data: 'brand_name', name: 'brand.name', orderable: false, searchable: false},
            {data: 'active_badge', name: 'is_active', searchable: false},
            {data: 'team_name', name: 'team.name', searchable: false},
            {data: 'sales_count', name: 'sales_count', orderable: false, searchable: false},
            {data: 'created_by_name', name: 'createdBy.name', searchable: false},
            {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end'}
        ]
    });
});
</script>
@endsection
