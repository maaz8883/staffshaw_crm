@extends('admin.layout')

@section('title', 'Settings')
@section('page-title', 'Settings')
@section('page-icon', 'gear')

@section('content')
<div class="row g-4">

    {{-- ── SMTP Settings ── --}}
    <div class="col-md-7" id="smtp">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-envelope-at"></i> SMTP / Mail Settings
            </div>
            <div class="card-body">

                @if(session('success') && !in_array(request()->fragment, ['otp','backup']))
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.settings.smtp') }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">SMTP Host <span class="text-danger">*</span></label>
                            <input type="text" name="mail_host" class="form-control"
                                value="{{ old('mail_host', $smtp['host']) }}"
                                placeholder="smtp.gmail.com" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Port <span class="text-danger">*</span></label>
                            <input type="number" name="mail_port" class="form-control"
                                value="{{ old('mail_port', $smtp['port']) }}"
                                placeholder="587" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="mail_username" class="form-control"
                                value="{{ old('mail_username', $smtp['username']) }}"
                                placeholder="your@email.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="mail_password" class="form-control"
                                value="{{ old('mail_password', $smtp['password']) }}"
                                placeholder="••••••••"
                                autocomplete="new-password">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Encryption</label>
                            <select name="mail_encryption" class="form-select">
                                @foreach(['tls'=>'TLS','ssl'=>'SSL','starttls'=>'STARTTLS',''=>'None'] as $val => $label)
                                    <option value="{{ $val }}" {{ old('mail_encryption', $smtp['encryption']) === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">From Address <span class="text-danger">*</span></label>
                            <input type="email" name="mail_from_address" class="form-control"
                                value="{{ old('mail_from_address', $smtp['from_address']) }}"
                                placeholder="noreply@yourdomain.com" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">From Name <span class="text-danger">*</span></label>
                            <input type="text" name="mail_from_name" class="form-control"
                                value="{{ old('mail_from_name', $smtp['from_name']) }}"
                                placeholder="CRM System" required>
                        </div>
                    </div>

                    @if($errors->hasAny(['mail_host','mail_port','mail_username','mail_password','mail_encryption','mail_from_address','mail_from_name']))
                        <div class="alert alert-danger mt-3 py-2">
                            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                        </div>
                    @endif

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save SMTP Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── OTP Emails ── --}}
    <div class="col-md-5" id="otp">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-phone"></i> OTP Recipient Emails
            </div>
            <div class="card-body">

                @if(session('success') && request()->fragment === 'otp')
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.settings.otp') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="otp_emails" class="form-label">
                            Email Addresses
                            <span class="text-muted small">(one per line)</span>
                        </label>
                        <textarea id="otp_emails" name="otp_emails"
                            class="form-control font-monospace" rows="8"
                            placeholder="admin@example.com&#10;manager@example.com">{{ old('otp_emails', $otpEmails) }}</textarea>
                        <div class="form-text">
                            OTP will be sent to <strong>all</strong> emails listed here when a user requests OTP login.
                            Leave empty to send to the user's own email.
                        </div>
                    </div>

                    @if($errors->has('otp_emails'))
                        <div class="alert alert-danger py-2">{{ $errors->first('otp_emails') }}</div>
                    @endif

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save OTP Settings
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Database Backup ── --}}
    <div class="col-12" id="backup">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-database-down"></i> Database Backup</span>
                <form method="POST" action="{{ route('admin.backup.store') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle"></i> Create Backup Now
                    </button>
                </form>
            </div>
            <div class="card-body">

                @if(session('success') && request()->fragment === 'backup')
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if($errors->has('backup'))
                    <div class="alert alert-danger py-2">
                        <i class="bi bi-exclamation-triangle"></i> {{ $errors->first('backup') }}
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>File</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th style="width:100px">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="backup-list">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    <span class="spinner-border spinner-border-sm me-1"></span> Loading...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-text mt-2">
                    Backups are stored in <code>storage/app/backups/</code> as gzipped SQL files.
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
(function () {
    function loadBackups() {
        fetch('{{ route('admin.backup.index') }}', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(files => {
            const tbody = document.getElementById('backup-list');
            if (!tbody) return;
            if (!files.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No backups yet.</td></tr>';
                return;
            }
            tbody.innerHTML = files.map(f => `
                <tr>
                    <td class="font-monospace">${f.name}</td>
                    <td>${f.size_human}</td>
                    <td class="text-muted">${f.created_at}</td>
                    <td>
                        <a href="${f.download_url}" class="btn btn-sm btn-outline-primary" title="Download">
                            <i class="bi bi-download"></i>
                        </a>
                        <form action="${f.delete_url}" method="POST" class="d-inline js-admin-delete-form" data-swal-title="Delete this backup?">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            `).join('');
        })
        .catch(() => {
            const tbody = document.getElementById('backup-list');
            if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-3">Failed to load backups.</td></tr>';
        });
    }
    loadBackups();
})();
</script>
@endsection
