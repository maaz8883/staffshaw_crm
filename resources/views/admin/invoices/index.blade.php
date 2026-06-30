@extends('admin.layout')

@section('title', 'Invoices')
@section('page-title', 'Invoices')
@section('page-icon', 'receipt')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="invoices-table" class="table table-striped w-100">
                <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Sale</th>
                    <th>Client</th>
                    <th>Brand</th>
                    <th>Amount</th>
                    <th>Sale Balance</th>
                    <th>Status</th>
                    <th>Date</th>
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
    $('#invoices-table').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        autoWidth: false,
        order: [[7, 'desc']],
        ajax: @json(route('admin.invoices.datatable')),
        columns: [
            {data: 'invoice_number', name: 'invoice_number'},
            {data: 'sale_link', name: 'title', orderable: false, searchable: false},
            {data: 'client_name', name: 'client_name'},
            {data: 'brand_name', name: 'brand_name'},
            {data: 'amount', name: 'amount'},
            {data: 'sale_balance', name: 'sale_balance', searchable: false},
            {data: 'status_badge', name: 'status', searchable: false},
            {data: 'issued_at', name: 'issued_at'},
            {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end'}
        ]
    });
});
</script>
@endsection
