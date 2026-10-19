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
    .pd-tile.is-stale .num { color: #fea362; }
    .pd-tile.is-done .num { color: #22a06b; }
    .pd-tile .sub { font-size: 12px; opacity: .7; }
    /* Needs attention chips */
    .pd-attn { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
    .pd-attn .item { border-radius: 10px; padding: 10px 14px; border: 1px solid var(--bs-border-color, rgba(0,0,0,.1)); }
    .pd-attn .item .n { font-size: 1.4rem; font-weight: 700; }
    .pd-attn .item .t { font-size: 12px; opacity: .75; }
    /* Pipeline */
    .pd-stage-dot { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 5px; vertical-align: -1px; }
    .pd-bar { display: flex; height: 10px; border-radius: 5px; overflow: hidden; background: rgba(128,128,128,.15); min-width: 120px; }
    .pd-bar span { display: block; height: 100%; }
    .pd-bar.lg { height: 16px; border-radius: 8px; }
    .pd-table { font-size: 13px; }
    .pd-table td, .pd-table th { padding: 6px 8px; vertical-align: middle; }
    .pd-table a { color: inherit; text-decoration: none; }
    .pd-table a:hover { text-decoration: underline; }
    .pd-scroll { max-height: 380px; overflow-y: auto; }
    .pd-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; padding: 2px 9px; border-radius: 999px; border: 1px solid var(--bs-border-color, rgba(0,0,0,.15)); background: transparent; color: inherit; cursor: pointer; }
    .pd-chip.active { border-color: #579dff; color: #579dff; }
    .pd-days { font-size: 12px; font-weight: 600; white-space: nowrap; color: #fea362; }
    .pd-avatar { display: inline-flex; width: 26px; height: 26px; border-radius: 50%; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #fff; background: #579dff; margin-right: 8px; flex: 0 0 26px; }
    .pd-help { font-size: 12px; opacity: .7; }
</style>

@if($teams->isEmpty())
    <div class="alert alert-info">
        You are not in a team yet, so there are no Trello boards to show. Ask an admin to add you to your team.
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

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100 pd-tile is-overdue"><div class="card-body">
            <div class="text-muted small">Late</div><div class="num" data-count="overdue">–</div><div class="sub" data-sub="overdue"></div></div></div></div>
        <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100 pd-tile is-today"><div class="card-body">
            <div class="text-muted small">Due today</div><div class="num" data-count="today">–</div></div></div></div>
        <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100 pd-tile is-week"><div class="card-body">
            <div class="text-muted small">Due in next 7 days</div><div class="num" data-count="week">–</div></div></div></div>
        <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100 pd-tile is-stale"><div class="card-body">
            <div class="text-muted small">Stuck (no activity 14+ days)</div><div class="num" data-count="stale">–</div><div class="sub" data-sub="stale"></div></div></div></div>
        <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100 pd-tile"><div class="card-body">
            <div class="text-muted small">Open cards</div><div class="num" data-count="open">–</div><div class="sub" data-sub="open"></div></div></div></div>
        <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100 pd-tile is-done"><div class="card-body">
            <div class="text-muted small">In completed lists</div><div class="num" data-count="done">–</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">Needs attention</div>
        <div class="card-body">
            <div class="pd-attn" id="pd-attn"><div class="text-muted small">Loading…</div></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach(['overdue' => 'Late', 'today' => 'Due today', 'week' => 'Due in next 7 days'] as $key => $title)
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent fw-semibold d-flex align-items-center justify-content-between gap-2">
                        <span>{{ $title }}</span>
                        @if($key === 'overdue')
                            <select class="form-select form-select-sm d-none" id="pd-late-board" style="width:auto;max-width:60%">
                                <option value="">All boards</option>
                            </select>
                        @endif
                    </div>
                    @if($key === 'overdue')
                        <div class="px-3 pt-2 d-flex flex-wrap gap-1" id="pd-late-age"></div>
                    @endif
                    <div class="card-body pd-list" data-list="{{ $key }}">
                        <div class="text-muted small">Loading…</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span>Work pipeline</span>
                    <span class="pd-help" id="pd-stage-legend"></span>
                </div>
                <div class="card-body">
                    <div class="pd-bar lg mb-2" id="pd-stage-bar"></div>
                    <div class="pd-help mb-3">Stage comes from each board's list names. Busiest boards first.</div>
                    <div class="pd-scroll">
                        <table class="table pd-table mb-0">
                            <thead><tr><th>Board</th><th class="text-end">To do</th><th class="text-end">In progress</th><th class="text-end">Review / client</th><th class="text-end">On hold</th><th class="text-end">Completed</th><th style="width:140px"></th></tr></thead>
                            <tbody id="pd-pipeline"><tr><td colspan="7" class="text-muted">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold">Workload by Trello member</div>
                <div class="card-body">
                    <div class="pd-help mb-2">Open cards assigned on Trello (shared accounts show as one member).</div>
                    <div class="pd-scroll" id="pd-members"><div class="text-muted small">Loading…</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold">Stuck cards <span class="pd-help fw-normal">· not completed, no activity for 14+ days (longest first)</span></div>
                <div class="card-body">
                    <div class="pd-list" id="pd-stale"><div class="text-muted small">Loading…</div></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold">Data cleanup</div>
                <div class="card-body" id="pd-hygiene"><div class="text-muted small">Loading…</div></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold">My recent Trello activity</div>
                <div class="card-body">
                    @if($myActivity->isEmpty())
                        <div class="text-muted small">No Trello activity from the CRM yet.</div>
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
                        <div class="text-muted small">No activity from your team yet.</div>
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

        const STAGES = [
            ['todo', 'To do', '#8590a2'], ['progress', 'In progress', '#579dff'], ['review', 'Review / client', '#e2b203'],
            ['hold', 'On hold', '#f87168'], ['done', 'Completed', '#4bce97'],
        ];
        const ATTENTION = [
            ['qa', 'QA pending', '#9f8fef'], ['revision', 'Revisions', '#fea362'], ['concern', 'Concerns', '#f87168'],
            ['client', 'Waiting on client', '#579dff'], ['hold', 'On hold', '#8590a2'],
        ];
        const AGE = [['', 'All'], ['1_7', '1–7 days'], ['8_30', '8–30 days'], ['31_plus', '30+ days']];
        const n = (v) => Number(v || 0).toLocaleString('en-US');
        let lateCards = [];
        let lateFilter = { board: '', age: '' };

        function stageBar(row, total) {
            if (!total) return '<div class="pd-bar"></div>';
            return '<div class="pd-bar">' + STAGES.map(([k, label, color]) => row[k]
                ? `<span title="${label}: ${n(row[k])}" style="width:${(row[k] / total) * 100}%;background:${color}"></span>` : '').join('') + '</div>';
        }

        function renderPipeline(stages, pipeline) {
            const total = STAGES.reduce((s, [k]) => s + (stages[k] || 0), 0);
            document.getElementById('pd-stage-bar').innerHTML = stageBar(stages, total).replace('class="pd-bar"', 'class="pd-bar lg" style="height:16px"');
            document.getElementById('pd-stage-legend').innerHTML = STAGES.map(([k, label, color]) =>
                `<span class="me-2"><span class="pd-stage-dot" style="background:${color}"></span>${label} ${n(stages[k])}</span>`).join('');
            const rows = (pipeline || []).filter((r) => STAGES.some(([k]) => r[k]));
            document.getElementById('pd-pipeline').innerHTML = rows.length ? rows.map((r) => {
                const t = STAGES.reduce((s, [k]) => s + (r[k] || 0), 0);
                return `<tr><td><a href="${esc(r.url)}">${esc(r.name)}</a></td>`
                    + STAGES.map(([k]) => `<td class="text-end">${r[k] ? n(r[k]) : '<span class="opacity-50">0</span>'}</td>`).join('')
                    + `<td>${stageBar(r, t)}</td></tr>`;
            }).join('') : '<tr><td colspan="7" class="text-muted">No cards.</td></tr>';
        }

        function renderAttention(a) {
            document.getElementById('pd-attn').innerHTML = ATTENTION.map(([k, label, color]) => `
                <div class="item" style="border-left:4px solid ${color}">
                    <div class="n">${n(a[k])}</div><div class="t">${label}</div>
                </div>`).join('');
        }

        function renderMembers(list) {
            const box = document.getElementById('pd-members');
            if (!list.length) { box.innerHTML = '<div class="text-muted small">No cards are assigned to members on Trello.</div>'; return; }
            const max = Math.max(...list.map((m) => m.open), 1);
            box.innerHTML = list.map((m) => `
                <div class="d-flex align-items-center mb-2">
                    <span class="pd-avatar">${esc((m.initials || m.name || '?').slice(0, 2))}</span>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between small"><span class="fw-semibold text-truncate">${esc(m.name)}</span>
                            <span class="text-nowrap">${n(m.open)} open${m.late ? ` · <span class="text-danger">${n(m.late)} late</span>` : ''}</span></div>
                        <div class="pd-bar mt-1" style="height:6px"><span style="width:${(m.open / max) * 100}%;background:#579dff"></span></div>
                    </div>
                </div>`).join('');
        }

        function renderStale(stale) {
            const box = document.getElementById('pd-stale');
            const cards = stale.cards || [];
            if (!cards.length) { box.innerHTML = '<div class="text-muted small">Nothing stuck 🎉</div>'; return; }
            box.innerHTML = cards.map((c) => `
                <a class="pd-card" href="${esc(c.url)}">
                    <div class="d-flex justify-content-between gap-2">
                        <div class="name">${esc(c.name)}</div><div class="pd-days">${n(c.days)} days idle</div>
                    </div>
                    <div class="meta mt-1">${esc(c.board)}${c.list ? ' · ' + esc(c.list) : ''}</div>
                </a>`).join('') + (stale.count > cards.length ? `<div class="pd-help mt-2">Showing the ${cards.length} longest idle of ${n(stale.count)}.</div>` : '');
        }

        function renderHygiene(h, open) {
            const pct = (v) => open ? Math.round((v / open) * 100) : 0;
            const row = (v, title, help) => `
                <div class="mb-3">
                    <div class="d-flex justify-content-between"><span class="fw-semibold">${title}</span><span>${n(v)} <span class="pd-help">(${pct(v)}%)</span></span></div>
                    <div class="pd-bar mt-1" style="height:6px"><span style="width:${pct(v)}%;background:#fea362"></span></div>
                    <div class="pd-help mt-1">${help}</div>
                </div>`;
            document.getElementById('pd-hygiene').innerHTML =
                row(h.no_due, 'No due date', 'These never show up as late — add due dates so deadlines are tracked.')
                + row(h.no_member, 'Nobody assigned', 'No member on the card, so no one owns it.')
                + '<div class="pd-help">Counts open cards outside on-hold lists.</div>';
        }

        function renderLateControls(byBoard, age) {
            const sel = document.getElementById('pd-late-board');
            sel.innerHTML = '<option value="">All boards</option>' + (byBoard || []).map((b) =>
                `<option value="${esc(b.id)}">${esc(b.name)} (${n(b.count)})</option>`).join('');
            sel.classList.toggle('d-none', (byBoard || []).length < 2);
            sel.value = lateFilter.board;
            const total = (age['1_7'] || 0) + (age['8_30'] || 0) + (age['31_plus'] || 0);
            document.getElementById('pd-late-age').innerHTML = total ? AGE.map(([k, label]) =>
                `<button type="button" class="pd-chip ${lateFilter.age === k ? 'active' : ''}" data-age="${k}">${label} ${n(k ? age[k] : total)}</button>`).join('') : '';
        }

        function lateBucket(days) { return days <= 7 ? '1_7' : (days <= 30 ? '8_30' : '31_plus'); }

        function renderLate() {
            const list = lateCards.filter((c) => (!lateFilter.board || c.board_id === lateFilter.board)
                && (!lateFilter.age || lateBucket(c.days_late || 0) === lateFilter.age));
            render('overdue', list);
            document.querySelectorAll('#pd-late-age .pd-chip').forEach((b) => b.classList.toggle('active', b.dataset.age === lateFilter.age));
        }

        document.getElementById('pd-late-board')?.addEventListener('change', (e) => { lateFilter.board = e.target.value; renderLate(); });
        document.getElementById('pd-late-age')?.addEventListener('click', (e) => {
            const b = e.target.closest('[data-age]');
            if (!b) return;
            lateFilter.age = b.dataset.age;
            renderLate();
        });

        function render(group, cards) {
            const box = document.querySelector(`[data-list="${group}"]`);
            if (!box) return;
            if (!cards.length) {
                box.innerHTML = '<div class="text-muted small">No cards 🎉</div>';
                return;
            }
            box.innerHTML = cards.map((c) => `
                <a class="pd-card" href="${esc(c.url)}">
                    <div class="d-flex justify-content-between gap-2">
                        <div class="name">${esc(c.name)}</div>
                        <div class="due ${group === 'overdue' ? 'is-overdue' : (group === 'today' ? 'is-today' : '')}">${group === 'overdue' && c.days_late ? esc(c.days_late + 'd late') : esc(dueText(c.due))}</div>
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
                const setText = (sel, v) => { const el = document.querySelector(sel); if (el) el.textContent = v; };
                setText('[data-count="overdue"]', n(c.overdue));
                setText('[data-count="today"]', n(c.today));
                setText('[data-count="week"]', n(c.week));
                setText('[data-count="open"]', n(c.open_cards));
                setText('[data-sub="open"]', `across ${n(c.boards)} boards`);
                setText('[data-count="done"]', n((data.stages || {}).done));
                const age = data.late_age || {};
                setText('[data-sub="overdue"]', c.overdue ? `${n(age['31_plus'])} over 30 days` : '');
                const stale = data.stale || { count: 0, buckets: {}, cards: [] };
                setText('[data-count="stale"]', n(stale.count));
                setText('[data-sub="stale"]', stale.count ? `${n((stale.buckets || {})['60_plus'])} idle 60+ days` : '');

                lateCards = (data.cards || {}).overdue || [];
                lateFilter = { board: '', age: '' };
                renderLateControls(data.late_by_board, age);
                renderLate();
                ['today', 'week'].forEach((g) => render(g, (data.cards || {})[g] || []));
                renderAttention(data.attention || {});
                renderPipeline(data.stages || {}, data.pipeline || []);
                renderMembers(data.members || []);
                renderStale(stale);
                renderHygiene(data.hygiene || {}, c.open_cards || 0);
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
