@extends('admin.layout')

@section('title', 'Dashboard')
@section('page-title', 'Production Dashboard')
@section('page-icon', 'kanban')

@section('content')
<style>
    .pd-tile .num { font-size: 2rem; font-weight: 700; line-height: 1.1; }
    .pd-tile.is-overdue .num { color: #dc3545; }
    .pd-tile.is-today .num { color: #e0a800; }
    .pd-tile.is-week .num { color: #0d6efd; }
    .pd-list { max-height: 420px; overflow-y: auto; }
    .pd-card { display: block; padding: 10px 12px; border-radius: 8px; text-decoration: none; color: inherit; border: 1px solid var(--bs-border-color, rgba(0,0,0,.1)); margin-bottom: 8px; }
    .pd-card:hover { border-color: #579dff; }
    .pd-card .name { font-weight: 600; }
    .pd-card .meta { font-size: 12px; opacity: .75; }
    .pd-card .due { font-size: 12px; font-weight: 600; white-space: nowrap; }
    .pd-card .due.is-overdue { color: #dc3545; }
    .pd-card .due.is-today { color: #e0a800; }
    .pd-label { display: inline-block; width: 28px; height: 6px; border-radius: 3px; margin-right: 3px; background: #9fadbc; }
    .pd-activity li { font-size: 13px; padding: 6px 0; border-bottom: 1px solid var(--bs-border-color, rgba(0,0,0,.08)); }
    .pd-activity li:last-child { border-bottom: 0; }
    .pd-activity .when { font-size: 11px; opacity: .65; }
</style>

@if($teams->isEmpty())
    <div class="alert alert-info">
        Aap kisi team mein nahi hain, isliye koi Trello board nahi hai. Admin se apni team set karwayein.
    </div>
@else
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div class="d-flex gap-2 align-items-center">
            @if($teams->count() > 1)
                <label for="pd-team" class="small text-muted mb-0">Team</label>
                <select id="pd-team" class="form-select form-select-sm" style="width:auto">
                    @foreach($teams as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            @else
                <span class="fw-semibold">{{ $teams->first()->name }}</span>
            @endif
            <span class="small text-muted" id="pd-updated"></span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pd-refresh"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            <a href="{{ route('admin.trello.index', ['team' => $teams->first()->id]) }}" class="btn btn-sm btn-primary" id="pd-boards-link"><i class="bi bi-kanban"></i> Open boards</a>
        </div>
    </div>

    <div class="alert alert-warning d-none" id="pd-error"></div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100 pd-tile is-overdue"><div class="card-body">
            <div class="text-muted small">Late</div><div class="num" data-count="overdue">–</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100 pd-tile is-today"><div class="card-body">
            <div class="text-muted small">Due today</div><div class="num" data-count="today">–</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100 pd-tile is-week"><div class="card-body">
            <div class="text-muted small">Due in next 7 days</div><div class="num" data-count="week">–</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100 pd-tile"><div class="card-body">
            <div class="text-muted small">Boards · open cards</div><div class="num" data-count="boards">–</div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        @foreach(['overdue' => 'Late', 'today' => 'Due today', 'week' => 'Due in next 7 days'] as $key => $title)
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent fw-semibold">{{ $title }}</div>
                    <div class="card-body pd-list" data-list="{{ $key }}">
                        <div class="text-muted small">Loading…</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold">My recent Trello activity</div>
                <div class="card-body">
                    @if($myActivity->isEmpty())
                        <div class="text-muted small">Abhi tak CRM se koi Trello kaam nahi kiya.</div>
                    @else
                        <ul class="list-unstyled mb-0 pd-activity">
                            @foreach($myActivity as $log)
                                <li>You {{ $log->description }}
                                    <div class="when">{{ $log->occurred_at->diffForHumans() }}@if(!empty($log->meta['board_name'])) · {{ $log->meta['board_name'] }}@endif</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold">Team activity</div>
                <div class="card-body">
                    @if($teamActivity->isEmpty())
                        <div class="text-muted small">Team ki taraf se abhi koi activity nahi.</div>
                    @else
                        <ul class="list-unstyled mb-0 pd-activity">
                            @foreach($teamActivity as $log)
                                <li><strong>{{ $log->user?->name ?? 'Someone' }}</strong> {{ $log->description }}
                                    <div class="when">{{ $log->occurred_at->diffForHumans() }}@if(!empty($log->meta['board_name'])) · {{ $log->meta['board_name'] }}@endif</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const overviewUrl = @json(route('admin.production.overview'));
        const boardsBase = @json(route('admin.trello.index'));
        const teamSel = document.getElementById('pd-team');
        const firstTeam = @json($teams->first()->id);
        const LABEL_COLORS = { green:'#4bce97', yellow:'#f5cd47', orange:'#fea362', red:'#f87168', purple:'#9f8fef',
            blue:'#579dff', sky:'#6cc3e0', lime:'#94c748', pink:'#e774bb', black:'#8590a2' };
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));

        function dueText(iso) {
            const d = new Date(iso);
            const opts = { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
            return d.toLocaleString('en-US', opts);
        }

        function render(group, cards) {
            const box = document.querySelector(`[data-list="${group}"]`);
            if (!box) return;
            if (!cards.length) {
                box.innerHTML = '<div class="text-muted small">Koi card nahi 🎉</div>';
                return;
            }
            box.innerHTML = cards.map((c) => `
                <a class="pd-card" href="${esc(c.url)}">
                    <div class="d-flex justify-content-between gap-2">
                        <div class="name">${esc(c.name)}</div>
                        <div class="due ${group === 'overdue' ? 'is-overdue' : (group === 'today' ? 'is-today' : '')}">${esc(dueText(c.due))}</div>
                    </div>
                    ${(c.labels || []).length ? `<div class="mt-1">${c.labels.map((l) => `<span class="pd-label" title="${esc(l.name)}" style="background:${LABEL_COLORS[l.color] || '#9fadbc'}"></span>`).join('')}</div>` : ''}
                    <div class="meta mt-1">${esc(c.board)}${c.list ? ' · ' + esc(c.list) : ''}</div>
                </a>`).join('');
        }

        async function load(refresh = false) {
            const team = teamSel ? teamSel.value : firstTeam;
            document.getElementById('pd-boards-link').href = boardsBase + '?team=' + encodeURIComponent(team);
            const err = document.getElementById('pd-error');
            err.classList.add('d-none');
            document.querySelectorAll('[data-list]').forEach((b) => { b.innerHTML = '<div class="text-muted small">Loading…</div>'; });
            try {
                const res = await fetch(overviewUrl + '?team=' + encodeURIComponent(team) + (refresh ? '&refresh=1' : ''), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.ok === false) throw new Error(data.message || 'Could not load Trello data');
                const c = data.counts || {};
                document.querySelector('[data-count="overdue"]').textContent = c.overdue ?? 0;
                document.querySelector('[data-count="today"]').textContent = c.today ?? 0;
                document.querySelector('[data-count="week"]').textContent = c.week ?? 0;
                document.querySelector('[data-count="boards"]').textContent = `${c.boards ?? 0} · ${c.open_cards ?? 0}`;
                ['overdue', 'today', 'week'].forEach((g) => render(g, (data.cards || {})[g] || []));
                document.getElementById('pd-updated').textContent = data.generated_at
                    ? 'Updated ' + new Date(data.generated_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
                    : '';
            } catch (e) {
                err.textContent = e.message;
                err.classList.remove('d-none');
                document.querySelectorAll('[data-list]').forEach((b) => { b.innerHTML = ''; });
            }
        }

        teamSel?.addEventListener('change', () => load());
        document.getElementById('pd-refresh').addEventListener('click', () => load(true));
        load();
    })();
    </script>
@endif
@endsection
