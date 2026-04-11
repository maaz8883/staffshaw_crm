<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent sign up — CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/admin-login.css') }}">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-card p-5">
                    <div class="login-header">
                        <h2><i class="bi bi-person-plus"></i> Agent sign up</h2>
                        <p>Create an account. An admin or your team lead must approve it before you can sign in.</p>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0 mt-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label">Full name</label>
                            <div class="input-group-icon">
                                <i class="bi bi-person"></i>
                                <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Your name">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group-icon">
                                <i class="bi bi-envelope"></i>
                                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="team_id" class="form-label">Team to join</label>
                            <select class="form-select" id="team_id" name="team_id" required>
                                <option value="">— Select team —</option>
                                @foreach($teams as $team)
                                    <option value="{{ $team->id }}" @selected(old('team_id') == $team->id)>
                                        {{ $team->name }}
                                        @if($team->company)
                                            ({{ $team->company->name }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Your team lead for this team will be notified.</div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group-icon">
                                <i class="bi bi-lock"></i>
                                <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="Min. 8 characters">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">Confirm password</label>
                            <div class="input-group-icon">
                                <i class="bi bi-lock-fill"></i>
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required minlength="8" placeholder="Repeat password">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="bi bi-send"></i> Submit request
                        </button>
                        <p class="text-center mb-0 text-muted small">
                            Already have an account?
                            <a href="{{ route('admin.login') }}">Sign in</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
