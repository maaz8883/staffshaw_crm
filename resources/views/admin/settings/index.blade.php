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

                @if(session('success') && request()->fragment !== 'otp')
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

</div>
@endsection
