@extends('admin.layout')

@section('title', 'Leads')
@section('page-title', 'Leads')
@section('page-icon', 'funnel')

@section('content')
    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
        <a href="{{ route('admin.leads.create') }}" class="btn btn-primary">Add Lead</a>

        {{-- Filters --}}
        <select id="filter-team" class="form-select form-select-sm" style="width:auto">
            <option value="">All Teams</option>
            @foreach($teams as $team)
                <option value="{{ $team->id }}">{{ $team->name }}</option>
            @endforeach
        </select>

        <select id="filter-status" class="form-select form-select-sm" style="width:auto">
            <option value="">All Statuses</option>
            @foreach(\App\Models\Lead::STATUS_LABELS as $s => $label)
                <option value="{{ $s }}">{{ $label }} ({{ $counts[$s] ?? 0 }})</option>
            @endforeach
        </select>

        <div class="form-check form-check-inline mb-0">
            <input class="form-check-input" type="checkbox" id="filter-overdue" value="1">
            <label class="form-check-label small" for="filter-overdue">Overdue follow-ups</label>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach(\App\Models\Lead::STATUS_LABELS as $key => $label)
            <span class="badge bg-light text-dark border">{{ $counts[$key] ?? 0 }} {{ $label }}</span>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="leads-table" class="table table-striped w-100">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Company</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Value</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Brand</th>
                        <th>Team</th>
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
            var table = $('#leads-table').DataTable({
                processing: true,
                serverSide: true,
                scrollX: true,
                autoWidth: false,
                ajax: {
                    url: @json(route('admin.leads.datatable')),
                    data: function (d) {
                        d.team_id  = $('#filter-team').val();
                        d.status   = $('#filter-status').val();
                        d.overdue_follow_up = $('#filter-overdue').is(':checked') ? 1 : 0;
                    }
                },
                columns: [
                    {data: 'name', name: 'name'},
                    {data: 'company', name: 'company'},
                    {data: 'email', name: 'email'},
                    {data: 'phone', name: 'phone'},
                    {data: 'lead_value', name: 'lead_value'},
                    {data: 'source', name: 'source'},
                    {data: 'status', name: 'status'},
                    {data: 'assigned_to_name', name: 'assignedUser.name', searchable: false},
                    {data: 'brand_name', name: 'brand.name', searchable: false},
                    {data: 'team_name', name: 'team.name', searchable: false},
                    {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end'}
                ]
            });

            $('#filter-team, #filter-status, #filter-overdue').on('change', function () {
                table.draw();
            });
        });
    </script>
@endsection
