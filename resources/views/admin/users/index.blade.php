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

        <select id="filter-team" class="form-select form-select-sm js-select2" data-width="180px">
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
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="banned" @selected(request('status') === 'banned')>Banned</option>
            <option value="pending" @selected(request('status') === 'pending')>Pending</option>
            <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
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

    {{-- Set a new password (old passwords are stored hashed and can't be shown) --}}
    <div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content" id="passwordForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Change password — <span id="passwordUserName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Current passwords can't be shown (they are stored encrypted). Set a new one and share it with the user privately.</p>
                    <label class="form-label" for="newPassword">New password</label>
                    <div class="input-group mb-2">
                        <input type="password" class="form-control" id="newPassword" name="password" minlength="8" required autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary" id="togglePassword" title="Show / hide"><i class="bi bi-eye"></i></button>
                        <button type="button" class="btn btn-outline-secondary" id="copyPassword" title="Copy"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <input type="hidden" name="password_confirmation" id="newPasswordConfirm">
                    <button type="button" class="btn btn-sm btn-link px-0" id="generatePassword"><i class="bi bi-magic"></i> Generate a strong password</button>
                    <div class="form-text">At least 8 characters.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save password</button>
                </div>
            </form>
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

            // ── Change password popup ──
            var pwModal = new bootstrap.Modal(document.getElementById('passwordModal'));
            var pwInput = document.getElementById('newPassword');
            $(document).on('click', '.js-change-password', function () {
                document.getElementById('passwordForm').action = this.dataset.url;
                document.getElementById('passwordUserName').textContent = this.dataset.name;
                pwInput.value = '';
                pwInput.type = 'password';
                pwModal.show();
                setTimeout(function () { pwInput.focus(); }, 300);
            });
            document.getElementById('togglePassword').addEventListener('click', function () {
                pwInput.type = pwInput.type === 'password' ? 'text' : 'password';
            });
            document.getElementById('generatePassword').addEventListener('click', function () {
                // 12 characters, no look-alikes (0/O, 1/l/I)
                var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
                var bytes = new Uint32Array(12);
                window.crypto.getRandomValues(bytes);
                pwInput.value = Array.from(bytes, function (b) { return chars[b % chars.length]; }).join('');
                pwInput.type = 'text'; // show it so it can be copied
            });
            document.getElementById('copyPassword').addEventListener('click', function () {
                if (!pwInput.value) return;
                navigator.clipboard && navigator.clipboard.writeText(pwInput.value);
                this.innerHTML = '<i class="bi bi-clipboard-check"></i>';
                var btn = this;
                setTimeout(function () { btn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1500);
            });
            document.getElementById('passwordForm').addEventListener('submit', function () {
                document.getElementById('newPasswordConfirm').value = pwInput.value;
            });
        });
    </script>
@endsection
