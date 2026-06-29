@extends('admin.layout')

@section('title', 'Brief Form Types')
@section('page-title', 'Brief Form Types')
@section('page-icon', 'ui-checks')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.brief-form-types.create') }}" class="btn btn-primary">Add Brief Form Type</a>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="brief-form-types-table" class="table table-striped w-100">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Default Path</th>
                    <th>Sort</th>
                    <th>Active</th>
                    <th>Brands</th>
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
            $('#brief-form-types-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: @json(route('admin.brief-form-types.datatable')),
                order: [[3, 'asc'], [0, 'asc']],
                columns: [
                    {data: 'name', name: 'name'},
                    {data: 'slug', name: 'slug'},
                    {data: 'default_form_path', name: 'default_form_path'},
                    {data: 'sort_order', name: 'sort_order'},
                    {data: 'is_active_badge', name: 'is_active', orderable: true, searchable: false},
                    {data: 'brand_brief_forms_count', name: 'brand_brief_forms_count', searchable: false},
                    {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end'}
                ]
            });
        });
    </script>
@endsection
