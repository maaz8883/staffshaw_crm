{{-- Trello-style quick search (Cards + Boards). Requires $team. --}}
@php
    $searchUrl = route('admin.trello.search', $team);
    $variant = $variant ?? 'light'; // light = boards index, dark = board toolbar
@endphp
<div class="tr-qs {{ $variant === 'dark' ? 'is-dark' : 'is-light' }}" data-tr-qs data-search-url="{{ $searchUrl }}">
    <div class="tr-qs-box">
        <i class="bi bi-search tr-qs-icon" aria-hidden="true"></i>
        <input type="search" class="tr-qs-input" placeholder="Search cards and boards…"
               autocomplete="off" aria-autocomplete="list" aria-controls="tr-qs-results"
               aria-label="Search Trello cards and boards">
        <button type="button" class="tr-qs-clear" hidden aria-label="Clear search">&times;</button>
    </div>
    <div class="tr-qs-drop" id="tr-qs-results" hidden role="listbox"></div>
</div>

@once
@push('styles')
<style>
    .tr-qs { position: relative; width: min(360px, 100%); z-index: 40; }
    .tr-qs-box {
        display: flex; align-items: center; gap: 8px;
        border-radius: 8px; padding: 0 10px; height: 36px;
        border: 1px solid rgba(255,255,255,.22);
        background: rgba(0,0,0,.28);
        transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
    }
    .tr-qs.is-light .tr-qs-box {
        background: var(--bg-card, #fff);
        border-color: var(--border-color, rgba(0,0,0,.14));
    }
    .tr-qs.is-open .tr-qs-box,
    .tr-qs-box:focus-within {
        border-color: #579dff;
        box-shadow: 0 0 0 2px rgba(87,157,255,.25);
    }
    .tr-qs-icon { color: rgba(255,255,255,.7); font-size: 14px; flex: 0 0 auto; }
    .tr-qs.is-light .tr-qs-icon { color: var(--text-muted, #6b778c); }
    .tr-qs-input {
        flex: 1 1 auto; min-width: 0; border: 0; outline: 0;
        background: transparent; color: #fff; font-size: 14px; height: 100%;
    }
    .tr-qs.is-light .tr-qs-input { color: var(--text-color, #172b4d); }
    .tr-qs-input::placeholder { color: rgba(255,255,255,.55); }
    .tr-qs.is-light .tr-qs-input::placeholder { color: var(--text-muted, #8c9bab); }
    .tr-qs-clear {
        border: 0; background: transparent; color: rgba(255,255,255,.7);
        font-size: 18px; line-height: 1; cursor: pointer; padding: 0 2px;
    }
    .tr-qs.is-light .tr-qs-clear { color: var(--text-muted, #6b778c); }
    .tr-qs-drop {
        position: absolute; left: 0; top: calc(100% + 6px);
        width: min(480px, calc(100vw - 24px));
        max-height: min(420px, 70vh); overflow: auto;
        border-radius: 10px; padding: 8px 0 0;
        background: #282e33; color: #f1f2f4;
        border: 1px solid rgba(255,255,255,.08);
        box-shadow: 0 12px 32px rgba(0,0,0,.45);
    }
    .tr-qs.is-light .tr-qs-drop {
        background: var(--bg-card, #fff);
        color: var(--text-color, #172b4d);
        border-color: var(--border-color, rgba(0,0,0,.1));
        box-shadow: 0 12px 28px rgba(0,0,0,.14);
    }
    .tr-qs-sec {
        padding: 6px 14px 4px; font-size: 11px; font-weight: 700;
        letter-spacing: .04em; text-transform: uppercase;
        color: #8c9bab;
    }
    .tr-qs.is-light .tr-qs-sec { color: var(--text-muted, #6b778c); }
    .tr-qs-item {
        display: flex; align-items: flex-start; gap: 10px;
        padding: 8px 14px; text-decoration: none; color: inherit;
        cursor: pointer; border: 0; width: 100%; background: transparent; text-align: left;
    }
    .tr-qs-item:hover, .tr-qs-item.is-active { background: rgba(255,255,255,.08); }
    .tr-qs.is-light .tr-qs-item:hover,
    .tr-qs.is-light .tr-qs-item.is-active { background: rgba(9,30,66,.06); }
    .tr-qs-ico {
        width: 28px; height: 28px; border-radius: 4px; flex: 0 0 28px;
        display: inline-flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,.08); color: #b6c2cf; font-size: 14px;
        margin-top: 1px;
    }
    .tr-qs.is-light .tr-qs-ico { background: rgba(9,30,66,.06); color: #626f86; }
    .tr-qs-thumb {
        width: 28px; height: 28px; border-radius: 4px; flex: 0 0 28px;
        background: #0079bf center/cover no-repeat; margin-top: 1px;
    }
    .tr-qs-text { min-width: 0; flex: 1 1 auto; }
    .tr-qs-title {
        display: block; font-size: 14px; font-weight: 600; line-height: 1.25;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tr-qs-sub {
        display: block; font-size: 12px; color: #8c9bab; margin-top: 2px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tr-qs.is-light .tr-qs-sub { color: var(--text-muted, #6b778c); }
    .tr-qs-empty, .tr-qs-loading {
        padding: 16px 14px; font-size: 13px; color: #8c9bab; text-align: center;
    }
    .tr-qs-foot {
        position: sticky; bottom: 0; margin-top: 4px;
        border-top: 1px solid rgba(255,255,255,.08);
        background: inherit; padding: 8px 14px;
        display: flex; align-items: center; gap: 8px;
        font-size: 13px; color: #b6c2cf;
    }
    .tr-qs.is-light .tr-qs-foot {
        border-top-color: var(--border-color, rgba(0,0,0,.08));
        color: var(--text-muted, #44546f);
    }
    .tr-qs-kbd {
        margin-left: auto; font-size: 11px; padding: 2px 6px; border-radius: 4px;
        border: 1px solid rgba(255,255,255,.15); color: #8c9bab;
    }
    .tr-qs.is-light .tr-qs-kbd { border-color: rgba(0,0,0,.12); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function initQuickSearch(root) {
        if (!root || root.dataset.bound === '1') return;
        root.dataset.bound = '1';
        const url = root.dataset.searchUrl;
        const input = root.querySelector('.tr-qs-input');
        const drop = root.querySelector('.tr-qs-drop');
        const clearBtn = root.querySelector('.tr-qs-clear');
        if (!url || !input || !drop) return;

        let timer = null;
        let seq = 0;
        let items = [];
        let active = -1;

        function close() {
            drop.hidden = true;
            root.classList.remove('is-open');
            items = [];
            active = -1;
        }

        function openDrop() {
            drop.hidden = false;
            root.classList.add('is-open');
        }

        function setActive(i) {
            active = i;
            drop.querySelectorAll('.tr-qs-item').forEach((el, idx) => {
                el.classList.toggle('is-active', idx === active);
            });
            const el = drop.querySelectorAll('.tr-qs-item')[active];
            el?.scrollIntoView({ block: 'nearest' });
        }

        function render(data, q) {
            const cards = data.cards || [];
            const boards = data.boards || [];
            items = [];
            let html = '';
            if (!cards.length && !boards.length) {
                html = '<div class="tr-qs-empty">No cards or boards match “' + esc(q) + '”</div>';
            } else {
                if (cards.length) {
                    html += '<div class="tr-qs-sec">Cards</div>';
                    cards.forEach((c) => {
                        items.push(c.url);
                        html += '<a class="tr-qs-item" href="' + esc(c.url) + '" role="option">' +
                            '<span class="tr-qs-ico"><i class="bi bi-card-heading"></i></span>' +
                            '<span class="tr-qs-text"><span class="tr-qs-title">' + esc(c.name) + '</span>' +
                            '<span class="tr-qs-sub">' + esc(c.subtitle || '') + '</span></span></a>';
                    });
                }
                if (boards.length) {
                    html += '<div class="tr-qs-sec">Boards</div>';
                    boards.forEach((b) => {
                        items.push(b.url);
                        const thumbStyle = b.bg_image
                            ? 'background-image:url(\'' + String(b.bg_image).replace(/'/g, '%27') + '\')'
                            : 'background:' + esc(b.bg || '#0079bf');
                        html += '<a class="tr-qs-item" href="' + esc(b.url) + '" role="option">' +
                            '<span class="tr-qs-thumb" style="' + thumbStyle + '"></span>' +
                            '<span class="tr-qs-text"><span class="tr-qs-title">' + esc(b.name) + '</span>' +
                            '<span class="tr-qs-sub">' + esc(b.subtitle || '') + '</span></span></a>';
                    });
                }
            }
            html += '<div class="tr-qs-foot"><i class="bi bi-search"></i> Advanced search' +
                '<span class="tr-qs-kbd">Enter</span></div>';
            drop.innerHTML = html;
            openDrop();
            active = items.length ? 0 : -1;
            if (active >= 0) setActive(active);
        }

        function runSearch(q) {
            const my = ++seq;
            drop.innerHTML = '<div class="tr-qs-loading">Searching…</div>';
            openDrop();
            fetch(url + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }).then((r) => r.json()).then((data) => {
                if (my !== seq) return;
                if (!data || data.ok === false) {
                    drop.innerHTML = '<div class="tr-qs-empty">' + esc(data?.message || 'Search failed') + '</div>';
                    openDrop();
                    return;
                }
                render(data, q);
            }).catch(() => {
                if (my !== seq) return;
                drop.innerHTML = '<div class="tr-qs-empty">Search failed</div>';
                openDrop();
            });
        }

        input.addEventListener('input', () => {
            const q = input.value.trim();
            clearBtn.hidden = q === '';
            clearTimeout(timer);
            if (q.length < 2) {
                close();
                return;
            }
            timer = setTimeout(() => runSearch(q), 220);
        });

        clearBtn?.addEventListener('click', () => {
            input.value = '';
            clearBtn.hidden = true;
            close();
            input.focus();
        });

        input.addEventListener('keydown', (e) => {
            if (drop.hidden) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!items.length) return;
                setActive(active < items.length - 1 ? active + 1 : 0);
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!items.length) return;
                setActive(active > 0 ? active - 1 : items.length - 1);
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                if (active >= 0 && items[active]) {
                    window.location.href = items[active];
                } else if (items[0]) {
                    window.location.href = items[0];
                }
            }
        });

        document.addEventListener('mousedown', (e) => {
            if (!root.contains(e.target)) close();
        });
    }

    document.querySelectorAll('[data-tr-qs]').forEach(initQuickSearch);
})();
</script>
@endpush
@endonce
