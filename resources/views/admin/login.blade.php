<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Login</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/admin-login.css') }}?v={{ @filemtime(public_path('css/admin-login.css')) }}">
</head>
<body>
    <div class="login-shell">
        <div class="login-brand">
            <img src="{{ asset('images/logo-dark.png') }}" alt="Tago" class="login-logo" height="56" style="height:56px;width:auto">
            <h1 class="visually-hidden">Tago CRM</h1>
            <p>Welcome back — sign in to continue</p>
        </div>

        <div class="login-card p-4 p-sm-5">
            <div class="login-header mb-3">
                <h2>CRM Login</h2>
                <p class="d-none d-sm-block">Use your password or request an OTP</p>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle"></i>
                    <ul class="mb-0 mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <ul class="nav nav-pills mb-4 d-flex" id="loginTabs">
                <li class="nav-item">
                    <button type="button" class="nav-link active w-100" id="tab-password" data-tab="password">
                        <i class="bi bi-lock me-1"></i> Password
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link w-100" id="tab-otp" data-tab="otp">
                        <i class="bi bi-phone me-1"></i> OTP
                    </button>
                </li>
            </ul>

            <div id="pane-password">
                <form method="POST" action="{{ route('admin.login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group-icon">
                            <i class="bi bi-envelope"></i>
                            <input type="email" class="form-control" name="email"
                                value="{{ old('email') }}" placeholder="Enter your email" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group-icon">
                            <i class="bi bi-lock"></i>
                            <input type="password" class="form-control" name="password"
                                placeholder="Enter your password" required>
                        </div>
                    </div>
                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </button>
                </form>
            </div>

            <div id="pane-otp" style="display:none">
                <form method="POST" action="{{ route('admin.otp.send') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group-icon">
                            <i class="bi bi-envelope"></i>
                            <input type="email" class="form-control" name="email"
                                value="{{ old('email') }}" placeholder="Enter your email" required>
                        </div>
                        <div class="form-text">OTP will be sent to the configured admin email(s).</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send"></i> Send OTP
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('#loginTabs .nav-link').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#loginTabs .nav-link').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                var tab = this.dataset.tab;
                document.getElementById('pane-password').style.display = tab === 'password' ? '' : 'none';
                document.getElementById('pane-otp').style.display = tab === 'otp' ? '' : 'none';
            });
        });

        @if(request()->routeIs('admin.login') && session()->has('_otp_tab'))
            document.getElementById('tab-otp').click();
        @endif
    </script>
</body>
</html>
