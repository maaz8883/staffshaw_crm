<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter OTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/admin-login.css') }}">
    <style>
        .otp-timer { font-size: 2rem; font-weight: 700; letter-spacing: 2px; }
        .otp-timer.warning { color: #d4860a; }
        .otp-timer.danger  { color: #A01830; }
        .otp-timer.safe    { color: #2E86C1; }
        .timer-ring {
            display: flex; align-items: center; justify-content: center;
            gap: 0.5rem; padding: 0.75rem 1.25rem; border-radius: 12px;
            background: #f8f9fc; border: 1px solid #e8eaf0; margin-bottom: 1.25rem;
        }
        .otp-input { letter-spacing: 0.6rem; font-size: 1.6rem; text-align: center; font-weight: 700; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card p-5">
                    <div class="login-header mb-3">
                        <h2><i class="bi bi-shield-check"></i> Enter OTP</h2>
                        <p>Check the configured admin email(s) for your 6-digit code.</p>
                    </div>

                    @if(session('info'))
                        <div class="alert alert-info py-2">
                            <i class="bi bi-info-circle"></i> {{ session('info') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger py-2">
                            @foreach($errors->all() as $error)
                                <div><i class="bi bi-exclamation-circle"></i> {{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Timer --}}
                    <div class="timer-ring">
                        <i class="bi bi-clock fs-5 text-muted"></i>
                        <span class="otp-timer safe" id="timer">05:00</span>
                        <span class="text-muted small">remaining</span>
                    </div>

                    {{-- Verify form --}}
                    <form method="POST" action="{{ route('admin.otp.verify') }}" id="otp-form">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="text" class="form-control" value="{{ $email }}" disabled>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">6-Digit OTP</label>
                            <input type="text" name="otp" id="otp-input"
                                class="form-control otp-input"
                                maxlength="6" placeholder="000000"
                                autocomplete="off" autofocus required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-2" id="submit-btn">
                            <i class="bi bi-check-circle"></i> Verify & Login
                        </button>
                    </form>

                    {{-- Resend form --}}
                    <form method="POST" action="{{ route('admin.otp.resend') }}" id="resend-form">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">
                        <button type="submit" id="resend-btn"
                            class="btn btn-outline-primary w-100 mb-3" disabled>
                            <i class="bi bi-arrow-clockwise"></i>
                            <span id="resend-label">Resend OTP</span>
                        </button>
                    </form>

                    <div class="text-center">
                        <a href="{{ route('admin.login') }}" class="text-muted small">
                            <i class="bi bi-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
        var DURATION     = 5 * 60;
        var storageKey   = 'otp_sent_at_{{ md5($email) }}';
        var serverSentAt = {{ $sentAt }}; // ms from DB — fixed per OTP

        // Only update storage if server has a newer OTP
        var stored = sessionStorage.getItem(storageKey);
        if (!stored || serverSentAt > parseInt(stored)) {
            sessionStorage.setItem(storageKey, serverSentAt.toString());
        }

        var sentAt     = parseInt(sessionStorage.getItem(storageKey));
        var timerEl    = document.getElementById('timer');
        var submitBtn  = document.getElementById('submit-btn');
        var otpInput   = document.getElementById('otp-input');
        var resendBtn  = document.getElementById('resend-btn');
        var resendLabel = document.getElementById('resend-label');
        var otpForm    = document.getElementById('otp-form');

        function pad(n) { return n < 10 ? '0' + n : n; }

        function getRemaining() {
            return Math.max(0, DURATION - Math.floor((Date.now() - sentAt) / 1000));
        }

        function onExpired() {
            clearInterval(interval);
            timerEl.textContent = '00:00';
            timerEl.className   = 'otp-timer danger';

            // Lock verify form
            submitBtn.disabled    = true;
            otpInput.disabled     = true;
            otpForm.style.opacity = '0.5';

            // Enable resend
            resendBtn.disabled    = false;
            resendLabel.textContent = 'Resend OTP';
            sessionStorage.removeItem(storageKey);
        }

        function render() {
            var remaining = getRemaining();
            var mins = Math.floor(remaining / 60);
            var secs = remaining % 60;
            timerEl.textContent = pad(mins) + ':' + pad(secs);

            timerEl.className = 'otp-timer';
            if (remaining <= 30)      timerEl.classList.add('danger');
            else if (remaining <= 90) timerEl.classList.add('warning');
            else                      timerEl.classList.add('safe');

            if (remaining <= 0) { onExpired(); return; }

            // Show countdown on resend button while active
            resendBtn.disabled    = true;
            resendLabel.textContent = 'Resend in ' + pad(mins) + ':' + pad(secs);
        }

        render();
        var interval = setInterval(render, 1000);
    })();
    </script>
</body>
</html>
