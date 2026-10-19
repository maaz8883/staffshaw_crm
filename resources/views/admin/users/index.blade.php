@extends('admin.layout')

@section('title', 'Users')
@section('page-title', 'Users')
@section('page-icon', 'person-lines-fill')

@section('content')
    <div class="mb-3 d-flex gap-2 flex-wrap align-items-end">
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Add User</a>

        @unless($singleCompany)
        <select id="filter-company" class="form-select form-select-sm" style="width:auto">
            <option value="">All Companies</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
        @endunless

        <select id="filter-team" class="form-select form-select-sm" style="width:auto">
            <option value="">All Teams</option>
            @foreach($teams as $team)
                <option value="{{ $team->id }}">{{ $team->name }}</option>
            @endforeach
        </select>

        <select id="filter-role" class="form-select form-select-sm" style="width:auto">
            <option value="">All Roles</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}">{{ $role->name }}</option>
            @endforeach
        </select>

        <select id="filter-status" class="form-select form-select-sm" style="width:auto">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="banned">Banned</option>
            <option value="pending">Pending</option>
            <option value="rejected">Rejected</option>
        </select>

        <button id="btn-reset" class="btn btn-outline-secondary btn-sm">Reset</button>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="users-table" class="table table-striped w-100">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    @unless($singleCompany)<th>Company</th>@endunless
                    <th>Team</th>
                    <th>Status</th>
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
            var table = $('#users-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: @json(route('admin.users.datatable')),
                    data: function (d) {
                        d.company_id = $('#filter-company').val() || '';
                        d.team_id    = $('#filter-team').val();
                        d.role_id    = $('#filter-role').val();
                        d.status     = $('#filter-status').val();
                    }
                },
                columns: [
                    {data: 'name',         name: 'name'},
                    {data: 'email',        name: 'email'},
                    {data: 'role_name',    name: 'role.name',    orderable: false, searchable: false},
                    @unless($singleCompany)
                    {data: 'company_name', name: 'company.name', orderable: false, searchable: false},
                    @endunless
                    {data: 'team_name',    name: 'team.name',    orderable: false, searchable: false},
                    {data: 'status_badge', name: 'account_status', orderable: false, searchable: false},
                    {data: 'actions',      name: 'actions',      orderable: false, searchable: false, className: 'text-end'}
                ]
            });

            $('#filter-company, #filter-team, #filter-role, #filter-status').on('change', function () {
                table.draw();
            });

            $('#btn-reset').on('click', function () {
                $('#filter-company, #filter-team, #filter-role, #filter-status').val('');
                table.draw();
            });
        });
    </script>
@endsection
