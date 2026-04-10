@extends('admin.layout')

@section('title', 'Companies')
@section('page-title', 'Companies')
@section('page-icon', 'buildings')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.companies.create') }}" class="btn btn-primary">Add Company</a>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="companies-table" class="table table-striped w-100">
                <thead>
                <tr>
                    <th>Logo</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Teams</th>
                    <th>Users</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
            </table>
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
            $('#companies-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: @json(route('admin.companies.datatable')),
                columns: [
                    {data: 'logo', name: 'logo', orderable: false, searchable: false},
                    {data: 'name', name: 'name'},
                    {data: 'description', name: 'description'},
                    {data: 'teams_count', name: 'teams_count', searchable: false},
                    {data: 'users_count', name: 'users_count', searchable: false},
                    {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end'}
                ]
            });
        });
    </script>
@endsection
