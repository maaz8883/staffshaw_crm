@extends('admin.layout')

@section('title', 'Sales')
@section('page-title', 'Sales')
@section('page-icon', 'cash-stack')

@section('content')

@if($pendingCount > 0 && !$isAgent)
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-hourglass-split fs-5"></i>
    <span><strong>{{ $pendingCount }}</strong> sale(s) pending your approval.</span>
</div>
@endif

<div class="mb-3 d-flex gap-2 flex-wrap align-items-end">
    @if(! auth()->user()->hasRole(['Admin', 'Finance']))
    <a href="{{ route('admin.sales.create') }}" class="btn btn-primary">Add Sale</a>
    @endif
    @if(auth()->user()->hasRole(['Admin', 'Manager', 'Agent', 'Project Manager']))
    <a href="{{ route('admin.sales.import') }}" class="btn btn-outline-primary">
        <i class="bi bi-upload"></i> Import CSV
    </a>
    @endif

    @if(!$isAgent)
    {{-- Team filter only when there's more than one team to pick (a team lead has just theirs) --}}
    @if($teams->count() > 1)
    <select id="filter-team" class="form-select form-select-sm js-select2" data-width="180px" style="width:180px">
        <option value="">All Teams</option>
        @foreach($teams as $team)
            <option value="{{ $team->id }}">{{ $team->name }}</option>
        @endforeach
    </select>
    @endif

    <select id="filter-user" class="form-select form-select-sm js-select2" data-width="180px" style="width:180px">
        <option value="">All Agents</option>
        @foreach($users as $user)
            <option value="{{ $user->id }}">{{ $user->name }}</option>
        @endforeach
    </select>
    @endif

    <select id="filter-status" class="form-select form-select-sm" style="width:auto">
        <option value="">All Statuses</option>
        <option value="completed">Completed</option>
        <option value="pending">Pending</option>
        <option value="cancelled">Cancelled</option>
        <option value="{{ \App\Models\Sale::STATUS_REFUNDED }}">Refunded</option>
        <option value="{{ \App\Models\Sale::STATUS_CHARGEBACK }}">Chargeback</option>
    </select>

    <select id="filter-approval" class="form-select form-select-sm" style="width:auto">
        <option value="">All Approvals</option>
        <option value="pending_approval">Pending Approval</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
    </select>

    <select id="filter-sale-type" class="form-select form-select-sm" style="width:auto">
        <option value="">All types</option>
        <option value="front">Front</option>
        <option value="upsell">Upsell</option>
    </select>

    <select id="filter-brand" class="form-select form-select-sm js-select2" data-width="180px" style="width:180px">
        <option value="">All Brands</option>
        @foreach($brands as $brand)
            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
        @endforeach
    </select>

    <select id="filter-month" class="form-select form-select-sm" style="width:auto">
        <option value="">All Months</option>
        @foreach(range(1, 12) as $m)
            <option value="{{ $m }}">{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
        @endforeach
    </select>

    <select id="filter-year" class="form-select form-select-sm" style="width:auto">
        <option value="">All Years</option>
        @foreach(range((int) now()->year + 1, 2020, -1) as $y)
            <option value="{{ $y }}">{{ $y }}</option>
        @endforeach
    </select>

    <select id="filter-remaining" class="form-select form-select-sm" style="width:auto">
        <option value="">All Remaining</option>
        <option value="zero">Fully paid</option>
        <option value="outstanding">Outstanding balance</option>
    </select>

    <button id="btn-reset" class="btn btn-outline-secondary btn-sm">Reset</button>
</div>

@if(($canBulkApprove ?? false) || ($canBulkDelete ?? false))
<div id="sales-bulk-bar" class="alert alert-secondary d-none align-items-center flex-wrap gap-2 mb-3 py-2">
    <span class="me-1"><strong id="sales-bulk-count">0</strong> selected</span>
    @if($canBulkApprove ?? false)
    <button type="button" id="btn-bulk-approve" class="btn btn-success btn-sm">
        <i class="bi bi-check2-circle"></i> Approve selected
    </button>
    @endif
    @if($canBulkDelete ?? false)
    <button type="button" id="btn-bulk-delete" class="btn btn-outline-danger btn-sm">
        <i class="bi bi-trash"></i> Delete selected
    </button>
    @endif
    <button type="button" id="btn-bulk-clear" class="btn btn-outline-secondary btn-sm">Clear</button>
</div>
@endif

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="sales-table" class="table table-striped w-100">
                <thead>
                <tr>
                    <th class="text-center" style="width:36px">
                        <input type="checkbox" class="form-check-input" id="sale-select-all" title="Select all on this page" aria-label="Select all">
                    </th>
                    <th>Title</th>
                    <th>Client</th>
                    <th>Brand</th>
                    <th>Type</th>
                    <th>Service</th>
                    <th>Amount</th>
                    <th>Received</th>
                    <th>Remaining</th>
                    <th>Date</th>
                    <th>Agent</th>
                    <th>Team</th>
                    <th>Status</th>
                    <th>Approval</th>
                    <th class="text-center">Refund</th>
                    <th class="text-center">Chargeback</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject Sale</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label for="approval_note" class="form-label">Reason for rejection <span class="text-danger">*</span></label>
                    <textarea name="approval_note" id="approval_note" class="form-control" rows="3" required
                        placeholder="Explain why this sale is being rejected..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Sale</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <style>
        #sales-table th:last-child,
        #sales-table td:last-child {
            white-space: nowrap;
            min-width: 220px;
        }
    </style>
@endpush

@section('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
@if(session()->has('trello_diagnostics'))
const crmDiagnostics = @json(session('trello_diagnostics'));
const crmConsoleMethod = crmDiagnostics.status === 'connection_error'
    ? 'error'
    : (crmDiagnostics.status === 'failed' ? 'warn' : 'info');

console.group('CRM sender');
console[crmConsoleMethod](crmDiagnostics.message ?? 'CRM request completed.', crmDiagnostics);
console.groupEnd();
@endif

$(function () {
    var canBulkApprove = @json((bool) ($canBulkApprove ?? false));
    var canBulkDelete = @json((bool) ($canBulkDelete ?? false));
    var bulkApproveUrl = @json(route('admin.sales.bulk-approve'));
    var bulkDeleteUrl = @json(route('admin.sales.bulk-delete'));
    var csrf = @json(csrf_token());

    var table = $('#sales-table').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        autoWidth: false,
        ajax: {
            url: @json(route('admin.sales.datatable')),
            data: function (d) {
                d.team_id          = $('#filter-team').val();
                d.user_id          = $('#filter-user').val();
                d.status           = $('#filter-status').val();
                d.approval_status  = $('#filter-approval').val();
                d.sale_type        = $('#filter-sale-type').val();
                d.brand_id         = $('#filter-brand').val();
                d.month            = $('#filter-month').val();
                d.year             = $('#filter-year').val();
                d.remaining_filter = $('#filter-remaining').val();
            }
        },
        columns: [
            {data: 'select',         name: 'select', orderable: false, searchable: false, className: 'text-center'},
            {data: 'title',          name: 'title'},
            {data: 'client_name',    name: 'client_name'},
            {data: 'brand_name',     name: 'brand.name', searchable: false},
            {data: 'sale_type_badge', name: 'sale_type', orderable: true, searchable: false},
            {data: 'service_category_badge', name: 'service_category', orderable: true, searchable: false},
            {data: 'amount',         name: 'amount'},
            {data: 'received_amount', name: 'received_amount'},
            {data: 'remaining_amount', name: 'remaining_amount', orderable: true, searchable: false},
            {data: 'sale_date',      name: 'sale_date'},
            {data: 'agent_name',     name: 'user.name',    searchable: false},
            {data: 'team_name',      name: 'team.name',    searchable: false},
            {data: 'status',         name: 'status'},
            {data: 'approval_badge', name: 'approval_status'},
            {data: 'refund_toggle',  name: 'is_refunded', orderable: true, searchable: false, className: 'text-center'},
            {data: 'chargeback_toggle', name: 'is_chargeback', orderable: true, searchable: false, className: 'text-center'},
            {data: 'actions',        name: 'actions', orderable: false, searchable: false, className: 'text-end'}
        ],
        drawCallback: function () {
            $('#sale-select-all').prop('checked', false);
            syncBulkBar();
        }
    });

    function selectedIds(attr) {
        var ids = [];
        $('#sales-table .sale-row-check:checked').each(function () {
            if (!attr || $(this).attr(attr) === '1') {
                ids.push(parseInt($(this).val(), 10));
            }
        });
        return ids;
    }

    function syncBulkBar() {
        var n = $('#sales-table .sale-row-check:checked').length;
        var bar = $('#sales-bulk-bar');
        if (!bar.length) return;
        $('#sales-bulk-count').text(n);
        if (n > 0) bar.removeClass('d-none').addClass('d-flex');
        else bar.addClass('d-none').removeClass('d-flex');
        if (canBulkApprove) {
            $('#btn-bulk-approve').prop('disabled', selectedIds('data-can-approve').length === 0);
        }
        if (canBulkDelete) {
            $('#btn-bulk-delete').prop('disabled', selectedIds('data-can-delete').length === 0);
        }
    }

    $(document).on('change', '.sale-row-check', syncBulkBar);

    $('#sale-select-all').on('change', function () {
        var on = $(this).is(':checked');
        $('#sales-table .sale-row-check').prop('checked', on);
        syncBulkBar();
    });

    $('#btn-bulk-clear').on('click', function () {
        $('#sales-table .sale-row-check, #sale-select-all').prop('checked', false);
        syncBulkBar();
    });

    function postBulk(url, ids, confirmTitle) {
        if (!ids.length) return;
        var go = function () {
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ ids: ids })
            }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
              .then(function (res) {
                  if (!res.ok || res.d.ok === false) throw new Error(res.d.message || 'Request failed');
                  if (window.Swal) Swal.fire({ icon: 'success', title: res.d.message || 'Done', timer: 2200, showConfirmButton: false });
                  else alert(res.d.message || 'Done');
                  table.draw(false);
              })
              .catch(function (err) {
                  if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                  else alert(err.message);
              });
        };
        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: confirmTitle,
                text: ids.length + ' sale(s) will be affected.',
                showCancelButton: true,
                confirmButtonText: 'Yes, continue'
            }).then(function (r) { if (r.isConfirmed) go(); });
        } else if (confirm(confirmTitle)) {
            go();
        }
    }

    $('#btn-bulk-approve').on('click', function () {
        postBulk(bulkApproveUrl, selectedIds('data-can-approve'), 'Approve selected sales?');
    });
    $('#btn-bulk-delete').on('click', function () {
        postBulk(bulkDeleteUrl, selectedIds('data-can-delete'), 'Delete selected sales?');
    });

    $('#filter-team, #filter-user, #filter-status, #filter-approval, #filter-sale-type, #filter-brand, #filter-month, #filter-year, #filter-remaining').on('change', function () {
        table.draw();
    });

    $('#btn-reset').on('click', function () {
        $('#filter-team, #filter-user, #filter-status, #filter-approval, #filter-sale-type, #filter-brand, #filter-month, #filter-year, #filter-remaining').val('').trigger('change.select2');
        table.draw();
    });

    // Reject modal
    $(document).on('click', '.btn-reject', function () {
        var url = $(this).data('url');
        $('#rejectForm').attr('action', url);
        $('#approval_note').val('');
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    });

    function bindMoneyToggle(switchSelector, activeAttr, amountField, title, confirmLabel) {
        $(document).on('change', switchSelector, function () {
            var input = this;
            var form = $(input).closest('form')[0];
            if (!form) return;

            var wasActive = form.getAttribute(activeAttr) === '1';
            if (wasActive) {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Revert ' + title.toLowerCase() + '?',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, revert'
                    }).then(function (r) {
                        if (r.isConfirmed) form.submit();
                        else input.checked = true;
                    });
                } else if (confirm('Revert ' + title.toLowerCase() + '?')) {
                    form.submit();
                } else {
                    input.checked = true;
                }
                return;
            }

            var max = form.getAttribute('data-max') || '0';
            var def = form.getAttribute('data-default') || max;
            var hidden = form.querySelector('input[name="' + amountField + '"]');

            if (window.Swal) {
                Swal.fire({
                    title: title + ' amount',
                    input: 'number',
                    inputLabel: 'How much? (max $' + max + ')',
                    inputValue: def,
                    inputAttributes: { min: '0.01', max: max, step: '0.01' },
                    showCancelButton: true,
                    confirmButtonText: confirmLabel,
                    inputValidator: function (value) {
                        var n = parseFloat(value);
                        if (!n || n <= 0) return 'Enter the amount';
                        if (n > parseFloat(max)) return 'Cannot exceed $' + max;
                        return null;
                    }
                }).then(function (r) {
                    if (r.isConfirmed) {
                        if (hidden) hidden.value = r.value;
                        form.submit();
                    } else {
                        input.checked = false;
                    }
                });
            } else {
                var value = prompt(title + ' amount (max $' + max + ')', def);
                var n = parseFloat(value);
                if (!value || !n || n <= 0 || n > parseFloat(max)) {
                    input.checked = false;
                    alert('Enter a valid amount up to $' + max);
                    return;
                }
                if (hidden) hidden.value = n;
                form.submit();
            }
        });
    }

    bindMoneyToggle('.js-refund-switch', 'data-refunded', 'refunded_amount', 'Refund', 'Mark refunded');
    bindMoneyToggle('.js-chargeback-switch', 'data-chargeback', 'chargeback_amount', 'Chargeback', 'Mark chargeback');
});
</script>
@endsection
