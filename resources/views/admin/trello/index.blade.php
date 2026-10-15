@extends('admin.layout')

@section('title', 'Trello')
@section('page-title', 'Trello')
@section('page-icon', 'trello')

@section('content')
<style>
    .trello-board-card {
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-card);
        transition: border-color .15s ease, transform .15s ease;
        height: 100%;
    }
    .trello-board-card:hover {
        border-color: #0079bf;
        transform: translateY(-1px);
    }
    .trello-board-card a { color: inherit; text-decoration: none; }
    .trello-board-swatch {
        height: 72px;
        border-radius: 10px 10px 0 0;
        background: linear-gradient(135deg, #0079bf, #026aa7);
    }
    .trello-meta { font-size: 12px; color: var(--text-muted); }
</style>

<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <form method="GET" action="{{ route('admin.trello.index') }}" class="d-flex gap-2 align-items-center flex-wrap">
        <label class="small text-muted mb-0">Team</label>
        <select name="team" class="form-select form-select-sm" style="width:auto;min-width:200px" onchange="this.form.submit()">
            @foreach($teams as $t)
                <option value="{{ $t->id }}" @selected((int) $team->id === (int) $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
    </form>
    <div class="d-flex gap-2 flex-wrap">
        @if($canManage)
            <a href="{{ route('admin.trello.edit', $team) }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plug"></i> {{ $integration?->isReady() ? 'Update connection' : 'Connect Trello' }}
            </a>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error') || $error)
    <div class="alert alert-danger">{{ session('error') ?? $error }}</div>
@endif

@if(! $integration?->isReady())
    <div class="card">
        <div class="card-body py-5 text-center">
            <div class="mb-2"><i class="bi bi-kanban" style="font-size:2rem;color:#0079bf"></i></div>
            <h5 class="mb-1">Trello not connected for {{ $team->name }}</h5>
            <p class="text-muted mb-0" style="max-width:440px;margin:0 auto">
                Each team uses its own Trello API key + token (separate account).
                @if(!empty($suggestedEmail))
                    For this team use <strong>{{ $suggestedEmail }}</strong>.
                @endif
            </p>
            @if($canManage)
                <a href="{{ route('admin.trello.edit', $team) }}" class="btn btn-primary mt-3">Connect this team</a>
            @endif
        </div>
    </div>
@else
    <div class="d-flex flex-wrap gap-3 align-items-center mb-3 trello-meta">
        <span>
            <i class="bi bi-person-check"></i>
            {{ $integration->displayAccount() }}
            @if($integration->trello_username)
                <span class="text-muted">({{ '@'.$integration->trello_username }})</span>
            @endif
        </span>
        @if($integration->last_verified_at)
            <span>Verified {{ $integration->last_verified_at->diffForHumans() }}</span>
        @endif
        <span>{{ count($boards) }} board{{ count($boards) === 1 ? '' : 's' }}</span>
    </div>

    @if(empty($boards) && ! $error)
        <div class="card"><div class="card-body text-muted text-center py-5">No open boards on this Trello account.</div></div>
    @else
        <div class="row g-3">
            @foreach($boards as $board)
                @php
                    $bg = data_get($board, 'prefs.backgroundColor') ?: '#0079bf';
                    $bgImage = data_get($board, 'prefs.backgroundImageScaled.0.url')
                        ?: data_get($board, 'prefs.backgroundImage');
                @endphp
                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <div class="trello-board-card">
                        <a href="{{ route('admin.trello.board', ['boardId' => $board['id'], 'team' => $team->id]) }}">
                            <div class="trello-board-swatch"
                                 style="@if($bgImage) background:url('{{ $bgImage }}') center/cover no-repeat; @else background:{{ $bg }}; @endif"></div>
                            <div class="p-3">
                                <div class="fw-semibold mb-1 text-truncate">{{ $board['name'] }}</div>
                                <div class="trello-meta text-truncate">
                                    {{ data_get($board, 'organization.displayName') ?: 'Personal / Workspace' }}
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endif
@endsection
