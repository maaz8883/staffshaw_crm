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
    /* Workspace groups (like Trello's "Your workspaces") */
    .trello-ws { margin-bottom: 28px; }
    .trello-ws-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .trello-ws-icon {
        width: 32px; height: 32px; border-radius: 6px; flex: 0 0 32px;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 15px;
    }
    .trello-ws-name { font-weight: 700; font-size: 16px; margin: 0; }
    .trello-ws-count { font-size: 12px; color: var(--text-muted); }
    .trello-board-search { max-width: 280px; }
</style>

<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <form method="GET" action="{{ route('admin.trello.index') }}" class="d-flex gap-2 align-items-center flex-wrap">
        <label class="small text-muted mb-0">Team</label>
        <select name="team" class="form-select form-select-sm js-select2" data-width="220px" style="width:220px" onchange="this.form.submit()">
            @foreach($teams as $t)
                <option value="{{ $t->id }}" @selected((int) $team->id === (int) $t->id)>{{ \App\Support\TeamTrelloAccounts::label($t) }}</option>
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
        @php
            // Group boards by Trello workspace (A–Z), boards without a workspace last
            $personal = 'Personal boards';
            $workspaces = collect($boards)
                ->groupBy(fn ($b) => data_get($b, 'organization.displayName') ?: $personal)
                ->map(fn ($group) => $group->sortBy(fn ($b) => mb_strtolower($b['name'] ?? ''))->values())
                ->sortBy(fn ($group, $name) => ($name === $personal ? '1' : '0').mb_strtolower($name));
            $wsColors = ['#579dff', '#4bce97', '#f87168', '#9f8fef', '#fea362', '#6cc3e0', '#e774bb', '#94c748'];
        @endphp

        @if(count($boards) > 8)
            <div class="mb-3">
                <input type="search" id="trello-board-filter" class="form-control form-control-sm trello-board-search"
                       placeholder="Search boards…" autocomplete="off">
            </div>
        @endif

        @foreach($workspaces as $wsName => $wsBoards)
            <section class="trello-ws" data-ws>
                <div class="trello-ws-head">
                    <span class="trello-ws-icon" style="background:{{ $wsColors[crc32($wsName) % count($wsColors)] }}">
                        {{ mb_strtoupper(mb_substr($wsName, 0, 1)) }}
                    </span>
                    <h6 class="trello-ws-name">{{ $wsName }}</h6>
                    <span class="trello-ws-count">{{ $wsBoards->count() }} board{{ $wsBoards->count() === 1 ? '' : 's' }}</span>
                </div>
                <div class="row g-3">
                    @foreach($wsBoards as $board)
                        @php
                            $bg = data_get($board, 'prefs.backgroundColor') ?: '#0079bf';
                            $bgImage = data_get($board, 'prefs.backgroundImageScaled.0.url')
                                ?: data_get($board, 'prefs.backgroundImage');
                        @endphp
                        <div class="col-sm-6 col-lg-4 col-xl-3" data-board-name="{{ mb_strtolower($board['name'] ?? '') }}">
                            <div class="trello-board-card">
                                <a href="{{ route('admin.trello.board', ['boardId' => $board['id'], 'team' => $team->id]) }}">
                                    <div class="trello-board-swatch"
                                         style="@if($bgImage) background:url('{{ $bgImage }}') center/cover no-repeat; @else background:{{ $bg }}; @endif"></div>
                                    <div class="p-3">
                                        <div class="fw-semibold text-truncate">{{ $board['name'] }}</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
        <div class="text-muted small d-none" id="trello-board-empty">No board matches your search.</div>

        <script>
        (function () {
            const input = document.getElementById('trello-board-filter');
            if (!input) return;
            input.addEventListener('input', () => {
                const q = input.value.trim().toLowerCase();
                let any = false;
                document.querySelectorAll('[data-ws]').forEach((ws) => {
                    let shown = 0;
                    ws.querySelectorAll('[data-board-name]').forEach((col) => {
                        const hit = !q || col.dataset.boardName.includes(q);
                        col.classList.toggle('d-none', !hit);
                        if (hit) shown++;
                    });
                    ws.classList.toggle('d-none', shown === 0); // hide workspaces with no match
                    if (shown) any = true;
                });
                document.getElementById('trello-board-empty').classList.toggle('d-none', any);
            });
        })();
        </script>
    @endif
@endif
@endsection
