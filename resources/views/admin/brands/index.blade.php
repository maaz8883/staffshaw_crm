@extends('admin.layout')

@section('title', 'Brands')
@section('page-title', 'Brands')
@section('page-icon', 'building')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.brands.create') }}" class="btn btn-primary">Add Brand</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="brands-table" class="table table-striped w-100">
                    <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>URL</th>
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
            $('#brands-table').DataTable({
                processing: true,
                serverSide: true,
                scrollX: true,
                autoWidth: false,
                ajax: @json(route('admin.brands.datatable')),
                columns: [
                    {data: 'image_thumb', name: 'image', orderable: false, searchable: false},
                    {data: 'name', name: 'name'},
                    {data: 'website', name: 'website'},
                    {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end'}
                ]
            });
        });
    </script>
@endsection
