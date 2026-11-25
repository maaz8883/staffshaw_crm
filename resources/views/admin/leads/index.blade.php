@extends('admin.layout')

@section('title', 'Leads')
@section('page-title', 'Leads')
@section('page-icon', 'funnel')

@section('content')
    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
        <a href="{{ route('admin.leads.create') }}" class="btn btn-primary">Add Lead</a>
        <a href="{{ route('admin.leads.import') }}" class="btn btn-outline-primary">
            <i class="bi bi-upload"></i> Import CSV
        </a>

        {{-- Filters (team filter only when there's more than one team to pick) --}}
        @if($teams->count() > 1)
        <select id="filter-team" class="form-select form-select-sm js-select2" data-width="180px" style="width:180px">
            <option value="">All Teams</option>
            @foreach($teams as $team)
                <option value="{{ $team->id }}">{{ $team->name }}</option>
            @endforeach
        </select>
        @endif

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

    @if($canBulkDelete ?? false)
    <div id="leads-bulk-bar" class="alert alert-light border d-none align-items-center gap-2 flex-wrap mb-3 py-2">
        <span class="small text-muted"><span id="leads-bulk-count">0</span> selected</span>
        <button type="button" id="btn-bulk-delete" class="btn btn-outline-danger btn-sm" disabled>
            <i class="bi bi-trash"></i> Delete selected
        </button>
        <button type="button" id="btn-bulk-clear" class="btn btn-outline-secondary btn-sm">Clear</button>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="leads-table" class="table table-striped w-100">
                    <thead>
                    <tr>
                        <th class="text-center" style="width:36px">
                            <input type="checkbox" class="form-check-input" id="lead-select-all" title="Select all on this page" aria-label="Select all">
                        </th>
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
            var canBulkDelete = @json((bool) ($canBulkDelete ?? false));
            var bulkDeleteUrl = @json(route('admin.leads.bulk-delete'));
            var csrf = @json(csrf_token());

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
                    {data: 'select', name: 'select', orderable: false, searchable: false, className: 'text-center'},
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
                ],
                drawCallback: function () {
                    $('#lead-select-all').prop('checked', false);
                    syncBulkBar();
                }
            });

            function selectedIds() {
                var ids = [];
                $('#leads-table .lead-row-check:checked').each(function () {
                    if ($(this).attr('data-can-delete') === '1') {
                        ids.push(parseInt($(this).val(), 10));
                    }
                });
                return ids;
            }

            function syncBulkBar() {
                var n = $('#leads-table .lead-row-check:checked').length;
                var bar = $('#leads-bulk-bar');
                if (!bar.length) return;
                $('#leads-bulk-count').text(n);
                if (n > 0) bar.removeClass('d-none').addClass('d-flex');
                else bar.addClass('d-none').removeClass('d-flex');
                if (canBulkDelete) {
                    $('#btn-bulk-delete').prop('disabled', selectedIds().length === 0);
                }
            }

            $(document).on('change', '.lead-row-check', syncBulkBar);

            $('#lead-select-all').on('change', function () {
                var on = $(this).is(':checked');
                $('#leads-table .lead-row-check').prop('checked', on);
                syncBulkBar();
            });

            $('#btn-bulk-clear').on('click', function () {
                $('#leads-table .lead-row-check, #lead-select-all').prop('checked', false);
                syncBulkBar();
            });

            $('#btn-bulk-delete').on('click', function () {
                var ids = selectedIds();
                if (!ids.length) return;
                var go = function () {
                    fetch(bulkDeleteUrl, {
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
                        title: 'Delete selected leads?',
                        text: ids.length + ' lead(s) will be permanently deleted.',
                        showCancelButton: true,
                        confirmButtonText: 'Delete',
                        confirmButtonColor: '#dc3545'
                    }).then(function (r) { if (r.isConfirmed) go(); });
                } else if (confirm('Delete ' + ids.length + ' lead(s)?')) {
                    go();
                }
            });

            $('#filter-team, #filter-status, #filter-overdue').on('change', function () {
                table.draw();
            });
        });
    </script>
@endsection
