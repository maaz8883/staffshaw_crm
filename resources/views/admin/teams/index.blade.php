@extends('admin.layout')

@section('title', 'Teams')
@section('page-title', 'Teams')
@section('page-icon', 'people')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.teams.create') }}" class="btn btn-primary">Create Team</a>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="teams-table" class="table table-striped w-100">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Team Head</th>
                    <th>Description</th>
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
            $('#teams-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: @json(route('admin.teams.datatable')),
                columns: [
                    {data: 'name', name: 'name'},
                    {data: 'company_name', name: 'company.name', searchable: false},
                    {data: 'team_head_name', name: 'teamHead.name', searchable: false},
                    {data: 'description', name: 'description'},
                    {data: 'users_count', name: 'users_count', searchable: false},
                    {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end'}
                ]
            });
        });
    </script>
@endsection
