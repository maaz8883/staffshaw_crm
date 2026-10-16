@extends('admin.layout')

@section('title', 'Connect Trello')
@section('page-title', 'Connect Trello · '.$team->name)
@section('page-icon', 'plug')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Each team has its own Trello account — generate the API key and token while signed in to that account.
                    @if(!empty($suggestedEmail))
                        Account for the <strong>{{ $team->name }}</strong> team:
                        <strong>{{ $suggestedEmail }}</strong>.
                    @endif
                </p>
                <ol class="small text-muted mb-0 ps-3">
                    <li>Copy the API key from your Trello Power-Up / app.</li>
                    <li>
                        Token generate:
                        <a href="https://trello.com/power-ups/admin" target="_blank" rel="noopener">trello.com/power-ups/admin</a>
                        → Authorization → Token link → Allow.
                    </li>
                    <li>Paste the key and token here. The CRM verifies the account when you save.</li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Credentials · {{ $team->name }}</div>
            <div class="card-body">
                @if($integration?->isReady())
                    <div class="alert alert-success py-2 small">
                        Connected as <strong>{{ $integration->displayAccount() }}</strong>
                        @if($integration->trello_username)
                            ({{ '@'.$integration->trello_username }})
                        @endif
                        · leave key/token blank to keep current values.
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.trello.store', $team) }}" class="mb-3">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Trello account email</label>
                        <input type="email" name="account_email" class="form-control"
                               value="{{ old('account_email', $integration?->account_email ?: ($suggestedEmail ?? '')) }}"
                               placeholder="{{ $suggestedEmail ?: 'team.trello@staffshaw.com' }}">
                        @error('account_email')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Key @unless($integration)<span class="text-danger">*</span>@endunless</label>
                        <input type="text" name="api_key" class="form-control font-monospace"
                               value="{{ old('api_key') }}"
                               placeholder="{{ $integration ? '•••• leave blank to keep' : 'Your Trello API key' }}"
                               autocomplete="off">
                        @error('api_key')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Token @unless($integration)<span class="text-danger">*</span>@endunless</label>
                        <input type="password" name="api_token" class="form-control font-monospace"
                               value="{{ old('api_token') }}"
                               placeholder="{{ $integration ? '•••• leave blank to keep' : 'Your Trello user token' }}"
                               autocomplete="new-password">
                        @error('api_token')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">Save &amp; verify</button>
                        <a href="{{ route('admin.trello.index', ['team' => $team->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>

                @if($integration)
                    <form method="POST" action="{{ route('admin.trello.verify', $team) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-success btn-sm">Test connection</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
