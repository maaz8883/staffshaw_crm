{{-- Project Manager dashboard: the team's Trello work that needs the PM (loaded by AJAX) --}}
<style>
    .pmt-tile .num { font-size: 1.8rem; font-weight: 700; line-height: 1.1; }
    .pmt-tile .sub { font-size: 12px; opacity: .7; }
    .pmt-list { max-height: 380px; overflow-y: auto; }
    .pmt-card { display: block; padding: 9px 12px; border-radius: 8px; text-decoration: none; color: inherit; border: 1px solid var(--bs-border-color, rgba(0,0,0,.1)); margin-bottom: 8px; }
    .pmt-card:hover { border-color: #579dff; }
    .pmt-card .name { font-weight: 600; }
    .pmt-card .meta { font-size: 12px; opacity: .75; }
    .pmt-card .due { font-size: 12px; font-weight: 600; white-space: nowrap; }
    .pmt-table { font-size: 13px; }
    .pmt-table td, .pmt-table th { padding: 7px 8px; vertical-align: middle; }
    .pmt-table a { color: inherit; }
    .pmt-stage { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; color: #fff; white-space: nowrap; }
    .pmt-activity li { font-size: 13px; padding: 6px 0; border-bottom: 1px solid var(--bs-border-color, rgba(0,0,0,.08)); }
    .pmt-activity li:last-child { border-bottom: 0; }
    .pmt-activity .when { font-size: 11px; opacity: .65; }
    .pmt-tabs .nav-link { padding: 4px 10px; font-size: 13px; }
</style>

<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div class="d-flex gap-2 align-items-center">
        <h6 class="mb-0"><i class="bi bi-kanban me-1"></i> Trello</h6>
        @if($pmTrelloTeams->count() > 1)
            <select id="pmt-team" class="form-select form-select-sm" style="width:auto">
                @foreach($pmTrelloTeams as $t)
                    <option value="{{ $t->id }}">{{ \App\Support\TeamTrelloAccounts::label($t) }}</option>
                @endforeach
            </select>
        @else
            <span class="text-muted small">{{ \App\Support\TeamTrelloAccounts::label($pmTrelloTeams->first()) }}</span>
        @endif
        <span class="small text-muted" id="pmt-updated"></span>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="pmt-refresh"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        <a href="{{ route('admin.trello.index', ['team' => $pmTrelloTeams->first()->id]) }}" class="btn btn-sm btn-primary" id="pmt-boards-link"><i class="bi bi-kanban"></i> Open boards</a>
    </div>
</div>

<div class="alert alert-warning d-none" id="pmt-error"></div>

<div class="row g-3 mb-3">
    @foreach([
        ['overdue', 'Late', '#dc3545'],
        ['today', 'Due today', '#e0a800'],
        ['week', 'Due in next 7 days', '#0d6efd'],
        ['client', 'Waiting on client', '#579dff'],
        ['revision', 'Revisions / QA', '#fea362'],
        ['stale', 'Stuck 14+ days', '#8590a2'],
    ] as [$key, $label, $color])
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 pmt-tile" style="border-left:4px solid {{ $color }} !important">
                <div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="num" style="color:{{ $color }}" data-pmt="{{ $key }}">–</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold">My clients' projects</span>
                <span class="small text-muted" id="pmt-proj-summary"></span>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-2">Clients you sold to or added, linked to a Trello card. Late and needs-attention first.</div>
                <div class="pmt-list">
                    <table class="table pmt-table mb-0">
                        <thead><tr><th>Client</th><th>Stage</th><th>List</th><th class="text-end">Due</th></tr></thead>
                        <tbody id="pmt-projects"><tr><td colspan="4" class="text-muted">Loading…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent">
                <ul class="nav nav-pills pmt-tabs" id="pmt-tabs">
                    <li class="nav-item"><button type="button" class="nav-link active" data-tab="overdue">Late <span data-pmt-tab="overdue"></span></button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-tab="today">Today <span data-pmt-tab="today"></span></button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-tab="week">Next 7 days <span data-pmt-tab="week"></span></button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-tab="stale">Stuck <span data-pmt-tab="stale"></span></button></li>
                </ul>
            </div>
            <div class="card-body pmt-list" id="pmt-cards"><div class="text-muted small">Loading…</div></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">My recent Trello activity</div>
    <div class="card-body">
        @if($myTrelloActivity->isEmpty())
            <div class="text-muted small">No Trello activity from the CRM yet.</div>
        @else
            <ul class="list-unstyled mb-0 pmt-activity">
                @foreach($myTrelloActivity as $log)
                    <li>You {{ $log->description }}
                        <div class="when">{{ $log->occurred_at->diffForHumans() }}@if(!empty($log->meta['board_name'])) · {{ $log->meta['board_name'] }}@endif</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

<script>
(function () {
    const url = @json(route('admin.pm.trello'));
    const boardsBase = @json(route('admin.trello.index'));
    const teamSel = document.getElementById('pmt-team');
    const firstTeam = @json($pmTrelloTeams->first()->id);
    const STAGES = { todo: ['To do', '#8590a2'], progress: ['In progress', '#579dff'], review: ['Review / client', '#c99a00'], hold: ['On hold', '#f87168'], done: ['Completed', '#22a06b'] };
    const ATTN = { qa: 'QA pending', revision: 'Revision', concern: 'Concern', client: 'Waiting on client', hold: 'On hold' };
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    const n = (v) => Number(v || 0).toLocaleString('en-US');
    const dueText = (iso) => new Date(iso).toLocaleString('en-US', { month: 'short', day: 'numeric' });
    let data = null;
    let tab = 'overdue';

    function renderProjects() {
        const box = document.getElementById('pmt-projects');
        const list = data.projects || [];
        const s = data.projects_summary || {};
        document.getElementById('pmt-proj-summary').innerHTML = s.total
            ? `${n(s.total)} projects${s.late ? ` · <span class="text-danger">${n(s.late)} late</span>` : ''}${s.attention ? ` · ${n(s.attention)} need attention` : ''} · ${n(s.done)} completed`
            : '';
        if (!list.length) {
            box.innerHTML = '<tr><td colspan="4" class="text-muted">None of your clients are linked to a card on this team\'s boards yet.</td></tr>';
            return;
        }
        box.innerHTML = list.map((p) => {
            const [label, color] = STAGES[p.stage] || STAGES.progress;
            const due = p.due ? `<span class="${p.late ? 'text-danger fw-semibold' : ''}">${esc(dueText(p.due))}${p.late ? ' · late' : ''}</span>` : '<span class="opacity-50">—</span>';
            return `<tr>
                <td><a href="${esc(p.url)}" class="fw-semibold text-decoration-none">${esc(p.client)}</a>
                    <div class="small text-muted text-truncate" style="max-width:260px">${esc(p.board)}${p.idle >= 14 && p.stage !== 'done' ? ` · <span style="color:#fea362">${n(p.idle)}d idle</span>` : ''}</div></td>
                <td><span class="pmt-stage" style="background:${color}">${label}</span>${p.attention ? `<div class="small mt-1">${esc(ATTN[p.attention] || '')}</div>` : ''}</td>
                <td class="small">${esc(p.list)}</td>
                <td class="text-end small">${due}</td>
            </tr>`;
        }).join('');
    }

    function renderCards() {
        const box = document.getElementById('pmt-cards');
        const cards = (data && data[tab]) || [];
        document.querySelectorAll('#pmt-tabs [data-tab]').forEach((b) => b.classList.toggle('active', b.dataset.tab === tab));
        if (!cards.length) { box.innerHTML = '<div class="text-muted small">No cards 🎉</div>'; return; }
        box.innerHTML = cards.map((c) => {
            const right = tab === 'stale' ? `<span style="color:#fea362">${n(c.days)}d idle</span>`
                : (tab === 'overdue' ? `<span class="text-danger">${n(c.days_late)}d late</span>` : esc(dueText(c.due)));
            return `<a class="pmt-card" href="${esc(c.url)}">
                <div class="d-flex justify-content-between gap-2"><div class="name">${esc(c.name)}</div><div class="due">${right}</div></div>
                <div class="meta mt-1">${esc(c.board)}${c.list ? ' · ' + esc(c.list) : ''}</div>
            </a>`;
        }).join('');
    }

    async function load(refresh = false) {
        const team = teamSel ? teamSel.value : firstTeam;
        document.getElementById('pmt-boards-link').href = boardsBase + '?team=' + encodeURIComponent(team);
        const err = document.getElementById('pmt-error');
        err.classList.add('d-none');
        try {
            const res = await fetch(url + '?team=' + encodeURIComponent(team) + (refresh ? '&refresh=1' : ''), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || json.ok === false) throw new Error(json.message || 'Could not load Trello data');
            data = json;
            const c = data.counts || {}, a = data.attention || {};
            const set = (k, v) => { const el = document.querySelector(`[data-pmt="${k}"]`); if (el) el.textContent = n(v); };
            set('overdue', c.overdue); set('today', c.today); set('week', c.week);
            set('client', a.client); set('revision', (a.revision || 0) + (a.qa || 0)); set('stale', data.stale_count);
            const tabCount = { overdue: c.overdue, today: c.today, week: c.week, stale: data.stale_count };
            Object.entries(tabCount).forEach(([k, v]) => { const el = document.querySelector(`[data-pmt-tab="${k}"]`); if (el) el.textContent = v ? `(${n(v)})` : ''; });
            renderProjects();
            renderCards();
            document.getElementById('pmt-updated').textContent = data.generated_at
                ? '· updated ' + new Date(data.generated_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : '';
        } catch (e) {
            err.textContent = e.message;
            err.classList.remove('d-none');
            document.getElementById('pmt-projects').innerHTML = '';
            document.getElementById('pmt-cards').innerHTML = '';
        }
    }

    document.getElementById('pmt-tabs').addEventListener('click', (e) => {
        const b = e.target.closest('[data-tab]');
        if (!b || !data) return;
        tab = b.dataset.tab;
        renderCards();
    });
    teamSel?.addEventListener('change', () => load());
    document.getElementById('pmt-refresh').addEventListener('click', () => load(true));
    load();
})();
</script>
