@extends('admin.layout')

@section('title', 'Brands')
@section('page-title', 'Brands')
@section('page-icon', 'building')

@section('content')
    <div class="mb-3">
        @if(!auth()->user()->hasRole('Agent'))
        <a href="{{ route('admin.brands.create') }}" class="btn btn-primary">Add Brand</a>
        @endif
    </div>

    <div class="card">
        <div class="card-body">
            <table id="brands-table" class="table table-striped w-100">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Industry</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Assigned To</th>
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
            $('#brands-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: @json(route('admin.brands.datatable')),
                columns: [
                    {data: 'name', name: 'name'},
                    {data: 'industry', name: 'industry'},
                    {data: 'email', name: 'email'},
                    {data: 'phone', name: 'phone'},
                    {data: 'assigned_to', name: 'assignedUser.name', searchable: false},
                    {data: 'status', name: 'status'},
                    {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end'}
                ]
            });
        });
    </script>
@endsection
