@extends('admin.layout')

@section('title', $board['name'] ?? 'Board')
@section('page-title', $board['name'] ?? 'Board')
@section('page-icon', 'kanban')

@section('content')
@php
    $boardBg = $boardBg ?? \App\Services\Trello\TrelloApiClient::boardBackground($board ?? null);
    $bgStyles = [];
    if (! empty($boardBg['color'])) {
        $bgStyles[] = 'background-color:'.$boardBg['color'];
    } elseif (! empty($boardBg['top']) || ! empty($boardBg['bottom'])) {
        $top = $boardBg['top'] ?? ($boardBg['bottom'] ?? '#1d2125');
        $bottom = $boardBg['bottom'] ?? $top;
        $bgStyles[] = 'background:linear-gradient(180deg, '.$top.' 0%, '.$bottom.' 100%)';
    } else {
        $bgStyles[] = 'background-color:#1d2125';
    }
    if (! empty($boardBg['image'])) {
        $bgStyles[] = "background-image:url('".e($boardBg['image'])."')";
        $bgStyles[] = ! empty($boardBg['tile'])
            ? 'background-size:auto;background-repeat:repeat'
            : 'background-size:cover;background-repeat:no-repeat;background-position:center center';
    }
    $bgAttr = implode(';', $bgStyles);
    $bgBright = ($boardBg['brightness'] ?? 'dark') === 'light' ? 'is-light' : 'is-dark';
@endphp
<style>
    /* Full-bleed board inside main — no negative margins (those caused a 2nd page scrollbar) */
    main:has(.trello-board-stage) {
        overflow-x: hidden;
        padding-left: 0;
        padding-right: 0;
        padding-bottom: 0;
    }
    main:has(.trello-board-stage) .page-title-row {
        padding-left: 2rem;
        padding-right: 2rem;
    }
    @media (max-width: 991px) {
        main:has(.trello-board-stage) .page-title-row {
            padding-left: 1rem;
            padding-right: 1rem;
        }
    }
    .trello-board-stage {
        margin: 0;
        padding: 14px 16px 18px;
        min-height: calc(100vh - 120px);
        border-radius: 0;
        background-color: #1d2125;
        position: relative;
        overflow-x: hidden; /* only .trello-board-wrap scrolls horizontally */
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }
    .trello-board-toolbar {
        display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
        justify-content: space-between; margin-bottom: 12px;
    }
    .trello-board-toolbar .trello-hint,
    .trello-board-toolbar .text-muted { color: rgba(255,255,255,.78) !important; }
    .trello-board-stage.is-light .trello-board-toolbar .trello-hint,
    .trello-board-stage.is-light .trello-board-toolbar .text-muted { color: rgba(0,0,0,.72) !important; }
    .trello-board-toolbar .btn-outline-secondary,
    .trello-board-toolbar .btn-outline-primary {
        background: rgba(0,0,0,.35); border-color: rgba(255,255,255,.25); color: #fff;
    }
    .trello-board-toolbar .btn-outline-secondary:hover,
    .trello-board-toolbar .btn-outline-primary:hover {
        background: rgba(0,0,0,.5); border-color: rgba(255,255,255,.4); color: #fff;
    }
    .trello-board-stage.is-light .trello-board-toolbar .btn-outline-secondary,
    .trello-board-stage.is-light .trello-board-toolbar .btn-outline-primary {
        background: rgba(255,255,255,.55); border-color: rgba(0,0,0,.15); color: #172b4d;
    }
    .trello-board-wrap {
        display: flex; gap: 12px;
        overflow-x: auto; overflow-y: hidden;
        padding-bottom: 10px;
        align-items: flex-start; min-height: 420px;
        width: 100%; max-width: 100%;
        box-sizing: border-box;
        cursor: grab;
        user-select: none;
    }
    .trello-board-wrap.is-panning { cursor: grabbing; }
    .trello-board-wrap .trello-card,
    .trello-board-wrap a,
    .trello-board-wrap button,
    .trello-board-wrap input { cursor: auto; user-select: auto; }
    .trello-board-wrap .trello-card { cursor: grab; }
    .trello-list {
        flex: 0 0 280px; max-width: 280px;
        background: rgba(16, 18, 22, .72);
        border: 1px solid rgba(255,255,255,.08); border-radius: 12px;
        display: flex; flex-direction: column; max-height: calc(100vh - 200px); min-height: 0;
        backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
        box-shadow: 0 1px 0 rgba(0,0,0,.2);
    }
    .trello-board-stage.is-light .trello-list {
        background: rgba(255,255,255,.78);
        border-color: rgba(0,0,0,.08);
    }
    .trello-list-head {
        padding: 12px 14px 8px; font-weight: 650; font-size: 13px; color: #f1f2f4;
        display: flex; justify-content: space-between; gap: 8px; flex-shrink: 0;
    }
    .trello-board-stage.is-light .trello-list-head { color: #172b4d; }
    .trello-list-head .text-muted,
    .trello-list-head .js-list-count { color: rgba(255,255,255,.55) !important; }
    .trello-board-stage.is-light .trello-list-head .text-muted,
    .trello-board-stage.is-light .trello-list-head .js-list-count { color: rgba(0,0,0,.45) !important; }
    .trello-list-body {
        padding: 0 8px 10px; overflow-x: hidden; overflow-y: auto;
        display: flex; flex-direction: column; gap: 8px; flex: 1 1 auto; min-height: 80px;
        -webkit-overflow-scrolling: touch;
    }
    .trello-list-name { overflow-wrap: anywhere; line-height: 1.35; }
    .trello-card {
        position: relative;
        background: #22272b; border: 1px solid transparent; border-radius: 8px;
        overflow: hidden; font-size: 14px; color: #b6c2cf;
        flex: 0 0 auto; cursor: grab; user-select: none;
        box-shadow: 0 1px 1px rgba(0,0,0,.35);
    }
    .trello-board-stage.is-light .trello-card {
        background: #fff; color: #172b4d; border-color: rgba(0,0,0,.06);
        box-shadow: 0 1px 1px rgba(9,30,66,.25);
    }
    .trello-card:active { cursor: grabbing; }
    .trello-card.sortable-ghost { opacity: .45; border-style: dashed; }
    .trello-card.sortable-drag { box-shadow: 0 8px 24px rgba(0,0,0,.35); cursor: grabbing; }
    .trello-card.is-saving { opacity: .65; pointer-events: none; }
    .trello-card-body { padding: 8px 12px 4px; }
    .trello-card-title { font-weight: 400; line-height: 20px; overflow-wrap: anywhere; min-width: 0; }
    .trello-card-name-row { display: flex; align-items: flex-start; margin-bottom: 4px; }
    .trello-card:hover { border-color: #579dff; }

    /* Mark-complete circle: slides in on hover, stays when complete */
    .trello-card-check {
        flex: 0 0 auto; width: 0; height: 20px; margin-right: 0;
        display: inline-flex; align-items: center; justify-content: flex-start;
        border: 0; padding: 0; background: transparent; color: #8c9bab;
        overflow: hidden; opacity: 0; cursor: pointer;
        transition: width .15s ease, margin-right .15s ease, opacity .15s ease;
    }
    .trello-card-check i { font-size: 16px; line-height: 1; }
    .trello-card:hover .trello-card-check,
    .trello-card.is-complete .trello-card-check { width: 16px; margin-right: 6px; opacity: 1; }
    .trello-card-check:hover { color: #dee4ea; }
    .trello-card.is-complete .trello-card-check { color: #4bce97; }
    .trello-board-stage.is-light .trello-card-check { color: #626f86; }
    .trello-board-stage.is-light .trello-card-check:hover { color: #172b4d; }
    .trello-board-stage.is-light .trello-card.is-complete .trello-card-check { color: #1f845a; }

    /* Hover edit (pencil) button, top-right like Trello */
    .trello-card-edit {
        position: absolute; top: 6px; right: 6px; z-index: 2;
        width: 32px; height: 32px; border: 0; border-radius: 50%;
        display: none; align-items: center; justify-content: center;
        background: #22272b; color: #b6c2cf; font-size: 14px; cursor: pointer;
        box-shadow: 0 1px 2px rgba(0,0,0,.35);
    }
    .trello-card:hover .trello-card-edit { display: inline-flex; }
    .trello-card-edit:hover { background: #2c333a; color: #dee4ea; }
    .trello-board-stage.is-light .trello-card-edit { background: #fff; color: #44546f; }
    .trello-board-stage.is-light .trello-card-edit:hover { background: #f1f2f4; color: #172b4d; }

    /* Badge row: due / description / comments / attachments / checklist + members */
    .trello-card-badges {
        display: flex; flex-wrap: wrap; align-items: center; gap: 0 2px;
        font-size: 12px; color: #9fadbc; padding-bottom: 4px;
    }
    .trello-badge {
        display: inline-flex; align-items: center; gap: 4px;
        min-height: 24px; padding: 0 4px; border-radius: 3px; line-height: 1;
        white-space: nowrap;
    }
    .trello-badge i { font-size: 14px; line-height: 1; }
    .trello-badge.is-due-overdue { background: #42221f; color: #fd9891; }
    .trello-badge.is-due-soon { background: #533f04; color: #f5cd47; }
    .trello-badge.is-due-complete { background: #164b35; color: #7ee2b8; }
    .trello-badge.is-check-done { background: #164b35; color: #7ee2b8; }
    .trello-card-members { display: flex; gap: 2px; margin-left: auto; padding: 2px 0; }
    .trello-card-avatar {
        width: 24px; height: 24px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 10px; font-weight: 700; color: #1d2125; text-transform: uppercase;
    }
    .trello-board-stage.is-light .trello-card-badges { color: #44546f; }
    .trello-board-stage.is-light .trello-badge.is-due-overdue { background: #ffeceb; color: #ae2e24; }
    .trello-board-stage.is-light .trello-badge.is-due-soon { background: #fff7d6; color: #7f5f01; }
    .trello-board-stage.is-light .trello-badge.is-due-complete,
    .trello-board-stage.is-light .trello-badge.is-check-done { background: #dcfff1; color: #216e4e; }
    .trello-card-cover {
        display: block; width: 100%; height: 140px; min-height: 140px;
        object-fit: cover; background: #1e293b; pointer-events: none;
    }
    .trello-card-cover-color { height: 32px; min-height: 32px; width: 100%; }
    .trello-labels { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 4px; }
    .trello-label { height: 8px; width: 40px; border-radius: 4px; background: #9fadbc; }
    .trello-empty { font-size: 12px; color: rgba(255,255,255,.5); padding: 8px 4px; pointer-events: none; }
    .trello-board-stage.is-light .trello-empty { color: rgba(0,0,0,.45); }
    .trello-hint { font-size: 12px; }
    .trello-board-empty {
        background: rgba(0,0,0,.35); border-radius: 12px; color: #fff;
        text-align: center; padding: 48px 16px;
    }

    /* Trello-style add-card composer (list footer) */
    .trello-list-footer {
        flex-shrink: 0; padding: 0 8px 10px;
    }
    .trello-list.is-composing .trello-list-footer { padding-top: 2px; }
    .trello-add-card-btn {
        display: flex; align-items: center; gap: 8px; width: 100%;
        border: 0; border-radius: 8px; background: transparent;
        color: rgba(255,255,255,.72); font-size: 14px; font-weight: 500;
        padding: 8px 10px; cursor: pointer; text-align: left;
    }
    .trello-add-card-btn:hover { background: rgba(255,255,255,.08); color: #fff; }
    .trello-board-stage.is-light .trello-add-card-btn { color: rgba(0,0,0,.55); }
    .trello-board-stage.is-light .trello-add-card-btn:hover { background: rgba(0,0,0,.06); color: #172b4d; }
    .trello-add-card-btn .plus { font-size: 18px; line-height: 1; font-weight: 400; }
    .trello-card-composer { display: none; }
    .trello-list.is-composing .trello-card-composer { display: block; }
    .trello-list.is-composing .trello-add-card-btn { display: none; }
    .trello-list.is-composing .js-empty-msg { display: none !important; }
    .trello-card-composer-input {
        width: 100%; min-height: 56px; resize: none;
        box-sizing: border-box; border: none; outline: none;
        border-radius: 8px; padding: 10px 12px;
        background: #22272b; color: #f1f2f4; font-size: 14px; line-height: 1.35;
        box-shadow: 0 1px 1px rgba(0,0,0,.2);
        font-family: inherit;
    }
    .trello-card-composer-input::placeholder { color: #8c9bab; }
    .trello-board-stage.is-light .trello-card-composer-input {
        background: #fff; color: #172b4d; box-shadow: 0 1px 1px rgba(0,0,0,.12);
    }
    .trello-card-composer-actions {
        display: flex; align-items: center; gap: 6px; margin-top: 8px;
    }
    .trello-card-composer-submit {
        border: 0; border-radius: 4px; padding: 8px 14px;
        background: #579dff; color: #1d2125; font-size: 14px; font-weight: 600;
        cursor: pointer; line-height: 1.2;
    }
    .trello-card-composer-submit:hover { background: #85b8ff; }
    .trello-card-composer-submit:disabled { opacity: .55; cursor: wait; }
    .trello-card-composer-cancel {
        width: 32px; height: 32px; border: 0; border-radius: 4px;
        background: transparent; color: #9fadbc; font-size: 22px; line-height: 1;
        cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    }
    .trello-card-composer-cancel:hover { background: rgba(255,255,255,.08); color: #fff; }
    .trello-board-stage.is-light .trello-card-composer-cancel { color: #626f86; }
    .trello-board-stage.is-light .trello-card-composer-cancel:hover { background: rgba(0,0,0,.06); color: #172b4d; }
</style>

<div class="trello-board-stage {{ $bgBright }}{{ !empty($boardBg['image']) ? ' has-photo' : '' }}" style="{{ $bgAttr }}">
<div class="trello-board-toolbar">
    <div>
        <a href="{{ route('admin.trello.index', ['team' => $team->id]) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Boards
        </a>
        <span class="ms-2 text-muted small">{{ $team->name }} · {{ $integration->displayAccount() }}</span>
        <span class="trello-hint ms-2">Drag to move · click title to open card</span>
    </div>
</div>

@if(empty($lists))
    <div class="trello-board-empty">This board has no open lists.</div>
@else
    <div class="trello-board-wrap" id="trello-board"
         data-team-id="{{ $team->id }}"
         data-board-id="{{ $board['id'] }}"
         data-me-id="{{ $integration->trello_member_id ?? '' }}"
         data-csrf="{{ csrf_token() }}"
         data-live-url="{{ url('admin/trello/teams/'.$team->id.'/boards/'.$board['id'].'/live') }}"
         data-live-ensure-url="{{ url('admin/trello/teams/'.$team->id.'/boards/'.$board['id'].'/live/ensure') }}"
         data-tick-url="{{ url('admin/trello/teams/'.$team->id.'/boards/'.$board['id'].'/tick') }}"
         data-move-url-template="{{ url('admin/trello/teams/'.$team->id.'/cards/__CARD__/move') }}"
         data-card-url-template="{{ url('admin/trello/teams/'.$team->id.'/cards/__CARD__') }}"
         data-create-card-url="{{ url('admin/trello/teams/'.$team->id.'/cards') }}"
         @if(config('broadcasting.default') === 'pusher' && config('broadcasting.connections.pusher.key'))
         data-pusher-key="{{ config('broadcasting.connections.pusher.key') }}"
         data-pusher-cluster="{{ config('broadcasting.connections.pusher.options.cluster', 'mt1') }}"
         data-broadcast-auth="{{ url('broadcasting/auth') }}"
         @endif>
        @foreach($lists as $list)
            @php $cards = $cardsByList[$list['id']] ?? []; @endphp
            <div class="trello-list" data-list-id="{{ $list['id'] }}">
                <div class="trello-list-head">
                    <span class="trello-list-name">{{ $list['name'] }}</span>
                    <span class="text-muted js-list-count">{{ count($cards) }}</span>
                </div>
                <div class="trello-list-body js-card-list" data-list-id="{{ $list['id'] }}">
                    {{-- Card tiles are rendered by cardTileHtml() from #trello-initial-cards --}}
                    <div class="trello-empty js-empty-msg" @if(count($cards) > 0) hidden @endif>No cards — drop here</div>
                </div>
                <div class="trello-list-footer">
                    <button type="button" class="trello-add-card-btn js-add-card-open">
                        <span class="plus" aria-hidden="true">+</span> Add a card
                    </button>
                    <div class="trello-card-composer">
                        <textarea class="trello-card-composer-input js-add-card-input" rows="2"
                                  placeholder="Enter a title or paste a link"></textarea>
                        <div class="trello-card-composer-actions">
                            <button type="button" class="trello-card-composer-submit js-add-card-submit">Add card</button>
                            <button type="button" class="trello-card-composer-cancel js-add-card-cancel" aria-label="Cancel">&times;</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
</div>{{-- /.trello-board-stage --}}

@include('admin.trello._card_modal')

@php
    $initialTiles = [
        'cards' => array_merge([], ...array_values($cardsByList ?? [])),
        'members' => $boardMembers ?? [],
    ];
@endphp
<script type="application/json" id="trello-initial-cards">@json($initialTiles)</script>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
@if(config('broadcasting.default') === 'pusher' && config('broadcasting.connections.pusher.key'))
    {{-- Real-time Trello changes (webhook → Pusher) --}}
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
@endif
<script>
(function () {
    const board = document.getElementById('trello-board');
    if (!board) return;

    const csrf = board.dataset.csrf;
    const teamId = board.dataset.teamId;
    const liveUrl = board.dataset.liveUrl;
    const liveEnsureUrl = board.dataset.liveEnsureUrl;
    const tickUrl = board.dataset.tickUrl;
    const boardId = board.dataset.boardId || '';
    let meId = board.dataset.meId || '';
    const moveTpl = board.dataset.moveUrlTemplate;
    const cardTpl = board.dataset.cardUrlTemplate;
    const createCardUrl = board.dataset.createCardUrl || '';
    let editingCommentId = null;
    let pendingDeleteCommentId = null;
    // Card modal attachments: show the newest few, rest behind "View all attachments"
    const ATT_PREVIEW_LIMIT = 4;
    let attShowAll = false;
    const LABEL_COLORS = {
        green:'#4bce97', yellow:'#f5cd47', orange:'#fea362', red:'#f87168', purple:'#9f8fef',
        blue:'#579dff', sky:'#6cc3e0', lime:'#94c748', pink:'#e774bb', black:'#8590a2'
    };
    const LABEL_COLOR_KEYS = Object.keys(LABEL_COLORS);
    const LABEL_CBF_KEY = 'tc-labels-cbf';
    let labelFormMode = null; // null | 'create' | 'edit'
    let editingLabelId = null;
    let selectedLabelColor = 'green';

    let state = null; // current card payload
    let currentCardId = null;
    let cardLoading = false;
    let dragged = false;
    let panning = false;
    let panMoved = false;
    let panStartX = 0;
    let panScrollLeft = 0;

    const drawer = document.getElementById('trello-drawer');
    const backdrop = document.getElementById('trello-drawer-backdrop');
    // Escape layout wrappers so fixed modal/popovers sit above the CRM chrome
    if (backdrop && backdrop.parentElement !== document.body) {
        document.body.appendChild(backdrop);
    }
    ['tc-link-pop', 'tc-image-pop', 'tc-attach-pop', 'tc-md-pop', 'tc-help-pop'].forEach((id) => {
        const el = document.getElementById(id);
        if (el && el.parentElement !== document.body) document.body.appendChild(el);
    });
    drawer.style.width = 'min(1040px, calc(100vw - 48px))';
    drawer.style.maxWidth = 'min(1040px, calc(100vw - 48px))';

    function api(url, opts = {}) {
        const headers = Object.assign({
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        }, opts.headers || {});
        if (opts.json) {
            headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(opts.json);
            delete opts.json;
        }
        return fetch(url, Object.assign({ headers }, opts)).then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.ok === false) throw new Error(data.message || ('HTTP ' + res.status));
            return data;
        });
    }

    /** Card API URL. Pass a path like 'comments' / 'attachments' BEFORE board_id query. */
    function cardUrl(id, path = '') {
        let url = cardTpl.replace('__CARD__', encodeURIComponent(id));
        if (path) {
            url = url.replace(/\/?$/, '') + '/' + String(path).replace(/^\//, '');
        }
        if (boardId) url += (url.includes('?') ? '&' : '?') + 'board_id=' + encodeURIComponent(boardId);
        return url;
    }

    function toastError(msg) {
        if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: msg });
        else alert(msg);
    }

    function refreshListUi(listEl) {
        const count = listEl.querySelectorAll('.trello-card').length;
        const countEl = listEl.closest('.trello-list')?.querySelector('.js-list-count');
        if (countEl) countEl.textContent = String(count);
        const empty = listEl.querySelector('.js-empty-msg');
        if (empty) empty.hidden = count > 0;
    }

    function resolvePos(listEl, cardEl) {
        const cards = [...listEl.querySelectorAll('.trello-card')];
        const index = cards.indexOf(cardEl);
        if (index <= 0) return 'top';
        if (index >= cards.length - 1) return 'bottom';
        const prev = Number(cards[index - 1]?.dataset.pos);
        const next = Number(cards[index + 1]?.dataset.pos);
        if (!Number.isNaN(prev) && !Number.isNaN(next)) return (prev + next) / 2;
        return 'bottom';
    }

    async function persistMove(cardEl, listEl) {
        const cardId = cardEl.dataset.cardId;
        const listId = listEl.dataset.listId;
        if (!cardId || !listId) return;
        const pos = resolvePos(listEl, cardEl);
        cardEl.classList.add('is-saving');
        try {
            const data = await api(moveTpl.replace('__CARD__', encodeURIComponent(cardId)), {
                method: 'POST', json: { list_id: listId, pos }
            });
            if (data.card?.pos !== undefined) cardEl.dataset.pos = String(data.card.pos);
        } catch (err) {
            toastError(err.message || 'Move failed');
            window.location.reload();
        } finally {
            cardEl.classList.remove('is-saving');
        }
    }

    // ── Drag & drop ─────────────────────────────────────────────
    if (typeof Sortable !== 'undefined') {
        board.querySelectorAll('.js-card-list').forEach((listEl) => {
            Sortable.create(listEl, {
                group: 'trello-cards',
                animation: 150,
                draggable: '.trello-card',
                filter: '.js-empty-msg, .trello-list-footer, .trello-card-composer',
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onStart() { dragged = true; panning = false; board.classList.remove('is-panning'); },
                onEnd(evt) {
                    refreshListUi(evt.from);
                    refreshListUi(evt.to);
                    setTimeout(() => { dragged = false; }, 50);
                    if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;
                    persistMove(evt.item, evt.to);
                },
            });
        });
    }

    // ── Add card composer (Trello-style) ─────────────────────────
    function closeAddCardComposer(listEl) {
        if (!listEl) {
            board.querySelectorAll('.trello-list.is-composing').forEach((l) => l.classList.remove('is-composing'));
            return;
        }
        listEl.classList.remove('is-composing');
        const input = listEl.querySelector('.js-add-card-input');
        if (input) input.value = '';
    }

    function openAddCardComposer(listEl) {
        if (!listEl) return;
        board.querySelectorAll('.trello-list.is-composing').forEach((l) => {
            if (l !== listEl) closeAddCardComposer(l);
        });
        listEl.classList.add('is-composing');
        const input = listEl.querySelector('.js-add-card-input');
        if (input) {
            input.value = '';
            requestAnimationFrame(() => input.focus());
        }
    }

    async function submitAddCard(listEl) {
        if (!listEl || !createCardUrl) return;
        const listId = listEl.dataset.listId;
        const input = listEl.querySelector('.js-add-card-input');
        const submitBtn = listEl.querySelector('.js-add-card-submit');
        const name = (input?.value || '').trim();
        if (!listId || !name) {
            input?.focus();
            return;
        }
        if (submitBtn) submitBtn.disabled = true;
        try {
            const payload = { list_id: listId, name, pos: 'bottom' };
            if (/^https?:\/\/\S+$/i.test(name)) payload.url = name;
            const data = await api(createCardUrl, { method: 'POST', json: payload });
            const card = data.card;
            if (card?.id) {
                const listBody = listEl.querySelector('.js-card-list');
                const empty = listBody?.querySelector('.js-empty-msg');
                const wrap = document.createElement('div');
                wrap.innerHTML = cardTileHtml(card);
                const el = wrap.firstElementChild;
                if (el && listBody) {
                    if (empty) listBody.insertBefore(el, empty);
                    else listBody.appendChild(el);
                }
                refreshListUi(listBody || listEl);
            }
            if (input) {
                input.value = '';
                input.focus();
            }
        } catch (err) {
            toastError(err.message || 'Could not add card');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    board.addEventListener('click', (e) => {
        const openBtn = e.target.closest('.js-add-card-open');
        if (openBtn) {
            e.preventDefault();
            e.stopPropagation();
            openAddCardComposer(openBtn.closest('.trello-list'));
            return;
        }
        const cancelBtn = e.target.closest('.js-add-card-cancel');
        if (cancelBtn) {
            e.preventDefault();
            e.stopPropagation();
            closeAddCardComposer(cancelBtn.closest('.trello-list'));
            return;
        }
        const submitBtn = e.target.closest('.js-add-card-submit');
        if (submitBtn) {
            e.preventDefault();
            e.stopPropagation();
            submitAddCard(submitBtn.closest('.trello-list'));
        }
    });

    board.addEventListener('keydown', (e) => {
        const input = e.target.closest?.('.js-add-card-input');
        if (!input) return;
        const listEl = input.closest('.trello-list');
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            closeAddCardComposer(listEl);
            return;
        }
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submitAddCard(listEl);
        }
    });

    document.addEventListener('mousedown', (e) => {
        const open = board.querySelector('.trello-list.is-composing');
        if (!open) return;
        if (e.target.closest('.trello-list.is-composing')) return;
        // Keep open while interacting with card modal / other chrome
        if (e.target.closest('#tc-backdrop, .tc-member-pop, .swal2-container')) return;
        closeAddCardComposer(open);
    });

    // ── Click-drag / wheel to scroll board (Trello-style pan) ────
    function canStartPan(target) {
        if (!target || dragged) return false;
        // Don't steal card drag / buttons / links / composer
        if (target.closest('.trello-card, a, button, input, textarea, select, .tc-icon-btn, .trello-list-footer')) return false;
        return true;
    }

    board.addEventListener('pointerdown', (e) => {
        if (e.button !== 0 || !canStartPan(e.target)) return;
        panning = true;
        panMoved = false;
        panStartX = e.clientX;
        panScrollLeft = board.scrollLeft;
        board.classList.add('is-panning');
        try { board.setPointerCapture(e.pointerId); } catch (_) {}
    });

    board.addEventListener('pointermove', (e) => {
        if (!panning) return;
        const dx = e.clientX - panStartX;
        if (Math.abs(dx) > 3) panMoved = true;
        board.scrollLeft = panScrollLeft - dx;
        e.preventDefault();
    });

    function endPan(e) {
        if (!panning) return;
        panning = false;
        board.classList.remove('is-panning');
        try { if (e?.pointerId != null) board.releasePointerCapture(e.pointerId); } catch (_) {}
        // Brief flag so click-after-pan does not open a card
        if (panMoved) {
            dragged = true;
            setTimeout(() => { dragged = false; panMoved = false; }, 80);
        }
    }
    board.addEventListener('pointerup', endPan);
    board.addEventListener('pointercancel', endPan);

    // Vertical wheel → horizontal board scroll (like Trello)
    board.addEventListener('wheel', (e) => {
        if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
        // If scrolling inside a tall list, let the list scroll vertically first
        const listBody = e.target.closest('.js-card-list');
        if (listBody && listBody.scrollHeight > listBody.clientHeight) {
            const atTop = listBody.scrollTop <= 0 && e.deltaY < 0;
            const atBottom = listBody.scrollTop + listBody.clientHeight >= listBody.scrollHeight - 1 && e.deltaY > 0;
            if (!atTop && !atBottom) return;
        }
        board.scrollLeft += e.deltaY;
        e.preventDefault();
    }, { passive: false });

    // ── Open card modal ─────────────────────────────────────────
    board.addEventListener('click', (e) => {
        if (dragged || panMoved) return;
        const cardEl = e.target.closest('.trello-card');
        if (!cardEl) return;
        if (e.target.closest('.js-card-complete')) {
            toggleCardComplete(cardEl);
            return;
        }
        openCard(cardEl.dataset.cardId);
    });

    function toggleCardComplete(cardEl) {
        setCardComplete(cardEl.dataset.cardId, !cardEl.classList.contains('is-complete'));
    }

    /** Modal title circle: green check when complete (Trello "Mark complete"). */
    function syncModalComplete() {
        const btn = document.getElementById('tc-complete-toggle');
        if (!btn) return;
        const on = !!state?.card?.dueComplete;
        btn.classList.toggle('is-complete', on);
        btn.querySelector('i').className = 'bi ' + (on ? 'bi-check-circle-fill' : 'bi-circle');
        btn.title = on ? 'Mark incomplete' : 'Mark complete';
        btn.setAttribute('aria-label', btn.title);
    }

    /** Mark a card complete/incomplete everywhere it shows (board tile + open card), rolled back on error. */
    async function setCardComplete(cardId, complete) {
        if (!cardId) return;
        const apply = (on) => {
            const tile = board.querySelector(`.trello-card[data-card-id="${cardId}"]`);
            if (tile) {
                tile.classList.toggle('is-complete', on);
                const icon = tile.querySelector('.js-card-complete i');
                if (icon) icon.className = 'bi ' + (on ? 'bi-check-circle-fill' : 'bi-circle');
                const due = tile.querySelector('.trello-badge .bi-clock')?.parentElement;
                if (due) due.classList.toggle('is-due-complete', on);
            }
            if (state?.card?.id === cardId) {
                state.card.dueComplete = on;
                syncModalComplete();
                renderCardDue();
            }
        };
        apply(complete);
        try {
            await api(cardUrl(cardId), { method: 'PUT', json: { dueComplete: complete } });
        } catch (err) {
            apply(!complete);
            toastError(err.message || 'Could not update card');
        }
    }

    function closePanels() {
        document.querySelectorAll('.tc-panel').forEach((p) => p.classList.remove('show'));
        document.querySelectorAll('.tc-action').forEach((b) => b.classList.remove('active'));
        closeMemberPop();
    }

    // ── Popovers open as dropdowns under the element that opened them (Trello-style) ──
    const PANEL_TRIGGERS = '[data-panel], [data-open-labels], [data-open-members], #tc-card-due-btn, .tc-del-checklist';
    let panelAnchor = null;

    function isVisible(el) {
        return !!(el && el.isConnected && el.getClientRects().length);
    }

    function anchorPanel(panel) {
        const main = panel.offsetParent;
        if (!main) return;
        const key = panel.id.replace(/^panel-/, '');
        let a = panelAnchor;
        if (!isVisible(a)) a = document.querySelector(`.tc-action[data-panel="${key}"]:not([hidden])`);
        if (!isVisible(a)) a = document.querySelector('.tc-action[data-panel="add"]');
        if (!isVisible(a)) return;
        const ar = a.getBoundingClientRect();
        const mr = main.getBoundingClientRect();
        const maxLeft = main.clientWidth - panel.offsetWidth - 8;
        const left = Math.max(8, Math.min(ar.left - mr.left + main.scrollLeft, maxLeft));
        panel.style.left = `${left}px`;
        panel.style.top = `${ar.bottom - mr.top + main.scrollTop + 4}px`;
    }

    // Capture phase: runs before the panel handlers (which stop propagation / re-render).
    drawer.addEventListener('click', (e) => {
        const trigger = e.target.closest(PANEL_TRIGGERS);
        if (trigger) {
            // Items inside the "Add to card" menu open their panel in the same spot (under Add).
            panelAnchor = trigger.closest('#panel-add')
                ? document.querySelector('.tc-action[data-panel="add"]')
                : trigger;
            return;
        }
        if (!e.target.closest('.tc-panel')) closePanels();
    }, true);
    document.addEventListener('keydown', () => { panelAnchor = null; }, true);

    let memberPopId = null;
    const memberPop = document.getElementById('tc-member-pop');
    if (memberPop && memberPop.parentElement !== document.body) {
        document.body.appendChild(memberPop);
    }

    function closeMemberPop() {
        memberPopId = null;
        if (!memberPop) return;
        memberPop.classList.remove('show');
        memberPop.hidden = true;
    }

    function openMemberPop(btn) {
        if (!memberPop || !btn) return;
        closePanels();
        const id = btn.dataset.memberId || '';
        const name = btn.dataset.memberName || 'Member';
        const user = (btn.dataset.memberUser || '').replace(/^@/, '');
        memberPopId = id;
        const av = document.getElementById('tc-member-pop-av');
        const nameEl = document.getElementById('tc-member-pop-name');
        const handleEl = document.getElementById('tc-member-pop-handle');
        const profile = document.getElementById('tc-member-pop-profile');
        if (av) {
            av.textContent = initials(name);
            av.style.background = avatarColor(name);
        }
        if (nameEl) nameEl.textContent = name;
        if (handleEl) handleEl.textContent = user ? '@' + user : '';
        if (profile) {
            profile.href = user ? `https://trello.com/u/${encodeURIComponent(user)}` : '#';
            profile.style.display = user ? '' : 'none';
        }
        memberPop.hidden = false;
        memberPop.classList.add('show');
        const rect = btn.getBoundingClientRect();
        const w = memberPop.offsetWidth || 280;
        const h = memberPop.offsetHeight || 160;
        let left = rect.left;
        let top = rect.bottom + 8;
        if (left + w > window.innerWidth - 8) left = window.innerWidth - w - 8;
        if (left < 8) left = 8;
        if (top + h > window.innerHeight - 8) top = Math.max(8, rect.top - h - 8);
        memberPop.style.left = left + 'px';
        memberPop.style.top = top + 'px';
    }

    let hideDetails = false;
    let descEditing = false;

    function closeDrawer() {
        backdrop.classList.remove('show');
        backdrop.setAttribute('aria-hidden', 'true');
        closePanels();
        closeMemberPop();
        closeMovePop();
        closeCoverPop();
        hideLinkHover();
        hideEditorLinkBar();
        hideEditorImageBar();
        closeLightbox();
        currentCardId = null;
        state = null;
        descEditing = false;
        clearCommentEditor();
        document.getElementById('tc-loading').hidden = true;
        document.getElementById('tc-content').hidden = true;
        const cover = document.getElementById('tc-cover');
        const coverWrap = document.getElementById('tc-cover-wrap');
        if (cover) {
            cover.onload = null;
            cover.removeAttribute('src');
            cover.src = '';
            cover.hidden = true;
            cover.style.opacity = '';
        }
        if (coverWrap) {
            coverWrap.classList.remove('has-image', 'color-only', 'is-full', 'is-wide', 'is-loading');
            coverWrap.style.background = '#626f86';
        }
    }
    document.getElementById('tc-close').addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) closeDrawer();
    });
    document.addEventListener('keydown', (e) => {
        if (!backdrop.classList.contains('show')) return;
        if (e.key === 'Escape') {
            const labelsPanel = document.getElementById('panel-labels');
            if (labelsPanel?.classList.contains('show')) {
                if (labelsPanel.classList.contains('is-form')) {
                    showLabelsBrowse();
                    renderLabelsList();
                } else {
                    closePanels();
                }
                e.preventDefault();
                return;
            }
            if (memberPop?.classList.contains('show')) {
                closeMemberPop();
                e.preventDefault();
                return;
            }
            if (document.getElementById('panel-dates')?.classList.contains('show')
                || document.getElementById('panel-members')?.classList.contains('show')
                || document.getElementById('panel-attach')?.classList.contains('show')) {
                closePanels();
                e.preventDefault();
                return;
            }
            closeDrawer();
            return;
        }
        const t = e.target;
        const typing = t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/i.test(t.tagName));
        // Trello shortcut: "1" opens Labels
        if (e.key === '1' && !e.ctrlKey && !e.metaKey && !e.altKey && !typing) {
            e.preventDefault();
            openLabelsPanel();
            return;
        }
        // Trello shortcut: "d" opens Dates
        if ((e.key === 'd' || e.key === 'D') && !e.ctrlKey && !e.metaKey && !e.altKey && !typing) {
            e.preventDefault();
            openDatesPanel();
            return;
        }
        if ((e.key === 'm' || e.key === 'M') && !e.ctrlKey && !e.metaKey && !e.altKey && !typing) {
            e.preventDefault();
            openMembersPanel();
        }
    });

    document.getElementById('tc-content').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-panel]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const panel = document.getElementById('panel-' + btn.dataset.panel);
        if (!panel) return;
        const open = panel.classList.contains('show');
        closePanels();
        if (!open) {
            panel.classList.add('show');
            anchorPanel(panel);
            document.querySelectorAll('.tc-action[data-panel="' + btn.dataset.panel + '"]').forEach((b) => b.classList.add('active'));
            if (btn.dataset.panel === 'checklist') {
                openChecklistPanel();
            }
            if (btn.dataset.panel === 'labels') {
                showLabelsBrowse();
                renderLabelsList();
                const search = document.getElementById('tc-labels-search');
                if (search) search.value = '';
                setTimeout(() => search?.focus(), 30);
            }
            if (btn.dataset.panel === 'dates') {
                openDatesPanel();
            }
            if (btn.dataset.panel === 'members') {
                openMembersPanel();
            }
            if (btn.dataset.panel === 'attach') {
                openAttachPanel();
            }
        }
    });

    document.getElementById('tc-card-labels')?.addEventListener('click', (e) => {
        if (e.target.closest('[data-open-labels]')) {
            e.preventDefault();
            e.stopPropagation();
            openLabelsPanel();
        }
    });
    document.getElementById('tc-card-members')?.addEventListener('click', (e) => {
        const addBtn = e.target.closest('[data-open-members]');
        if (addBtn) {
            e.preventDefault();
            e.stopPropagation();
            closeMemberPop();
            openMembersPanel();
            return;
        }
        const memAv = e.target.closest('.tc-card-member[data-member-id]');
        if (memAv) {
            e.preventDefault();
            e.stopPropagation();
            openMemberPop(memAv);
        }
    });
    document.getElementById('tc-member-pop-close')?.addEventListener('click', () => closeMemberPop());
    document.getElementById('tc-member-pop-remove')?.addEventListener('click', async () => {
        if (!memberPopId || !currentCardId) return;
        const id = memberPopId;
        const cardId = currentCardId;
        closeMemberPop();
        await optimistic(() => setCardMemberLocal(id, false), () => api(cardUrl(cardId, 'members'), {
            method: 'POST',
            json: { member_id: id, active: false },
        }), 'Could not remove member');
    });
    document.addEventListener('mousedown', (e) => {
        if (!memberPop?.classList.contains('show')) return;
        if (e.target.closest('#tc-member-pop') || e.target.closest('.tc-card-member[data-member-id]')) return;
        closeMemberPop();
    });
    document.getElementById('tc-card-due-btn')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        openDatesPanel();
    });

    document.getElementById('tc-labels-close')?.addEventListener('click', () => closePanels());
    document.getElementById('tc-members-close')?.addEventListener('click', () => closePanels());
    document.getElementById('tc-members-search')?.addEventListener('input', () => renderMembersList());
    document.getElementById('tc-labels-back')?.addEventListener('click', () => {
        showLabelsBrowse();
        renderLabelsList();
    });
    document.getElementById('tc-labels-search')?.addEventListener('input', () => renderLabelsList());
    document.getElementById('tc-labels-create')?.addEventListener('click', () => showLabelsForm('create'));
    document.getElementById('tc-labels-cbf')?.addEventListener('click', () => {
        const on = localStorage.getItem(LABEL_CBF_KEY) === '1';
        localStorage.setItem(LABEL_CBF_KEY, on ? '0' : '1');
        syncLabelsColorblind();
    });
    document.getElementById('tc-label-save')?.addEventListener('click', async () => {
        if (!currentCardId) return;
        const name = document.getElementById('tc-label-name')?.value ?? '';
        try {
            if (labelFormMode === 'edit' && editingLabelId) {
                await api(`/admin/trello/teams/${teamId}/labels/${editingLabelId}`, {
                    method: 'PUT',
                    json: { name, color: selectedLabelColor, board_id: boardId },
                });
            } else {
                await api(cardUrl(currentCardId, 'labels/create'), {
                    method: 'POST',
                    json: { name, color: selectedLabelColor, board_id: boardId },
                });
            }
            await reloadCard();
            openLabelsPanel();
        } catch (err) { toastError(err.message); }
    });

    function resetCardChrome() {
        state = null;
        descEditing = false;
        const cover = document.getElementById('tc-cover');
        const coverWrap = document.getElementById('tc-cover-wrap');
        const coverList = document.getElementById('tc-cover-list');
        if (cover) {
            cover.onload = null;
            cover.removeAttribute('src');
            cover.src = '';
            cover.hidden = true;
        }
        if (coverWrap) {
            coverWrap.classList.remove('has-image', 'color-only', 'is-full', 'is-wide');
            coverWrap.classList.add('is-loading');
            coverWrap.style.background = '';
        }
        if (coverList) coverList.hidden = true;
        const name = document.getElementById('tc-name');
        if (name) name.value = '';
        closePanels();
    }

    async function openCard(cardId) {
        currentCardId = cardId;
        attShowAll = false;
        descExpanded = false;
        cardLoading = true;
        resetCardChrome();
        backdrop.classList.add('show');
        backdrop.setAttribute('aria-hidden', 'false');
        document.getElementById('tc-loading').hidden = false;
        document.getElementById('tc-content').hidden = true;
        try {
            const data = await api(cardUrl(cardId));
            // Ignore stale response if user opened another card meanwhile
            if (currentCardId !== cardId) return;
            state = data;
            renderCard(data);
            document.getElementById('tc-cover-wrap')?.classList.remove('is-loading');
            document.getElementById('tc-loading').hidden = true;
            document.getElementById('tc-content').hidden = false;
            syncDescMore();
        } catch (err) {
            if (currentCardId !== cardId) return;
            closeDrawer();
            toastError(err.message || 'Could not open card');
        } finally {
            if (currentCardId === cardId) cardLoading = false;
        }
    }

    function toLocalInput(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        const pad = (n) => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function fromLocalInput(val) {
        if (!val) return null;
        const d = new Date(val);
        return Number.isNaN(d.getTime()) ? null : d.toISOString();
    }

    const AVATAR_COLORS = ['#579dff', '#1f845a', '#946f00', '#ae2e24', '#5e4db2', '#216e4e', '#e56910', '#8270db'];

    function initials(name) {
        const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }

    function avatarColor(name) {
        let h = 0;
        const s = String(name || '');
        for (let i = 0; i < s.length; i++) h = (h + s.charCodeAt(i) * 17) % AVATAR_COLORS.length;
        return AVATAR_COLORS[h];
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, (ch) => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[ch]));
    }

    // ── Initial card tiles (same renderer as live refresh / add card) ──
    const INITIAL_TILES = JSON.parse(document.getElementById('trello-initial-cards')?.textContent || '{}');
    const BOARD_MEMBERS = new Map((INITIAL_TILES.members || []).map((m) => [m.id, m]));
    (function renderInitialCards() {
        const byList = {};
        (INITIAL_TILES.cards || []).forEach((c) => { (byList[c.idList] ||= []).push(c); });
        board.querySelectorAll('.js-card-list').forEach((listEl) => {
            const empty = listEl.querySelector('.js-empty-msg');
            const html = (byList[listEl.dataset.listId] || []).map(cardTileHtml).join('');
            if (empty) empty.insertAdjacentHTML('beforebegin', html);
            else listEl.insertAdjacentHTML('beforeend', html);
            refreshListUi(listEl);
        });
    })();

    function resolveDescMediaUrl(rawUrl, alt) {
        const url = String(rawUrl || '').trim();
        const atts = state?.attachments || [];
        const cardId = currentCardId || state?.card?.id || '';

        // Prefer proxied attachment URLs (Trello CDN blocks browser loads)
        for (const a of atts) {
            if (!a?.id) continue;
            if (url.includes(a.id)) {
                return a.preview_url || a.download_url || url;
            }
        }
        const file = decodeURIComponent((url.split('/').pop() || '').split('?')[0] || '');
        const altName = String(alt || '').trim();
        for (const a of atts) {
            const name = a.name || a.fileName || '';
            if (!name) continue;
            if ((file && file === name) || (altName && altName === name)) {
                return a.preview_url || a.download_url || url;
            }
        }

        // Rewrite trello.com / api.trello.com attachment paths → CRM proxy
        const m = url.match(/\/cards\/([^/]+)\/attachments\/([^/]+)/i);
        if (m && teamId) {
            const cId = m[1];
            const attId = m[2];
            const preview = url.match(/\/previews\/([^/]+)/i);
            if (preview) {
                return `/admin/trello/teams/${teamId}/covers/${cId}/${attId}/${preview[1]}`;
            }
            return `/admin/trello/teams/${teamId}/cards/${cId}/attachments/${attId}`;
        }

        // Relative trello markdown sometimes only has filename — map via card attachments
        if (!/^https?:\/\//i.test(url) && cardId && teamId) {
            for (const a of atts) {
                const name = a.name || a.fileName || '';
                if (name && (url === name || altName === name)) {
                    return a.preview_url || a.download_url || url;
                }
            }
        }

        return url;
    }

    /** Trello-style link chip: favicon + page title, filled in later by hydrateSmartLinks(). */
    function smartLinkHtml(url) {
        const label = url.replace(/^https?:\/\//i, '').replace(/\/$/, '');
        return `<a class="tc-smart-link" href="${url}" target="_blank" rel="noopener" data-preview-url="${url}">`
            + `<img class="ico" alt="" hidden><span class="t">${label}</span></a>`;
    }

    // url → Promise<{title, icon, description, site_name}> (one request per URL per page load)
    const linkPreviewCache = new Map();

    function fetchLinkPreview(url) {
        if (!linkPreviewCache.has(url)) {
            linkPreviewCache.set(url, api(`/admin/trello/link-preview?url=${encodeURIComponent(url)}`)
                .catch(() => ({})));
        }
        return linkPreviewCache.get(url);
    }

    function setFavicon(img, src) {
        if (!img || !src) return;
        img.onerror = () => { img.hidden = true; };
        img.onload = () => { img.hidden = false; };
        img.src = src;
    }

    function hydrateSmartLinks(root) {
        root.querySelectorAll('a.tc-smart-link[data-preview-url]:not([data-hydrated])').forEach((a) => {
            a.dataset.hydrated = '1';
            fetchLinkPreview(a.dataset.previewUrl).then((p) => {
                if (p?.title) a.querySelector('.t').textContent = p.title;
                setFavicon(a.querySelector('.ico'), p?.icon);
            });
        });
    }

    // ── Smart link hover card (title, description, Copy link, site) ──
    let linkHoverEl = null;
    let linkHoverTimer = null;
    let linkHoverFor = null;

    function linkHoverCard() {
        if (linkHoverEl) return linkHoverEl;
        linkHoverEl = document.createElement('div');
        linkHoverEl.className = 'tc-link-hover';
        linkHoverEl.hidden = true;
        linkHoverEl.innerHTML = `
            <div class="hd"><img class="ico" alt="" hidden><a class="ttl" target="_blank" rel="noopener"></a></div>
            <p class="desc" hidden></p>
            <button type="button" class="copy"><i class="bi bi-link-45deg"></i> Copy link</button>
            <div class="ft"><img class="ico" alt="" hidden><span class="site"></span></div>`;
        linkHoverEl.addEventListener('mouseenter', () => clearTimeout(linkHoverTimer));
        linkHoverEl.addEventListener('mouseleave', hideLinkHoverSoon);
        linkHoverEl.querySelector('.copy').addEventListener('click', async () => {
            const url = linkHoverEl.dataset.url || '';
            try {
                await navigator.clipboard.writeText(url);
                if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Link copied', showConfirmButton: false, timer: 1200 });
            } catch (err) { toastError('Could not copy link'); }
            hideLinkHover();
        });
        document.body.appendChild(linkHoverEl);
        return linkHoverEl;
    }

    function hideLinkHover() {
        clearTimeout(linkHoverTimer);
        linkHoverFor = null;
        if (linkHoverEl) linkHoverEl.hidden = true;
    }

    function hideLinkHoverSoon() {
        clearTimeout(linkHoverTimer);
        linkHoverTimer = setTimeout(hideLinkHover, 250);
    }

    async function showLinkHover(a) {
        const url = a.dataset.previewUrl;
        const card = linkHoverCard();
        linkHoverFor = a;
        const p = await fetchLinkPreview(url);
        if (linkHoverFor !== a) return;
        let host = url;
        try { host = new URL(url).hostname.replace(/^www\./, ''); } catch (e) {}
        card.dataset.url = url;
        const ttl = card.querySelector('.ttl');
        ttl.href = url;
        ttl.textContent = p?.title || url.replace(/^https?:\/\//i, '');
        const desc = card.querySelector('.desc');
        desc.textContent = p?.description || '';
        desc.hidden = !p?.description;
        card.querySelector('.site').textContent = p?.site_name || host;
        card.querySelectorAll('.ico').forEach((img) => { img.hidden = true; setFavicon(img, p?.icon); });
        card.hidden = false;
        const r = a.getBoundingClientRect();
        const w = card.offsetWidth;
        const h = card.offsetHeight;
        let top = r.bottom + 6;
        if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 6);
        card.style.left = `${Math.max(8, Math.min(r.left, window.innerWidth - w - 8))}px`;
        card.style.top = `${top}px`;
    }

    document.addEventListener('mouseover', (e) => {
        const a = e.target.closest?.('a.tc-smart-link[data-preview-url]');
        if (!a) return;
        clearTimeout(linkHoverTimer);
        if (linkHoverFor === a && linkHoverEl && !linkHoverEl.hidden) return;
        linkHoverTimer = setTimeout(() => showLinkHover(a), 350);
    });
    document.addEventListener('mouseout', (e) => {
        const a = e.target.closest?.('a.tc-smart-link[data-preview-url]');
        if (a && !a.contains(e.relatedTarget)) hideLinkHoverSoon();
    });
    document.addEventListener('scroll', hideLinkHover, true);

    function myTrelloUsername() {
        const me = (state?.board_members || []).find((m) => m.id === meId);
        return String(me?.username || '').toLowerCase();
    }

    function linkifyPlainText(text, opts = {}) {
        // Only run on unprotected plain text — never inside already-built <a>/<img>
        let t = text;
        // Text is already HTML-escaped, so a query "&" arrives as "&amp;" — keep it in the URL
        t = t.replace(
            /(https?:\/\/(?:[^\s<&]|&amp;)+)/g,
            (url) => opts.smart ? smartLinkHtml(url) : `<a href="${url}" target="_blank" rel="noopener">${url}</a>`
        );
        t = t.replace(
            /(^|[^:\w.\-\/])([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/g,
            (_, before, email) => `${before}<a href="mailto:${email}">${email}</a>`
        );
        const meUser = myTrelloUsername();
        t = t.replace(
            /(^|[\s(])@([a-zA-Z][a-zA-Z0-9_]{2,})\b/g,
            (_, before, user) => `${before}<span class="tc-mention-pill${meUser && user.toLowerCase() === meUser ? ' is-me' : ''}">@${user}</span>`
        );
        return t;
    }

    function linkify(text, opts = {}) {
        // Heal already-saved corruption from nested <a> unwrap:
        // [](mailto:real@x.com)[label@y.com](mailto:label@y.com) → [label@y.com](mailto:real@x.com)
        let raw = String(text ?? '').replace(
            /\[\]\((mailto:[^\s)]+)\)\[([^\]]+)\]\(mailto:[^\s)]+\)/gi,
            '[$2]($1)'
        );

        let html = escapeHtml(raw);
        // Optional markdown title: space + "..." (after escapeHtml quotes are &quot;)
        const mdTitle = '(?:\\s+&quot;[\\s\\S]*?&quot;)?';
        const slots = [];
        const protect = (snippet) => {
            const i = slots.length;
            slots.push(snippet);
            return `\u0000MD${i}\u0000`;
        };

        // Images: ![alt](url "title")
        html = html.replace(
            new RegExp('!\\[([^\\]]*)\\]\\(([^)\\s]+)(?:' + mdTitle + ')\\)', 'g'),
            (_, alt, url) => {
                const clean = String(url).trim();
                const src = resolveDescMediaUrl(clean, alt);
                const originalMd = `![${alt}](${clean})`;
                if (!src) return escapeHtml(originalMd);
                return protect(
                    `<img class="tc-desc-img" src="${escapeHtml(src)}" alt="${escapeHtml(alt || 'image')}" data-md="${escapeHtml(originalMd)}" loading="lazy">`
                );
            }
        );

        // Links: [label](https://...|mailto:...|...)  — allow empty label
        html = html.replace(
            new RegExp('\\[([^\\]]*)\\]\\(([^)\\s]+)(?:' + mdTitle + ')\\)', 'g'),
            (_, label, url) => {
                const href = String(url).trim();
                if (!/^(https?:|mailto:|tel:|\/)/i.test(href)) {
                    return `[${label}](${href})`;
                }
                // Skip empty-label mailto leftovers after heal (nothing useful to show)
                if (label === '' && /^mailto:/i.test(href)) {
                    return '';
                }
                const target = /^https?:/i.test(href) ? ' target="_blank" rel="noopener"' : '';
                return protect(`<a href="${escapeHtml(href)}"${target}>${label}</a>`);
            }
        );

        // Bare URLs / emails / @mentions — only in unprotected plain text
        html = html.replace(/(^|>)([^<]*)/g, (_, edge, plain) => edge + linkifyPlainText(plain, opts));

        // Restore protected markdown links/images (so emails inside labels are not re-wrapped)
        html = html.replace(/\u0000MD(\d+)\u0000/g, (_, i) => slots[Number(i)] || '');

        return html;
    }

    function relativeWhen(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        // Trello: "just now" / "9 minutes ago" / "17 hours ago", then full date after a day.
        const mins = Math.floor((Date.now() - d.getTime()) / 60000);
        if (mins >= 0 && mins < 1) return 'just now';
        if (mins >= 0 && mins < 60) return `${mins} minute${mins === 1 ? '' : 's'} ago`;
        if (mins >= 0 && mins < 24 * 60) {
            const hrs = Math.floor(mins / 60);
            return `${hrs} hour${hrs === 1 ? '' : 's'} ago`;
        }
        return d.toLocaleString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit'
        });
    }

    function fileExtension(name) {
        const n = String(name || '').trim();
        // URLs / links without a real filename
        if (/^https?:\/\//i.test(n) && !/\.[a-z0-9]{2,5}($|\?)/i.test(n.split('/').pop() || '')) {
            return 'LINK';
        }
        const base = (n.split('/').pop() || n).split('?')[0];
        const m = base.match(/\.([a-z0-9]{1,8})$/i);
        return m ? m[1].toUpperCase() : 'FILE';
    }

    function isImageAttachment(a) {
        if (a?.preview_url) return true;
        const n = (a?.name || a?.fileName || a?.url || '').toLowerCase();
        return /\.(png|jpe?g|gif|webp|bmp|svg|ico)$/i.test(n);
    }

    function attachmentThumbHtml(a) {
        if (isImageAttachment(a) && a.preview_url) {
            return `<img src="${escapeHtml(a.preview_url)}" alt="">`;
        }
        if (isImageAttachment(a) && a.download_url) {
            return `<img src="${escapeHtml(a.download_url)}" alt="">`;
        }
        const ext = fileExtension(a?.name || a?.fileName || a?.url || '');
        const cls = 'ext-' + ext.toLowerCase().replace(/[^a-z0-9]/g, '');
        const label = ext.length > 5 ? ext.slice(0, 4) : ext;
        return `<div class="ph ${cls}" title="${escapeHtml(ext)}">${escapeHtml(label)}</div>`;
    }

    /** Inline Markdown: `code`, **bold**, *italic*, ~~strike~~ + links/images/mentions (via linkify). */
    function mdInline(text, opts = {}) {
        return String(text).split(/(`[^`\n]+`)/g).map((part) => {
            if (/^`[^`\n]+`$/.test(part)) return `<code>${escapeHtml(part.slice(1, -1))}</code>`;
            // Only touch text between tags so URLs / attributes are never rewritten
            return linkify(part, opts).replace(/(^|>)([^<]*)/g, (_, edge, plain) => edge + plain
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/(^|[^*])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>')
                .replace(/~~([^~]+)~~/g, '<del>$1</del>'));
        }).join('');
    }

    /** Block Markdown → HTML for the description view (Trello renders Markdown, not raw text). */
    function renderMarkdown(md, opts = {}) {
        const lines = String(md || '').replace(/\r\n?/g, '\n').split('\n');
        const ul = /^\s*[-*+]\s+/;
        const ol = /^\s*\d+[.)]\s+/;
        const isFence = (l) => /^\s*```/.test(l);
        const isHeading = (l) => /^#{1,6}\s+/.test(l);
        const isQuote = (l) => /^\s*>/.test(l);
        const isRule = (l) => /^\s*([-*_])(\s*\1){2,}\s*$/.test(l);
        const inline = (t) => mdInline(t, opts);
        const out = [];
        let i = 0;
        while (i < lines.length) {
            const line = lines[i];
            if (isFence(line)) {
                const lang = (line.match(/^\s*```\s*([\w#+.-]*)/) || [])[1] || '';
                const code = [];
                i++;
                while (i < lines.length && !/^\s*```\s*$/.test(lines[i])) code.push(lines[i++]);
                i++; // closing fence
                // Empty lines get a <br> so they keep their height (and stay editable)
                out.push(`<pre class="tc-md-code" data-lang="${escapeHtml(lang)}"><code>${code.map((l) => `<span class="ln">${escapeHtml(l) || '<br>'}</span>`).join('')}</code></pre>`);
                continue;
            }
            if (!line.trim()) { i++; continue; }
            const h = line.match(/^(#{1,6})\s+(.*)$/);
            if (h) {
                out.push(`<h${h[1].length}>${inline(h[2])}</h${h[1].length}>`);
                i++;
                continue;
            }
            if (isRule(line)) { out.push('<hr>'); i++; continue; }
            if (isQuote(line)) {
                const q = [];
                while (i < lines.length && isQuote(lines[i])) q.push(lines[i++].replace(/^\s*>\s?/, ''));
                out.push(`<blockquote>${renderMarkdown(q.join('\n'), opts)}</blockquote>`);
                continue;
            }
            if (ul.test(line) || ol.test(line)) {
                const re = ol.test(line) ? ol : ul;
                const tag = re === ol ? 'ol' : 'ul';
                const items = [];
                while (i < lines.length && re.test(lines[i]) && !isRule(lines[i])) items.push(lines[i++].replace(re, ''));
                out.push(`<${tag}>${items.map((t) => `<li>${inline(t)}</li>`).join('')}</${tag}>`);
                continue;
            }
            const para = [];
            while (i < lines.length && lines[i].trim() && !isFence(lines[i]) && !isHeading(lines[i])
                && !isQuote(lines[i]) && !ul.test(lines[i]) && !ol.test(lines[i]) && !isRule(lines[i])) {
                para.push(lines[i++]);
            }
            out.push(`<p>${para.map(inline).join('<br>')}</p>`);
        }
        return out.join('');
    }

    /**
     * Rich editor HTML → Markdown (inverse of renderMarkdown, so a description survives
     * open → edit → save unchanged). Blocks are separated by a blank line, like Trello.
     */
    function editorHtmlToMarkdown(root) {
        let md = '';
        const kids = (n) => [...n.childNodes].forEach(walk);
        const endLine = () => { if (md && !md.endsWith('\n')) md += '\n'; };
        const startBlock = () => { if (md && !md.endsWith('\n\n')) md += md.endsWith('\n') ? '\n' : '\n\n'; };
        const walk = (node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                // Editors insert NBSPs (after links/mentions); Markdown wants plain spaces
                md += node.textContent.replace(/​/g, '').replace(/ /g, ' ');
                return;
            }
            if (node.nodeType !== Node.ELEMENT_NODE) return;
            const tag = node.tagName.toLowerCase();
            const inPre = !!node.closest('pre');
            if (tag === 'br') {
                // A code line's trailing <br> only keeps it visible — the line itself is the newline
                if (inPre && node.parentElement?.classList.contains('ln') && !node.nextSibling) return;
                md += '\n';
                return;
            }
            if (tag === 'pre') {
                startBlock();
                const start = md.length;
                kids(node);
                const body = md.slice(start).replace(/\n$/, '');
                md = md.slice(0, start) + '```' + (node.dataset.lang || '') + '\n' + body + '\n```\n\n';
                return;
            }
            if (inPre) {
                // Code lines (span.ln) / browser-inserted divs are one line each
                kids(node);
                if (node.classList?.contains('ln') || tag === 'div' || tag === 'p') md += '\n';
                return;
            }
            if (tag === 'img') {
                md += node.getAttribute('data-md')
                    || `![${node.getAttribute('alt') || 'image'}](${node.getAttribute('src') || ''})`;
                return;
            }
            if (tag === 'a') {
                const href = node.getAttribute('href') || '';
                const label = node.textContent || '';
                // Bare URL stays bare; skip empty [](mailto:...) shells from unwrapped nested <a>
                if (href && label === href) md += href;
                else if (href && href !== '#' && label.trim() !== '') md += `[${label}](${href})`;
                else if (!href || href === '#') md += label;
                return;
            }
            if (tag === 'span' && node.classList.contains('tc-mention-pill')) {
                md += node.getAttribute('data-mention') || node.textContent || '';
                return;
            }
            if (tag === 'strong' || tag === 'b') {
                md += '**'; [...node.childNodes].forEach(walk); md += '**'; return;
            }
            if (tag === 'em' || tag === 'i') {
                md += '*'; [...node.childNodes].forEach(walk); md += '*'; return;
            }
            if (tag === 's' || tag === 'strike' || tag === 'del') {
                md += '~~'; [...node.childNodes].forEach(walk); md += '~~'; return;
            }
            if (tag === 'code') {
                md += '`'; kids(node); md += '`'; return;
            }
            if (tag === 'blockquote') {
                startBlock();
                const start = md.length;
                kids(node);
                const chunk = md.slice(start).replace(/\n+$/, '').split('\n')
                    .map((l) => (l ? '> ' + l : '>')).join('\n');
                md = md.slice(0, start) + chunk + '\n\n';
                return;
            }
            if (/^h[1-6]$/.test(tag)) {
                startBlock();
                md += '#'.repeat(Number(tag[1])) + ' ';
                kids(node);
                md += '\n\n';
                return;
            }
            if (tag === 'ul' || tag === 'ol') {
                startBlock();
                let n = 0;
                [...node.childNodes].forEach((child) => {
                    if (child.nodeType === Node.ELEMENT_NODE && child.tagName.toLowerCase() === 'li') {
                        endLine();
                        md += tag === 'ol' ? `${++n}. ` : '- ';
                        kids(child);
                        endLine();
                    } else {
                        walk(child);
                    }
                });
                md += '\n';
                return;
            }
            if (tag === 'li') {
                endLine();
                md += '- ';
                kids(node);
                endLine();
                return;
            }
            if (tag === 'hr') {
                startBlock();
                md += '---\n\n';
                return;
            }
            if (tag === 'p' && !node.closest('li')) {
                startBlock();
                kids(node);
                md += '\n\n';
                return;
            }
            if (tag === 'div' || tag === 'p') {
                endLine();
                kids(node);
                endLine();
                return;
            }
            kids(node);
        };
        kids(root);
        // Collapse extra blank lines, but never inside ``` code blocks
        return md.split(/(```[^\n]*\n[\s\S]*?\n```)/g)
            .map((part, i) => (i % 2 ? part : part.replace(/\n{3,}/g, '\n\n')))
            .join('')
            .replace(/^\n+|\n+$/g, '');
    }

    // Long description → clipped view + "Show more" / "Show less" (kept while the same card is open)
    const DESC_COLLAPSE_PX = 440;
    let descExpanded = false;

    function syncDescMore() {
        const view = document.getElementById('tc-desc-view');
        const btn = document.getElementById('tc-desc-more');
        if (!view || !btn) return;
        if (view.hidden || view.classList.contains('is-empty')) {
            view.classList.remove('is-collapsed');
            btn.hidden = true;
            return;
        }
        view.classList.remove('is-collapsed');
        const tall = view.scrollHeight > DESC_COLLAPSE_PX + 40;
        btn.hidden = !tall;
        view.classList.toggle('is-collapsed', tall && !descExpanded);
        btn.querySelector('i').className = 'bi ' + (descExpanded ? 'bi-chevron-up' : 'bi-chevron-down');
        btn.querySelector('span').textContent = descExpanded ? 'Show less' : 'Show more';
    }

    document.getElementById('tc-desc-more')?.addEventListener('click', () => {
        descExpanded = !descExpanded;
        syncDescMore();
        if (!descExpanded) document.getElementById('tc-desc-view')?.scrollIntoView({ block: 'nearest' });
    });
    // Images change the height after they load
    document.getElementById('tc-desc-view')?.addEventListener('load', syncDescMore, true);

    function setDescMode(edit) {
        descEditing = edit;
        const view = document.getElementById('tc-desc-view');
        const editEl = document.getElementById('tc-desc-edit');
        const editBtn = document.getElementById('tc-edit-desc');
        const visual = document.getElementById('tc-desc-visual');
        const ta = document.getElementById('tc-desc');
        if (!view || !editEl) return;
        view.hidden = edit;
        editEl.hidden = !edit;
        syncDescMore();
        const hasText = !!(ta?.value || '').trim();
        if (editBtn) editBtn.hidden = edit || !hasText;
        if (!edit) {
            hideMentionMenu();
            hideCodeBar();
            hideEditorLinkBar();
            hideEditorImageBar();
        }
        if (edit && visual) {
            // WYSIWYG like Trello: edit the rendered Markdown, converted back on Save
            visual.innerHTML = renderMarkdown(ta.value || '');
            descDirty = false;
            syncDescCancelLabel();
            visual.focus();
        }
    }

    // Save only sends the description when the user actually changed something,
    // so opening the editor and pressing Save can never rewrite an existing description.
    let descDirty = false;
    // Trello: "Cancel" turns into "Discard changes" once something was edited
    function syncDescCancelLabel() {
        const btn = document.getElementById('tc-cancel-desc');
        if (btn) btn.textContent = descDirty ? 'Discard changes' : 'Cancel';
    }
    document.getElementById('tc-desc-visual')?.addEventListener('input', () => {
        descDirty = true;
        syncDescCancelLabel();
    });

    function renderDesc(desc) {
        const view = document.getElementById('tc-desc-view');
        const ta = document.getElementById('tc-desc');
        ta.value = desc || '';
        if (desc && String(desc).trim()) {
            view.classList.remove('is-empty');
            view.innerHTML = renderMarkdown(desc, { smart: true });
            hydrateSmartLinks(view);
        } else {
            view.classList.add('is-empty');
            view.textContent = 'Add a more detailed description...';
        }
        setDescMode(false);
    }

    function renderCard(data) {
        state = data;
        if (data.me_id) meId = String(data.me_id);
        const c = data.card;
        document.getElementById('tc-name').value = c.name || '';
        syncModalComplete();
        // Render desc after state.attachments is available (proxied image URLs).
        // While the description is being edited, a background re-sync must not close the editor.
        if (descEditing) document.getElementById('tc-desc').value = c.desc || '';
        else renderDesc(c.desc || '');
        syncDatesPanel(c);
        syncWatchCheckbox();

        const cover = document.getElementById('tc-cover');
        const coverWrap = document.getElementById('tc-cover-wrap');
        const coverBg = data.cover_bg || c.cover?.edgeColor || null;
        const coverSize = data.cover_size || c.cover?.size || 'normal';
        coverWrap.classList.remove('has-image', 'color-only', 'is-full', 'is-wide', 'is-loading');
        coverWrap.style.background = coverBg || '#626f86';
        if (coverSize === 'full') coverWrap.classList.add('is-full');
        if (data.cover_proxy_url) {
            cover.onload = null;
            cover.hidden = false;
            coverWrap.classList.add('has-image');
            // Only show image after it loads — avoids flash of previous card
            cover.style.opacity = '0';
            cover.onload = () => { cover.style.opacity = '1'; };
            cover.src = data.cover_proxy_url;
        } else {
            cover.hidden = true;
            cover.removeAttribute('src');
            cover.style.opacity = '';
            if (coverBg) coverWrap.classList.add('color-only');
            else coverWrap.style.background = '#626f86';
        }

        const coverList = document.getElementById('tc-cover-list');
        const currentList = (data.lists || []).find((l) => l.id === c.idList);
        const listName = document.getElementById('tc-list-name');
        if (listName) listName.textContent = currentList?.name || data.list?.name || '';
        if (coverList) coverList.hidden = !(currentList || data.list);

        renderLabelsList();
        renderCardLabels();
        renderCardMembers();
        renderCardDue();
        renderMembersList();
        syncLabelsColorblind();

        const checklists = data.checklists || [];
        const checkSec = document.getElementById('tc-checklists-sec');
        if (checkSec) checkSec.hidden = checklists.length === 0;
        document.getElementById('tc-checklists').innerHTML = checklists.map(cl => {
            const items = (cl.checkItems || []).slice().sort((a, b) => (a.pos || 0) - (b.pos || 0));
            const doneCount = items.filter((it) => it.state === 'complete').length;
            const pct = items.length ? Math.round((doneCount / items.length) * 100) : 0;
            const hideChecked = hiddenCheckedLists.has(cl.id);
            const rows = items.map(it => `
                <label class="tc-checkitem ${it.state === 'complete' ? 'done' : ''}">
                    <input type="checkbox" class="tc-check" data-item="${it.id}" ${it.state === 'complete' ? 'checked' : ''}>
                    <span class="flex-grow-1">${escapeHtml(it.name)}</span>
                    <button type="button" class="tc-btn tc-btn-danger tc-del-item" data-checklist="${cl.id}" data-item="${it.id}" title="Delete item">&times;</button>
                </label>`).join('');
            const hideBtn = doneCount > 0
                ? `<button type="button" class="tc-btn tc-toggle-checked" data-checklist="${cl.id}">${hideChecked ? `Show checked items (${doneCount})` : 'Hide checked items'}</button>`
                : '';
            return `<div class="tc-checklist${hideChecked ? ' hide-checked' : ''}" data-checklist="${cl.id}">
                <div class="tc-checklist-h">
                    <strong><i class="bi bi-check2-square"></i>${escapeHtml(cl.name)}</strong>
                    <span class="ops">
                        ${hideBtn}
                        <button type="button" class="tc-btn tc-del-checklist" data-checklist="${cl.id}">Delete</button>
                    </span>
                </div>
                <div class="tc-check-progress${items.length && pct === 100 ? ' is-done' : ''}">
                    <span class="pct">${pct}%</span>
                    <span class="bar"><span class="fill" style="width:${pct}%"></span></span>
                </div>
                ${rows}
                <div class="tc-check-add">
                    <button type="button" class="tc-btn tc-check-add-open">Add an item</button>
                    <div class="tc-check-add-form">
                        <textarea class="tc-new-item" rows="2" placeholder="Add an item"></textarea>
                        <div class="row-actions">
                            <button type="button" class="tc-btn tc-btn-primary tc-add-item" data-checklist="${cl.id}">Add</button>
                            <button type="button" class="tc-btn tc-btn-ghost tc-check-add-cancel">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');

        const coverId = c.cover?.idAttachment || c.idAttachmentCover || null;
        // Trello: newest first, only the first few until "View all attachments".
        const allAtts = (data.attachments || []).slice()
            .sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0));
        const atts = attShowAll ? allAtts : allAtts.slice(0, ATT_PREVIEW_LIMIT);
        const attToggle = document.getElementById('tc-att-toggle');
        if (attToggle) {
            const hiddenCount = allAtts.length - ATT_PREVIEW_LIMIT;
            attToggle.hidden = hiddenCount <= 0;
            attToggle.textContent = attShowAll ? 'Show fewer attachments' : `View all attachments (${hiddenCount} hidden)`;
        }
        document.getElementById('tc-attachments').innerHTML = atts.map(a => {
            const isCover = a.id === coverId;
            const when = a.date ? relativeWhen(a.date) : '';
            const size = a.bytes ? Math.round(a.bytes / 1024) + ' KB' : '';
            const fileName = a.name || a.fileName || 'file';
            const mime = String(a.mimeType || a.mime_type || '');
            const canCover = !!a.preview_url || mime.startsWith('image/');
            const coverAction = isCover
                ? `<button type="button" class="tc-att-remove-cover" data-att="${a.id}">Remove cover</button>`
                : (canCover ? `<button type="button" class="tc-att-make-cover" data-att="${a.id}">Make cover</button>` : '');
            return `<div class="tc-att" data-att-id="${a.id}">
                ${attachmentThumbHtml(a)}
                <div class="meta">
                    <a class="name" href="${a.download_url}" target="_blank" rel="noopener">${escapeHtml(fileName)}</a>
                    <div class="sub">
                        ${when ? `<span>Added ${escapeHtml(when)}</span>` : ''}
                        ${size ? `<span>${size}</span>` : ''}
                        ${isCover ? `<span class="tc-cover-badge"><i class="bi bi-image"></i> Cover</span>` : ''}
                    </div>
                </div>
                <div class="ops">
                    <a href="${a.download_url}" target="_blank" rel="noopener" class="tc-icon-btn" title="Open"><i class="bi bi-box-arrow-up-right"></i></a>
                    <button type="button" class="tc-icon-btn tc-att-more" data-att="${a.id}" title="More" aria-haspopup="true" aria-expanded="false"><i class="bi bi-three-dots"></i></button>
                    <div class="tc-att-menu" data-menu-for="${a.id}">
                        <button type="button" class="tc-att-edit" data-att="${a.id}" data-name="${escapeHtml(fileName)}">Edit</button>
                        <button type="button" class="tc-att-comment" data-att="${a.id}" data-name="${escapeHtml(fileName)}">Comment</button>
                        <button type="button" class="tc-att-download" data-url="${escapeHtml(a.download_url || '')}" data-name="${escapeHtml(fileName)}">Download</button>
                        ${coverAction}
                        <button type="button" class="tc-att-remove danger" data-att="${a.id}">Remove</button>
                    </div>
                </div>
            </div>`;
        }).join('') || '<span class="tc-muted">No files</span>';

        const commentsEl = document.getElementById('tc-comments');
        const activity = (data.activity && data.activity.length)
            ? data.activity
            : (data.comments || []);
        // Trello "Hide details" = comments only; show details = full activity
        const feed = hideDetails
            ? activity.filter((a) => (a.type || '') === 'commentCard')
            : activity;
        commentsEl.innerHTML = feed.map((act) => renderActivityItem(act, data.attachments || [])).join('')
            || '<span class="tc-muted">No activity yet</span>';
        hydrateSmartLinks(commentsEl);
        commentsEl.style.display = '';
    }

    function activityWho(act) {
        return act.memberCreator?.fullName || act.memberCreator?.username || 'User';
    }

    function syncLabelsColorblind() {
        const panel = document.getElementById('panel-labels');
        const on = localStorage.getItem(LABEL_CBF_KEY) === '1';
        panel?.classList.toggle('colorblind', on);
        document.getElementById('tc-card-labels')?.classList.toggle('colorblind', on);
        // badges wrapper follows labels CBF for pills only
        const btn = document.getElementById('tc-labels-cbf');
        if (btn) btn.textContent = on ? 'Disable colorblind friendly mode' : 'Enable colorblind friendly mode';
    }

    function openLabelsPanel() {
        closePanels();
        const panel = document.getElementById('panel-labels');
        if (!panel) return;
        panel.classList.add('show');
        anchorPanel(panel);
        document.querySelectorAll('.tc-action[data-panel="labels"]').forEach((b) => b.classList.add('active'));
        showLabelsBrowse();
        renderLabelsList();
        const search = document.getElementById('tc-labels-search');
        if (search) search.value = '';
        setTimeout(() => search?.focus(), 30);
    }

    function formatDueBadge(iso) {
        const d = iso ? new Date(iso) : null;
        if (!d || Number.isNaN(d.getTime())) return '';
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        let h = d.getHours();
        const m = String(d.getMinutes()).padStart(2, '0');
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        if (h === 0) h = 12;
        return `${months[d.getMonth()]} ${d.getDate()}, ${h}:${m} ${ampm}`;
    }

    function dueStatus(card) {
        if (!card?.due) return null;
        if (card.dueComplete) return { cls: 'complete', text: 'Complete' };
        const due = new Date(card.due);
        if (Number.isNaN(due.getTime())) return null;
        const now = Date.now();
        const ms = due.getTime() - now;
        if (ms < 0) return { cls: 'overdue', text: 'Overdue' };
        // Within 24 hours
        if (ms <= 24 * 60 * 60 * 1000) return { cls: 'soon', text: 'Due soon' };
        return null;
    }

    function syncCardBadgesVisibility() {
        const badges = document.getElementById('tc-card-badges');
        const labels = document.getElementById('tc-card-labels');
        const due = document.getElementById('tc-card-due');
        const members = document.getElementById('tc-card-members');
        if (!badges) return;
        const show = !!(labels && !labels.hidden)
            || !!(due && !due.hidden)
            || !!(members && !members.hidden);
        badges.hidden = !show;
        // Trello: action buttons only for things the card doesn't have yet (still in the Add menu)
        const actionLabels = document.getElementById('tc-action-labels');
        const actionDates = document.getElementById('tc-action-dates');
        const actionMembers = document.getElementById('tc-action-members');
        if (actionLabels) actionLabels.hidden = !!(labels && !labels.hidden);
        if (actionDates) actionDates.hidden = !!(due && !due.hidden);
        if (actionMembers) actionMembers.hidden = !!(members && !members.hidden);
    }

    function openMembersPanel() {
        closePanels();
        const panel = document.getElementById('panel-members');
        if (!panel) return;
        panel.classList.add('show');
        anchorPanel(panel);
        document.querySelectorAll('.tc-action[data-panel="members"]').forEach((b) => b.classList.add('active'));
        const search = document.getElementById('tc-members-search');
        if (search) search.value = '';
        renderMembersList();
        setTimeout(() => search?.focus(), 30);
    }

    function renderMembersList() {
        const list = document.getElementById('tc-members');
        if (!list || !state) return;
        const q = (document.getElementById('tc-members-search')?.value || '').trim().toLowerCase();
        const c = state.card || {};
        const active = new Set(c.idMembers || (state.members || []).map((m) => m.id));
        let members = state.board_members || [];
        if (q) {
            members = members.filter((m) =>
                String(m.fullName || '').toLowerCase().includes(q)
                || String(m.username || '').toLowerCase().includes(q)
            );
        }
        if (!members.length) {
            list.innerHTML = '<span class="tc-muted">No members found</span>';
            return;
        }
        list.innerHTML = members.map((m) => {
            const name = m.fullName || m.username || m.id || 'Member';
            const on = active.has(m.id);
            const ini = initials(name);
            const col = avatarColor(name);
            return `<button type="button" class="tc-member-row ${on ? 'active' : ''}" data-member="${m.id}" data-active="${on ? 1 : 0}">
                <span class="av" style="background:${col}">${escapeHtml(ini)}</span>
                <span class="name">${escapeHtml(name)}</span>
                <i class="bi bi-check2 check" aria-hidden="true"></i>
            </button>`;
        }).join('');
    }

    function renderCardMembers() {
        const wrap = document.getElementById('tc-card-members');
        const row = document.getElementById('tc-card-members-row');
        if (!wrap || !row) return;
        const c = state?.card || {};
        const byId = Object.fromEntries((state?.board_members || []).map((m) => [m.id, m]));
        const cardMembers = Array.isArray(state?.members) ? state.members : [];
        const selected = (c.idMembers || []).map((id) =>
            cardMembers.find((m) => m.id === id) || byId[id]
        ).filter(Boolean);
        if (!selected.length) {
            wrap.hidden = true;
            row.innerHTML = '';
            syncCardBadgesVisibility();
            return;
        }
        wrap.hidden = false;
        row.innerHTML = selected.map((m) => {
            const name = m.fullName || m.username || m.id || '?';
            const user = (m.username || '').replace(/^@/, '');
            return `<button type="button" class="tc-card-member" data-member-id="${m.id}"
                data-member-name="${escapeHtml(name)}" data-member-user="${escapeHtml(user)}"
                title="${escapeHtml(name)}" style="background:${avatarColor(name)}">${escapeHtml(initials(name))}</button>`;
        }).join('') + `<button type="button" class="tc-card-members-add" data-open-members title="Add member" aria-label="Add member">+</button>`;
        syncCardBadgesVisibility();
    }

    function renderCardLabels() {
        const wrap = document.getElementById('tc-card-labels');
        const row = document.getElementById('tc-card-labels-row');
        if (!wrap || !row) return;
        const c = state?.card || {};
        const labels = Array.isArray(c.labels) ? c.labels : [];
        // Resolve from board_labels if card.labels missing name/color
        const byId = Object.fromEntries((state?.board_labels || []).map((l) => [l.id, l]));
        const selected = labels.length
            ? labels
            : (c.idLabels || []).map((id) => byId[id]).filter(Boolean);
        if (!selected.length) {
            wrap.hidden = true;
            row.innerHTML = '';
            syncCardBadgesVisibility();
            return;
        }
        wrap.hidden = false;
        row.innerHTML = selected.map((l) => {
            const col = LABEL_COLORS[l.color] || '#9fadbc';
            const name = l.name || '';
            return `<button type="button" class="tc-card-label" data-open-labels data-label-pill="${l.id}"
                style="background-color:${col}" title="${escapeHtml(name || l.color || 'Label')}">${escapeHtml(name)}</button>`;
        }).join('') + `<button type="button" class="tc-card-labels-add" data-open-labels title="Add label" aria-label="Add label">+</button>`;
        syncCardBadgesVisibility();
    }

    function renderCardDue() {
        const wrap = document.getElementById('tc-card-due');
        const whenEl = document.getElementById('tc-card-due-when');
        const badgeEl = document.getElementById('tc-card-due-badge');
        if (!wrap || !whenEl || !badgeEl) return;
        const c = state?.card || {};
        if (!c.due) {
            wrap.hidden = true;
            whenEl.textContent = '';
            badgeEl.hidden = true;
            badgeEl.textContent = '';
            badgeEl.className = 'badge';
            syncCardBadgesVisibility();
            return;
        }
        wrap.hidden = false;
        whenEl.textContent = formatDueBadge(c.due);
        const st = dueStatus(c);
        if (st) {
            badgeEl.hidden = false;
            badgeEl.textContent = st.text;
            badgeEl.className = 'badge ' + st.cls;
        } else {
            badgeEl.hidden = true;
            badgeEl.textContent = '';
            badgeEl.className = 'badge';
        }
        syncCardBadgesVisibility();
    }

    function openDatesPanel() {
        closePanels();
        const panel = document.getElementById('panel-dates');
        if (!panel) return;
        panel.classList.add('show');
        anchorPanel(panel);
        document.querySelectorAll('.tc-action[data-panel="dates"]').forEach((b) => b.classList.add('active'));
        syncDatesPanel(state?.card || {});
        renderCalendar();
    }

    // ── Dates / calendar (Trello-style) ───────────────────────────
    let calYear = new Date().getFullYear();
    let calMonth = new Date().getMonth();
    const MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    function formatMdY(d) {
        if (!(d instanceof Date) || Number.isNaN(d.getTime())) return '';
        return `${d.getMonth() + 1}/${d.getDate()}/${d.getFullYear()}`;
    }
    function parseMdY(str) {
        const m = String(str || '').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (!m) return null;
        const d = new Date(Number(m[3]), Number(m[1]) - 1, Number(m[2]));
        return Number.isNaN(d.getTime()) ? null : d;
    }
    function formatTimeLocal(d) {
        if (!(d instanceof Date) || Number.isNaN(d.getTime())) return '12:00';
        const hh = String(d.getHours()).padStart(2, '0');
        const mm = String(d.getMinutes()).padStart(2, '0');
        return `${hh}:${mm}`;
    }
    function sameDay(a, b) {
        return a && b && a.getFullYear() === b.getFullYear()
            && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }
    function syncDatesPanel(card) {
        const due = card?.due ? new Date(card.due) : null;
        const start = card?.start ? new Date(card.start) : null;
        const dueOk = due && !Number.isNaN(due.getTime());
        const startOk = start && !Number.isNaN(start.getTime());
        const dueOn = document.getElementById('tc-due-on');
        const startOn = document.getElementById('tc-start-on');
        const dueDate = document.getElementById('tc-due-date');
        const dueTime = document.getElementById('tc-due-time');
        const startDate = document.getElementById('tc-start-date');
        const reminder = document.getElementById('tc-due-reminder');
        const dueComplete = document.getElementById('tc-due-complete');
        const hiddenDue = document.getElementById('tc-due');

        if (dueOn) dueOn.checked = !!dueOk;
        if (startOn) startOn.checked = !!startOk;
        if (dueDate) {
            dueDate.value = dueOk ? formatMdY(due) : '';
            dueDate.disabled = !dueOk;
        }
        if (dueTime) {
            dueTime.value = dueOk ? formatTimeLocal(due) : '12:00';
            dueTime.disabled = !dueOk;
        }
        if (startDate) {
            startDate.value = startOk ? formatMdY(start) : '';
            startDate.disabled = !startOk;
        }
        if (reminder) {
            const r = card?.dueReminder;
            reminder.value = (r === null || r === undefined) ? '1440' : String(r);
        }
        if (dueComplete) dueComplete.checked = !!card?.dueComplete;
        if (hiddenDue) hiddenDue.value = dueOk ? toLocalInput(card.due) : '';

        const focus = dueOk ? due : (startOk ? start : new Date());
        calYear = focus.getFullYear();
        calMonth = focus.getMonth();
        renderCalendar();
    }
    function renderCalendar() {
        const label = document.getElementById('tc-cal-month');
        const grid = document.getElementById('tc-cal-grid');
        if (!label || !grid) return;
        label.textContent = `${MONTH_NAMES[calMonth]} ${calYear}`;
        const dueDate = parseMdY(document.getElementById('tc-due-date')?.value);
        const startDate = parseMdY(document.getElementById('tc-start-date')?.value);
        const dueOn = !!document.getElementById('tc-due-on')?.checked;
        const startOn = !!document.getElementById('tc-start-on')?.checked;

        const first = new Date(calYear, calMonth, 1);
        const startPad = first.getDay();
        const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
        const prevDays = new Date(calYear, calMonth, 0).getDate();
        const cells = [];
        for (let i = 0; i < 42; i++) {
            let y = calYear, m = calMonth, day, muted = false;
            if (i < startPad) {
                day = prevDays - startPad + i + 1;
                m = calMonth - 1;
                if (m < 0) { m = 11; y--; }
                muted = true;
            } else if (i >= startPad + daysInMonth) {
                day = i - (startPad + daysInMonth) + 1;
                m = calMonth + 1;
                if (m > 11) { m = 0; y++; }
                muted = true;
            } else {
                day = i - startPad + 1;
            }
            const d = new Date(y, m, day);
            const selected = dueOn && sameDay(d, dueDate);
            const isStart = startOn && sameDay(d, startDate);
            cells.push(`<button type="button" class="${muted ? 'muted' : ''} ${selected ? 'selected' : ''} ${isStart ? 'start-mark' : ''}"
                data-y="${y}" data-m="${m}" data-d="${day}">${day}</button>`);
        }
        grid.innerHTML = cells.join('');
        grid.querySelectorAll('button[data-y]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const d = new Date(Number(btn.dataset.y), Number(btn.dataset.m), Number(btn.dataset.d));
                const dueOnEl = document.getElementById('tc-due-on');
                const dueDateEl = document.getElementById('tc-due-date');
                const dueTimeEl = document.getElementById('tc-due-time');
                if (dueOnEl) dueOnEl.checked = true;
                if (dueDateEl) { dueDateEl.disabled = false; dueDateEl.value = formatMdY(d); }
                if (dueTimeEl) dueTimeEl.disabled = false;
                calYear = d.getFullYear();
                calMonth = d.getMonth();
                renderCalendar();
            });
        });
    }

    function showLabelsBrowse() {
        const panel = document.getElementById('panel-labels');
        panel?.classList.remove('is-form');
        labelFormMode = null;
        editingLabelId = null;
        const title = document.getElementById('tc-labels-title');
        if (title) title.textContent = 'Labels';
    }

    function showLabelsForm(mode, label = null) {
        const panel = document.getElementById('panel-labels');
        panel?.classList.add('is-form');
        labelFormMode = mode;
        editingLabelId = label?.id || null;
        const title = document.getElementById('tc-labels-title');
        if (title) title.textContent = mode === 'edit' ? 'Edit label' : 'Create label';
        const nameEl = document.getElementById('tc-label-name');
        if (nameEl) nameEl.value = label?.name || '';
        selectedLabelColor = label?.color && LABEL_COLORS[label.color] ? label.color : 'green';
        const saveBtn = document.getElementById('tc-label-save');
        if (saveBtn) saveBtn.textContent = mode === 'edit' ? 'Save' : 'Create';
        renderLabelColorPicker();
        setTimeout(() => nameEl?.focus(), 30);
    }

    function renderLabelColorPicker() {
        const wrap = document.getElementById('tc-label-colors');
        if (!wrap) return;
        wrap.innerHTML = LABEL_COLOR_KEYS.map((key) =>
            `<button type="button" data-color="${key}" class="${key === selectedLabelColor ? 'selected' : ''}"
                style="background:${LABEL_COLORS[key]}" title="${key}" aria-label="${key}"></button>`
        ).join('');
        wrap.querySelectorAll('button[data-color]').forEach((btn) => {
            btn.addEventListener('click', () => {
                selectedLabelColor = btn.dataset.color;
                renderLabelColorPicker();
            });
        });
    }

    function renderLabelsList() {
        const list = document.getElementById('tc-labels');
        if (!list || !state) return;
        const q = (document.getElementById('tc-labels-search')?.value || '').trim().toLowerCase();
        const c = state.card || {};
        const activeLabels = new Set((c.idLabels || (c.labels || []).map((l) => l.id)));
        let labels = state.board_labels || [];
        if (q) {
            labels = labels.filter((l) =>
                String(l.name || '').toLowerCase().includes(q)
                || String(l.color || '').toLowerCase().includes(q)
            );
        }
        if (!labels.length) {
            list.innerHTML = '<span class="tc-muted">No labels found</span>';
            return;
        }
        list.innerHTML = labels.map((l) => {
            const col = LABEL_COLORS[l.color] || '#9fadbc';
            const on = activeLabels.has(l.id);
            const name = l.name || '';
            return `<div class="tc-label-row">
                <input type="checkbox" class="tc-label-check" data-label="${l.id}" data-active="${on ? 1 : 0}" ${on ? 'checked' : ''} aria-label="Toggle label">
                <button type="button" class="tc-label-bar" data-label="${l.id}" data-active="${on ? 1 : 0}" style="background-color:${col}">${escapeHtml(name)}</button>
                <button type="button" class="tc-label-edit" data-edit-label="${l.id}" title="Edit" aria-label="Edit label"><i class="bi bi-pencil-square"></i></button>
            </div>`;
        }).join('');
    }

    function renderReactionChips(actionId) {
        const rows = state?.reactions?.[actionId] || [];
        return rows.map((r) => {
            const emoji = r.emoji?.native || r.native || '';
            const count = r.count ?? 1;
            if (!emoji) return '';
            return `<span class="tc-reaction-chip" title="${escapeHtml(r.emoji?.shortName || '')}">${emoji}<span class="n">${count}</span></span>`;
        }).filter(Boolean).join('');
    }

    function renderActivityItem(act, attachments) {
        const type = act.type || '';
        const who = activityWho(act);
        const when = relativeWhen(act.date);
        const avatar = `<div class="tc-avatar" style="background:${avatarColor(who)}">${escapeHtml(initials(who))}</div>`;

        if (type === 'commentCard') {
            const text = act.data?.text || '';
            const authorId = act.memberCreator?.id || '';
            const isMine = !!(meId && authorId && meId === authorId);
            const linkMatch = String(text).match(/https?:\/\/[^\s<>"')\]]+/i);
            const linkUrl = linkMatch ? linkMatch[0].replace(/[.,;:!?)]+$/, '') : '';
            const chips = renderReactionChips(act.id);
            const textActions = [];
            if (isMine) {
                textActions.push(`<button type="button" class="tc-edit-comment" data-action="${act.id}">Edit</button>`);
                textActions.push(`<button type="button" class="tc-del-comment" data-action="${act.id}">Delete</button>`);
            } else {
                textActions.push(`<button type="button" class="tc-reply">Reply</button>`);
                if (linkUrl) {
                    textActions.push(`<button type="button" class="tc-link-attach" data-url="${escapeHtml(linkUrl)}">Add link as attachment</button>`);
                }
            }
            const reactBtn = `<button type="button" class="tc-react" title="Add reaction" aria-label="Add reaction"><i class="bi bi-emoji-smile"></i><span class="plus">+</span></button>`;
            const links = `${chips}${reactBtn}${textActions.length ? textActions.join('<span class="sep">·</span>') : ''}`;
            return `<div class="tc-comment" data-action="${act.id}">
                ${avatar}
                <div class="tc-comment-body">
                    <div class="tc-comment-meta">
                        <strong>${escapeHtml(who)}</strong>
                        <span class="when">${escapeHtml(when)}</span>
                    </div>
                    <div class="tc-bubble">${renderMarkdown(text, { smart: true })}</div>
                    <div class="tc-comment-links">${links}</div>
                </div>
            </div>`;
        }

        let line = '';
        let media = '';

        if (type === 'addAttachmentToCard') {
            const att = act.data?.attachment || {};
            const name = att.name || att.fileName || 'a file';
            const attId = att.id || '';
            const local = attachments.find((a) => a.id === attId);
            const href = local?.download_url || att.url || '#';
            const preview = local?.preview_url || '';
            line = `<strong>${escapeHtml(who)}</strong> attached <a href="${escapeHtml(href)}" target="_blank" rel="noopener">${escapeHtml(name)}</a> to this card`;
            if (preview) media = `<img class="tc-activity-img" data-att="${escapeHtml(attId)}" src="${escapeHtml(preview)}" alt="${escapeHtml(name)}" loading="lazy">`;
        } else if (type === 'deleteAttachmentFromCard') {
            const name = act.data?.attachment?.name || 'an attachment';
            line = `<strong>${escapeHtml(who)}</strong> deleted <strong>${escapeHtml(name)}</strong> from this card`;
        } else if (type === 'createCard' || type === 'copyCard') {
            const listName = act.data?.list?.name || act.data?.card?.idList || 'a list';
            const verb = type === 'copyCard' ? 'copied this card to' : 'added this card to';
            line = `<strong>${escapeHtml(who)}</strong> ${verb} <strong>${escapeHtml(listName)}</strong>`;
        } else if (type === 'addMemberToCard') {
            const member = act.data?.member?.name || act.data?.member?.fullName || '';
            if (member && member !== who) {
                line = `<strong>${escapeHtml(who)}</strong> added <strong>${escapeHtml(member)}</strong> to this card`;
            } else {
                line = `<strong>${escapeHtml(who)}</strong> joined this card`;
            }
        } else if (type === 'removeMemberFromCard') {
            const member = act.data?.member?.name || act.data?.member?.fullName || 'a member';
            line = `<strong>${escapeHtml(who)}</strong> removed <strong>${escapeHtml(member)}</strong> from this card`;
        } else if (type === 'updateCard') {
            // Trello only shows these card updates; position, description, cover, rename… are hidden.
            const d = act.data || {};
            const old = d.old || {};
            const has = (k) => Object.prototype.hasOwnProperty.call(old, k);
            const dueText = (iso) => {
                const dt = new Date(iso);
                if (Number.isNaN(dt.getTime())) return '';
                const opts = { month: 'short', day: 'numeric' };
                if (dt.getFullYear() !== new Date().getFullYear()) opts.year = 'numeric';
                return `${dt.toLocaleDateString('en-US', opts)} at ${dt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}`;
            };
            if (d.listBefore && d.listAfter) {
                line = `<strong>${escapeHtml(who)}</strong> moved this card from <strong>${escapeHtml(d.listBefore.name || '')}</strong> to <strong>${escapeHtml(d.listAfter.name || '')}</strong>`;
            } else if (has('closed')) {
                line = d.card?.closed
                    ? `<strong>${escapeHtml(who)}</strong> archived this card`
                    : `<strong>${escapeHtml(who)}</strong> sent this card to the board`;
            } else if (has('dueComplete')) {
                line = `<strong>${escapeHtml(who)}</strong> marked this card as ${d.card?.dueComplete ? 'complete' : 'incomplete'}`;
            } else if (has('due')) {
                const due = d.card?.due;
                if (!due) line = `<strong>${escapeHtml(who)}</strong> removed the due date from this card`;
                else if (!old.due) line = `<strong>${escapeHtml(who)}</strong> set this card to be due ${escapeHtml(dueText(due))}`;
                else line = `<strong>${escapeHtml(who)}</strong> changed the due date of this card to ${escapeHtml(dueText(due))}`;
            } else if (has('start')) {
                const start = d.card?.start;
                if (!start) line = `<strong>${escapeHtml(who)}</strong> removed the start date from this card`;
                else line = `<strong>${escapeHtml(who)}</strong> set the start date of this card to ${escapeHtml(dueText(start))}`;
            } else {
                return '';
            }
        } else if (type === 'addChecklistToCard') {
            const name = act.data?.checklist?.name || 'a checklist';
            line = `<strong>${escapeHtml(who)}</strong> added ${escapeHtml(name)} to this card`;
        } else if (type === 'removeChecklistFromCard') {
            const name = act.data?.checklist?.name || 'a checklist';
            line = `<strong>${escapeHtml(who)}</strong> removed ${escapeHtml(name)} from this card`;
        } else if (type === 'createCheckItem') {
            const name = act.data?.checkItem?.name || 'an item';
            line = `<strong>${escapeHtml(who)}</strong> added <strong>${escapeHtml(name)}</strong> to ${escapeHtml(act.data?.checklist?.name || 'checklist')}`;
        } else if (type === 'updateCheckItemStateOnCard') {
            const name = act.data?.checkItem?.name || 'an item';
            const done = (act.data?.checkItem?.state || '') === 'complete';
            line = `<strong>${escapeHtml(who)}</strong> marked <strong>${escapeHtml(name)}</strong> ${done ? 'complete' : 'incomplete'}`;
        } else if (type === 'moveCardToBoard' || type === 'moveCardFromBoard') {
            const boardName = act.data?.boardTarget?.name || act.data?.board?.name || 'another board';
            line = `<strong>${escapeHtml(who)}</strong> moved this card to <strong>${escapeHtml(boardName)}</strong>`;
        } else {
            line = `<strong>${escapeHtml(who)}</strong> ${escapeHtml(type.replace(/([A-Z])/g, ' $1').toLowerCase())}`;
        }

        return `<div class="tc-comment tc-activity" data-action="${act.id}">
            ${avatar}
            <div class="tc-comment-body">
                <div class="tc-activity-line">${line}</div>
                <span class="when">${escapeHtml(when)}</span>
                ${media}
                ${type === 'addAttachmentToCard' ? `<div class="tc-comment-links"><button type="button" class="tc-reply">Reply</button></div>` : ''}
            </div>
        </div>`;
    }

    async function reloadCard() {
        if (!currentCardId) return;
        const data = await api(cardUrl(currentCardId));
        state = data;
        renderCard(data);
        // Update board card title if visible
        const el = board.querySelector(`.trello-card[data-card-id="${currentCardId}"] .trello-card-title`);
        if (el && data.card?.name) el.textContent = data.card.name;
    }

    // ── Instant (optimistic) updates ──
    // Trello round trips take seconds, so actions change `state` and re-render right away,
    // save in the background, then quietly re-sync with Trello. A failed save re-syncs too,
    // which puts the real data back.
    let pendingWrites = 0;
    let syncTimer = null;
    let syncSeq = 0;

    function rerenderCard() {
        if (state) renderCard(state);
    }

    function syncCardSoon(delay = 300) {
        clearTimeout(syncTimer);
        const cardId = currentCardId;
        syncTimer = setTimeout(async () => {
            if (!cardId || currentCardId !== cardId || pendingWrites > 0) return;
            const seq = ++syncSeq;
            try {
                const data = await api(cardUrl(cardId));
                // Ignore if the card changed, a newer sync started, or the user made another change meanwhile
                if (seq !== syncSeq || currentCardId !== cardId || pendingWrites > 0) return;
                state = data;
                renderCard(data);
                const el = board.querySelector(`.trello-card[data-card-id="${cardId}"] .trello-card-title`);
                if (el && data.card?.name) el.textContent = data.card.name;
            } catch (err) { /* keep the optimistic view; the next sync will fix it */ }
        }, delay);
    }

    /** Apply a change locally now, save it in the background, then re-sync. */
    async function optimistic(apply, write, errMsg = 'Could not save to Trello', onError = null) {
        if (!state) return;
        try { apply(); } catch (err) { console.error(err); }
        rerenderCard();
        pendingWrites++;
        syncSeq++; // drop any sync already in flight (it would show the old data)
        try {
            await write();
        } catch (err) {
            toastError(err.message || errMsg);
            if (onError) onError(err);
        } finally {
            pendingWrites--;
            syncCardSoon();
        }
    }

    /** Add/remove a member on the open card in local state (members panel, avatars, member popover). */
    function setCardMemberLocal(memberId, active) {
        const c = state.card;
        const ids = new Set(c.idMembers || []);
        if (active) ids.add(memberId);
        else ids.delete(memberId);
        c.idMembers = [...ids];
        const members = Array.isArray(state.members) ? state.members : [];
        if (active && !members.some((m) => m.id === memberId)) {
            const m = (state.board_members || []).find((x) => x.id === memberId);
            if (m) members.push(m);
        }
        state.members = members.filter((m) => ids.has(m.id));
    }

    /** The connected Trello member, for comments/actions shown before Trello confirms them. */
    function meMember() {
        const m = (state?.board_members || []).find((x) => x.id === meId) || {};
        return { id: meId, fullName: m.fullName || 'You', username: m.username || '', initials: m.initials || '' };
    }

    // Name blur save
    document.getElementById('tc-complete-toggle')?.addEventListener('click', () => {
        if (!state?.card?.id) return;
        setCardComplete(state.card.id, !state.card.dueComplete);
    });

    document.getElementById('tc-name').addEventListener('change', async (e) => {
        if (!currentCardId) return;
        const name = e.target.value;
        const cardId = currentCardId;
        await optimistic(() => {
            state.card.name = name;
            const el = board.querySelector(`.trello-card[data-card-id="${cardId}"] .trello-card-title`);
            if (el) el.textContent = name;
        }, () => api(cardUrl(cardId), { method: 'PUT', json: { name } }), 'Could not rename card');
    });

    document.getElementById('tc-edit-desc')?.addEventListener('click', () => setDescMode(true));
    document.getElementById('tc-desc-view')?.addEventListener('click', (e) => {
        if (e.target.closest('a, img')) return;
        setDescMode(true);
    });
    document.getElementById('tc-cancel-desc')?.addEventListener('click', () => {
        closeDescMenus();
        if (state?.card) renderDesc(state.card.desc || '');
        else setDescMode(false);
    });

    const descEditor = document.getElementById('tc-desc-editor');
    const descVisual = document.getElementById('tc-desc-visual');
    const cmtEditor = document.getElementById('tc-cmt-editor');
    const cmtVisual = document.getElementById('tc-cmt-visual');
    const commentComposer = document.getElementById('tc-comment-composer');
    const commentText = document.getElementById('tc-comment-text');
    let activeVisual = descVisual;
    let activeEditor = descEditor;

    function setActiveRich(visual) {
        if (!visual) return;
        activeVisual = visual;
        activeEditor = visual.closest('.tc-rich-editor') || descEditor;
        if (visual === cmtVisual) commentComposer?.classList.add('is-open');
    }

    function closeDescMenus() {
        document.querySelectorAll('.tc-dd').forEach((d) => d.classList.remove('show'));
        document.querySelectorAll('.tc-tb.open').forEach((b) => b.classList.remove('open'));
    }

    function focusDesc() {
        (activeVisual || descVisual)?.focus();
    }

    function runDescCmd(cmd) {
        focusDesc();
        if (cmd === 'code') {
            const sel = window.getSelection()?.toString() || 'code';
            document.execCommand('insertHTML', false, `<code>${escapeHtml(sel)}</code>`);
            return;
        }
        document.execCommand(cmd, false, null);
    }

    function applyDescBlock(block) {
        focusDesc();
        document.execCommand('formatBlock', false, block === 'p' ? 'p' : block);
    }

    function commentMarkdown() {
        if (!cmtVisual) return '';
        return editorHtmlToMarkdown(cmtVisual).trim();
    }

    // Trello: Save stays disabled while the comment box is empty.
    function syncCommentSave() {
        const saveBtn = document.getElementById('tc-add-comment');
        if (!saveBtn || !cmtVisual) return;
        const hasText = cmtVisual.textContent.trim() !== '' || !!cmtVisual.querySelector('img, .tc-mention-pill');
        saveBtn.disabled = !hasText;
    }
    // Editing an existing comment: only send it when the user changed something
    let cmtDirty = false;
    if (cmtVisual) {
        new MutationObserver(syncCommentSave).observe(cmtVisual, { childList: true, subtree: true, characterData: true });
        cmtVisual.addEventListener('input', () => {
            cmtDirty = true;
            syncCommentSave();
        });
    }

    function clearCommentEditor() {
        if (cmtVisual) cmtVisual.innerHTML = '';
        if (commentText) commentText.value = '';
        editingCommentId = null;
        const saveBtn = document.getElementById('tc-add-comment');
        if (saveBtn) saveBtn.textContent = 'Save';
        commentComposer?.classList.remove('is-open');
        hideMentionMenu();
        hideCodeBar();
        hideEditorLinkBar();
        hideEditorImageBar();
    }

    function startEditComment(act) {
        if (!act || !cmtVisual) return;
        editingCommentId = act.id || null;
        focusCommentEditor();
        const text = act.data?.text || '';
        // WYSIWYG like the description editor
        cmtVisual.innerHTML = renderMarkdown(text);
        cmtDirty = false;
        placeCaretAtEnd(cmtVisual);
        const saveBtn = document.getElementById('tc-add-comment');
        if (saveBtn) saveBtn.textContent = 'Save';
    }

    function placeCaretAtEnd(el) {
        if (!el) return;
        el.focus();
        const sel = window.getSelection();
        if (!sel) return;
        const range = document.createRange();
        range.selectNodeContents(el);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
    }

    function mentionPillHtml(username) {
        const handle = '@' + String(username || '').replace(/^@/, '');
        if (handle === '@') return '';
        return `<span class="tc-mention-pill" data-mention="${escapeHtml(handle)}" contenteditable="false">${escapeHtml(handle)}</span>`;
    }

    /** Trello Reply: open composer + prefill author + @mentions from that activity. */
    function startReplyTo(act) {
        focusCommentEditor();
        if (!cmtVisual || !act) return;
        const handles = [];
        const addHandle = (raw) => {
            const u = String(raw || '').replace(/^@/, '').trim();
            if (!u || u.toLowerCase() === 'board') return;
            if (handles.some((h) => h.toLowerCase() === u.toLowerCase())) return;
            handles.push(u);
        };
        addHandle(act.memberCreator?.username);
        const text = act.data?.text || '';
        const re = /@([a-zA-Z0-9_]+)/g;
        let m;
        while ((m = re.exec(text))) addHandle(m[1]);
        if (!handles.length) {
            placeCaretAtEnd(cmtVisual);
            return;
        }
        cmtVisual.innerHTML = handles.map((h) => mentionPillHtml(h)).filter(Boolean).join('&nbsp;') + '&nbsp;';
        placeCaretAtEnd(cmtVisual);
    }

    function syncWatchCheckbox() {
        const el = document.getElementById('tc-cmt-watch');
        if (el) el.checked = !!state?.card?.subscribed;
    }

    function focusCommentEditor() {
        setActiveRich(cmtVisual);
        commentComposer?.classList.add('is-open');
        syncWatchCheckbox();
        cmtVisual?.focus();
        cmtVisual?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function openDescLinkPop() {
        const sel = window.getSelection()?.toString() || '';
        const urlEl = document.getElementById('tc-link-url');
        const textEl = document.getElementById('tc-link-text');
        if (urlEl) urlEl.value = '';
        if (textEl) textEl.value = sel;
        openPop('tc-link-pop');
        setTimeout(() => urlEl?.focus(), 50);
    }

    // Last caret inside a rich editor — restored when inserting after focus moved to a search box.
    let lastRichRange = null;
    document.addEventListener('selectionchange', () => {
        const sel = window.getSelection();
        if (!sel?.rangeCount) return;
        const range = sel.getRangeAt(0);
        const host = range.startContainer.nodeType === 1 ? range.startContainer : range.startContainer.parentElement;
        if (host?.closest?.('.tc-rich-visual')) lastRichRange = range.cloneRange();
    });

    function insertDescHtml(html) {
        focusDesc();
        const root = activeVisual || descVisual;
        if (lastRichRange && root?.contains(lastRichRange.startContainer)) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(lastRichRange);
        }
        document.execCommand('insertHTML', false, html);
    }

    function descEditorFocused() {
        const el = document.activeElement;
        const inDesc = !!(descVisual && (el === descVisual || descVisual.contains(el)));
        const inCmt = !!(cmtVisual && (el === cmtVisual || cmtVisual.contains(el)));
        if (inDesc || inCmt) {
            if (inCmt) setActiveRich(cmtVisual);
            else if (inDesc) setActiveRich(descVisual);
            return true;
        }
        // Keep shortcuts working while interacting with this editor's toolbar
        return !!(activeVisual && (
            (activeVisual === descVisual && descEditing) || activeVisual === cmtVisual
        ) && el?.closest?.('.tc-rich-editor'));
    }

    // ── @Mention autocomplete (Trello-style) ─────────────────────
    const mentionMenu = document.getElementById('tc-mention-menu');
    if (mentionMenu && mentionMenu.parentElement !== document.body) {
        document.body.appendChild(mentionMenu);
    }
    let mentionCtx = null;
    let mentionItems = [];
    let mentionIndex = 0;

    function mentionMembers() {
        const list = state?.board_members || state?.members || [];
        return list.map((m) => ({
            id: m.id || '',
            name: m.fullName || m.username || m.id || 'Member',
            username: (m.username || '').replace(/^@/, '') || String(m.fullName || m.id || 'user').replace(/\s+/g, '').toLowerCase(),
            initials: initials(m.fullName || m.username || m.id || '?'),
            color: avatarColor(m.fullName || m.username || m.id || '?'),
            board: false,
        }));
    }

    function getMentionContext() {
        const sel = window.getSelection();
        if (!sel?.rangeCount || !sel.isCollapsed) return null;
        const node = sel.anchorNode;
        const root = activeVisual || descVisual;
        if (!node || node.nodeType !== Node.TEXT_NODE || !root?.contains(node)) return null;
        const offset = sel.anchorOffset;
        const before = String(node.textContent || '').slice(0, offset);
        const m = before.match(/(^|[\s([{\u00a0])@([a-zA-Z0-9_]*)$/);
        if (!m) return null;
        const query = m[2] || '';
        const atPos = offset - query.length - 1;
        return { node, query, start: atPos, end: offset };
    }

    function hideMentionMenu() {
        mentionCtx = null;
        mentionItems = [];
        mentionIndex = 0;
        if (mentionMenu) {
            mentionMenu.classList.remove('show');
            mentionMenu.hidden = true;
            mentionMenu.innerHTML = '';
        }
    }

    function positionMentionMenu() {
        if (!mentionMenu || !mentionCtx) return;
        const range = document.createRange();
        try {
            range.setStart(mentionCtx.node, mentionCtx.start);
            range.setEnd(mentionCtx.node, mentionCtx.end);
        } catch (_) {
            return;
        }
        const rect = range.getBoundingClientRect();
        const top = rect.bottom + 6;
        let left = rect.left;
        const maxLeft = window.innerWidth - 300;
        if (left > maxLeft) left = Math.max(8, maxLeft);
        mentionMenu.style.top = Math.min(top, window.innerHeight - 120) + 'px';
        mentionMenu.style.left = Math.max(8, left) + 'px';
    }

    function renderMentionMenu() {
        if (!mentionMenu || !mentionCtx) return;
        const q = mentionCtx.query.toLowerCase();
        const members = mentionMembers().filter((m) => {
            if (!q) return true;
            return m.name.toLowerCase().includes(q)
                || m.username.toLowerCase().includes(q)
                || ('@' + m.username).includes(q);
        });
        const boardOpt = {
            id: '__board__',
            name: 'All members on the board',
            username: 'board',
            initials: '',
            color: '#44546f',
            board: true,
        };
        const showBoard = !q || 'board'.includes(q) || 'all members'.includes(q) || 'members'.includes(q);
        mentionItems = showBoard ? [boardOpt, ...members] : members;
        if (!mentionItems.length) {
            mentionMenu.innerHTML = '<div class="empty">No matches</div>';
            mentionMenu.hidden = false;
            mentionMenu.classList.add('show');
            positionMentionMenu();
            return;
        }
        if (mentionIndex >= mentionItems.length) mentionIndex = 0;
        mentionMenu.innerHTML = mentionItems.map((m, i) => {
            const av = m.board
                ? `<span class="av board"><i class="bi bi-people"></i></span>`
                : `<span class="av" style="background:${m.color}">${escapeHtml(m.initials)}</span>`;
            return `<button type="button" role="option" data-idx="${i}" class="${i === mentionIndex ? 'active' : ''}">
                ${av}
                <span class="meta">
                    <span class="name">${escapeHtml(m.name)}</span>
                    <span class="handle">@${escapeHtml(m.username)}</span>
                </span>
            </button>`;
        }).join('');
        mentionMenu.querySelectorAll('button[data-idx]').forEach((btn) => {
            btn.addEventListener('mousedown', (e) => e.preventDefault());
            btn.addEventListener('click', () => {
                const item = mentionItems[Number(btn.dataset.idx)];
                if (item) insertMention(item);
            });
        });
        mentionMenu.hidden = false;
        mentionMenu.classList.add('show');
        positionMentionMenu();
        mentionMenu.querySelector('button.active')?.scrollIntoView({ block: 'nearest' });
    }

    function refreshMentionMenu() {
        if (!descEditorFocused()) {
            hideMentionMenu();
            return;
        }
        const ctx = getMentionContext();
        if (!ctx) {
            hideMentionMenu();
            return;
        }
        mentionCtx = ctx;
        renderMentionMenu();
    }

    function insertMention(item) {
        if (!mentionCtx || !item) return;
        const { node, start, end } = mentionCtx;
        try {
            const range = document.createRange();
            range.setStart(node, start);
            range.setEnd(node, end);
            range.deleteContents();
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        } catch (_) { /* ignore */ }
        const handle = '@' + item.username;
        insertDescHtml(
            `<span class="tc-mention-pill" data-mention="${escapeHtml(handle)}" contenteditable="false">${escapeHtml(handle)}</span>&nbsp;`
        );
        hideMentionMenu();
        focusDesc();
    }

    function openMentionPicker() {
        focusDesc();
        insertDescHtml('@');
        // Let DOM update then open menu
        setTimeout(refreshMentionMenu, 0);
    }

    // ── Emoji picker (Trello-style reactions / insert) ────────────
    const emojiPop = document.getElementById('tc-emoji-pop');
    if (emojiPop && emojiPop.parentElement !== document.body) {
        document.body.appendChild(emojiPop);
    }
    const EMOJI_FREQ_KEY = 'tc-emoji-freq';
    const EMOJI_SKIN_KEY = 'tc-emoji-skin';
    const EMOJI_SKINS = ['', '\u{1F3FB}', '\u{1F3FC}', '\u{1F3FD}', '\u{1F3FE}', '\u{1F3FF}'];
    const EMOJI_CATS = [
        { id: 'recent', icon: '🕒', title: 'Frequently used' },
        { id: 'smileys', icon: '😀', title: 'Smileys & People' },
        { id: 'animals', icon: '🐶', title: 'Animals & Nature' },
        { id: 'food', icon: '🍎', title: 'Food & Drink' },
        { id: 'activity', icon: '⚽', title: 'Activity' },
        { id: 'travel', icon: '🚗', title: 'Travel & Places' },
        { id: 'objects', icon: '💡', title: 'Objects' },
        { id: 'symbols', icon: '💯', title: 'Symbols' },
        { id: 'flags', icon: '🏳️', title: 'Flags' },
    ];
    const EMOJI_DATA = {
        smileys: '😀 😃 😄 😁 😆 😅 🤣 😂 🙂 🙃 😉 😊 😇 🥰 😍 🤩 😘 😗 ☺️ 😚 😙 🥲 😋 😛 😜 🤪 😝 🤑 🤗 🤭 🤫 🤔 🤐 🤨 😐 😑 😶 😏 😒 🙄 😬 🤥 😌 😔 😪 🤤 😴 😷 🤒 🤕 🤢 🤮 🤧 🥵 🥶 🥴 😵 🤯 🤠 🥳 🥸 😎 🤓 🧐 😕 😟 🙁 ☹️ 😮 😯 😲 😳 🥺 😦 😧 😨 😰 😥 😢 😭 😱 😖 😣 😞 😓 😩 😫 🥱 😤 😡 😠 🤬 😈 👿 💀 ☠️ 💩 🤡 👹 👺 👻 👽 👾 🤖 😺 😸 😹 😻 😼 😽 🙀 😿 😾 👋 🤚 🖐️ ✋ 🖖 👌 🤌 🤏 ✌️ 🤞 🤟 🤘 🤙 👈 👉 👆 🖕 👇 ☝️ 👍 👎 ✊ 👊 🤛 🤜 👏 🙌 👐 🤲 🤝 🙏 ✍️ 💅 🤳 💪 🦾 🦿 🦵 🦶 👂 🦻 👃 🧠 🫀 🫁 🦷 🦴 👀 👁️ 👅 👄 💋 🩸'.split(/\s+/),
        animals: '🐶 🐱 🐭 🐹 🐰 🦊 🐻 🐼 🐻‍️ 🐨 🐯 🦁 🐮 🐷 🐸 🐵 🙈 🙉 🙊 🐒 🐔 🐧 🐦 🐤 🐣 🐥 🦆 🦅 🦉 🦇 🐺 🐗 🐴 🦄 🐝 🪱 🐛 🦋 🐌 🐞 🐜 🪰 🪲 🪳 🦟 🦗 🕷️ 🦂 🐢 🐍 🦎 🦖 🦕 🐙 🦑 🦐 🦞 🦀 🐡 🐠 🐟 🐬 🐳 🐋 🦈 🐊 🐅 🐆 🦓 🦍 🦧 🦣 🐘 🦛 🦏 🐪 🐫 🦒 🦘 🦬 🐃 🐂 🐄 🐎 🐖 🐏 🐑 🦙 🐐 🦌 🐕 🐩 🦮 🐕‍️ 🐈 🐈‍️ 🪶 🐓 🦃 🦤 🦚 🦜 🦢 🦩 🕊️ 🐇 🦝 🦨 🦡 🦫 🦦 🦥 🐁 🐀 🐿️ 🦔 🐾 🐉 🐲 🌵 🎄 🌲 🌳 🌴 🪵 🌱 🌿 ☘️ 🍀 🎍 🪴 🎋 🍃 🍂 🍁 🍄 🐚 🪨 💐 🌷 🌹 🥀 🌺 🌸 🌼 🌻 🌞 🌝 🌛 🌜 🌚 🌕 🌖 🌗 🌘 🌑 🌒 🌓 🌔 🌙 🌎 🌍 🌏 🪐 💫 ⭐ 🌟 ✨ ⚡ ☄️ 💥 🔥 🌪️ 🌈 ☀️ 🌤️ ⛅ 🌥️ ☁️ 🌦️ 🌧️ ⛈️ 🌩️ 🌨️ ❄️ ☃️ ⛄ 🌬️ 💨 💧 💦 ☔ ☂️ 🌊 🌫️'.split(/\s+/),
        food: '🍎 🍐 🍊 🍋 🍌 🍉 🍇 🍓 🫐 🍈 🍒 🍑 🥭 🍍 🥥 🥝 🍅 🍆 🥑 🥦 🥬 🥒 🌶️ 🫑 🌽 🥕 🫒 🧄 🧅 🥔 🍠 🥐 🥯 🍞 🥖 🥨 🧀 🥚 🍳 🧈 🥞 🧇 🥓 🥩 🍗 🍖 🦴 🌭 🍔 🍟 🍕 🫓 🥪 🥙 🧆 🌮 🌯 🫔 🥗 🥘 🫕 🥫 🍝 🍜 🍲 🍛 🍣 🍱 🥟 🦪 🍤 🍙 🍚 🍘 🍥 🥠 🥮 🍢 🍡 🍧 🍨 🍦 🥧 🧁 🍰 🎂 🍮 🍭 🍬 🍫 🍿 🍩 🍪 🌰 🥜 🍯 🥛 🍼 ☕ 🫖 🍵 🧃 🥤 🧋 🍶 🍺 🍻 🥂 🍷 🥃 🍸 🍹 🧉 🍾 🧊 🥄 🍴 🍽️ 🥣 🥡 🥢 🧂'.split(/\s+/),
        activity: '⚽ 🏀 🏈 ⚾ 🥎 🎾 🏐 🏉 🥏 🎱 🪀 🏓 🏸 🏒 🏑 🥍 🏏 🪃 🥅 ⛳ 🪁 🏹 🎣 🤿 🥊 🥋 🎽 🛹 🛼 🛷 ⛸️ 🥌 🎿 ⛷️ 🏂 🪂 🏋️ 🤼 🤸 ⛹️ 🤺 핸드 🏌️ 🏇 🧘 🏄 🏊 🤽 🚣 🧗 🚴 🚵 🎪 🎭 🩰 🎨 🎬 🎤 🎧 🎼 🎹 🥁 🪘 🎷 🎺 🪗 🎸 🪕 🎻 🎲 ♟️ 🎯 🎳 🎮 🎰 🧩'.split(/\s+/),
        travel: '🚗 🚕 🚙 🚌 🚎 🏎️ 🚓 🚑 🚒 🚐 🛻 🚚 🚛 🚜 🦯 🦽 🦼 🛴 🚲 🛵 🏍️ 🚨 🚔 🚍 🚘 🚖 🚡 🚠 🚟 🚃 🚋 🚞 🚝 🚄 🚅 🚈 🚂 🚆 🚇 🚊 🚉 ✈️ 🛫 🛬 🛩️ 💺 🛰️ 🚀 🛸 🚁 🛶 ⛵ 🚤 🛥️ 🛳️ ⛴️ 🚢 ⚓ 🪝 ⛽ 🚧 🚦 🚥 🚏 🗺️ 🗿 🗽 🗼 🏰 🏯 🏟️ 🎡 🎢 🎠 ⛲ ⛱️ 🏖️ 🏝️ 🏜️ 🌋 ⛰️ 🏔️ 🗻 🏕️ ⛺ 🏠 🏡 🏘️ 🏚️ 🏗️ 🏭 🏢 🏬 🏣 🏤 🏥 🏦 🏨 🏪 🏫 🏩 💒 🏛️ ⛪ 🕌 🕍 🛕 🕋 ⛩️ 🛤️ 🛣️ 🗾 🎑 🏞️ 🌅 🌄 🌠 🎇 🎆 🌇 🌆 🏙️ 🌃 🌌 🌉 🌁'.split(/\s+/),
        objects: '⌚ 📱 📲 💻 ⌨️ 🖥️ 🖨️ 🖱️ 🖲️ 🕹️ 🗜️ 💽 💾 💿 📀 📼 📷 📸 📹 🎥 📽️ 🎞️ 📞 ☎️ 📟 📠 📺 📻 🎙️ 🎚️ 🎛️ 🧭 ⏱️ ⏲️ ⏰ 🕰️ ⌛ ⏳ 📡 🔋 🔌 💡 🔦 🕯️ 🪔 🧯 🛢️ 💸 💵 💴 💶 💷 🪙 💰 💳 💎 ⚖️ 🪜 🧰 🪛 🔧 🔨 ⚒️ 🛠️ ⛏️ 🪚 🔩 ⚙️ 🪤 🧱 🔫 💣 🧨 🪓 🔪 🗡️ ⚔️ 🛡️ 🚬 ⚰️ 🪦 ⚱️ 🏺 🔮 📿 🧿 💈 ⚗️ 🔭 🔬 🕳️ 🩹 🩺 💊 💉 🩸 🧬 🦠 🧫 🧪 🌡️ 🧹 🪠 🧺 🧻 🚽 🚰 🚿 🛁 🛀 🧼 🪥 🪒 🧽 🪣 🧴 🛎️ 🔑 🗝️ 🚪 🪑 🛋️ 🛏️ 🛌 🧸 🖼️ 🪞 🪟 🛍️ 🛒 🎁 🎈 🎏 🎀 🪄 🪅 🎊 🎉 🎎 🏮 🎐 ✉️ 📩 📨 📧 💌 📥 📤 📦 🏷️ 🪧 📭 📫 📬 📪 📮 📯 📜 📃 📄 📑 🧾 📊 📈 📉 🗒️ 🗓️ 📆 📅 🗑️ 📇 🗃️ 🗳️ 🗄️ 📋 📁 📂 🗂️ 🗞️ 📰 📓 📔 📒 📕 📗 📘 📙 📚 📖 🔖 🧷 🔗 📎 🖇️ 📐 📏 🧮 📌 📍 ✂️ 🖊️ 🖋️ ✒️ 🖌️ 🖍️ 📝 ✏️ 🔍 🔎 🔏 🔐 🔒 🔓'.split(/\s+/),
        symbols: '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 🤎 💔 ❣️ 💕 💞 💓 💗 💖 💘 💝 💟 ☮️ ✝️ ☪️ 🕉️ ☸️ ✡️ 🔯 🕎 ☯️ ☦️ 🛐 ⛎ ♈ ♉ ♊ ♋ ♌ ♍ ♎ ♏ ♐ ♑ ♒ ♓ 🆔 ⚛️ 🉑 ☢️ ☣️ 📴 📳 🈶 🈚 🈸 🈺 🈷️ ✴️ 🆚 💮 🉐 ㊙️ ㊗️ 🈴 🈵 🈹 🈲 🅰️ 🅱️ 🆎 🆑 🅾️ 🆘 ❌ ⭕ 🛑 ⛔ 📛 🚫 💯 💢 ♨️ 🚷 🚯 🚳 🚱 🔞 📵 🚭 ❗ ❕ ❓ ❔ ‼️ ⁉️ 🔅 🔆 〽️ ⚠️ 🚸 🔱 ⚜️ 🔰 ♻️ ✅ 🈯 💹 ❇️ ✳️ ❎ 🌐 💠 Ⓜ️ 🌀 💤 🏧 🚾 ♿ 🅿️ 🛗 🈳 🈂️ 🛂 🛃 🛄 🛅 ♂️ ♀️ ⚧️ ✖️ ➕ ➖ ➗ ♾️ ‼️ 💱 💲 ⚕️ ♻️ ⚜️ 🔱 📛 🔰 ⭕ ✅ ☑️ ✔️ ❌ ❎ ➰ ➿ 〽️ ✳️ ✴️ ❇️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️ ©️'.split(/\s+/).filter((e) => e && e !== '©️'),
        flags: '🏁 🚩 🎌 🏴 🏳️ 🏳️‍🌈 🏳️‍⚧️ 🏴‍☠️ 🇦🇨 🇦🇩 🇦🇪 🇦🇫 🇦🇬 🇦🇮 🇦🇱 🇦🇲 🇦🇴 🇦🇶 🇦🇷 🇦🇸 🇦🇹 🇦🇺 🇦🇼 🇦🇽 🇦🇿 🇧🇦 🇧🇧 🇧🇩 🇧🇪 🇧🇫 🇧🇬 🇧🇭 🇧🇮 🇧🇯 🇧🇱 🇧🇲 🇧🇳 🇧🇴 🇧🇶 🇧🇷 🇧🇸 🇧🇹 🇧🇻 🇧🇼 🇧🇾 🇧🇿 🇨🇦 🇨🇨 🇨🇩 🇨🇫 🇨🇬 🇨🇭 🇨🇮 🇨🇰 🇨🇱 🇨🇲 🇨🇳 🇨🇴 🇨🇷 🇨🇺 🇨🇻 🇨🇼 🇨🇽 🇨🇾 🇨🇿 🇩🇪 🇩🇯 🇩🇰 🇩🇲 🇩🇴 🇩🇿 🇪🇨 🇪🇪 🇪🇬 🇪🇭 🇪🇷 🇪🇸 🇪🇹 🇪🇺 🇫🇮 🇫🇯 🇫🇰 🇫🇲 🇫🇴 🇫🇷 🇬🇦 🇬🇧 🇬🇩 🇬🇪 🇬🇫 🇬🇬 🇬🇭 🇬🇮 🇬🇱 🇬🇲 🇬🇳 🇬🇵 🇬🇶 🇬🇷 🇬🇸 🇬🇹 🇬🇺 🇬🇼 🇬🇾 🇭🇰 🇭🇲 🇭🇳 🇭🇷 🇭🇹 🇭🇺 🇮🇨 🇮🇩 🇮🇪 🇮🇱 🇮🇲 🇮🇳 🇮🇴 🇮🇶 🇮🇷 🇮🇸 🇮🇹 🇯🇪 🇯🇲 🇯🇴 🇯🇵 🇰🇪 🇰🇬 🇰🇭 🇰🇮 🇰🇲 🇰🇳 🇰🇵 🇰🇷 🇰🇼 🇰🇾 🇰🇿 🇱🇦 🇱🇧 🇱🇨 🇱🇮 🇱🇰 🇱🇷 🇱🇸 🇱🇹 🇱🇺 🇱🇻 🇱🇾 🇲🇦 🇲🇨 🇲🇩 🇲🇪 🇲🇫 🇲🇬 🇲🇭 🇲🇰 🇲🇱 🇲🇲 🇲🇳 🇲🇴 🇲🇵 🇲🇶 🇲🇷 🇲🇸 🇲🇹 🇲🇺 🇲🇻 🇲🇼 🇲🇽 🇲🇾 🇲🇿 🇳🇦 🇳🇨 🇳🇪 🇳🇫 🇳🇬 🇳🇮 🇳🇱 🇳🇴 🇳🇵 🇳🇷 🇳🇺 🇳🇿 🇴🇲 🇵🇦 🇵🇪 🇵🇫 🇵🇬 🇵🇭 🇵🇰 🇵🇱 🇵🇲 🇵🇳 🇵🇷 🇵🇸 🇵🇹 🇵🇼 🇵🇾 🇶🇦 🇷🇪 🇷🇴 🇷🇸 🇷🇺 🇷🇼 🇸🇦 🇸🇧 🇸🇨 🇸🇩 🇸🇪 🇸🇬 🇸🇭 🇸🇮 🇸🇯 🇸🇰 🇸🇱 🇸🇲 🇸🇳 🇸🇴 🇸🇷 🇸🇸 🇸🇹 🇸🇻 🇸🇽 🇸🇾 🇸🇿 🇹🇦 🇹🇨 🇹🇩 🇹🇫 🇹🇬 🇹🇭 🇹🇯 🇹🇰 🇹🇱 🇹🇲 🇹🇳 🇹🇴 🇹🇷 🇹🇹 🇹🇻 🇹🇼 🇹🇿 🇺🇦 🇺🇬 🇺🇲 🇺🇳 🇺🇸 🇺🇾 🇺🇿 🇻🇦 🇻🇨 🇻🇪 🇻🇬 🇻🇮 🇻🇳 🇻🇺 🇼🇫 🇼🇸 🇽🇰 🇾🇪 🇾🇹 🇿🇦 🇿🇲 🇿🇼'.split(/\s+/),
    };
    EMOJI_DATA.animals = EMOJI_DATA.animals.filter((e) => e && !e.includes('\u200d'));
    EMOJI_DATA.symbols = '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 🤎 💔 ❣️ 💕 💞 💓 💗 💖 💘 💝 💟 ☮️ ✝️ ☪️ 🕉️ ☸️ ✡️ 🔯 🕎 ☯️ ☦️ 🛐 ⚛️ ☢️ ☣️ 💯 💢 ♨️ ❗ ❕ ❓ ❔ ‼️ ⁉️ 💫 ⭐ 🌟 ✨ ⚡ ✅ ❎ ❌ ⭕ 🔴 🟠 🟡 🟢 🔵 🟣 🟤 ⚫ ⚪ 🟥 🟧 🟨 🟩 🟦 🟪 🟫 ⬛ ⬜ ◼️ ◻️ ◾ ◽ ▪️ ▫️ 🔶 🔷 🔸 🔹 🔺 🔻 💠 🔘 🔳 🔲 ♠ ♣ ♥ ♦ 🎵 🎶 🔔 🔕 📣 📢'.split(/\s+/);

    let emojiCtx = { mode: 'react', actionId: '', anchor: null };
    let emojiCat = 'recent';
    let emojiSkin = Number(localStorage.getItem(EMOJI_SKIN_KEY) || 0) || 0;

    function emojiFreq() {
        try { return JSON.parse(localStorage.getItem(EMOJI_FREQ_KEY) || '[]') || []; }
        catch (_) { return []; }
    }
    function bumpEmojiFreq(emoji) {
        const list = emojiFreq().filter((e) => e !== emoji);
        list.unshift(emoji);
        localStorage.setItem(EMOJI_FREQ_KEY, JSON.stringify(list.slice(0, 36)));
    }
    function applySkin(emoji) {
        if (!emojiSkin || !EMOJI_SKINS[emojiSkin]) return emoji;
        // People/hand emojis that commonly support Fitzpatrick modifiers
        const base = emoji.replace(/[\u{1F3FB}-\u{1F3FF}]/gu, '');
        if (/^(👋|🤚|🖐️|✋|🖖|👌|🤌|🤏|✌️|🤞|🤟|🤘|🤙|👈|👉|👆|🖕|👇|☝️|👍|👎|✊|👊|🤛|🤜|👏|🙌|👐|🤲|🙏|✍️|💅|🤳|💪|🦵|🦶|👂|👃)$/u.test(base)) {
            return base + EMOJI_SKINS[emojiSkin];
        }
        return emoji;
    }
    function closeEmojiPicker() {
        if (!emojiPop) return;
        emojiPop.classList.remove('show');
        emojiPop.hidden = true;
        emojiCtx = { mode: 'react', actionId: '', anchor: null };
    }
    function renderEmojiPicker() {
        if (!emojiPop) return;
        const catsEl = document.getElementById('tc-emoji-cats');
        const bodyEl = document.getElementById('tc-emoji-body');
        const q = (document.getElementById('tc-emoji-search')?.value || '').trim().toLowerCase();
        const skinBtn = document.getElementById('tc-emoji-skin');
        if (skinBtn) skinBtn.textContent = '✋' + (EMOJI_SKINS[emojiSkin] || '');

        if (catsEl && !catsEl.dataset.ready) {
            catsEl.innerHTML = EMOJI_CATS.map((c) =>
                `<button type="button" data-cat="${c.id}" title="${escapeHtml(c.title)}">${c.icon}</button>`
            ).join('');
            catsEl.dataset.ready = '1';
            catsEl.addEventListener('click', (e) => {
                const btn = e.target.closest('button[data-cat]');
                if (!btn) return;
                emojiCat = btn.dataset.cat;
                const search = document.getElementById('tc-emoji-search');
                if (search) search.value = '';
                renderEmojiPicker();
            });
        }
        catsEl?.querySelectorAll('button[data-cat]').forEach((b) => {
            b.classList.toggle('active', b.dataset.cat === emojiCat && !q);
        });

        let sections = [];
        if (q) {
            const all = Object.values(EMOJI_DATA).flat();
            const hits = all.filter((e) => e.includes(q) || e.toLowerCase().includes(q));
            // Search by rough keyword via short map
            const keywords = {
                smile: '😀', laugh: '😂', love: '😍', thumb: '👍', fire: '🔥', check: '✅',
                heart: '❤️', clap: '👏', party: '🎉', eyes: '👀', cry: '😢', angry: '😡',
            };
            Object.entries(keywords).forEach(([k, v]) => {
                if (k.includes(q) || q.includes(k)) hits.unshift(v);
            });
            const uniq = [...new Set(hits)].slice(0, 80);
            sections = [{ title: 'Search results', list: uniq }];
        } else if (emojiCat === 'recent') {
            const freq = emojiFreq();
            const fallback = ['👍', '❤️', '😂', '🎉', '🔥', '👀', '👏', '💯', '✅', '😊', '🙏', '😎'];
            sections = [{ title: 'Frequently used', list: (freq.length ? freq : fallback) }];
            sections.push({ title: 'Smileys & People', list: EMOJI_DATA.smileys.slice(0, 54) });
        } else {
            const cat = EMOJI_CATS.find((c) => c.id === emojiCat) || EMOJI_CATS[1];
            sections = [{ title: cat.title, list: EMOJI_DATA[emojiCat] || EMOJI_DATA.smileys }];
        }

        bodyEl.innerHTML = sections.map((sec) => {
            const buttons = (sec.list || []).map((em) => {
                const shown = applySkin(em);
                return `<button type="button" data-emoji="${escapeHtml(shown)}" title="${escapeHtml(shown)}">${shown}</button>`;
            }).join('');
            return `<div class="tc-emoji-sec-title">${escapeHtml(sec.title)}</div><div class="tc-emoji-grid">${buttons}</div>`;
        }).join('');

        bodyEl.querySelectorAll('button[data-emoji]').forEach((btn) => {
            btn.addEventListener('mouseenter', () => {
                document.getElementById('tc-emoji-preview').textContent = btn.dataset.emoji;
                document.getElementById('tc-emoji-hint').textContent = btn.dataset.emoji;
                btn.classList.add('is-hover');
            });
            btn.addEventListener('mouseleave', () => {
                btn.classList.remove('is-hover');
                document.getElementById('tc-emoji-hint').textContent = 'Pick an emoji…';
            });
            btn.addEventListener('click', () => pickEmoji(btn.dataset.emoji));
        });
    }
    async function pickEmoji(emoji) {
        if (!emoji) return;
        bumpEmojiFreq(emoji.replace(/[\u{1F3FB}-\u{1F3FF}]/gu, ''));
        const ctx = { ...emojiCtx };
        closeEmojiPicker();
        if (ctx.mode === 'insert') {
            insertDescHtml(emoji);
            return;
        }
        if (ctx.mode === 'react' && ctx.actionId) {
            if (isTempId(ctx.actionId)) return stillSaving();
            await optimistic(() => {
                state.reactions = state.reactions || {};
                const rows = state.reactions[ctx.actionId] = state.reactions[ctx.actionId] || [];
                const row = rows.find((r) => (r.emoji?.native || r.native) === emoji);
                if (row) row.count = (row.count || 0) + 1;
                else rows.push({ count: 1, emoji: { native: emoji } });
            }, () => api(`/admin/trello/teams/${teamId}/comments/${ctx.actionId}/reactions`, {
                method: 'POST',
                json: { native: emoji },
            }), 'Could not add reaction');
        }
    }
    function openEmojiPicker(anchor, opts = {}) {
        if (!emojiPop) return;
        hideMentionMenu();
        closeDescMenus();
        emojiCtx = {
            mode: opts.mode || 'react',
            actionId: opts.actionId || '',
            anchor: anchor || null,
        };
        emojiCat = 'recent';
        const search = document.getElementById('tc-emoji-search');
        if (search) search.value = '';
        renderEmojiPicker();
        emojiPop.hidden = false;
        emojiPop.classList.add('show');
        const rect = (anchor || document.body).getBoundingClientRect?.() || { left: 80, bottom: 120, top: 120 };
        const popW = emojiPop.offsetWidth || 352;
        const popH = emojiPop.offsetHeight || 380;
        let left = rect.left;
        let top = rect.bottom + 6;
        if (left + popW > window.innerWidth - 8) left = window.innerWidth - popW - 8;
        if (left < 8) left = 8;
        if (top + popH > window.innerHeight - 8) top = Math.max(8, rect.top - popH - 6);
        emojiPop.style.left = left + 'px';
        emojiPop.style.top = top + 'px';
        setTimeout(() => search?.focus(), 30);
    }
    document.getElementById('tc-emoji-search')?.addEventListener('input', () => renderEmojiPicker());
    document.getElementById('tc-emoji-skin')?.addEventListener('click', () => {
        emojiSkin = (emojiSkin + 1) % EMOJI_SKINS.length;
        localStorage.setItem(EMOJI_SKIN_KEY, String(emojiSkin));
        renderEmojiPicker();
    });
    document.addEventListener('mousedown', (e) => {
        if (!emojiPop?.classList.contains('show')) return;
        if (e.target.closest('#tc-emoji-pop') || e.target.closest('.tc-react') || e.target.closest('[data-insert="emoji"]')) return;
        closeEmojiPicker();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && emojiPop?.classList.contains('show')) {
            e.stopPropagation();
            closeEmojiPicker();
        }
    });

    /** Markdown shortcuts: "# " "## " "* " "1. " "> " "--- " "``` " at line start */
    /**
     * Pasted HTML → clean editor HTML: go through Markdown so only what the editor
     * understands survives (headings, lists, links, bold…), never foreign styles or boxes.
     */
    function sanitizePastedHtml(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        doc.querySelectorAll('script, style, meta, link, title, noscript, iframe, object').forEach((n) => n.remove());
        const md = editorHtmlToMarkdown(doc.body);
        if (!md.trim()) return '';
        const out = renderMarkdown(md);
        // A single paragraph is pasted inline (doesn't split the current line)
        const single = out.match(/^<p>([\s\S]*)<\/p>$/);
        return single && !single[1].includes('</p>') ? single[1] : out;
    }

    /** Insert <a href=url>label</a> at the caret and leave the caret just after it. */
    function insertLinkAtCaret(url, label) {
        const marker = 'tc-new-link';
        document.execCommand('insertHTML', false,
            `<a href="${escapeHtml(url)}" target="_blank" rel="noopener" data-${marker}>${escapeHtml(label)}</a>`);
        const root = activeVisual || descVisual;
        const a = root?.querySelector(`a[data-${marker}]`);
        if (!a) return;
        a.removeAttribute(`data-${marker}`);
        const sel = window.getSelection();
        const r = document.createRange();
        r.setStartAfter(a);
        r.collapse(true);
        sel.removeAllRanges();
        sel.addRange(r);
    }

    /** Typing a space/Enter right after a bare URL turns it into a link (Trello auto-link). */
    function autoLinkBeforeCaret() {
        const sel = window.getSelection();
        if (!sel?.rangeCount || !sel.isCollapsed) return;
        const node = sel.anchorNode;
        if (!node || node.nodeType !== Node.TEXT_NODE) return;
        if (node.parentElement?.closest('a, pre, code')) return;
        const offset = sel.anchorOffset;
        const before = node.textContent.slice(0, offset);
        const m = before.match(/(https?:\/\/[^\s ]+)$/i);
        if (!m) return;
        const range = document.createRange();
        range.setStart(node, offset - m[1].length);
        range.setEnd(node, offset);
        const a = document.createElement('a');
        a.href = m[1];
        a.target = '_blank';
        a.rel = 'noopener';
        range.surroundContents(a);
        const r = document.createRange();
        r.setStartAfter(a);
        r.collapse(true);
        sel.removeAllRanges();
        sel.addRange(r);
    }

    // ── Code blocks in the rich editor (Trello: numbered lines + language bar) ──
    /** Insert a block and put the caret inside the element marked data-caret. */
    function insertBlockWithCaret(html) {
        insertDescHtml(html);
        const root = activeVisual || descVisual;
        const target = root?.querySelector('[data-caret]');
        if (!target) return null;
        target.removeAttribute('data-caret');
        const sel = window.getSelection();
        const r = document.createRange();
        r.setStart(target, 0);
        r.collapse(true);
        sel.removeAllRanges();
        sel.addRange(r);
        root.dispatchEvent(new Event('input', { bubbles: true }));
        return target;
    }

    function insertCodeBlock() {
        const line = insertBlockWithCaret('<pre class="tc-md-code" data-lang=""><code><span class="ln" data-caret><br></span></code></pre><p><br></p>');
        if (line) syncCodeBar();
    }

    function insertQuoteBlock() {
        insertBlockWithCaret('<blockquote><p data-caret><br></p></blockquote><p><br></p>');
    }

    /** Caret context when it is inside a code block of the active editor. */
    function codeCaret() {
        const sel = window.getSelection();
        if (!sel?.rangeCount) return null;
        const n = sel.anchorNode;
        const el = n?.nodeType === 1 ? n : n?.parentElement;
        const pre = el?.closest?.('pre');
        const root = activeVisual || descVisual;
        if (!pre || !root?.contains(pre)) return null;
        return { sel, pre, line: el.closest('.ln'), root };
    }

    /** Enter inside a code block: new numbered line (rest of the line moves down). */
    function splitCodeLine(ctx) {
        const { sel, line, root } = ctx;
        if (!line) {
            document.execCommand('insertText', false, '\n');
            return;
        }
        const range = sel.getRangeAt(0);
        range.deleteContents();
        const tail = document.createRange();
        tail.setStart(range.startContainer, range.startOffset);
        tail.setEnd(line, line.childNodes.length);
        const next = document.createElement('span');
        next.className = 'ln';
        next.appendChild(tail.extractContents());
        if (!next.textContent) next.innerHTML = '<br>';
        if (!line.textContent) line.innerHTML = '<br>';
        line.after(next);
        const r = document.createRange();
        r.setStart(next, 0);
        r.collapse(true);
        sel.removeAllRanges();
        sel.addRange(r);
        root.dispatchEvent(new Event('input', { bubbles: true }));
        syncCodeBar();
    }

    /** ArrowDown on the last code line leaves the block (adds a paragraph if needed). */
    function leaveCodeBlock(ctx) {
        const { sel, pre, root } = ctx;
        let next = pre.nextElementSibling;
        if (!next) {
            next = document.createElement('p');
            next.innerHTML = '<br>';
            pre.after(next);
            root.dispatchEvent(new Event('input', { bubbles: true }));
        }
        const r = document.createRange();
        r.setStart(next, 0);
        r.collapse(true);
        sel.removeAllRanges();
        sel.addRange(r);
        syncCodeBar();
    }

    let activeCodePre = null;
    function hideCodeBar() {
        activeCodePre = null;
        const bar = document.getElementById('tc-code-bar');
        if (bar) bar.hidden = true;
        document.getElementById('tc-code-menu')?.setAttribute('hidden', '');
    }

    /** Show the language bar under the code block holding the caret (description or comment editor). */
    function syncCodeBar() {
        const bar = document.getElementById('tc-code-bar');
        if (!bar) return;
        const ctx = codeCaret();
        if (!ctx) {
            hideCodeBar();
            return;
        }
        activeCodePre = ctx.pre;
        document.getElementById('tc-code-lang').value = ctx.pre.dataset.lang || '';
        document.getElementById('tc-code-wrap').classList.toggle('active', ctx.pre.classList.contains('is-wrap'));
        bar.hidden = false;
        const pr = ctx.pre.getBoundingClientRect();
        const maxLeft = window.innerWidth - bar.offsetWidth - 8;
        bar.style.left = `${Math.max(8, Math.min(pr.left + Math.min(80, pr.width / 4), maxLeft))}px`;
        bar.style.top = `${Math.min(pr.bottom + 4, window.innerHeight - bar.offsetHeight - 8)}px`;
    }

    (function bindCodeBar() {
        const bar = document.getElementById('tc-code-bar');
        if (!bar) return;
        const markDirty = () => activeCodePre?.closest('.tc-rich-visual')?.dispatchEvent(new Event('input', { bubbles: true }));
        // Keep the editor selection when pressing bar buttons
        bar.addEventListener('mousedown', (e) => { if (!e.target.closest('select')) e.preventDefault(); });
        document.getElementById('tc-code-lang').addEventListener('change', (e) => {
            if (!activeCodePre) return;
            activeCodePre.dataset.lang = e.target.value;
            markDirty();
        });
        document.getElementById('tc-code-wrap').addEventListener('click', (e) => {
            if (!activeCodePre) return;
            e.currentTarget.classList.toggle('active', activeCodePre.classList.toggle('is-wrap'));
        });
        document.getElementById('tc-code-more').addEventListener('click', () => {
            document.getElementById('tc-code-menu').toggleAttribute('hidden');
        });
        document.getElementById('tc-code-menu').addEventListener('click', async (e) => {
            const act = e.target.closest('[data-code-act]')?.dataset.codeAct;
            if (!act || !activeCodePre) return;
            document.getElementById('tc-code-menu').setAttribute('hidden', '');
            if (act === 'copy') {
                const lines = [...activeCodePre.querySelectorAll('.ln')].map((l) => l.textContent);
                try {
                    await navigator.clipboard.writeText(lines.length ? lines.join('\n') : activeCodePre.textContent);
                    if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Code copied', showConfirmButton: false, timer: 1200 });
                } catch (err) { toastError('Could not copy code'); }
            } else if (act === 'delete') {
                const visual = activeCodePre.closest('.tc-rich-visual');
                activeCodePre.remove();
                hideCodeBar();
                visual?.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        // Follow the caret: show under the code block it is in, hide elsewhere in the editor
        const inEditor = (n) => !!(n && (n.nodeType === 1 ? n : n.parentElement)?.closest?.('.tc-rich-visual'));
        document.addEventListener('selectionchange', () => {
            if (inEditor(window.getSelection()?.anchorNode)) syncCodeBar();
        });
        document.addEventListener('mousedown', (e) => {
            if (bar.hidden) return;
            if (!e.target.closest('#tc-code-bar') && !inEditor(e.target)) hideCodeBar();
        });
        document.addEventListener('scroll', () => { if (!bar.hidden) syncCodeBar(); }, true);
    })();

    // ── Image in the editors: click selects it, "•••" below → Copy / Delete (Trello) ──
    let hideEditorImageBar = () => {};
    (function bindImageBar() {
        const bar = document.getElementById('tc-img-bar');
        const menu = document.getElementById('tc-img-menu');
        const moreBtn = document.getElementById('tc-img-more');
        if (!bar || !menu || !moreBtn) return;
        let img = null;

        const place = () => {
            if (!img?.isConnected) return hide();
            const r = img.getBoundingClientRect();
            bar.hidden = false;
            bar.style.left = `${Math.max(8, r.left + r.width / 2 - bar.offsetWidth / 2)}px`;
            bar.style.top = `${Math.min(window.innerHeight - bar.offsetHeight - 8, r.bottom + 8)}px`;
        };
        const hide = () => {
            img?.classList.remove('is-selected');
            img = null;
            bar.hidden = true;
            menu.hidden = true;
            moreBtn.classList.remove('active');
        };
        const select = (el) => {
            if (img !== el) hide();
            img = el;
            img.classList.add('is-selected');
            place();
        };
        const markDirty = (node) => node?.closest?.('.tc-rich-visual')?.dispatchEvent(new Event('input', { bubbles: true }));
        const removeImg = () => {
            if (!img) return;
            const visual = img.closest('.tc-rich-visual');
            const holder = img.parentElement;
            const next = img.nextSibling;
            img.remove();
            if (next?.nodeName === 'BR' && !next.nextSibling) next.remove();
            // Drop the paragraph it sat in if nothing else is left
            if (holder && holder !== visual && !holder.textContent.trim() && !holder.querySelector('img')) holder.remove();
            visual?.dispatchEvent(new Event('input', { bubbles: true }));
            hide();
        };

        document.addEventListener('click', (e) => {
            const el = e.target.closest?.('.tc-rich-visual img');
            if (el) select(el);
        });
        document.addEventListener('mousedown', (e) => {
            if (!img) return;
            if (e.target.closest('#tc-img-bar') || e.target === img) return;
            hide();
        });
        document.addEventListener('keydown', (e) => {
            if (!img) return;
            if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault();
                removeImg();
            } else if (e.key === 'Escape') {
                e.stopPropagation();
                hide();
            } else if (!e.target.closest?.('#tc-img-bar')) {
                hide();
            }
        }, true);
        document.addEventListener('scroll', () => { if (img) place(); }, true);
        bar.addEventListener('mousedown', (e) => e.preventDefault());

        moreBtn.addEventListener('click', () => {
            menu.hidden = !menu.hidden;
            moreBtn.classList.toggle('active', !menu.hidden);
        });
        menu.addEventListener('click', async (e) => {
            const act = e.target.closest('[data-img-act]')?.dataset.imgAct;
            if (!act || !img) return;
            if (act === 'delete') {
                removeImg();
                return;
            }
            // Copy as HTML <img> (so pasting into a card editor shows the image) + the PNG itself
            // when the browser allows it (other sites' images can't be read, so that part may be skipped).
            const src = img.getAttribute('src') || '';
            const absSrc = img.currentSrc || img.src;
            const md = img.getAttribute('data-md') || `![image](${absSrc})`;
            const html = `<img src="${escapeHtml(absSrc)}" alt="${escapeHtml(img.alt || 'image')}" data-md="${escapeHtml(md)}">`;
            const textItems = {
                'text/html': new Blob([html], { type: 'text/html' }),
                'text/plain': new Blob([absSrc], { type: 'text/plain' }),
            };
            let png = null;
            try {
                const blob = await fetch(src, { credentials: 'same-origin' }).then((r) => r.blob());
                png = blob.type === 'image/png' ? blob : await new Promise((resolve, reject) => {
                    const c = document.createElement('canvas');
                    c.width = img.naturalWidth;
                    c.height = img.naturalHeight;
                    c.getContext('2d').drawImage(img, 0, 0);
                    c.toBlob((b) => (b ? resolve(b) : reject(new Error('no blob'))), 'image/png');
                });
            } catch (err) { png = null; }
            try {
                await navigator.clipboard.write([new ClipboardItem(png ? { ...textItems, 'image/png': png } : textItems)]);
            } catch (err) {
                try { await navigator.clipboard.writeText(absSrc); } catch (e2) { toastError('Could not copy image'); return; }
            }
            if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Image copied', showConfirmButton: false, timer: 1200 });
            menu.hidden = true;
            moreBtn.classList.remove('active');
        });
        hideEditorImageBar = hide;
    })();

    // ── Link toolbar in the editors (Trello: edit / unlink / open / copy) ──
    // Closing the editor / card hides it (set by bindLinkBar).
    let hideEditorLinkBar = () => {};
    (function bindLinkBar() {
        const bar = document.getElementById('tc-link-bar');
        const pop = document.getElementById('tc-link-edit');
        if (!bar || !pop) return;
        const urlIn = document.getElementById('tc-link-edit-url');
        const textIn = document.getElementById('tc-link-edit-text');
        let link = null;

        const linkAtCaret = () => {
            const n = window.getSelection()?.anchorNode;
            const el = n?.nodeType === 1 ? n : n?.parentElement;
            const a = el?.closest?.('a[href]');
            return a && a.closest('.tc-rich-visual') && !a.closest('pre') ? a : null;
        };
        const placeUnder = (el, anchor) => {
            const r = anchor.getClientRects()[anchor.getClientRects().length - 1] || anchor.getBoundingClientRect();
            el.hidden = false;
            const left = Math.max(8, Math.min(r.left, window.innerWidth - el.offsetWidth - 8));
            let top = r.bottom + 6;
            if (top + el.offsetHeight > window.innerHeight - 8) top = Math.max(8, r.top - el.offsetHeight - 6);
            el.style.left = `${left}px`;
            el.style.top = `${top}px`;
        };
        const hideAll = () => { bar.hidden = true; pop.hidden = true; link = null; };
        const markDirty = (a) => a?.closest('.tc-rich-visual')?.dispatchEvent(new Event('input', { bubbles: true }));

        const sync = () => {
            if (!pop.hidden) return;
            const a = linkAtCaret();
            if (!a) { bar.hidden = true; link = null; return; }
            link = a;
            placeUnder(bar, a);
        };
        document.addEventListener('selectionchange', () => {
            const n = window.getSelection()?.anchorNode;
            if (n && (n.nodeType === 1 ? n : n.parentElement)?.closest?.('.tc-rich-visual')) sync();
        });
        document.addEventListener('mousedown', (e) => {
            if (bar.hidden && pop.hidden) return;
            if (e.target.closest('#tc-link-bar, #tc-link-edit')) return;
            if (e.target.closest('.tc-rich-visual a[href]')) return;
            hideAll();
        });
        document.addEventListener('scroll', () => { if (!bar.hidden || !pop.hidden) hideAll(); }, true);
        bar.addEventListener('mousedown', (e) => e.preventDefault());

        bar.addEventListener('click', async (e) => {
            const act = e.target.closest('[data-link-act]')?.dataset.linkAct;
            if (!act || !link) return;
            const href = link.getAttribute('href') || '';
            if (act === 'open') {
                window.open(href, '_blank', 'noopener');
            } else if (act === 'copy') {
                try {
                    await navigator.clipboard.writeText(href);
                    if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Link copied', showConfirmButton: false, timer: 1200 });
                } catch (err) { toastError('Could not copy link'); }
            } else if (act === 'unlink') {
                const a = link;
                const parent = a.parentNode;
                while (a.firstChild) parent.insertBefore(a.firstChild, a);
                parent.removeChild(a);
                markDirty(parent);
                parent.normalize?.();
                hideAll();
            } else if (act === 'edit') {
                urlIn.value = href;
                textIn.value = link.textContent === href ? '' : link.textContent;
                bar.hidden = true;
                placeUnder(pop, link);
                setTimeout(() => urlIn.focus(), 20);
            }
        });

        const save = () => {
            const url = urlIn.value.trim();
            if (!link) return hideAll();
            if (!url) { urlIn.focus(); return; }
            const a = link;
            a.setAttribute('href', url);
            a.textContent = textIn.value.trim() || url;
            markDirty(a);
            hideAll();
            // Caret back just after the link
            const sel = window.getSelection();
            const r = document.createRange();
            r.setStartAfter(a);
            r.collapse(true);
            a.closest('.tc-rich-visual')?.focus();
            sel.removeAllRanges();
            sel.addRange(r);
        };
        document.getElementById('tc-link-edit-save').addEventListener('click', save);
        document.getElementById('tc-link-edit-cancel').addEventListener('click', hideAll);
        document.getElementById('tc-link-edit-clear').addEventListener('click', () => { urlIn.value = ''; urlIn.focus(); });
        [urlIn, textIn].forEach((input) => input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); save(); }
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); hideAll(); }
        }));
        hideEditorLinkBar = hideAll;
    })();

    function tryMarkdownTypingShortcut(e) {
        if (e.key !== ' ' && e.key !== 'Enter') return false;
        const sel = window.getSelection();
        if (!sel?.rangeCount || !sel.isCollapsed) return false;
        const node = sel.anchorNode;
        const root = activeVisual || descVisual;
        if (!node || node.nodeType !== Node.TEXT_NODE || !root?.contains(node)) return false;
        const offset = sel.anchorOffset;
        const before = String(node.textContent || '').slice(0, offset);
        const lineStart = Math.max(before.lastIndexOf('\n') + 1, 0);
        const token = before.slice(lineStart);
        const spaceMap = [
            ['######', 'h6'], ['#####', 'h5'], ['####', 'h4'], ['###', 'h3'], ['##', 'h2'], ['#', 'h1'],
            ['*', 'ul'], ['-', 'ul'], ['1.', 'ol'], ['>', 'quote'], ['```', 'pre'],
        ];
        const enterMap = [['---', 'hr'], ['***', 'hr'], ['```', 'pre']];
        const map = e.key === 'Enter' ? enterMap : spaceMap.concat([['---', 'hr']]);
        for (const [tok, action] of map) {
            if (token !== tok) continue;
            e.preventDefault();
            const range = document.createRange();
            range.setStart(node, lineStart);
            range.setEnd(node, offset);
            range.deleteContents();
            sel.removeAllRanges();
            const r = document.createRange();
            r.setStart(node, Math.min(lineStart, node.textContent.length));
            r.collapse(true);
            sel.addRange(r);
            if (action === 'ul') runDescCmd('insertUnorderedList');
            else if (action === 'ol') runDescCmd('insertOrderedList');
            else if (action === 'quote') insertQuoteBlock();
            else if (action === 'hr') insertDescHtml('<hr><br>');
            else if (action === 'pre') insertCodeBlock();
            else applyDescBlock(action);
            return true;
        }
        return false;
    }

    function handleDescShortcut(e) {
        if (!descEditorFocused()) return;

        // Mention menu navigation takes priority
        if (mentionMenu?.classList.contains('show')) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                mentionIndex = (mentionIndex + 1) % Math.max(mentionItems.length, 1);
                renderMentionMenu();
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                mentionIndex = (mentionIndex - 1 + mentionItems.length) % Math.max(mentionItems.length, 1);
                renderMentionMenu();
                return;
            }
            if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                const item = mentionItems[mentionIndex];
                if (item) insertMention(item);
                return;
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                hideMentionMenu();
                return;
            }
        }

        // Inside a code block: Enter = new numbered line, ArrowDown on last line = leave block
        if ((e.key === 'Enter' && !e.ctrlKey && !e.metaKey) || e.key === 'ArrowDown') {
            const ctx = codeCaret();
            if (ctx && e.key === 'Enter') {
                e.preventDefault();
                splitCodeLine(ctx);
                return;
            }
            if (ctx && e.key === 'ArrowDown' && ctx.line && !ctx.line.nextElementSibling) {
                e.preventDefault();
                leaveCodeBlock(ctx);
                return;
            }
        }

        if (tryMarkdownTypingShortcut(e)) return;

        if ((e.key === ' ' || e.key === 'Enter') && !e.ctrlKey && !e.metaKey && !e.altKey) {
            autoLinkBeforeCaret();
        }

        const mod = e.ctrlKey || e.metaKey;
        const shift = e.shiftKey;
        const alt = e.altKey;
        const key = e.key;
        const lower = key.length === 1 ? key.toLowerCase() : key;

        // Ctrl+/ → Editor help
        if (mod && !alt && !shift && key === '/') {
            e.preventDefault();
            openEditorHelp();
            return;
        }

        if (!mod) return;

        // Ctrl+Shift+…
        if (shift && !alt) {
            if (lower === 's') { e.preventDefault(); runDescCmd('strikeThrough'); return; }
            // Ctrl+Shift+V → paste event below forces plain text (don't preventDefault here)
            if (lower === 'v') return;
            if (lower === '7' || key === '&') { e.preventDefault(); runDescCmd('insertOrderedList'); return; }
            if (lower === '8' || key === '*') { e.preventDefault(); runDescCmd('insertUnorderedList'); return; }
            if (lower === '9' || key === '(') { e.preventDefault(); insertDescHtml('<blockquote><br></blockquote>'); return; }
            if (lower === 'm') { e.preventDefault(); runDescCmd('code'); return; }
            if (key === '-' || key === '_') { e.preventDefault(); insertDescHtml('<hr><br>'); return; }
            return;
        }

        // Ctrl+Alt+…
        if (alt && !shift) {
            if (key >= '0' && key <= '6') {
                e.preventDefault();
                applyDescBlock(key === '0' ? 'p' : ('h' + key));
                return;
            }
            return;
        }

        // Ctrl+…
        if (!alt && !shift) {
            if (lower === 'b') { e.preventDefault(); runDescCmd('bold'); return; }
            if (lower === 'i') { e.preventDefault(); runDescCmd('italic'); return; }
            if (lower === 'k') { e.preventDefault(); openDescLinkPop(); return; }
            if (key === '\\' || key === 'Backslash') { e.preventDefault(); runDescCmd('removeFormat'); return; }
            if (lower === 'z') { e.preventDefault(); runDescCmd('undo'); return; }
            if (lower === 'y') { e.preventDefault(); runDescCmd('redo'); return; }
        }
    }

    function bindRichVisual(visual) {
        if (!visual) return;
        visual.addEventListener('focus', () => {
            setActiveRich(visual);
            if (visual === cmtVisual) commentComposer?.classList.add('is-open');
        });
        visual.addEventListener('keydown', handleDescShortcut);
        visual.addEventListener('keyup', (e) => {
            if (['ArrowUp', 'ArrowDown', 'Enter', 'Tab', 'Escape'].includes(e.key)) return;
            refreshMentionMenu();
        });
        visual.addEventListener('click', () => {
            setActiveRich(visual);
            refreshMentionMenu();
        });
        visual.addEventListener('paste', (e) => {
            setActiveRich(visual);
            const text = (e.clipboardData || window.clipboardData)?.getData('text/plain') || '';
            // Inside a code block: always plain text
            if (codeCaret()) {
                e.preventDefault();
                document.execCommand('insertText', false, text);
                return;
            }
            // Copied image (from a card editor, a website, "Copy image"…) → paste the image itself
            const richHtml = (e.clipboardData || window.clipboardData)?.getData('text/html') || '';
            if (/<img\s/i.test(richHtml)) {
                e.preventDefault();
                const clean = sanitizePastedHtml(richHtml);
                if (clean) document.execCommand('insertHTML', false, clean);
                return;
            }
            // A pasted URL becomes a link (Trello); over selected text it links that text
            const url = text.trim();
            // A pasted image URL shows the image (Trello), not a link
            if (/^https?:\/\/\S+\.(png|jpe?g|gif|webp|bmp|svg|avif)(\?\S*)?$/i.test(url) && window.getSelection()?.isCollapsed) {
                e.preventDefault();
                insertDescImage(url, 'image');
                return;
            }
            if (/^https?:\/\/\S+$/i.test(url)) {
                e.preventDefault();
                const sel = window.getSelection();
                if (sel && !sel.isCollapsed) {
                    document.execCommand('createLink', false, url);
                } else {
                    insertLinkAtCaret(url, url);
                }
                return;
            }
            // Rich paste (from Trello, websites, Word…): keep the content, drop foreign styles/boxes
            const html = (e.clipboardData || window.clipboardData)?.getData('text/html') || '';
            if (html) {
                e.preventDefault();
                const clean = sanitizePastedHtml(html);
                if (clean) document.execCommand('insertHTML', false, clean);
                else document.execCommand('insertText', false, text);
            }
        });
    }
    bindRichVisual(descVisual);
    bindRichVisual(cmtVisual);

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#tc-mention-menu') && !e.target.closest('.tc-rich-visual')) {
            hideMentionMenu();
        }
        // Collapse comment editor when clicking away empty
        if (commentComposer?.classList.contains('is-open')
            && !e.target.closest('#tc-comment-composer')
            && !e.target.closest('#tc-mention-menu')
            && !e.target.closest('.tc-pop-backdrop.show')) {
            if (!commentMarkdown()) clearCommentEditor();
        }
    });

    function insertDescImage(attOrUrl, name) {
        let src, md, alt;
        if (typeof attOrUrl === 'string') {
            src = attOrUrl;
            alt = name || 'image';
            md = `![${alt}](${src})`;
        } else {
            const a = attOrUrl;
            alt = a.name || a.fileName || 'image';
            src = a.preview_url || a.download_url;
            const trelloUrl = `https://trello.com/1/cards/${currentCardId}/attachments/${a.id}/download/${encodeURIComponent(alt)}`;
            md = `![${alt}](${trelloUrl})`;
        }
        insertDescHtml(`<img class="tc-desc-img" src="${escapeHtml(src)}" alt="${escapeHtml(alt)}" data-md="${escapeHtml(md)}"><br>`);
    }

    function isImageAttachment(a) {
        const n = (a.name || a.fileName || '').toLowerCase();
        const mime = String(a.mimeType || a.mime_type || '');
        return !!a.preview_url || mime.startsWith('image/') || /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(n);
    }

    /** Insert attachment into description (image embed or file link) — Trello "Attach and insert link". */
    function insertDescAttachment(a) {
        if (!a) return;
        if (isImageAttachment(a)) {
            insertDescImage(a);
            return;
        }
        const name = a.name || a.fileName || 'file';
        const href = a.download_url
            || `https://trello.com/1/cards/${currentCardId}/attachments/${a.id}/download/${encodeURIComponent(name)}`;
        insertDescHtml(`<a href="${escapeHtml(href)}" target="_blank" rel="noopener">${escapeHtml(name)}</a>&nbsp;`);
    }

    function fillAttachPickList() {
        const list = document.getElementById('tc-attach-pick-list');
        if (!list) return;
        const atts = state?.attachments || [];
        if (!atts.length) {
            list.innerHTML = '<div class="tc-muted">No attachments on this card yet</div>';
            return;
        }
        list.innerHTML = atts.map((a) => {
            const name = a.name || a.fileName || 'file';
            const ext = (name.split('.').pop() || 'file').slice(0, 5);
            const thumb = a.preview_url
                ? `<img class="thumb" src="${escapeHtml(a.preview_url)}" alt="">`
                : `<span class="thumb">${escapeHtml(ext)}</span>`;
            return `<button type="button" data-att="${a.id}">
                ${thumb}
                <span class="meta">
                    <span class="name">${escapeHtml(name)}</span>
                    <span class="sub">${isImageAttachment(a) ? 'Insert image' : 'Insert link'}</span>
                </span>
            </button>`;
        }).join('');
        list.querySelectorAll('button[data-att]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const a = atts.find((x) => x.id === btn.dataset.att);
                if (!a) return;
                closePop('tc-attach-pop');
                if (activeVisual === descVisual) setDescMode(true);
                else if (activeVisual === cmtVisual) focusCommentEditor();
                insertDescAttachment(a);
            });
        });
    }

    function openPop(id) {
        closeDescMenus();
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('show');
        el.setAttribute('aria-hidden', 'false');
    }
    function closePop(id) {
        const el = typeof id === 'string' ? document.getElementById(id) : id;
        if (!el) return;
        el.classList.remove('show');
        el.setAttribute('aria-hidden', 'true');
    }
    document.querySelectorAll('[data-close-pop]').forEach((btn) => {
        btn.addEventListener('click', () => closePop(btn.closest('.tc-pop-backdrop')));
    });
    document.querySelectorAll('.tc-pop-backdrop').forEach((bd) => {
        bd.addEventListener('click', (e) => { if (e.target === bd) closePop(bd); });
    });

    document.getElementById('tc-del-comment-confirm')?.addEventListener('click', async () => {
        const actionId = pendingDeleteCommentId;
        if (!actionId) return;
        pendingDeleteCommentId = null;
        closePop('tc-del-comment-pop');
        if (isTempId(actionId)) return stillSaving();
        await optimistic(() => {
            if (Array.isArray(state.activity)) state.activity = state.activity.filter((a) => a.id !== actionId);
            if (Array.isArray(state.comments)) state.comments = state.comments.filter((a) => a.id !== actionId);
        }, () => api(`/admin/trello/teams/${teamId}/comments/${actionId}`, { method: 'DELETE' }), 'Could not delete comment');
    });

    // Toolbar dropdowns (description + comment editors)
    document.querySelectorAll('.tc-rich-editor .tc-tb[data-dd]').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const editor = btn.closest('.tc-rich-editor');
            const visual = editor?.querySelector('.tc-rich-visual');
            if (visual) setActiveRich(visual);
            const dd = editor?.querySelector(`.tc-dd[data-dd-panel="${btn.dataset.dd}"]`);
            if (!dd || !editor) return;
            const open = dd.classList.contains('show');
            closeDescMenus();
            if (open) return;
            const editorRect = editor.getBoundingClientRect();
            const btnRect = btn.getBoundingClientRect();
            dd.style.top = (btnRect.bottom - editorRect.top + 4) + 'px';
            dd.classList.add('show');
            // Under the button; shift left only if it would overflow the editor (narrow comment column)
            const maxLeft = Math.max(0, editorRect.width - dd.offsetWidth);
            dd.style.left = Math.min(Math.max(0, btnRect.left - editorRect.left), maxLeft) + 'px';
            btn.classList.add('open');
            const q = dd.querySelector('.tc-ins-q');
            if (q) {
                q.value = '';
                filterInsertMenu(dd);
                setTimeout(() => q.focus(), 20);
            }
        });
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.tc-desc-toolbar')) closeDescMenus();
    });

    document.querySelectorAll('.tc-rich-editor .tc-tb[data-cmd]').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const visual = btn.closest('.tc-rich-editor')?.querySelector('.tc-rich-visual');
            if (visual) setActiveRich(visual);
            runDescCmd(btn.dataset.cmd);
            closeDescMenus();
        });
    });

    document.querySelectorAll('.tc-rich-editor .tc-dd [data-cmd]').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const visual = btn.closest('.tc-rich-editor')?.querySelector('.tc-rich-visual');
            if (visual) setActiveRich(visual);
            runDescCmd(btn.dataset.cmd);
            closeDescMenus();
        });
    });

    document.querySelectorAll('.tc-rich-editor .tc-dd [data-block]').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const visual = btn.closest('.tc-rich-editor')?.querySelector('.tc-rich-visual');
            if (visual) setActiveRich(visual);
            applyDescBlock(btn.dataset.block);
            closeDescMenus();
        });
    });

    document.querySelectorAll('.tc-rich-editor .tc-dd [data-insert]').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const visual = btn.closest('.tc-rich-editor')?.querySelector('.tc-rich-visual');
            if (visual) setActiveRich(visual);
            const kind = btn.dataset.insert;
            closeDescMenus();
            if (kind === 'mention') {
                openMentionPicker();
                return;
            }
            if (kind === 'emoji') {
                // Anchor to the toolbar "+" button (the menu item itself is hidden by now)
                const plusBtn = btn.closest('.tc-rich-editor')?.querySelector('.tc-tb[data-dd="insert"]');
                openEmojiPicker(plusBtn || btn, { mode: 'insert' });
                return;
            }
            if (kind === 'code') {
                insertCodeBlock();
                return;
            }
            if (kind === 'quote') {
                insertQuoteBlock();
            }
        });
    });

    // "+" insert menu: search box filters items; ↑/↓ moves, Enter picks (Trello-style)
    function filterInsertMenu(dd) {
        const q = (dd.querySelector('.tc-ins-q')?.value || '').trim().toLowerCase();
        let first = null;
        dd.querySelectorAll('.tc-ins-item').forEach((item) => {
            const hit = !q || (item.dataset.keys || item.textContent).toLowerCase().includes(q);
            item.hidden = !hit;
            item.classList.remove('active');
            if (hit && !first) first = item;
        });
        first?.classList.add('active');
        const empty = dd.querySelector('.tc-ins-empty');
        if (empty) empty.hidden = !!first;
    }
    document.querySelectorAll('.tc-rich-editor .tc-ins-q').forEach((input) => {
        const dd = input.closest('.tc-dd');
        input.addEventListener('input', () => filterInsertMenu(dd));
        input.addEventListener('keydown', (e) => {
            const items = [...dd.querySelectorAll('.tc-ins-item:not([hidden])')];
            const cur = items.findIndex((i) => i.classList.contains('active'));
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!items.length) return;
                const next = e.key === 'ArrowDown' ? (cur + 1) % items.length : (cur - 1 + items.length) % items.length;
                items.forEach((i) => i.classList.remove('active'));
                items[next].classList.add('active');
            } else if (e.key === 'Enter') {
                e.preventDefault();
                items[Math.max(0, cur)]?.click();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                closeDescMenus();
                focusDesc();
            }
        });
    });

    // Link / image / attach / md / help (shared popovers)
    document.querySelectorAll('.tc-rich-link').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', () => {
            const visual = btn.closest('.tc-rich-editor')?.querySelector('.tc-rich-visual');
            if (visual) setActiveRich(visual);
            openDescLinkPop();
        });
    });
    document.getElementById('tc-link-insert')?.addEventListener('click', () => {
        const url = document.getElementById('tc-link-url').value.trim();
        const text = document.getElementById('tc-link-text').value.trim() || url;
        if (!url) return;
        closePop('tc-link-pop');
        insertDescHtml(`<a href="${escapeHtml(url)}" target="_blank" rel="noopener">${escapeHtml(text)}</a>`);
    });

    // Image popover
    function fillImageGrid() {
        const grid = document.getElementById('tc-img-grid');
        if (!grid) return;
        const atts = (state?.attachments || []).filter((a) => {
            const n = (a.name || a.fileName || '').toLowerCase();
            return a.preview_url || /\.(png|jpe?g|gif|webp|bmp)$/i.test(n);
        });
        if (!atts.length) {
            grid.innerHTML = '<div class="tc-muted" style="grid-column:1/-1">No uploaded images on this card yet</div>';
            return;
        }
        grid.innerHTML = atts.map((a) => `
            <button type="button" data-att="${a.id}" title="${escapeHtml(a.name || a.fileName || '')}">
                ${a.preview_url ? `<img src="${a.preview_url}" alt="">` : `<div class="ph">${escapeHtml(a.name || 'file')}</div>`}
            </button>
        `).join('');
        grid.querySelectorAll('button[data-att]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const a = atts.find((x) => x.id === btn.dataset.att);
                if (!a) return;
                closePop('tc-image-pop');
                insertDescImage(a);
            });
        });
    }
    document.querySelectorAll('.tc-rich-image').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', () => {
            const visual = btn.closest('.tc-rich-editor')?.querySelector('.tc-rich-visual');
            if (visual) setActiveRich(visual);
            fillImageGrid();
            document.getElementById('tc-img-url').value = '';
            openPop('tc-image-pop');
        });
    });
    document.getElementById('tc-img-url-submit')?.addEventListener('click', () => {
        const url = document.getElementById('tc-img-url').value.trim();
        if (!url) return;
        closePop('tc-image-pop');
        insertDescImage(url);
    });
    document.getElementById('tc-img-upload-btn')?.addEventListener('click', () => {
        document.getElementById('tc-img-file')?.click();
    });
    document.getElementById('tc-img-file')?.addEventListener('change', async (e) => {
        const file = e.target.files?.[0];
        if (!file || !currentCardId) return;
        const fd = new FormData();
        fd.append('file', file);
        try {
            const res = await fetch(cardUrl(currentCardId, 'attachments'), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.ok === false) throw new Error(data.message || 'Upload failed');
            e.target.value = '';
            await reloadCard();
            closePop('tc-image-pop');
            const att = (state?.attachments || []).find((a) => a.id === data.attachment?.id)
                || (state?.attachments || [])[0];
            if (att) {
                if (activeVisual === descVisual) setDescMode(true);
                else if (activeVisual === cmtVisual) focusCommentEditor();
                insertDescImage(att);
            }
        } catch (err) { toastError(err.message); }
    });

    document.querySelectorAll('.tc-rich-attach').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const visual = btn.closest('.tc-rich-editor')?.querySelector('.tc-rich-visual');
            if (visual) setActiveRich(visual);
            fillAttachPickList();
            openPop('tc-attach-pop');
        });
    });
    document.getElementById('tc-attach-upload-btn')?.addEventListener('click', () => {
        document.getElementById('tc-attach-file')?.click();
    });
    document.getElementById('tc-attach-file')?.addEventListener('change', async (e) => {
        const file = e.target.files?.[0];
        if (!file || !currentCardId) return;
        const fd = new FormData();
        fd.append('file', file);
        try {
            const res = await fetch(cardUrl(currentCardId, 'attachments'), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.ok === false) throw new Error(data.message || 'Upload failed');
            e.target.value = '';
            await reloadCard();
            closePop('tc-attach-pop');
            const att = (state?.attachments || []).find((a) => a.id === data.attachment?.id)
                || (state?.attachments || [])[0];
            if (att) {
                if (activeVisual === descVisual) setDescMode(true);
                else if (activeVisual === cmtVisual) focusCommentEditor();
                insertDescAttachment(att);
            }
        } catch (err) { toastError(err.message); }
    });

    function openEditorHelp() {
        openPop('tc-help-pop');
    }
    document.querySelectorAll('.tc-rich-help').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            openEditorHelp();
        });
    });
    document.querySelectorAll('.tc-rich-md').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const visual = btn.closest('.tc-rich-editor')?.querySelector('.tc-rich-visual');
            if (visual) setActiveRich(visual);
            let md = '';
            if (visual === cmtVisual) md = commentMarkdown();
            else if (descEditing && descVisual) md = editorHtmlToMarkdown(descVisual);
            else md = document.getElementById('tc-desc')?.value || state?.card?.desc || '';
            const body = document.getElementById('tc-md-body');
            if (body) body.textContent = md || '';
            const copyBtn = document.getElementById('tc-md-copy');
            if (copyBtn) {
                copyBtn.classList.remove('copied');
                copyBtn.innerHTML = '<i class="bi bi-copy"></i> Copy';
            }
            openPop('tc-md-pop');
        });
    });
    document.getElementById('tc-md-copy')?.addEventListener('click', async () => {
        const text = document.getElementById('tc-md-body')?.textContent || '';
        const btn = document.getElementById('tc-md-copy');
        try {
            await navigator.clipboard.writeText(text);
            if (btn) {
                btn.classList.add('copied');
                btn.innerHTML = '<i class="bi bi-check2"></i> Copied';
                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<i class="bi bi-copy"></i> Copy';
                }, 1500);
            }
        } catch (_) {
            // Fallback for older browsers
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (__) {}
            ta.remove();
            if (btn) {
                btn.classList.add('copied');
                btn.innerHTML = '<i class="bi bi-check2"></i> Copied';
            }
        }
    });

    document.getElementById('tc-save-desc').addEventListener('click', async () => {
        try {
            closeDescMenus();
            if (!descDirty) {
                renderDesc(state?.card?.desc || document.getElementById('tc-desc').value || '');
                return;
            }
            const desc = editorHtmlToMarkdown(descVisual);
            const cardId = currentCardId;
            document.getElementById('tc-desc').value = desc;
            // Shown right away; if Trello rejects it the editor re-opens with the text
            await optimistic(() => {
                state.card.desc = desc;
                renderDesc(desc);
            }, () => api(cardUrl(cardId), { method: 'PUT', json: { desc } }), 'Could not save description', () => {
                if (currentCardId !== cardId) return;
                document.getElementById('tc-desc').value = desc;
                setDescMode(true);
                descDirty = true;
                syncDescCancelLabel();
            });
        } catch (err) { toastError(err.message); }
    });

    document.getElementById('tc-hide-details')?.addEventListener('click', () => {
        hideDetails = !hideDetails;
        const btn = document.getElementById('tc-hide-details');
        btn.textContent = hideDetails ? 'Show details' : 'Hide details';
        if (state) renderCard(state);
    });


    // ── Attachment preview lightbox (Trello) ──
    const lightbox = document.getElementById('tc-lightbox');
    let lbList = [];
    let lbIndex = 0;

    function formatBytes(n) {
        if (!n) return '';
        if (n >= 1048576) return (n / 1048576).toFixed(2) + ' MB';
        if (n >= 1024) return Math.round(n / 1024) + ' KB';
        return n + ' B';
    }

    function lbAttachments() {
        // Same order as the Attachments section: newest first
        return (state?.attachments || []).slice().sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0));
    }

    function renderLightbox() {
        const a = lbList[lbIndex];
        if (!a) return closeLightbox();
        const name = a.name || a.fileName || 'file';
        const url = a.download_url || a.url || '#';
        const mime = String(a.mimeType || a.mime_type || '').toLowerCase();
        const stage = document.getElementById('tc-lb-stage');
        if (isImageAttachment(a)) {
            stage.innerHTML = `<img src="${escapeHtml(url)}" alt="${escapeHtml(name)}">`;
        } else if (mime === 'application/pdf' || /\.pdf$/i.test(name)) {
            stage.innerHTML = `<iframe src="${escapeHtml(url)}" title="${escapeHtml(name)}"></iframe>`;
        } else {
            stage.innerHTML = `<div class="tc-lb-file"><div class="ext">${escapeHtml(fileExtension(name))}</div>
                <div>There is no preview available for this attachment.</div></div>`;
        }
        document.getElementById('tc-lb-name').textContent = name;
        const when = a.date ? new Date(a.date).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';
        document.getElementById('tc-lb-meta').textContent = [when && `Added ${when}`, formatBytes(a.bytes)].filter(Boolean).join(' • ');
        document.getElementById('tc-lb-open').href = url;
        const coverId = state?.card?.cover?.idAttachment || state?.card?.idAttachmentCover || null;
        const coverBtn = document.getElementById('tc-lb-cover');
        const canCover = isImageAttachment(a);
        coverBtn.hidden = !canCover && a.id !== coverId;
        coverBtn.querySelector('span').textContent = a.id === coverId ? 'Remove cover' : 'Make cover';
        document.getElementById('tc-lb-prev').hidden = lbList.length < 2;
        document.getElementById('tc-lb-next').hidden = lbList.length < 2;
    }

    function openLightbox(attId) {
        lbList = lbAttachments();
        const i = lbList.findIndex((a) => a.id === attId);
        if (!lightbox || i < 0) return false;
        lbIndex = i;
        renderLightbox();
        lightbox.hidden = false;
        return true;
    }

    function closeLightbox() {
        if (!lightbox) return;
        lightbox.hidden = true;
        document.getElementById('tc-lb-stage').innerHTML = '';
    }

    function stepLightbox(dir) {
        if (lbList.length < 2) return;
        lbIndex = (lbIndex + dir + lbList.length) % lbList.length;
        renderLightbox();
    }

    // Open from the Attachments section (thumbnail / name) and from attachment images in the activity feed
    drawer.addEventListener('click', (e) => {
        const hit = e.target.closest('.tc-att > img, .tc-att > .ph, .tc-att a.name, img.tc-activity-img[data-att]');
        if (!hit) return;
        const attId = hit.dataset.att || hit.closest('.tc-att')?.dataset.attId;
        if (attId && openLightbox(attId)) {
            e.preventDefault();
            e.stopPropagation();
        }
    });

    document.getElementById('tc-lb-close')?.addEventListener('click', closeLightbox);
    document.getElementById('tc-lb-prev')?.addEventListener('click', () => stepLightbox(-1));
    document.getElementById('tc-lb-next')?.addEventListener('click', () => stepLightbox(1));
    lightbox?.addEventListener('click', (e) => {
        // Click on the dark background (not the file or controls) closes
        if (e.target === lightbox || e.target.id === 'tc-lb-stage') closeLightbox();
    });
    document.addEventListener('keydown', (e) => {
        if (!lightbox || lightbox.hidden) return;
        if (e.key === 'Escape') { e.preventDefault(); e.stopImmediatePropagation(); closeLightbox(); }
        else if (e.key === 'ArrowLeft') { e.preventDefault(); stepLightbox(-1); }
        else if (e.key === 'ArrowRight') { e.preventDefault(); stepLightbox(1); }
    }, true);

    document.getElementById('tc-lb-download')?.addEventListener('click', () => {
        const a = lbList[lbIndex];
        if (!a?.download_url) return;
        const link = document.createElement('a');
        link.href = a.download_url;
        link.download = a.name || a.fileName || '';
        document.body.appendChild(link);
        link.click();
        link.remove();
    });
    document.getElementById('tc-lb-cover')?.addEventListener('click', async () => {
        const a = lbList[lbIndex];
        if (!a) return;
        const coverId = state?.card?.cover?.idAttachment || state?.card?.idAttachmentCover || null;
        try {
            await api(cardUrl(currentCardId, 'cover'), {
                method: 'PUT',
                json: { attachment_id: a.id === coverId ? null : a.id },
            });
            await reloadCard();
            lbList = lbAttachments();
            lbIndex = Math.max(0, lbList.findIndex((x) => x.id === a.id));
            renderLightbox();
        } catch (err) { toastError(err.message); }
    });
    document.getElementById('tc-lb-delete')?.addEventListener('click', async () => {
        const a = lbList[lbIndex];
        if (!a || !confirm('Remove this attachment from Trello?')) return;
        const done = removeAttachmentOptimistic(a.id);
        // Move to the next attachment straight away
        lbList = lbAttachments();
        if (!lbList.length) closeLightbox();
        else {
            lbIndex = Math.min(lbIndex, lbList.length - 1);
            renderLightbox();
        }
        await done;
    });

    document.getElementById('tc-att-toggle')?.addEventListener('click', () => {
        attShowAll = !attShowAll;
        if (state) renderCard(state);
    });

    // ── "Cover" popover (Trello-style: size, remove, colors, attachments, upload) ──
    const coverPop = document.getElementById('tc-cover-pop');
    const coverBtn = document.getElementById('tc-cover-btn');
    const COVER_ATTS_LIMIT = 6;
    let coverShowAll = false;

    function closeCoverPop() {
        if (coverPop) coverPop.hidden = true;
    }

    function currentCover() {
        const cover = state?.card?.cover || {};
        return {
            idAttachment: cover.idAttachment || state?.card?.idAttachmentCover || null,
            color: cover.color || null,
            size: cover.size || state?.cover_size || 'normal',
        };
    }

    function renderCoverPop() {
        if (!coverPop) return;
        const cur = currentCover();
        const hasCover = !!(cur.idAttachment || cur.color);
        const img = state?.cover_proxy_url || '';
        const bg = cur.color ? (LABEL_COLORS[cur.color] || '#596773') : '#596773';
        coverPop.querySelectorAll('.js-cover-pv').forEach((el) => {
            el.style.backgroundColor = hasCover ? bg : '#596773';
            el.style.backgroundImage = cur.idAttachment && img ? `url("${img}")` : '';
        });
        coverPop.querySelectorAll('.tc-cover-size').forEach((b) => {
            b.disabled = !hasCover;
            b.classList.toggle('active', hasCover && b.dataset.size === cur.size);
        });
        document.getElementById('tc-cover-remove').hidden = !hasCover;

        document.getElementById('tc-cover-colors').innerHTML = LABEL_COLOR_KEYS.map((key) =>
            `<button type="button" data-cover-color="${key}" title="${key}" aria-label="${key}"
                class="${!cur.idAttachment && cur.color === key ? 'active' : ''}" style="background:${LABEL_COLORS[key]}"></button>`
        ).join('');

        const images = (state?.attachments || []).filter((a) => isImageAttachment(a) && (a.preview_url || a.download_url));
        const shown = coverShowAll ? images : images.slice(0, COVER_ATTS_LIMIT);
        document.getElementById('tc-cover-atts').innerHTML = shown.map((a) =>
            `<button type="button" data-cover-att="${escapeHtml(a.id)}" title="${escapeHtml(a.name || '')}"
                class="${cur.idAttachment === a.id ? 'active' : ''}"
                style="background-image:url('${escapeHtml(a.preview_url || a.download_url)}')"></button>`
        ).join('') || '<span class="tc-muted">No image attachments</span>';
        const more = document.getElementById('tc-cover-more');
        more.hidden = images.length <= COVER_ATTS_LIMIT;
        more.textContent = coverShowAll ? 'Show fewer' : 'Show more';
    }

    function openCoverPop() {
        if (!coverPop || !coverBtn || !state?.card) return;
        closePanels();
        closeMovePop();
        coverShowAll = false;
        renderCoverPop();
        coverPop.hidden = false;
        const dr = drawer.getBoundingClientRect();
        const br = coverBtn.getBoundingClientRect();
        const left = Math.min(br.left - dr.left, dr.width - coverPop.offsetWidth - 8);
        const top = br.bottom - dr.top + 4;
        coverPop.style.left = `${Math.max(8, left)}px`;
        coverPop.style.top = `${top}px`;
        coverPop.style.maxHeight = `${Math.max(240, dr.height - top - 8)}px`;
    }

    async function saveCover(json) {
        try {
            await api(cardUrl(currentCardId, 'cover'), { method: 'PUT', json });
            await reloadCard();
            if (coverPop && !coverPop.hidden) renderCoverPop();
        } catch (err) {
            toastError(err.message || 'Could not update cover');
        }
    }

    coverBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (coverPop && !coverPop.hidden) closeCoverPop();
        else openCoverPop();
    });
    document.getElementById('tc-cover-close')?.addEventListener('click', closeCoverPop);
    coverPop?.addEventListener('click', async (e) => {
        const sizeBtn = e.target.closest('.tc-cover-size');
        if (sizeBtn && !sizeBtn.disabled) {
            const cur = currentCover();
            if (cur.size === sizeBtn.dataset.size) return;
            await saveCover(cur.idAttachment
                ? { attachment_id: cur.idAttachment, size: sizeBtn.dataset.size }
                : { color: cur.color, size: sizeBtn.dataset.size });
            return;
        }
        const colorBtn = e.target.closest('[data-cover-color]');
        if (colorBtn) {
            await saveCover({ color: colorBtn.dataset.coverColor, size: currentCover().size });
            return;
        }
        const attBtn = e.target.closest('[data-cover-att]');
        if (attBtn) {
            await saveCover({ attachment_id: attBtn.dataset.coverAtt, size: currentCover().size });
            return;
        }
        if (e.target.closest('#tc-cover-remove')) {
            await saveCover({ remove: true });
            return;
        }
        if (e.target.closest('#tc-cover-more')) {
            coverShowAll = !coverShowAll;
            renderCoverPop();
            return;
        }
        if (e.target.closest('#tc-cover-upload')) {
            document.getElementById('tc-cover-file')?.click();
        }
    });
    document.getElementById('tc-cover-file')?.addEventListener('change', async (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file || !currentCardId) return;
        const btn = document.getElementById('tc-cover-upload');
        btn.disabled = true;
        btn.textContent = 'Uploading…';
        try {
            const fd = new FormData();
            fd.append('file', file);
            const res = await fetch(cardUrl(currentCardId, 'attachments'), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.ok === false) throw new Error(data.message || 'Upload failed');
            if (data.attachment?.id) {
                await saveCover({ attachment_id: data.attachment.id, size: currentCover().size });
            } else {
                await reloadCard();
            }
        } catch (err) {
            toastError(err.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Upload a cover image';
        }
    });
    drawer.addEventListener('click', (e) => {
        if (!coverPop || coverPop.hidden) return;
        if (!e.target.closest('#tc-cover-pop, #tc-cover-btn')) closeCoverPop();
    }, true);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && coverPop && !coverPop.hidden) {
            e.preventDefault();
            e.stopImmediatePropagation();
            closeCoverPop();
        }
    }, true);

    // ── "Move card" popover (Trello-style: board → list → position) ──
    const movePop = document.getElementById('tc-move-pop');
    const moveBtn = document.getElementById('tc-list-btn');
    const moveBoardSel = document.getElementById('tc-move-board');
    const moveListSel = document.getElementById('tc-move-list');
    const movePosSel = document.getElementById('tc-move-pos');
    const moveSubmit = document.getElementById('tc-move-submit');
    const moveTargetsUrl = `/admin/trello/teams/${teamId}/move-targets`;
    let moveLists = [];
    let moveBoardsLoaded = false;

    function closeMovePop() {
        if (!movePop) return;
        movePop.hidden = true;
        moveBtn?.classList.remove('active');
    }

    function renderMovePositions() {
        const card = state?.card || {};
        const list = moveLists.find((l) => l.id === moveListSel.value);
        const others = (list?.cards || []).filter((c) => c.id !== card.id);
        const sameList = list && list.id === card.idList && moveBoardSel.value === (card.idBoard || boardId);
        const count = others.length + 1;
        let selected = 1;
        if (sameList) {
            const all = (list.cards || []).slice().sort((a, b) => Number(a.pos) - Number(b.pos));
            selected = Math.max(1, all.findIndex((c) => c.id === card.id) + 1);
        }
        movePosSel.innerHTML = Array.from({ length: count }, (_, i) =>
            `<option value="${i + 1}" ${i + 1 === selected ? 'selected' : ''}>${i + 1}</option>`
        ).join('');
    }

    function renderMoveLists(preferListId) {
        moveListSel.innerHTML = moveLists.map((l) =>
            `<option value="${escapeHtml(l.id)}">${escapeHtml(l.name)}</option>`
        ).join('') || '<option value="">No lists</option>';
        if (preferListId && moveLists.some((l) => l.id === preferListId)) moveListSel.value = preferListId;
        renderMovePositions();
    }

    async function loadMoveBoard(targetBoardId, preferListId) {
        moveSubmit.disabled = true;
        moveListSel.innerHTML = '<option value="">Loading…</option>';
        movePosSel.innerHTML = '';
        try {
            const params = new URLSearchParams({ board_id: targetBoardId });
            if (!moveBoardsLoaded) params.set('with_boards', '1');
            const data = await api(`${moveTargetsUrl}?${params}`);
            if (data.boards) {
                moveBoardSel.innerHTML = data.boards.map((b) =>
                    `<option value="${escapeHtml(b.id)}">${escapeHtml(b.name)}</option>`
                ).join('');
                moveBoardsLoaded = true;
            }
            moveBoardSel.value = targetBoardId;
            moveLists = data.lists || [];
            renderMoveLists(preferListId);
        } catch (err) {
            toastError(err.message || 'Could not load lists');
        } finally {
            moveSubmit.disabled = false;
        }
    }

    function openMovePop() {
        if (!movePop || !moveBtn || !state?.card) return;
        closePanels();
        closeCoverPop();
        const dr = drawer.getBoundingClientRect();
        const br = moveBtn.getBoundingClientRect();
        movePop.style.left = `${Math.max(8, br.left - dr.left)}px`;
        movePop.style.top = `${br.bottom - dr.top + 4}px`;
        movePop.hidden = false;
        moveBtn.classList.add('active');
        const card = state.card;
        loadMoveBoard(card.idBoard || boardId, card.idList);
    }

    moveBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (movePop && !movePop.hidden) closeMovePop();
        else openMovePop();
    });
    document.getElementById('tc-move-close')?.addEventListener('click', closeMovePop);
    moveBoardSel?.addEventListener('change', () => loadMoveBoard(moveBoardSel.value, null));
    moveListSel?.addEventListener('change', renderMovePositions);
    // Close on click outside the popover (capture, like the other popovers)
    drawer.addEventListener('click', (e) => {
        if (!movePop || movePop.hidden) return;
        if (!e.target.closest('#tc-move-pop, #tc-list-btn')) closeMovePop();
    }, true);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && movePop && !movePop.hidden) {
            e.preventDefault();
            e.stopImmediatePropagation();
            closeMovePop();
        }
    }, true);

    moveSubmit?.addEventListener('click', async () => {
        const card = state?.card || {};
        const targetBoard = moveBoardSel.value;
        const list = moveLists.find((l) => l.id === moveListSel.value);
        if (!card.id || !list) return;
        // Position N → Trello pos between neighbours (excluding this card)
        const others = (list.cards || []).filter((c) => c.id !== card.id)
            .sort((a, b) => Number(a.pos) - Number(b.pos));
        const idx = Number(movePosSel.value || 1) - 1;
        let pos = 'bottom';
        if (idx <= 0) pos = 'top';
        else if (idx < others.length) pos = (Number(others[idx - 1].pos) + Number(others[idx].pos)) / 2;
        moveSubmit.disabled = true;
        try {
            const json = { list_id: list.id, pos };
            if (targetBoard && targetBoard !== (card.idBoard || boardId)) json.board_id = targetBoard;
            await api(moveTpl.replace('__CARD__', encodeURIComponent(card.id)), { method: 'POST', json });
            closeMovePop();
            window.location.reload();
        } catch (err) {
            toastError(err.message || 'Move failed');
        } finally {
            moveSubmit.disabled = false;
        }
    });

    document.getElementById('tc-dates-close')?.addEventListener('click', () => closePanels());
    document.getElementById('tc-cal-prev-m')?.addEventListener('click', () => { calMonth--; if (calMonth < 0) { calMonth = 11; calYear--; } renderCalendar(); });
    document.getElementById('tc-cal-next-m')?.addEventListener('click', () => { calMonth++; if (calMonth > 11) { calMonth = 0; calYear++; } renderCalendar(); });
    document.getElementById('tc-cal-prev-y')?.addEventListener('click', () => { calYear--; renderCalendar(); });
    document.getElementById('tc-cal-next-y')?.addEventListener('click', () => { calYear++; renderCalendar(); });
    document.getElementById('tc-start-on')?.addEventListener('change', (e) => {
        const on = !!e.target.checked;
        const startIn = document.getElementById('tc-start-date');
        if (startIn) {
            startIn.disabled = !on;
            if (on && !startIn.value) {
                const base = parseMdY(document.getElementById('tc-due-date')?.value) || new Date();
                startIn.value = formatMdY(base);
            }
        }
        renderCalendar();
    });
    document.getElementById('tc-due-on')?.addEventListener('change', (e) => {
        const on = !!e.target.checked;
        const dueDate = document.getElementById('tc-due-date');
        const dueTime = document.getElementById('tc-due-time');
        if (dueDate) dueDate.disabled = !on;
        if (dueTime) dueTime.disabled = !on;
        if (on && dueDate && !dueDate.value) {
            const n = new Date();
            dueDate.value = formatMdY(n);
            calYear = n.getFullYear();
            calMonth = n.getMonth();
        }
        renderCalendar();
    });
    document.getElementById('tc-due-date')?.addEventListener('change', () => {
        const d = parseMdY(document.getElementById('tc-due-date')?.value);
        if (d) { calYear = d.getFullYear(); calMonth = d.getMonth(); }
        renderCalendar();
    });
    document.getElementById('tc-start-date')?.addEventListener('change', () => renderCalendar());

    document.getElementById('tc-save-due')?.addEventListener('click', async () => {
        try {
            const startOn = document.getElementById('tc-start-on')?.checked;
            const dueOn = document.getElementById('tc-due-on')?.checked;
            const dueDate = parseMdY(document.getElementById('tc-due-date')?.value);
            const startDate = parseMdY(document.getElementById('tc-start-date')?.value);
            const time = document.getElementById('tc-due-time')?.value || '12:00';
            const reminder = Number(document.getElementById('tc-due-reminder')?.value ?? -1);
            let dueIso = '';
            if (dueOn && dueDate) {
                const [hh, mm] = time.split(':').map((n) => Number(n) || 0);
                dueDate.setHours(hh, mm, 0, 0);
                dueIso = dueDate.toISOString();
            }
            let startIso = '';
            if (startOn && startDate) {
                startDate.setHours(0, 0, 0, 0);
                startIso = startDate.toISOString();
            }
            const fields = {
                due: dueIso,
                start: startIso,
                dueReminder: Number.isFinite(reminder) ? reminder : -1,
                dueComplete: !!document.getElementById('tc-due-complete')?.checked,
            };
            closePanels();
            await saveDatesOptimistic(fields);
        } catch (err) { toastError(err.message); }
    });

    document.getElementById('tc-clear-due')?.addEventListener('click', async () => {
        closePanels();
        await saveDatesOptimistic({ due: '', start: '', dueComplete: false, dueReminder: -1 });
    });

    /** Dates show on the card (and board tile completion) immediately, then save. */
    async function saveDatesOptimistic(fields) {
        const cardId = currentCardId;
        await optimistic(() => {
            state.card.due = fields.due || null;
            state.card.start = fields.start || null;
            state.card.dueReminder = fields.dueReminder;
            state.card.dueComplete = !!fields.dueComplete;
            const tile = board.querySelector(`.trello-card[data-card-id="${cardId}"]`);
            if (tile) {
                tile.classList.toggle('is-complete', !!fields.dueComplete);
                const icon = tile.querySelector('.js-card-complete i');
                if (icon) icon.className = 'bi ' + (fields.dueComplete ? 'bi-check-circle-fill' : 'bi-circle');
            }
        }, () => api(cardUrl(cardId), { method: 'PUT', json: fields }), 'Could not save dates');
    }

    document.getElementById('tc-add-comment')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const text = commentMarkdown();
        if (!text) return;
        if (editingCommentId && !cmtDirty) {
            clearCommentEditor();
            return;
        }
        // Shown immediately; saved to Trello in the background (typed text comes back if it fails)
        const editId = editingCommentId;
        const draftHtml = cmtVisual?.innerHTML || '';
        const cardId = currentCardId;
        clearCommentEditor();
        await optimistic(() => {
            const feeds = [state.activity, state.comments].filter(Array.isArray);
            if (editId) {
                feeds.forEach((feed) => feed.forEach((a) => {
                    if (a.id === editId) a.data = { ...(a.data || {}), text };
                }));
            } else {
                const temp = {
                    id: 'tmp-' + Date.now(),
                    type: 'commentCard',
                    date: new Date().toISOString(),
                    data: { text },
                    memberCreator: meMember(),
                };
                if (Array.isArray(state.activity)) state.activity.unshift(temp);
                if (Array.isArray(state.comments)) state.comments.unshift({ ...temp });
            }
        }, () => (editId
            ? api(`/admin/trello/teams/${teamId}/comments/${editId}`, { method: 'PUT', json: { text } })
            : api(cardUrl(cardId, 'comments'), { method: 'POST', json: { text } })
        ), 'Could not save comment', () => {
            if (currentCardId !== cardId || !cmtVisual) return;
            focusCommentEditor();
            cmtVisual.innerHTML = draftHtml;
            editingCommentId = editId;
            placeCaretAtEnd(cmtVisual);
        });
        syncCommentSave();
    });

    document.getElementById('tc-cmt-watch')?.addEventListener('change', async (e) => {
        if (!currentCardId) return;
        const subscribed = !!e.target.checked;
        try {
            await api(cardUrl(currentCardId), { method: 'PUT', json: { subscribed } });
            if (state?.card) state.card.subscribed = subscribed;
        } catch (err) {
            e.target.checked = !subscribed;
            toastError(err.message);
        }
    });

    // Checklists whose checked items are hidden (kept across card re-renders).
    const hiddenCheckedLists = new Set();
    let pendingDeleteChecklistId = null;

    document.getElementById('tc-del-checklist-confirm')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const id = pendingDeleteChecklistId;
        if (!id) return;
        closePanels();
        pendingDeleteChecklistId = null;
        if (isTempId(id)) return stillSaving();
        await optimistic(() => {
            state.checklists = (state.checklists || []).filter((cl) => cl.id !== id);
        }, () => api(`/admin/trello/teams/${teamId}/checklists/${id}`, { method: 'DELETE' }), 'Could not delete checklist');
    });

    // "Add an item": stays open after adding so several items can be typed in a row (Trello).
    async function addChecklistItem(btn) {
        const checklistId = btn.dataset.checklist;
        const input = btn.closest('.tc-check-add')?.querySelector('.tc-new-item');
        const name = (input?.value || '').trim();
        if (!name) {
            input?.focus();
            return;
        }
        if (isTempId(checklistId)) return stillSaving();
        const reopen = () => {
            const box = document.querySelector(`.tc-checklist[data-checklist="${checklistId}"] .tc-check-add`);
            box?.classList.add('is-open');
            box?.querySelector('.tc-new-item')?.focus();
        };
        // Item appears at once; the add box stays open for the next one
        await optimistic(() => {
            const cl = (state.checklists || []).find((x) => x.id === checklistId);
            if (!cl) return;
            const maxPos = Math.max(0, ...(cl.checkItems || []).map((it) => Number(it.pos) || 0));
            cl.checkItems = [...(cl.checkItems || []), { id: 'tmp-' + Date.now(), name, state: 'incomplete', pos: maxPos + 1024 }];
            setTimeout(reopen, 0);
        }, () => api(`/admin/trello/teams/${teamId}/checklists/${checklistId}/items`, { method: 'POST', json: { name } }),
        'Could not add checklist item');
    }

    function isTempId(id) {
        return String(id || '').startsWith('tmp-');
    }

    function stillSaving() {
        if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Still saving to Trello — try again in a moment', showConfirmButton: false, timer: 1600 });
    }

    document.getElementById('tc-checklists')?.addEventListener('keydown', (e) => {
        const input = e.target.closest('.tc-new-item');
        if (!input) return;
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            const btn = input.closest('.tc-check-add')?.querySelector('.tc-add-item');
            if (btn) addChecklistItem(btn);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            input.closest('.tc-check-add')?.classList.remove('is-open');
        }
    });

    // Trello "Add checklist": title defaults to "Checklist" (pre-selected), optional copy-from source.
    function openChecklistPanel() {
        const title = document.getElementById('tc-new-checklist');
        const source = document.getElementById('tc-checklist-source');
        if (source) {
            source.innerHTML = '<option value="">(none)</option>' + (state?.checklists || [])
                .map((cl) => `<option value="${escapeHtml(cl.id)}">${escapeHtml(cl.name || 'Checklist')}</option>`)
                .join('');
        }
        if (title) {
            title.value = 'Checklist';
            setTimeout(() => { title.focus(); title.select(); }, 30);
        }
    }

    async function submitChecklist() {
        const btn = document.getElementById('tc-add-checklist');
        const name = document.getElementById('tc-new-checklist').value.trim();
        const sourceId = document.getElementById('tc-checklist-source')?.value || '';
        if (!name) return;
        const cardId = currentCardId;
        closePanels();
        await optimistic(() => {
            const source = (state.checklists || []).find((cl) => cl.id === sourceId);
            const items = (source?.checkItems || []).map((it, i) => ({ ...it, id: `tmp-${Date.now()}-${i}`, state: 'incomplete' }));
            state.checklists = [...(state.checklists || []), { id: 'tmp-' + Date.now(), name, checkItems: items }];
        }, () => api(cardUrl(cardId, 'checklists'), { method: 'POST', json: { name, source_id: sourceId || null } }),
        'Could not add checklist');
        btn.disabled = false;
    }

    document.getElementById('tc-add-checklist').addEventListener('click', submitChecklist);
    document.getElementById('tc-new-checklist')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitChecklist();
        }
    });

    let attachRecentUrl = '';

    function openAttachPanel() {
        closePanels();
        const panel = document.getElementById('panel-attach');
        if (!panel) return;
        panel.classList.add('show');
        anchorPanel(panel);
        document.querySelectorAll('.tc-action[data-panel="attach"]').forEach((b) => b.classList.add('active'));
        const link = document.getElementById('tc-attach-link');
        const text = document.getElementById('tc-attach-link-text');
        if (link) link.value = '';
        if (text) text.value = '';
        attachRecentUrl = '';
        renderAttachRecent();
        setTimeout(() => link?.focus(), 30);
    }

    function renderAttachRecent() {
        const list = document.getElementById('tc-attach-recent');
        if (!list) return;
        // Prefer other open cards on this board as "recently viewed"
        const cards = [...document.querySelectorAll('.trello-card[data-card-id]')].slice(0, 8);
        const items = cards.map((el) => {
            const id = el.dataset.cardId;
            const title = el.querySelector('.trello-card-title')?.textContent?.trim() || 'Card';
            const url = el.querySelector('a[href*="trello.com"]')?.href
                || `https://trello.com/c/${id}`;
            return { id, title, url };
        }).filter((c) => c.id && c.id !== currentCardId);
        if (!items.length) {
            list.innerHTML = '<span class="tc-muted" style="padding:6px 8px;display:block">No recent items</span>';
            return;
        }
        list.innerHTML = items.map((c) => `
            <button type="button" data-url="${escapeHtml(c.url)}" data-name="${escapeHtml(c.title)}" class="${attachRecentUrl === c.url ? 'selected' : ''}">
                <span class="ico"><i class="bi bi-kanban"></i></span>
                <span class="meta">
                    <span class="name">${escapeHtml(c.title)}</span>
                    <span class="sub">Board card</span>
                </span>
            </button>
        `).join('');
        list.querySelectorAll('button[data-url]').forEach((btn) => {
            btn.addEventListener('click', () => {
                attachRecentUrl = btn.dataset.url || '';
                const link = document.getElementById('tc-attach-link');
                const text = document.getElementById('tc-attach-link-text');
                if (link) link.value = attachRecentUrl;
                if (text && !text.value) text.value = btn.dataset.name || '';
                renderAttachRecent();
            });
        });
    }

    async function uploadCardFile(file) {
        if (!file || !currentCardId) return;
        const fd = new FormData();
        fd.append('file', file);
        const res = await fetch(cardUrl(currentCardId, 'attachments'), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) throw new Error(data.message || 'Upload failed');
        await reloadCard();
        closePanels();
        if (window.Swal) Swal.fire({ toast:true, position:'top-end', icon:'success', title:'File attached', showConfirmButton:false, timer:1200 });
    }

    document.getElementById('tc-attach-close')?.addEventListener('click', () => closePanels());
    document.getElementById('tc-attach-cancel')?.addEventListener('click', () => closePanels());
    document.getElementById('tc-attach-choose')?.addEventListener('click', () => {
        document.getElementById('tc-file')?.click();
    });
    document.getElementById('tc-file')?.addEventListener('change', async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        try {
            await uploadCardFile(file);
        } catch (err) { toastError(err.message); }
        e.target.value = '';
    });
    document.getElementById('tc-attach-insert')?.addEventListener('click', async () => {
        if (!currentCardId) return;
        const url = (document.getElementById('tc-attach-link')?.value || '').trim();
        const name = (document.getElementById('tc-attach-link-text')?.value || '').trim();
        if (!url) {
            toastError('Paste a link first');
            return;
        }
        try {
            await api(cardUrl(currentCardId, 'attach-url'), {
                method: 'POST',
                json: { url, name: name || null },
            });
            await reloadCard();
            closePanels();
            if (window.Swal) Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Link attached', showConfirmButton:false, timer:1200 });
        } catch (err) { toastError(err.message); }
    });

    // Drag & drop files onto attach panel / card main
    document.getElementById('panel-attach')?.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
    });
    document.getElementById('panel-attach')?.addEventListener('drop', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        const file = e.dataTransfer?.files?.[0];
        if (!file) return;
        try { await uploadCardFile(file); }
        catch (err) { toastError(err.message); }
    });

    // Delegated clicks inside drawer
    document.getElementById('tc-content').addEventListener('click', async (e) => {
        const editLabelBtn = e.target.closest('[data-edit-label]');
        if (editLabelBtn) {
            const id = editLabelBtn.dataset.editLabel;
            const label = (state?.board_labels || []).find((l) => l.id === id);
            showLabelsForm('edit', label || { id, name: '', color: 'green' });
            return;
        }
        const labelBtn = e.target.closest('[data-label]');
        if (labelBtn) {
            const id = labelBtn.dataset.label;
            const active = labelBtn.dataset.active !== '1';
            const cardId = currentCardId;
            await optimistic(() => {
                const c = state.card;
                const ids = new Set(c.idLabels || (c.labels || []).map((l) => l.id));
                if (active) ids.add(id);
                else ids.delete(id);
                const byId = Object.fromEntries((state.board_labels || []).map((l) => [l.id, l]));
                c.idLabels = [...ids];
                c.labels = c.idLabels.map((x) => (c.labels || []).find((l) => l.id === x) || byId[x]).filter(Boolean);
                state.labels = c.labels;
                // Board tile labels too
                const tile = board.querySelector(`.trello-card[data-card-id="${cardId}"]`);
                if (tile) {
                    const html = c.labels.map((l) => `<span class="trello-label" title="${escapeHtml(l.name || '')}" style="background:${LABEL_COLORS[l.color] || '#9fadbc'}"></span>`).join('');
                    let wrap = tile.querySelector('.trello-labels');
                    if (!wrap && html) {
                        wrap = document.createElement('div');
                        wrap.className = 'trello-labels';
                        tile.querySelector('.trello-card-body')?.prepend(wrap);
                    }
                    if (wrap) {
                        if (html) wrap.innerHTML = html;
                        else wrap.remove();
                    }
                }
            }, () => api(cardUrl(cardId, 'labels'), { method: 'POST', json: { label_id: id, active } }), 'Could not update label');
            return;
        }
        const memBtn = e.target.closest('[data-member]');
        if (memBtn) {
            const id = memBtn.dataset.member;
            const active = memBtn.dataset.active !== '1';
            const cardId = currentCardId;
            await optimistic(() => setCardMemberLocal(id, active),
                () => api(cardUrl(cardId, 'members'), { method: 'POST', json: { member_id: id, active } }),
                'Could not update members');
            return;
        }
        const reply = e.target.closest('.tc-reply');
        if (reply) {
            const row = reply.closest('[data-action]');
            const actId = row?.dataset.action;
            const feed = state?.activity || state?.comments || [];
            const act = actId ? feed.find((a) => a.id === actId) : null;
            editingCommentId = null;
            startReplyTo(act);
            return;
        }
        const editComment = e.target.closest('.tc-edit-comment');
        if (editComment && isTempId(editComment.dataset.action)) {
            stillSaving();
            return;
        }
        if (editComment) {
            const feed = state?.activity || state?.comments || [];
            const act = feed.find((a) => a.id === editComment.dataset.action);
            startEditComment(act);
            return;
        }
        const linkAttach = e.target.closest('.tc-link-attach');
        if (linkAttach) {
            const url = linkAttach.dataset.url;
            if (!url || !currentCardId) return;
            try {
                await api(cardUrl(currentCardId, 'attach-url'), { method: 'POST', json: { url } });
                await reloadCard();
                if (window.Swal) Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Link attached', showConfirmButton:false, timer:1200 });
            } catch (err) { toastError(err.message); }
            return;
        }
        const reactBtn = e.target.closest('.tc-react');
        if (reactBtn) {
            const row = reactBtn.closest('[data-action]');
            openEmojiPicker(reactBtn, { mode: 'react', actionId: row?.dataset.action || '' });
            return;
        }
        const delComment = e.target.closest('.tc-del-comment');
        if (delComment) {
            pendingDeleteCommentId = delComment.dataset.action || '';
            if (!pendingDeleteCommentId) return;
            openPop('tc-del-comment-pop');
            return;
        }
        const delCl = e.target.closest('.tc-del-checklist');
        if (delCl) {
            // Trello-style confirm popover under the Delete button
            const panel = document.getElementById('panel-del-checklist');
            const wasOpen = panel?.classList.contains('show') && pendingDeleteChecklistId === delCl.dataset.checklist;
            closePanels();
            if (!panel || wasOpen) return;
            pendingDeleteChecklistId = delCl.dataset.checklist;
            panel.classList.add('show');
            anchorPanel(panel);
            return;
        }
        const toggleChecked = e.target.closest('.tc-toggle-checked');
        if (toggleChecked) {
            const id = toggleChecked.dataset.checklist;
            const hide = !hiddenCheckedLists.has(id);
            if (hide) hiddenCheckedLists.add(id);
            else hiddenCheckedLists.delete(id);
            const wrap = toggleChecked.closest('.tc-checklist');
            wrap?.classList.toggle('hide-checked', hide);
            const doneCount = wrap ? wrap.querySelectorAll('.tc-checkitem.done').length : 0;
            toggleChecked.textContent = hide ? `Show checked items (${doneCount})` : 'Hide checked items';
            return;
        }
        const addOpen = e.target.closest('.tc-check-add-open');
        if (addOpen) {
            document.querySelectorAll('.tc-check-add.is-open').forEach((el) => el.classList.remove('is-open'));
            const box = addOpen.closest('.tc-check-add');
            box?.classList.add('is-open');
            box?.querySelector('.tc-new-item')?.focus();
            return;
        }
        const addCancel = e.target.closest('.tc-check-add-cancel');
        if (addCancel) {
            addCancel.closest('.tc-check-add')?.classList.remove('is-open');
            return;
        }
        const addItem = e.target.closest('.tc-add-item');
        if (addItem) {
            await addChecklistItem(addItem);
            return;
        }
        const delItem = e.target.closest('.tc-del-item');
        if (delItem) {
            e.preventDefault(); // the row is a <label>: don't toggle the checkbox
            const { checklist: clId, item: itemId } = delItem.dataset;
            if (isTempId(itemId) || isTempId(clId)) return stillSaving();
            await optimistic(() => {
                const cl = (state.checklists || []).find((x) => x.id === clId);
                if (cl) cl.checkItems = (cl.checkItems || []).filter((it) => it.id !== itemId);
            }, () => api(`/admin/trello/teams/${teamId}/checklists/${clId}/items/${itemId}`, { method: 'DELETE' }),
            'Could not delete checklist item');
            return;
        }
        const attMore = e.target.closest('.tc-att-more');
        if (attMore) {
            const menu = attMore.parentElement?.querySelector('.tc-att-menu');
            const open = menu?.classList.contains('show');
            closeAttMenus();
            if (menu && !open) {
                menu.classList.add('show');
                attMore.setAttribute('aria-expanded', 'true');
            }
            return;
        }
        const attEdit = e.target.closest('.tc-att-edit');
        if (attEdit) {
            closeAttMenus();
            const current = attEdit.dataset.name || 'file';
            const name = window.prompt('Edit attachment name', current);
            if (name === null) return;
            const trimmed = name.trim();
            if (!trimmed || trimmed === current) return;
            const attId = attEdit.dataset.att;
            const cardId = currentCardId;
            await optimistic(() => {
                (state.attachments || []).forEach((a) => { if (a.id === attId) a.name = trimmed; });
            }, () => api(cardUrl(cardId, 'attachments/' + attId), { method: 'PUT', json: { name: trimmed } }),
            'Could not rename attachment');
            return;
        }
        const attComment = e.target.closest('.tc-att-comment');
        if (attComment) {
            closeAttMenus();
            const fileName = attComment.dataset.name || 'file';
            focusCommentEditor();
            const prefix = `[${fileName}] `;
            const current = commentMarkdown();
            if (!current.includes(prefix)) {
                insertDescHtml(escapeHtml(prefix));
            }
            return;
        }
        const attDownload = e.target.closest('.tc-att-download');
        if (attDownload) {
            closeAttMenus();
            const url = attDownload.dataset.url;
            if (url) {
                const a = document.createElement('a');
                a.href = url;
                a.download = attDownload.dataset.name || '';
                a.target = '_blank';
                a.rel = 'noopener';
                document.body.appendChild(a);
                a.click();
                a.remove();
            }
            return;
        }
        const makeCover = e.target.closest('.tc-att-make-cover');
        if (makeCover) {
            closeAttMenus();
            try {
                await api(cardUrl(currentCardId, 'cover'), {
                    method: 'PUT',
                    json: { attachment_id: makeCover.dataset.att },
                });
                await reloadCard();
            } catch (err) { toastError(err.message); }
            return;
        }
        const removeCover = e.target.closest('.tc-att-remove-cover');
        if (removeCover) {
            closeAttMenus();
            try {
                await api(cardUrl(currentCardId, 'cover'), {
                    method: 'PUT',
                    json: { attachment_id: null },
                });
                await reloadCard();
            } catch (err) { toastError(err.message); }
            return;
        }
        const attRemove = e.target.closest('.tc-att-remove');
        if (attRemove) {
            closeAttMenus();
            if (!confirm('Remove this attachment from Trello?')) return;
            await removeAttachmentOptimistic(attRemove.dataset.att);
        }
    });

    /** Attachment disappears at once (and stops being the cover), then the delete is sent. */
    async function removeAttachmentOptimistic(attId) {
        const cardId = currentCardId;
        await optimistic(() => {
            state.attachments = (state.attachments || []).filter((a) => a.id !== attId);
            const c = state.card;
            if (c?.cover?.idAttachment === attId || c?.idAttachmentCover === attId) {
                if (c.cover) c.cover.idAttachment = null;
                c.idAttachmentCover = null;
                state.cover_proxy_url = null;
            }
        }, () => api(cardUrl(cardId, 'attachments/' + attId), { method: 'DELETE' }), 'Could not remove attachment');
    }

    function closeAttMenus() {
        document.querySelectorAll('.tc-att-menu.show').forEach((m) => m.classList.remove('show'));
        document.querySelectorAll('.tc-att-more[aria-expanded="true"]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.tc-att .ops')) closeAttMenus();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeAttMenus();
    });

    document.getElementById('tc-content').addEventListener('change', async (e) => {
        const cb = e.target.closest('.tc-check');
        if (!cb || !currentCardId) return;
        const itemId = cb.dataset.item;
        if (isTempId(itemId)) { cb.checked = !cb.checked; return stillSaving(); }
        const stateVal = cb.checked ? 'complete' : 'incomplete';
        const cardId = currentCardId;
        await optimistic(() => {
            (state.checklists || []).forEach((cl) => (cl.checkItems || []).forEach((it) => {
                if (it.id === itemId) it.state = stateVal;
            }));
        }, () => api(`/admin/trello/teams/${teamId}/cards/${cardId}/checkitems/${itemId}`, {
            method: 'PUT',
            json: { state: stateVal },
        }), 'Could not update checklist item');
    });

    // ── Live sync: short tick polls (no SSE — SSE blocked PHP on localhost) ──
    let lastBoardActivity = @json($board['dateLastActivity'] ?? null);
    let lastLiveVersion = 0;
    let liveBusy = false;
    let tickBusy = false;
    let tickTimer = null;
    let liveMode = 'tick';
    const TICK_MS = 4000;

    /** Trello due badge: "Oct 2" (year only when not this year), red overdue / yellow <24h / green complete. */
    function cardDueBadge(due, dueComplete) {
        if (!due) return '';
        const d = new Date(due);
        if (Number.isNaN(d.getTime())) return '';
        const opts = { month: 'short', day: 'numeric' };
        if (d.getFullYear() !== new Date().getFullYear()) opts.year = 'numeric';
        const text = d.toLocaleDateString('en-US', opts);
        const ms = d.getTime() - Date.now();
        let cls = '';
        let title = 'This card is due later.';
        if (dueComplete) { cls = 'is-due-complete'; title = 'This card is complete.'; }
        else if (ms < 0) { cls = 'is-due-overdue'; title = 'This card is past due.'; }
        else if (ms < 24 * 3600 * 1000) { cls = 'is-due-soon'; title = 'This card is due in less than twenty-four hours.'; }
        return `<span class="trello-badge ${cls}" title="${title}"><i class="bi bi-clock"></i>${escapeHtml(text)}</span>`;
    }

    function cardBadgesHtml(c) {
        const b = c.badges || {};
        const out = [];
        if (b.subscribed) out.push(`<span class="trello-badge" title="You are watching this card."><i class="bi bi-eye"></i></span>`);
        out.push(cardDueBadge(c.due, c.dueComplete));
        if (b.description) out.push(`<span class="trello-badge" title="This card has a description."><i class="bi bi-text-left"></i></span>`);
        if (b.comments > 0) out.push(`<span class="trello-badge" title="Comments"><i class="bi bi-chat"></i>${b.comments}</span>`);
        if (b.attachments > 0) out.push(`<span class="trello-badge" title="Attachments"><i class="bi bi-paperclip"></i>${b.attachments}</span>`);
        if (b.checkItems > 0) {
            const done = b.checkItemsChecked >= b.checkItems;
            out.push(`<span class="trello-badge${done ? ' is-check-done' : ''}" title="Checklist items"><i class="bi bi-check2-square"></i>${b.checkItemsChecked || 0}/${b.checkItems}</span>`);
        }
        const members = (c.idMembers || []).map((id) => {
            const m = BOARD_MEMBERS.get(id) || {};
            const name = m.fullName || m.username || '?';
            const ini = m.initials || initials(name);
            return `<span class="trello-card-avatar" title="${escapeHtml(name)}" style="background:${avatarColor(name)};color:#fff">${escapeHtml(ini)}</span>`;
        }).join('');
        const left = out.filter(Boolean).join('');
        if (!left && !members) return '';
        return `<div class="trello-card-badges">${left}${members ? `<span class="trello-card-members">${members}</span>` : ''}</div>`;
    }

    function cardTileHtml(c) {
        const labels = (c.labels || []).map((l) => {
            const col = LABEL_COLORS[l.color] || '#9fadbc';
            return `<span class="trello-label" title="${escapeHtml(l.name || '')}" style="background:${col}"></span>`;
        }).join('');
        let cover = '';
        if (c.cover_proxy_url) {
            cover = `<img src="${escapeHtml(c.cover_proxy_url)}" alt="" class="trello-card-cover" loading="lazy">`;
        } else if (c.cover_color) {
            cover = `<div class="trello-card-cover-color" style="background:${escapeHtml(c.cover_color)}"></div>`;
        }
        const complete = !!c.dueComplete;
        return `<div class="trello-card${complete ? ' is-complete' : ''}" data-card-id="${escapeHtml(c.id)}" data-pos="${escapeHtml(String(c.pos ?? ''))}" data-activity="${escapeHtml(c.dateLastActivity || '')}">
            ${cover}
            <button type="button" class="trello-card-edit" title="Open card" aria-label="Open card"><i class="bi bi-pencil"></i></button>
            <div class="trello-card-body">
                ${labels ? `<div class="trello-labels">${labels}</div>` : ''}
                <div class="trello-card-name-row">
                    <button type="button" class="trello-card-check js-card-complete" title="${complete ? 'Mark incomplete' : 'Mark complete'}"
                            aria-label="${complete ? 'Mark incomplete' : 'Mark complete'}">
                        <i class="bi ${complete ? 'bi-check-circle-fill' : 'bi-circle'}"></i>
                    </button>
                    <div class="trello-card-title">${escapeHtml(c.name || '')}</div>
                </div>
                ${cardBadgesHtml(c)}
            </div>
        </div>`;
    }

    function applyLiveSnapshot(data) {
        const remoteLists = (data.lists || []).map((l) => l.id).filter(Boolean).sort().join(',');
        const localLists = [...board.querySelectorAll('.trello-list')].map((l) => l.dataset.listId).filter(Boolean).sort().join(',');
        if (remoteLists !== localLists) {
            window.location.reload();
            return;
        }

        const cards = data.cards || [];
        const byList = {};
        cards.forEach((c) => {
            if (!c.idList) return;
            (byList[c.idList] ||= []).push(c);
        });

        board.querySelectorAll('.js-card-list').forEach((listEl) => {
            const listId = listEl.dataset.listId;
            const listCards = (byList[listId] || []).slice().sort((a, b) => Number(a.pos || 0) - Number(b.pos || 0));
            const empty = listEl.querySelector('.js-empty-msg');
            listCards.forEach((c) => {
                let el = board.querySelector(`.trello-card[data-card-id="${c.id}"]`);
                if (!el) {
                    const wrap = document.createElement('div');
                    wrap.innerHTML = cardTileHtml(c);
                    el = wrap.firstElementChild;
                } else if ((el.dataset.activity || '') !== (c.dateLastActivity || '')) {
                    const wrap = document.createElement('div');
                    wrap.innerHTML = cardTileHtml(c);
                    const neu = wrap.firstElementChild;
                    if (neu) {
                        el.replaceWith(neu);
                        el = neu;
                    }
                } else {
                    el.dataset.pos = String(c.pos ?? '');
                    const title = el.querySelector('.trello-card-title');
                    if (title && title.textContent !== (c.name || '')) title.textContent = c.name || '';
                }
                if (el && empty) listEl.insertBefore(el, empty);
                else if (el) listEl.appendChild(el);
            });
            refreshListUi(listEl);
        });

        const keep = new Set(cards.map((c) => c.id));
        board.querySelectorAll('.trello-card').forEach((el) => {
            if (!keep.has(el.dataset.cardId)) el.remove();
        });
        board.querySelectorAll('.js-card-list').forEach(refreshListUi);
    }

    async function refreshFromLive() {
        if (!liveUrl || liveBusy || dragged) return;
        if (board.querySelector('.trello-card.is-saving')) return;
        liveBusy = true;
        try {
            const data = await api(liveUrl);
            lastBoardActivity = data.board?.dateLastActivity || lastBoardActivity;
            applyLiveSnapshot(data);

            if (currentCardId && !descEditing) {
                const remote = (data.cards || []).find((c) => c.id === currentCardId);
                const localAct = state?.card?.dateLastActivity || '';
                const typing = document.activeElement === cmtVisual || document.activeElement === commentText;
                if (remote && !typing && remote.dateLastActivity && remote.dateLastActivity !== localAct) {
                    // Re-sync the open card (skipped while one of our own saves is still in flight)
                    syncCardSoon(0);
                }
            }
        } catch (_) {
            // ignore — next tick retries
        } finally {
            liveBusy = false;
        }
    }

    async function pollTick() {
        // Pause while a card is loading so artisan serve is not fighting concurrent Trello calls.
        if (!tickUrl || tickBusy || dragged || cardLoading || document.hidden) return;
        tickBusy = true;
        try {
            const data = await api(tickUrl);
            const v = Number(data.v || 0);
            const activity = data.activity || null;
            const changed = !!data.changed || (v > 0 && v !== lastLiveVersion) || (activity && activity !== lastBoardActivity);
            if (v) lastLiveVersion = v;
            if (activity) lastBoardActivity = activity;
            if (changed) await refreshFromLive();
        } catch (_) {
            // quiet
        } finally {
            tickBusy = false;
        }
    }

    function stopTickPoll() {
        if (tickTimer) clearInterval(tickTimer);
        tickTimer = null;
    }

    function startTickPoll() {
        if (tickTimer || document.hidden) return;
        // With live Pusher updates the poll is only a safety net
        tickTimer = setInterval(pollTick, realtimeOn ? REALTIME_TICK_MS : TICK_MS);
        pollTick();
    }

    // ── Real-time: Trello webhook → CRM → Pusher → this page ──
    const REALTIME_TICK_MS = 30000;
    let realtimeOn = false;
    let realtimeRefreshTimer = null;

    function setRealtime(on) {
        if (realtimeOn === on) return;
        realtimeOn = on;
        stopTickPoll();
        startTickPoll();
    }

    /** Fetch the fresh board (and open card) once a burst of Trello events settles. */
    function realtimeRefreshSoon() {
        clearTimeout(realtimeRefreshTimer);
        realtimeRefreshTimer = setTimeout(async () => {
            if (liveBusy || dragged) return realtimeRefreshSoon();
            await refreshFromLive();
        }, 250);
    }

    /** Apply what the webhook told us right away, before the fresh data arrives. */
    function applyRemoteAction(action, cardId) {
        const type = action?.type || '';
        const data = action?.data || {};
        const openCard = !!(state?.card && currentCardId && currentCardId === cardId);
        const feeds = () => [state.activity, state.comments].filter(Array.isArray);

        if (type === 'commentCard' && openCard && action.id) {
            const known = feeds().some((f) => f.some((a) => a.id === action.id));
            if (!known) {
                // Our own just-posted comment comes back too: swap the temporary one instead of duplicating
                const temp = (state.activity || []).find((a) => isTempId(a.id) && a.data?.text === data.text);
                if (temp) {
                    feeds().forEach((f) => f.forEach((a) => { if (a === temp || (isTempId(a.id) && a.data?.text === data.text)) a.id = action.id; }));
                } else {
                    const entry = { id: action.id, type, date: action.date, data: { text: data.text || '' }, memberCreator: action.memberCreator || {} };
                    feeds().forEach((f) => f.unshift({ ...entry }));
                }
                if (!descEditing) rerenderCard();
            }
        } else if (type === 'updateComment' && openCard && data.action?.id) {
            feeds().forEach((f) => f.forEach((a) => { if (a.id === data.action.id) a.data = { ...(a.data || {}), text: data.action.text || '' }; }));
            if (!descEditing) rerenderCard();
        } else if (type === 'deleteComment' && openCard && data.action?.id) {
            if (Array.isArray(state.activity)) state.activity = state.activity.filter((a) => a.id !== data.action.id);
            if (Array.isArray(state.comments)) state.comments = state.comments.filter((a) => a.id !== data.action.id);
            if (!descEditing) rerenderCard();
        }

        // Board tile: rename / move / archive straight away
        if (type === 'updateCard' && cardId) {
            const tile = board.querySelector(`.trello-card[data-card-id="${cardId}"]`);
            if (!tile) return;
            if (data.old && 'name' in data.old && data.card?.name) {
                const title = tile.querySelector('.trello-card-title');
                if (title) title.textContent = data.card.name;
            }
            if (data.card?.closed) {
                const list = tile.closest('.js-card-list');
                tile.remove();
                if (list) refreshListUi(list);
            } else if (data.listAfter?.id) {
                const from = tile.closest('.js-card-list');
                const to = board.querySelector(`.js-card-list[data-list-id="${data.listAfter.id}"]`);
                if (to && to !== from) {
                    const pos = Number(data.card?.pos);
                    const before = [...to.querySelectorAll('.trello-card')].find((t) => Number(t.dataset.pos) > pos)
                        || to.querySelector('.js-empty-msg');
                    to.insertBefore(tile, before || null);
                    if (Number.isFinite(pos)) tile.dataset.pos = String(pos);
                    if (from) refreshListUi(from);
                    refreshListUi(to);
                }
            }
        }
    }

    function bootRealtime() {
        const key = board.dataset.pusherKey;
        if (!key || typeof Pusher === 'undefined') return;
        const pusher = new Pusher(key, {
            cluster: board.dataset.pusherCluster || 'mt1',
            channelAuthorization: {
                endpoint: board.dataset.broadcastAuth,
                transport: 'ajax',
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            },
        });
        const channel = pusher.subscribe('private-trello.team.' + teamId);
        let subscribed = false;
        channel.bind('pusher:subscription_succeeded', () => {
            subscribed = true;
            setRealtime(pusher.connection.state === 'connected');
        });
        channel.bind('pusher:subscription_error', () => {
            subscribed = false;
            setRealtime(false);
        });
        // Dropped connection → fast polling again until Pusher is back (it reconnects on its own)
        pusher.connection.bind('state_change', ({ current }) => {
            setRealtime(subscribed && current === 'connected');
            if (current === 'connected' && subscribed) realtimeRefreshSoon(); // catch up on missed events
        });
        channel.bind('trello.changed', (payload) => {
            if (!payload || payload.boardId !== boardId) return;
            try { applyRemoteAction(payload.action || {}, payload.cardId || ''); } catch (err) { console.error(err); }
            realtimeRefreshSoon();
        });
    }

    async function bootLiveSync() {
        bootRealtime();
        if (liveEnsureUrl) {
            try {
                const meta = await api(liveEnsureUrl, { method: 'POST', json: {} });
                liveMode = meta.mode || 'tick';
            } catch (_) {
                liveMode = 'tick';
            }
        }
        startTickPoll();
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stopTickPoll();
        else startTickPoll();
    });
    bootLiveSync();
})();
</script>
@endsection
