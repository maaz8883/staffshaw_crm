@extends('admin.layout')

@section('title', 'Leads')
@section('page-title', 'Leads')
@section('page-icon', 'funnel')

@section('content')
    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
        @if(!auth()->user()->hasRole('Agent'))
        <a href="{{ route('admin.leads.create') }}" class="btn btn-primary">Add Lead</a>
        @endif

        {{-- Filters --}}
        <select id="filter-team" class="form-select form-select-sm" style="width:auto">
            <option value="">All Teams</option>
            @foreach($teams as $team)
                <option value="{{ $team->id }}">{{ $team->name }}</option>
            @endforeach
        </select>

        <select id="filter-status" class="form-select form-select-sm" style="width:auto">
            <option value="">All Statuses</option>
            @foreach(['new','contacted','proposal','won','lost'] as $s)
                <option value="{{ $s }}">{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="leads-table" class="table table-striped w-100">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Team</th>
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
            var table = $('#leads-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: @json(route('admin.leads.datatable')),
                    data: function (d) {
                        d.team_id  = $('#filter-team').val();
                        d.status   = $('#filter-status').val();
                    }
                },
                columns: [
                    {data: 'name', name: 'name'},
                    {data: 'email', name: 'email'},
                    {data: 'phone', name: 'phone'},
                    {data: 'source', name: 'source'},
                    {data: 'status', name: 'status'},
                    {data: 'assigned_to_name', name: 'assignedUser.name', searchable: false},
                    {data: 'team_name', name: 'team.name', searchable: false},
                    {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end'}
                ]
            });

            $('#filter-team, #filter-status').on('change', function () {
                table.draw();
            });
        });
    </script>
@endsection
