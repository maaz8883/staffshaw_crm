{{-- Trello card modal — match official Trello dark UI --}}
<style>
    :root {
        --tc-bg: #242528;
        --tc-surface: #1e2124;
        --tc-elevated: #2b2c2f;
        --tc-hover: #33383e;
        --tc-border: #384047;
        --tc-text: #d5d8db;
        --tc-text-strong: #f1f2f4;
        --tc-muted: #9fadbc;
        --tc-link: #579dff;
        --tc-btn: #33383e;
        --tc-accent: #579dff;
        --tc-accent-ink: #062a4e;
        --tc-danger: #f87168;
        --tc-radius: 8px;
    }

    /* Sit below CRM topbar (64px) so cover icons are never clipped */
    .tc-backdrop {
        position: fixed;
        top: 64px; right: 0; bottom: 0; left: 0;
        z-index: 1050;
        background: rgba(0, 0, 0, .75);
        opacity: 0; visibility: hidden; transition: .15s ease;
        overflow: hidden;
        padding: 20px 16px 24px;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        -webkit-overflow-scrolling: touch;
    }
    .tc-backdrop.show { opacity: 1; visibility: visible; }

    /*
     * Card modal ≈ 1100px; comments pane a bit wider (~400px).
     * Old classic was 768px — that looks too narrow vs current trello.com.
     */
    .tc-backdrop .tc-modal,
    #trello-drawer.tc-modal {
        width: min(1100px, calc(100vw - 48px)) !important;
        max-width: min(1100px, calc(100vw - 48px)) !important;
        max-height: calc(100vh - 64px - 44px) !important;
        min-width: 0 !important;
        margin: 0 auto !important;
        flex: 0 1 auto !important;
        box-sizing: border-box !important;
        background: #242528;
        color: var(--tc-text);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0,0,0,.6);
        transform: translateY(8px);
        transition: transform .15s ease;
        position: relative;
        display: flex;
        flex-direction: column;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans", Ubuntu, "Droid Sans", "Helvetica Neue", sans-serif;
        font-size: 14px;
    }
    .tc-backdrop.show .tc-modal { transform: translateY(0); }

    /*
     * Cover — Trello "normal" size: edgeColor banner + image centered (contain).
     * Only size=full uses object-fit:cover. Never auto-crop wide images.
     */
    .tc-cover-wrap {
        position: relative;
        height: 200px;
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: #626f86;
    }
    .tc-cover-wrap.color-only { height: 116px; }
    .tc-cover-wrap img {
        display: block;
        height: 100%;
        width: auto;
        max-width: 100%;
        max-height: 200px;
        object-fit: contain;
        object-position: center center;
        pointer-events: none;
        user-select: none;
    }
    .tc-cover-wrap.is-full img {
        width: 100%;
        max-width: 100%;
        height: 200px;
        object-fit: cover;
    }
    .tc-cover-list {
        position: absolute; top: 12px; left: 12px; z-index: 2;
        display: inline-flex; align-items: center; gap: 4px;
        background: rgba(9, 30, 66, .54);
        backdrop-filter: blur(4px);
        color: #fff; border-radius: 8px;
        padding: 6px 10px; font-size: 12px; font-weight: 600;
        max-width: calc(100% - 120px);
    }
    .tc-cover-list:has(.tc-list-btn) { padding: 0; }
    .tc-list-btn {
        display: inline-flex; align-items: center; gap: 4px; max-width: 100%;
        border: 0; background: transparent; color: inherit; cursor: pointer;
        padding: 6px 10px; font-size: 12px; font-weight: 600; border-radius: 8px;
    }
    .tc-list-btn span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tc-list-btn i { font-size: 11px; opacity: .85; }
    .tc-list-btn:hover, .tc-list-btn.active { background: rgba(9, 30, 66, .35); }

    /* Cover popover */
    .tc-cover-pop {
        position: absolute; z-index: 40; width: 304px; max-width: calc(100% - 24px);
        display: flex; flex-direction: column;
        background: #282e33; border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-cover-pop[hidden] { display: none; }
    .tc-cover-pop-body { padding: 0 12px 12px; overflow-y: auto; min-height: 0; }
    .tc-cover-h { font-size: 12px; font-weight: 600; color: #9fadbc; margin: 12px 0 6px; }
    .tc-cover-h:first-child { margin-top: 4px; }
    .tc-cover-sizes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .tc-cover-size {
        height: 62px; padding: 0; border: 0; border-radius: 4px; overflow: hidden; cursor: pointer;
        background: #22272b; display: flex; flex-direction: column; text-align: left;
        box-shadow: 0 0 0 1px #384047;
    }
    .tc-cover-size.active { box-shadow: 0 0 0 2px #22272b, 0 0 0 4px #579dff; }
    .tc-cover-size:disabled { opacity: .5; cursor: default; }
    .tc-cover-size .pv-top { height: 26px; flex: 0 0 26px; background: #596773 center/cover no-repeat; }
    .tc-cover-size .pv-body { flex: 1; padding: 5px 6px; display: flex; flex-direction: column; gap: 3px; }
    .tc-cover-size .pv-foot { display: flex; align-items: center; gap: 3px; margin-top: auto; }
    .tc-cover-size .pv-full {
        flex: 1; display: flex; flex-direction: column; justify-content: flex-end; gap: 3px;
        padding: 6px; background: #596773 center/cover no-repeat;
    }
    .tc-cover-size .ln { display: block; height: 4px; border-radius: 2px; background: #8c9bab; }
    .tc-cover-size .pv-full .ln { background: #fff; }
    .tc-cover-size .w80 { width: 80%; } .tc-cover-size .w60 { width: 60%; } .tc-cover-size .w20 { width: 20%; }
    .tc-cover-size .dot { width: 8px; height: 8px; border-radius: 50%; background: #8c9bab; margin-left: auto; }
    .tc-cover-wide-btn {
        display: block; width: 100%; margin-top: 8px; height: 32px; border: 0; border-radius: 3px;
        background: #33383e; color: #b6c2cf; font-size: 14px; font-weight: 500; cursor: pointer;
    }
    .tc-cover-wide-btn:hover { background: #40464d; color: #dee4ea; }
    .tc-cover-wide-btn:disabled { opacity: .55; cursor: wait; }
    .tc-cover-colors { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
    .tc-cover-colors button {
        height: 32px; border: 0; border-radius: 3px; cursor: pointer; padding: 0;
    }
    .tc-cover-colors button:hover { filter: brightness(1.12); }
    .tc-cover-colors button.active { box-shadow: 0 0 0 2px #282e33, 0 0 0 4px #579dff; }
    .tc-cover-atts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .tc-cover-atts button {
        height: 48px; padding: 0; border: 0; border-radius: 3px; cursor: pointer;
        background: #22272b center/cover no-repeat;
    }
    .tc-cover-atts button:hover { filter: brightness(1.1); }
    .tc-cover-atts button.active { box-shadow: 0 0 0 2px #282e33, 0 0 0 4px #579dff; }
    .tc-cover-atts .tc-muted { grid-column: 1 / -1; }
    .tc-cover-tip { margin: 8px 0 0; font-size: 12px; color: #9fadbc; }

    /* Move card popover */
    .tc-move-pop {
        position: absolute; z-index: 40; width: 304px; max-width: calc(100% - 24px);
        background: #282e33; border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-move-pop[hidden] { display: none; }
    .tc-move-tabs {
        display: flex; gap: 16px; margin: 0 12px; border-bottom: 1px solid #384047;
    }
    .tc-move-tabs span {
        font-size: 14px; color: #b6c2cf; padding: 4px 0 6px; margin-bottom: -1px;
    }
    .tc-move-tabs span.active { color: #579dff; border-bottom: 2px solid #579dff; }
    .tc-move-sub { font-size: 12px; font-weight: 600; color: #9fadbc; margin: 10px 0 4px; }
    .tc-move-row { display: flex; gap: 8px; margin-top: 8px; }
    .tc-move-row .list { flex: 1 1 auto; min-width: 0; }
    .tc-move-row .pos { flex: 0 0 76px; }
    .tc-move-pop .tc-cl-select { text-overflow: ellipsis; }
    .tc-move-pop .tc-cl-select option { background: #22272b; color: #b6c2cf; }
    .tc-cover-actions {
        position: absolute; top: 12px; right: 12px; z-index: 2;
        display: flex; gap: 6px;
    }
    .tc-icon-btn {
        width: 32px; height: 32px; border: 0; border-radius: 50%;
        background: rgba(9, 30, 66, .54); color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        text-decoration: none; cursor: pointer; font-size: 14px;
        flex-shrink: 0;
    }
    .tc-icon-btn:hover { background: rgba(9, 30, 66, .72); color: #fff; }

    /* Body — two panes with independent scroll (like Trello) */
    .tc-body {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        padding: 0 0 52px;
        overflow: hidden;
    }
    .tc-cover-wrap.is-loading {
        background: linear-gradient(90deg, #2b2c2f 0%, #3a3d42 50%, #2b2c2f 100%);
        background-size: 200% 100%;
        animation: tc-shimmer 1.2s ease-in-out infinite;
    }
    .tc-cover-wrap.is-loading img { visibility: hidden; }

    .tc-loading {
        flex: 1 1 auto;
        min-height: 280px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 16px;
        padding: 40px 24px 56px;
        color: var(--tc-muted);
    }
    .tc-loading[hidden] { display: none !important; }
    .tc-spinner {
        width: 36px; height: 36px;
        border: 3px solid #3d444d;
        border-top-color: #579dff;
        border-radius: 50%;
        animation: tc-spin .7s linear infinite;
    }
    .tc-loading-text {
        font-size: 14px; font-weight: 500; color: #9fadbc;
    }
    .tc-skeleton {
        width: 100%; max-width: 520px;
        display: flex; flex-direction: column; gap: 10px;
        margin-top: 8px;
    }
    .tc-skel {
        height: 12px; border-radius: 6px;
        background: linear-gradient(90deg, #2b2c2f 0%, #3a3d42 50%, #2b2c2f 100%);
        background-size: 200% 100%;
        animation: tc-shimmer 1.2s ease-in-out infinite;
    }
    .tc-skel.w60 { width: 60%; }
    .tc-skel.w80 { width: 80%; }
    .tc-skel.w40 { width: 40%; }
    .tc-skel.h40 { height: 40px; }
    @keyframes tc-spin { to { transform: rotate(360deg); } }
    @keyframes tc-shimmer {
        0% { background-position: 100% 0; }
        100% { background-position: -100% 0; }
    }
    #tc-content:not([hidden]) {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .tc-grid {
        flex: 1 1 auto;
        min-height: 0;
        display: grid;
        /* main + slightly wider activity pane */
        grid-template-columns: minmax(0, 1fr) minmax(360px, 420px);
        gap: 0;
        align-items: stretch;
        overflow: hidden;
    }
    @media (max-width: 860px) {
        .tc-grid {
            grid-template-columns: 1fr;
            overflow-y: auto;
        }
        .tc-main, .tc-side {
            overflow: visible;
            max-height: none;
        }
        .tc-backdrop { padding: 12px 8px; align-items: flex-start; overflow-y: auto; }
        .tc-side { border-top: 1px solid var(--tc-border); border-left: 0; }
    }

    .tc-main, .tc-side {
        min-width: 0;
        min-height: 0;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }
    .tc-main { padding: 12px 12px 24px 20px; }
    .tc-side {
        padding: 12px 16px 24px 12px;
        background: transparent;
        border-left: 1px solid rgba(255,255,255,.06);
    }

    /* Thin dark scrollbars (Trello-like) */
    .tc-main::-webkit-scrollbar,
    .tc-side::-webkit-scrollbar { width: 8px; }
    .tc-main::-webkit-scrollbar-track,
    .tc-side::-webkit-scrollbar-track { background: transparent; }
    .tc-main::-webkit-scrollbar-thumb,
    .tc-side::-webkit-scrollbar-thumb {
        background: #596773;
        border-radius: 8px;
        border: 2px solid transparent;
        background-clip: padding-box;
    }
    .tc-main::-webkit-scrollbar-thumb:hover,
    .tc-side::-webkit-scrollbar-thumb:hover { background: #738496; background-clip: padding-box; }
    .tc-main, .tc-side { scrollbar-width: thin; scrollbar-color: #596773 transparent; }

    /* Title row */
    .tc-title-row {
        display: flex; align-items: flex-start; gap: 12px;
        margin-bottom: 8px;
    }
    /* Mark-complete circle before the card title (Trello) */
    .tc-title-icon {
        width: 24px; height: 24px; margin-top: 8px; flex-shrink: 0; padding: 0;
        border: 0; border-radius: 50%; background: transparent; cursor: pointer;
        color: #9fadbc; font-size: 18px; line-height: 1;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .tc-title-icon:hover { color: #dee4ea; }
    .tc-title-icon.is-complete { color: #4bce97; }
    .tc-title-icon:disabled { cursor: wait; opacity: .7; }
    .tc-title {
        flex: 1; width: 100%; border: 0; background: transparent;
        color: #f1f2f4;
        font-size: 20px; font-weight: 600; line-height: 1.35;
        padding: 4px 8px; margin: 0 -8px; outline: none;
        border-radius: 8px;
        box-shadow: none;
    }
    .tc-title:hover { background: rgba(255,255,255,.04); }
    .tc-title:focus {
        background: #22272b;
        box-shadow: inset 0 0 0 2px #85b8ff;
    }

    /* Action chips — compact, one row like Trello */
    .tc-actions {
        display: flex; flex-wrap: wrap; gap: 6px;
        margin: 0 0 18px 36px;
        padding: 2px 0;
        /* avoid overflow-x:auto — it clips button tops in Chromium */
    }
    .tc-action {
        border: 0; border-radius: 8px;
        background: #33383e; color: #f1f2f4;
        padding: 6px 10px; font-size: 13px; font-weight: 500;
        cursor: pointer; display: inline-flex; align-items: center; gap: 5px;
        line-height: 18px; white-space: nowrap; flex-shrink: 0;
    }
    .tc-action[hidden] { display: none; }
    .tc-action:hover, .tc-action.active { background: #40464d; }
    .tc-action i { font-size: 13px; opacity: .95; }

    /* Selected labels + due date badges (Trello-style) */
    .tc-card-badges {
        display: flex; flex-wrap: wrap; align-items: flex-start; gap: 16px 24px;
        margin: 0 0 18px 36px;
    }
    .tc-card-badges:empty,
    .tc-card-badges[hidden] { display: none !important; }
    .tc-card-labels[hidden],
    .tc-card-due[hidden] { display: none !important; }
    .tc-card-labels-h,
    .tc-card-due-h {
        font-size: 12px; font-weight: 600; color: #8c9bab;
        margin: 0 0 6px;
    }
    .tc-card-labels-row {
        display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
    }
    .tc-card-label {
        display: inline-flex; align-items: center;
        min-height: 32px; max-width: 100%;
        padding: 0 12px; border-radius: 3px;
        color: #fff; font-size: 14px; font-weight: 600;
        border: 0; cursor: pointer; text-align: left;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,.12);
    }
    .tc-card-label:hover { filter: brightness(1.08); }
    .tc-card-labels-add {
        width: 32px; height: 32px; border: 0; border-radius: 3px;
        background: #33383e; color: #b6c2cf; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 18px; line-height: 1; flex-shrink: 0;
    }
    .tc-card-labels-add:hover { background: #40464d; color: #fff; }
    .tc-card-labels.colorblind .tc-card-label {
        background-image: repeating-linear-gradient(
            -45deg, rgba(255,255,255,.22), rgba(255,255,255,.22) 3px,
            transparent 3px, transparent 7px
        );
    }
    .tc-card-due-btn {
        display: inline-flex; align-items: center; gap: 8px;
        min-height: 32px; padding: 4px 10px 4px 12px;
        border: 1px solid #738496; border-radius: 3px;
        background: #22272b; color: #f1f2f4; cursor: pointer;
        font-size: 14px; font-weight: 500; line-height: 1.2;
    }
    .tc-card-due-btn:hover { background: #282e33; border-color: #8c9bab; }
    .tc-card-due-btn .when { white-space: nowrap; }
    .tc-card-due-btn .badge {
        display: inline-flex; align-items: center;
        padding: 2px 6px; border-radius: 3px;
        font-size: 12px; font-weight: 600; line-height: 1.2;
    }
    .tc-card-due-btn .badge.overdue {
        background: #5d1f1a; color: #fd9891;
    }
    .tc-card-due-btn .badge.soon {
        background: #533f04; color: #f5cd47;
    }
    .tc-card-due-btn .badge.complete {
        background: #1c3329; color: #7ee2b8;
    }
    .tc-card-due-btn .chev {
        margin-left: 2px; font-size: 12px; color: #8c9bab;
    }
    .tc-card-members[hidden] { display: none !important; }
    .tc-card-members-h {
        font-size: 12px; font-weight: 600; color: #8c9bab; margin: 0 0 6px;
    }
    .tc-card-members-row {
        display: flex; flex-wrap: wrap; align-items: center; gap: 4px;
    }
    .tc-card-member {
        width: 32px; height: 32px; border-radius: 50%; border: 0; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 700; color: #fff; cursor: pointer;
        flex-shrink: 0;
    }
    .tc-card-member:hover { filter: brightness(1.08); outline: 2px solid #85b8ff; outline-offset: 1px; }
    .tc-card-members-add {
        width: 32px; height: 32px; border: 1px solid #738496; border-radius: 50%;
        background: rgba(51, 56, 62, .85); color: #b6c2cf; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 18px; line-height: 1; flex-shrink: 0;
    }
    .tc-card-members-add:hover { background: #40464d; color: #fff; border-color: #8c9bab; }

    /* Member profile popover (click avatar) */
    .tc-member-pop {
        display: none; position: fixed; z-index: 10080;
        width: min(280px, calc(100vw - 24px));
        border-radius: 8px; overflow: hidden;
        box-shadow: 0 12px 32px rgba(0,0,0,.55);
        background: #282e33;
    }
    .tc-member-pop.show { display: block; }
    .tc-member-pop-top {
        position: relative; background: #579dff; min-height: 64px; padding: 14px 40px 22px 72px;
    }
    .tc-member-pop-av {
        position: absolute; left: 12px; top: 22px;
        width: 48px; height: 48px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 15px; font-weight: 700; color: #1d2125;
        box-shadow: 0 0 0 3px #282e33;
    }
    .tc-member-pop-name {
        margin: 0; font-size: 16px; font-weight: 700; color: #172b4d;
        line-height: 1.2; word-break: break-word;
    }
    .tc-member-pop-handle {
        margin: 2px 0 0; font-size: 13px; color: #44546f;
    }
    .tc-member-pop-close {
        position: absolute; top: 8px; right: 8px;
        width: 24px; height: 24px; border: 0; border-radius: 4px;
        background: rgba(255,255,255,.35); color: #172b4d; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; font-size: 14px;
    }
    .tc-member-pop-close:hover { background: rgba(255,255,255,.55); }
    .tc-member-pop-actions {
        background: #282e33; padding: 6px;
    }
    .tc-member-pop-actions button,
    .tc-member-pop-actions a {
        display: block; width: 100%; text-align: left;
        border: 0; background: transparent; color: #f1f2f4;
        padding: 10px 12px; border-radius: 6px; font-size: 14px;
        cursor: pointer; text-decoration: none;
    }
    .tc-member-pop-actions button:hover,
    .tc-member-pop-actions a:hover { background: #33383e; }
    .tc-member-pop-actions .sep {
        height: 1px; background: #384047; margin: 4px 6px;
    }

    /* Members popover */
    #panel-members.tc-panel {
        width: min(304px, calc(100% - 34px));
        padding: 0; overflow: hidden;
        background: #282e33;
        box-shadow: 0 8px 24px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-members-head {
        display: flex; align-items: center; justify-content: center;
        position: relative; padding: 12px 36px 8px; min-height: 40px;
    }
    .tc-members-head h3 {
        margin: 0; font-size: 14px; font-weight: 600; color: #b6c2cf; text-align: center;
    }
    .tc-members-close {
        position: absolute; right: 8px; top: 8px;
        width: 28px; height: 28px; border: 0; border-radius: 6px;
        background: transparent; color: #8c9bab; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .tc-members-close:hover { background: #33383e; color: #f1f2f4; }
    .tc-members-body { padding: 0 12px 12px; }
    .tc-members-search {
        width: 100%; box-sizing: border-box;
        background: #22272b; border: 2px solid #579dff; border-radius: 3px;
        color: #f1f2f4; font-size: 14px; padding: 8px 10px; outline: none;
        margin-bottom: 12px;
    }
    .tc-members-search::placeholder { color: #738496; }
    .tc-members-sec {
        font-size: 12px; font-weight: 600; color: #8c9bab; margin: 0 0 6px;
    }
    .tc-members-list {
        display: flex; flex-direction: column; gap: 2px;
        max-height: 280px; overflow-y: auto;
        scrollbar-width: thin; scrollbar-color: #596773 transparent;
    }
    .tc-member-row {
        display: flex; align-items: center; gap: 10px; width: 100%;
        border: 0; background: transparent; color: #f1f2f4;
        padding: 6px 8px; border-radius: 6px; cursor: pointer; text-align: left;
    }
    .tc-member-row:hover { background: #33383e; }
    .tc-member-row.active { background: #33383e; }
    .tc-member-row .av {
        width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 700; color: #fff;
    }
    .tc-member-row .name {
        flex: 1; min-width: 0; font-size: 14px; font-weight: 600;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tc-member-row .check {
        color: #579dff; font-size: 16px; flex-shrink: 0; opacity: 0;
    }
    .tc-member-row.active .check { opacity: 1; }

    .tc-action[data-panel="members"] { position: relative; }
    .tc-action[data-panel="members"] .tc-tip {
        display: none; position: absolute; left: 0; top: calc(100% + 6px);
        align-items: center; gap: 6px;
        background: #dcdfe4; color: #172b4d; font-size: 12px; font-weight: 500;
        padding: 6px 8px; border-radius: 3px; white-space: nowrap;
        box-shadow: 0 4px 12px rgba(0,0,0,.25); z-index: 20; pointer-events: none;
    }
    .tc-action[data-panel="members"] .tc-tip kbd {
        background: #44546f; color: #fff; font-size: 11px; font-weight: 700;
        min-width: 16px; height: 16px; line-height: 16px; text-align: center;
        border-radius: 3px; padding: 0 4px; font-family: inherit;
    }
    .tc-action[data-panel="members"]:hover .tc-tip { display: inline-flex; }

    /* Attach popover (Trello-style) */
    #panel-attach.tc-panel {
        width: min(360px, calc(100% - 34px));
        padding: 0; overflow: hidden;
        background: #282e33;
        box-shadow: 0 8px 24px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-attach-head {
        display: flex; align-items: center; justify-content: center;
        position: relative; padding: 12px 36px 8px; min-height: 40px;
    }
    .tc-attach-head h3 {
        margin: 0; font-size: 14px; font-weight: 600; color: #b6c2cf; text-align: center;
    }
    .tc-attach-close {
        position: absolute; right: 8px; top: 8px;
        width: 28px; height: 28px; border: 0; border-radius: 6px;
        background: transparent; color: #8c9bab; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .tc-attach-close:hover { background: #33383e; color: #f1f2f4; }
    .tc-attach-body { padding: 4px 16px 14px; }
    .tc-attach-sec-title {
        margin: 0 0 4px; font-size: 14px; font-weight: 700; color: #d5d8db;
    }
    .tc-attach-help {
        margin: 0 0 10px; font-size: 12px; color: #8c9bab; line-height: 1.35;
    }
    .tc-attach-choose {
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid #738496; border-radius: 3px;
        background: #22272b; color: #f1f2f4; font-size: 14px; font-weight: 500;
        padding: 8px 12px; cursor: pointer;
    }
    .tc-attach-choose:hover { background: #33383e; }
    .tc-attach-divider {
        height: 1px; background: #384047; margin: 14px 0;
    }
    .tc-attach-body label {
        display: block; font-size: 12px; font-weight: 600; color: #8c9bab; margin: 0 0 6px;
    }
    .tc-attach-body label .req { color: #f87168; }
    .tc-attach-body .tc-pop-field {
        margin-bottom: 10px;
        background: #22272b; border: 1px solid #738496; border-radius: 3px;
        color: #f1f2f4; font-size: 14px; padding: 8px 10px; width: 100%;
        box-sizing: border-box; outline: none;
    }
    .tc-attach-body .tc-pop-field:focus { border-color: #85b8ff; }
    .tc-attach-recent-h {
        margin: 12px 0 8px; font-size: 12px; font-weight: 600; color: #8c9bab;
    }
    .tc-attach-recent {
        display: flex; flex-direction: column; gap: 2px;
        max-height: 160px; overflow-y: auto; margin-bottom: 10px;
        scrollbar-width: thin; scrollbar-color: #596773 transparent;
    }
    .tc-attach-recent button {
        display: flex; align-items: flex-start; gap: 10px; width: 100%;
        border: 0; background: transparent; color: #f1f2f4;
        padding: 8px; border-radius: 6px; cursor: pointer; text-align: left;
    }
    .tc-attach-recent button:hover,
    .tc-attach-recent button.selected { background: #33383e; }
    .tc-attach-recent .ico {
        width: 28px; height: 20px; border-radius: 3px; background: #1868db;
        flex-shrink: 0; margin-top: 2px;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-size: 11px;
    }
    .tc-attach-recent .meta { min-width: 0; flex: 1; }
    .tc-attach-recent .name {
        display: block; font-size: 14px; font-weight: 600;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tc-attach-recent .sub {
        display: block; font-size: 12px; color: #8c9bab;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tc-attach-foot {
        display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px;
    }
    .tc-attach-cancel {
        border: 0; background: transparent; color: #b6c2cf;
        font-size: 14px; font-weight: 500; padding: 8px 12px; border-radius: 3px; cursor: pointer;
    }
    .tc-attach-cancel:hover { background: #33383e; color: #fff; }
    .tc-attach-insert {
        border: 0; border-radius: 3px; background: #0c66e4; color: #fff;
        font-size: 14px; font-weight: 600; padding: 8px 14px; cursor: pointer;
    }
    .tc-attach-insert:hover { background: #1558cf; }
    .tc-attach-insert:disabled { opacity: .5; cursor: default; }

    .tc-panel {
        display: none;
        margin: 0 0 16px 34px;
        padding: 12px;
        border-radius: 8px;
        background: var(--tc-surface);
        box-shadow: 0 0 0 1px var(--tc-border);
    }
    .tc-panel.show { display: block; }
    /* Popovers open as dropdowns right under the button that opened them (positioned by anchorPanel()) */
    .tc-main { position: relative; }
    .tc-main > .tc-panel {
        position: absolute; z-index: 30; margin: 0;
        max-width: calc(100% - 16px);
    }
    #panel-checklist.tc-panel {
        width: 304px; padding: 0; overflow: hidden;
        background: #282e33;
        box-shadow: 0 8px 24px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    #panel-del-checklist.tc-panel {
        width: 304px; padding: 0; overflow: hidden;
        background: #282e33;
        box-shadow: 0 8px 24px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-del-cl-body { padding: 0 12px 12px; }
    .tc-del-cl-body p { margin: 0 0 10px; font-size: 14px; line-height: 20px; color: #b6c2cf; }
    .tc-cl-form { display: flex; flex-direction: column; padding: 4px 12px 12px; }
    .tc-cl-label { font-size: 12px; font-weight: 700; color: #b6c2cf; margin: 8px 0 4px; }
    .tc-cl-input, .tc-cl-select {
        width: 100%; box-sizing: border-box; height: 36px;
        background: #22272b; color: #b6c2cf; font-size: 14px;
        border: 1px solid #738496; border-radius: 3px; padding: 6px 10px; outline: none;
    }
    .tc-cl-input:focus { border-color: #85b8ff; box-shadow: inset 0 0 0 1px #85b8ff; }
    .tc-cl-select-wrap { position: relative; }
    .tc-cl-select { appearance: none; -webkit-appearance: none; padding-right: 32px; cursor: pointer; }
    .tc-cl-select:focus { border-color: #85b8ff; }
    .tc-cl-select-wrap i {
        position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
        font-size: 12px; color: #b6c2cf; pointer-events: none;
    }
    .tc-cl-submit {
        margin-top: 12px; height: 32px; border: 0; border-radius: 3px;
        background: #579dff; color: #1d2125; font-size: 14px; font-weight: 500; cursor: pointer;
    }
    .tc-cl-submit:hover { background: #85b8ff; }
    .tc-cl-submit:disabled { opacity: .6; cursor: wait; }
    .tc-panel-title {
        font-size: 12px; font-weight: 600; color: var(--tc-muted);
        text-transform: uppercase; letter-spacing: .02em; margin-bottom: 10px;
    }

    /* "Add to card" popover (Trello-style) */
    #panel-add.tc-panel {
        width: min(304px, calc(100% - 34px));
        padding: 0; overflow: hidden;
        background: #282e33;
        box-shadow: 0 8px 24px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-add-head {
        display: flex; align-items: center; justify-content: center;
        position: relative; padding: 12px 36px 8px; min-height: 40px;
    }
    .tc-add-head h3 { margin: 0; font-size: 14px; font-weight: 600; color: #b6c2cf; }
    .tc-add-close {
        position: absolute; right: 8px; top: 8px;
        width: 28px; height: 28px; border: 0; border-radius: 6px;
        background: transparent; color: #8c9bab; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .tc-add-close:hover { background: #33383e; color: #f1f2f4; }
    .tc-add-list { display: flex; flex-direction: column; padding: 0 0 8px; }
    .tc-add-item {
        display: flex; align-items: center; gap: 12px; width: 100%;
        border: 0; background: transparent; text-align: left; cursor: pointer;
        padding: 6px 12px;
    }
    .tc-add-item:hover { background: #33383e; }
    .tc-add-item .ico {
        flex: 0 0 auto; width: 40px; height: 40px; border-radius: 4px;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid #384047; color: #b6c2cf; font-size: 16px;
    }
    .tc-add-item .txt { display: flex; flex-direction: column; min-width: 0; }
    .tc-add-item strong { font-size: 14px; font-weight: 500; color: #b6c2cf; line-height: 20px; }
    .tc-add-item small { font-size: 12px; color: #9fadbc; line-height: 16px; }

    /* Labels popover (Trello-style) */
    #panel-labels.tc-panel {
        width: min(304px, calc(100% - 34px));
        padding: 0; overflow: hidden;
        background: #282e33;
        box-shadow: 0 8px 24px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-labels-head {
        display: flex; align-items: center; justify-content: center;
        position: relative; padding: 12px 36px 8px; min-height: 40px;
    }
    .tc-labels-head h3 {
        margin: 0; font-size: 14px; font-weight: 600; color: #b6c2cf; text-align: center;
    }
    .tc-labels-head .tc-labels-close {
        position: absolute; right: 8px; top: 8px;
        width: 28px; height: 28px; border: 0; border-radius: 6px;
        background: transparent; color: #8c9bab; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .tc-labels-head .tc-labels-close:hover { background: #33383e; color: #f1f2f4; }
    .tc-labels-head .tc-labels-back {
        position: absolute; left: 8px; top: 8px;
        width: 28px; height: 28px; border: 0; border-radius: 6px;
        background: transparent; color: #8c9bab; cursor: pointer;
        display: none; align-items: center; justify-content: center;
    }
    #panel-labels.is-form .tc-labels-back { display: inline-flex; }
    .tc-labels-head .tc-labels-back:hover { background: #33383e; color: #f1f2f4; }
    .tc-labels-body { padding: 0 12px 12px; }
    .tc-labels-search {
        width: 100%; box-sizing: border-box;
        background: #22272b; border: 2px solid #579dff; border-radius: 3px;
        color: #f1f2f4; font-size: 14px; padding: 8px 10px; outline: none;
        margin-bottom: 12px;
    }
    .tc-labels-search::placeholder { color: #738496; }
    .tc-labels-sec {
        font-size: 12px; font-weight: 600; color: #8c9bab; margin: 0 0 8px;
    }
    .tc-labels-list {
        display: flex; flex-direction: column; gap: 4px;
        max-height: 280px; overflow-y: auto; margin-bottom: 10px;
        scrollbar-width: thin; scrollbar-color: #596773 transparent;
    }
    .tc-label-row {
        display: flex; align-items: center; gap: 8px;
    }
    .tc-label-check {
        width: 16px; height: 16px; margin: 0; accent-color: #579dff; cursor: pointer; flex-shrink: 0;
    }
    .tc-label-bar {
        flex: 1; min-width: 0; border: 0; border-radius: 3px; height: 32px;
        padding: 0 12px; text-align: left; color: #fff; font-size: 14px; font-weight: 600;
        cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,.12);
    }
    .tc-label-bar:hover { filter: brightness(1.08); }
    .tc-label-edit {
        width: 28px; height: 28px; border: 0; border-radius: 6px;
        background: transparent; color: #8c9bab; cursor: pointer; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .tc-label-edit:hover { background: #33383e; color: #f1f2f4; }
    .tc-labels-create-btn,
    .tc-labels-cbf-btn {
        display: block; width: 100%; border: 0; border-radius: 3px;
        background: #33383e; color: #d5d8db; font-size: 14px; font-weight: 500;
        padding: 8px 12px; cursor: pointer; text-align: center; margin-top: 4px;
    }
    .tc-labels-create-btn:hover,
    .tc-labels-cbf-btn:hover { background: #3d444b; color: #fff; }
    .tc-labels-divider {
        height: 1px; background: #384047; margin: 10px 0 6px;
    }
    .tc-labels-form { display: none; }
    #panel-labels.is-form .tc-labels-browse { display: none; }
    #panel-labels.is-form .tc-labels-form { display: block; }
    .tc-labels-form label {
        display: block; font-size: 12px; font-weight: 600; color: #8c9bab; margin: 0 0 6px;
    }
    .tc-labels-form .tc-pop-field { margin-bottom: 12px; }
    .tc-label-colors {
        display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-bottom: 14px;
    }
    .tc-label-colors button {
        height: 32px; border: 2px solid transparent; border-radius: 3px; cursor: pointer; padding: 0;
    }
    .tc-label-colors button.selected { border-color: #fff; box-shadow: 0 0 0 2px #579dff; }
    .tc-labels-form-actions { display: flex; gap: 8px; }
    #panel-labels.colorblind .tc-label-bar {
        background-image: repeating-linear-gradient(
            -45deg, rgba(255,255,255,.22), rgba(255,255,255,.22) 3px,
            transparent 3px, transparent 7px
        );
    }
    .tc-action[data-panel="labels"] { position: relative; }
    .tc-action[data-panel="labels"] .tc-tip {
        display: none; position: absolute; left: 0; top: calc(100% + 6px);
        align-items: center; gap: 6px;
        background: #dcdfe4; color: #172b4d; font-size: 12px; font-weight: 500;
        padding: 6px 8px; border-radius: 3px; white-space: nowrap;
        box-shadow: 0 4px 12px rgba(0,0,0,.25); z-index: 20; pointer-events: none;
    }
    .tc-action[data-panel="labels"] .tc-tip kbd {
        background: #44546f; color: #fff; font-size: 11px; font-weight: 700;
        min-width: 16px; height: 16px; line-height: 16px; text-align: center;
        border-radius: 3px; padding: 0 4px; font-family: inherit;
    }
    .tc-action[data-panel="labels"]:hover .tc-tip,
    .tc-action[data-panel="dates"]:hover .tc-tip { display: inline-flex; }
    .tc-action[data-panel="dates"] { position: relative; }
    .tc-action[data-panel="dates"] .tc-tip {
        display: none; position: absolute; left: 0; top: calc(100% + 6px);
        align-items: center; gap: 6px;
        background: #dcdfe4; color: #172b4d; font-size: 12px; font-weight: 500;
        padding: 6px 8px; border-radius: 3px; white-space: nowrap;
        box-shadow: 0 4px 12px rgba(0,0,0,.25); z-index: 20; pointer-events: none;
    }
    .tc-action[data-panel="dates"] .tc-tip kbd {
        background: #44546f; color: #fff; font-size: 11px; font-weight: 700;
        min-width: 16px; height: 16px; line-height: 16px; text-align: center;
        border-radius: 3px; padding: 0 4px; font-family: inherit;
    }

    /* Dates popover (Trello-style) */
    #panel-dates.tc-panel {
        width: min(304px, calc(100% - 34px));
        padding: 0; overflow: hidden;
        background: #282e33;
        box-shadow: 0 8px 24px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-dates-head {
        display: flex; align-items: center; justify-content: center;
        position: relative; padding: 12px 36px 8px; min-height: 40px;
    }
    .tc-dates-head h3 {
        margin: 0; font-size: 14px; font-weight: 600; color: #b6c2cf; text-align: center;
    }
    .tc-dates-close {
        position: absolute; right: 8px; top: 8px;
        width: 28px; height: 28px; border: 1px solid #579dff; border-radius: 6px;
        background: #22272b; color: #b6c2cf; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; font-size: 16px;
    }
    .tc-dates-close:hover { background: #33383e; color: #fff; }
    .tc-dates-body { padding: 0 12px 14px; }
    .tc-cal-nav {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 8px; gap: 4px;
    }
    .tc-cal-nav .month {
        flex: 1; text-align: center; font-size: 14px; font-weight: 600; color: #f1f2f4;
    }
    .tc-cal-nav button {
        border: 0; background: transparent; color: #8c9bab; width: 28px; height: 28px;
        border-radius: 6px; cursor: pointer; font-size: 13px;
    }
    .tc-cal-nav button:hover { background: #33383e; color: #fff; }
    .tc-cal-week {
        display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; margin-bottom: 4px;
    }
    .tc-cal-week span {
        text-align: center; font-size: 11px; color: #8c9bab; padding: 4px 0; font-weight: 600;
    }
    .tc-cal-grid {
        display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; margin-bottom: 14px;
    }
    .tc-cal-grid button {
        border: 0; background: transparent; color: #f1f2f4; height: 32px;
        border-radius: 50%; cursor: pointer; font-size: 13px; position: relative;
    }
    .tc-cal-grid button.muted { color: #596773; }
    .tc-cal-grid button:hover { background: #33383e; }
    .tc-cal-grid button.selected {
        background: #579dff; color: #fff; font-weight: 700;
    }
    .tc-cal-grid button.start-mark::after {
        content: ''; position: absolute; left: 8px; right: 8px; bottom: 3px;
        height: 2px; background: #579dff; border-radius: 1px;
    }
    .tc-cal-grid button.selected.start-mark::after { background: rgba(255,255,255,.7); }
    .tc-dates-field { margin-bottom: 12px; }
    .tc-dates-field > span {
        display: block; font-size: 12px; font-weight: 600; color: #8c9bab; margin-bottom: 6px;
    }
    .tc-dates-row {
        display: flex; align-items: center; gap: 8px;
    }
    .tc-dates-row input[type="checkbox"] {
        width: 16px; height: 16px; margin: 0; accent-color: #579dff; cursor: pointer; flex-shrink: 0;
    }
    .tc-dates-row input[type="text"],
    .tc-dates-row input[type="time"],
    .tc-dates-select {
        background: #22272b; border: 1px solid #738496; border-radius: 3px;
        color: #f1f2f4; font-size: 14px; padding: 6px 8px; outline: none;
    }
    .tc-dates-row input[type="text"]:focus,
    .tc-dates-row input[type="time"]:focus,
    .tc-dates-select:focus { border-color: #85b8ff; }
    .tc-dates-row input[type="text"]:disabled,
    .tc-dates-row input[type="time"]:disabled {
        opacity: .45; cursor: default;
    }
    .tc-dates-row .date-in { width: 110px; }
    .tc-dates-row .time-in { width: 100px; }
    .tc-dates-select {
        width: 100%; cursor: pointer; appearance: auto;
    }
    .tc-dates-hint {
        font-size: 12px; color: #8c9bab; line-height: 1.35; margin: -4px 0 14px;
    }
    .tc-dates-save {
        display: block; width: 100%; border: 0; border-radius: 3px;
        background: #0c66e4; color: #fff; font-size: 14px; font-weight: 600;
        padding: 8px 12px; cursor: pointer; margin-bottom: 8px;
    }
    .tc-dates-save:hover { background: #1558cf; }
    .tc-dates-remove {
        display: block; width: 100%; border: 1px solid #738496; border-radius: 3px;
        background: transparent; color: #f1f2f4; font-size: 14px; font-weight: 500;
        padding: 8px 12px; cursor: pointer;
    }
    .tc-dates-remove:hover { background: #33383e; }

    /* Sections */
    .tc-sec { margin-bottom: 24px; }
    .tc-sec-h {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        margin-bottom: 8px; min-height: 32px;
    }
    .tc-sec-h .left {
        display: inline-flex; align-items: center; gap: 10px;
        font-size: 14px; font-weight: 600; color: var(--tc-text-strong);
    }
    .tc-sec-h .left i { font-size: 16px; color: var(--tc-muted); width: 24px; text-align: center; }
    .tc-sec-body { padding-left: 34px; }

    .tc-muted { color: var(--tc-muted); font-size: 12px; }
    .tc-link-btn {
        border: 0; background: var(--tc-btn); color: var(--tc-text-strong);
        border-radius: 3px; padding: 6px 12px; font-size: 14px; font-weight: 500;
        cursor: pointer; line-height: 20px;
    }
    .tc-link-btn:hover { background: var(--tc-hover); }

    .tc-field {
        width: 100%; background: var(--tc-surface);
        border: 1px solid transparent; color: var(--tc-text-strong);
        border-radius: 8px; padding: 8px 12px; font-size: 14px;
        outline: none; resize: vertical; line-height: 1.45;
    }
    .tc-field:focus {
        background: #22272b;
        border-color: var(--tc-accent);
        box-shadow: 0 0 0 1px var(--tc-accent);
    }
    .tc-field::placeholder { color: #738496; }

    .tc-desc-view {
        font-size: 14px; line-height: 1.5; color: var(--tc-text);
        white-space: pre-wrap; word-break: break-word;
        min-height: 56px; cursor: pointer;
        border-radius: 8px; padding: 8px 12px;
        background: transparent;
    }
    .tc-desc-view:hover { background: rgba(255,255,255,.03); }
    /* Rendered Markdown (Trello look) */
    .tc-desc-view:not(.is-empty) { white-space: normal; padding: 0; line-height: 24px; color: #b6c2cf; }
    .tc-desc-view:not(.is-empty):hover { background: transparent; }
    .tc-desc-view > :first-child { margin-top: 0; }
    :is(.tc-desc-view, .tc-bubble) p { margin: 0 0 12px; }
    :is(.tc-desc-view, .tc-bubble) h1, :is(.tc-desc-view, .tc-bubble) h2, :is(.tc-desc-view, .tc-bubble) h3,
    :is(.tc-desc-view, .tc-bubble) h4, :is(.tc-desc-view, .tc-bubble) h5, :is(.tc-desc-view, .tc-bubble) h6 {
        color: #dee4ea; font-weight: 600; line-height: 1.25; margin: 24px 0 10px;
    }
    :is(.tc-desc-view, .tc-bubble) h1 { font-size: 24px; }
    :is(.tc-desc-view, .tc-bubble) h2 { font-size: 22px; }
    :is(.tc-desc-view, .tc-bubble) h3 { font-size: 18px; }
    :is(.tc-desc-view, .tc-bubble) h4 { font-size: 16px; }
    :is(.tc-desc-view, .tc-bubble) h5, :is(.tc-desc-view, .tc-bubble) h6 { font-size: 14px; }
    :is(.tc-desc-view, .tc-bubble) ul, :is(.tc-desc-view, .tc-bubble) ol { margin: 0 0 12px; padding-left: 24px; }
    :is(.tc-desc-view, .tc-bubble) li { margin: 0; }
    :is(.tc-desc-view, .tc-bubble) blockquote {
        margin: 0 0 12px; padding: 0 0 0 16px; border-left: 2px solid #454f59; color: #b6c2cf;
    }
    :is(.tc-desc-view, .tc-bubble) blockquote > :last-child { margin-bottom: 0; }
    :is(.tc-desc-view, .tc-bubble) hr { border: 0; border-top: 1px solid #384047; margin: 16px 0; }
    :is(.tc-desc-view, .tc-bubble) code {
        background: #2c333a; color: #dee4ea; border-radius: 3px; padding: 1px 4px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px;
    }
    /* Code block with line numbers */
    :is(.tc-desc-view, .tc-bubble) pre.tc-md-code {
        margin: 0 0 12px; padding: 8px 0; border-radius: 6px; background: #2c333a;
        overflow-x: auto; counter-reset: ln;
    }
    :is(.tc-desc-view, .tc-bubble) pre.tc-md-code code { background: none; padding: 0; display: block; }
    :is(.tc-desc-view, .tc-bubble) pre.tc-md-code .ln {
        display: block; white-space: pre; padding-right: 12px; line-height: 21px;
    }
    :is(.tc-desc-view, .tc-bubble) pre.tc-md-code .ln::before {
        counter-increment: ln; content: counter(ln);
        display: inline-block; width: 32px; padding-right: 10px; margin-right: 10px;
        text-align: right; color: #8c9bab; user-select: none;
    }
    :is(.tc-desc-view, .tc-bubble) a.tc-smart-link {
        display: inline-flex; align-items: center; gap: 6px; max-width: 100%;
        vertical-align: middle; padding: 1px 6px; border-radius: 3px;
        border: 1px solid #384047; background: #22272b; color: #85b8ff; text-decoration: none;
    }
    :is(.tc-desc-view, .tc-bubble) a.tc-smart-link:hover { background: #2c333a; }
    /* Long descriptions: clipped with a fade until "Show more" (Trello) */
    .tc-desc-view.is-collapsed {
        max-height: 440px; overflow: hidden; position: relative;
        -webkit-mask-image: linear-gradient(to bottom, #000 calc(100% - 72px), transparent);
        mask-image: linear-gradient(to bottom, #000 calc(100% - 72px), transparent);
    }
    .tc-desc-more {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        width: 100%; margin-top: 4px; height: 32px;
        border: 0; border-radius: 3px; cursor: pointer;
        background: transparent; color: #b6c2cf; font-size: 14px; font-weight: 500;
        box-shadow: inset 0 0 0 1px #384047;
    }
    .tc-desc-more[hidden] { display: none; }
    .tc-desc-more:hover { background: #33383e; color: #dee4ea; }
    .tc-desc-more i { font-size: 13px; }
    .tc-desc-view.is-empty {
        color: #9fadbc; background: #33383e;
        display: flex; align-items: flex-start;
        min-height: 56px;
    }
    .tc-desc-view.is-empty:hover { background: #40464d; }
    .tc-desc-view a { color: var(--tc-link); }
    .tc-desc-view img.tc-desc-img,
    .tc-desc-visual img.tc-desc-img,
    .tc-bubble img,
    .tc-comment-body img {
        display: block;
        max-width: 100%;
        width: auto;
        height: auto;
        border-radius: 8px;
        margin: 10px 0 4px;
        background: #1e2124;
    }

    /* Trello-like description editor */
    .tc-desc-editor {
        border: 1px solid #85b8ff;
        border-radius: 8px;
        background: #22272b;
        overflow: visible;
        position: relative;
    }
    .tc-desc-toolbar {
        display: flex; align-items: center; flex-wrap: wrap; gap: 2px;
        padding: 8px 10px;
        border-bottom: 1px solid #384047;
        background: #1e2124;
        border-radius: 8px 8px 0 0;
    }
    .tc-desc-toolbar-right { margin-left: auto; display: flex; gap: 2px; }
    .tc-tb {
        position: relative;
        border: 0; background: transparent; color: #b6c2cf;
        min-width: 32px; height: 32px; padding: 0 6px; border-radius: 6px;
        cursor: pointer; font-size: 13px; font-weight: 700;
        display: inline-flex; align-items: center; justify-content: center; gap: 2px;
    }
    .tc-tb:hover, .tc-tb.open { background: #33383e; color: #fff; }
    .tc-tb i { font-size: 15px; font-weight: 400; }
    .tc-tb .chev { font-size: 10px; opacity: .75; }
    .tc-desc-toolbar .sep {
        width: 1px; height: 20px; background: #384047; margin: 0 4px;
    }
    .tc-dd {
        display: none; position: absolute; top: 40px; left: 0; z-index: 30;
        min-width: 240px; padding: 6px; border-radius: 8px;
        background: #282e33; border: 1px solid #384047;
        box-shadow: 0 8px 24px rgba(0,0,0,.45);
    }
    .tc-dd.show { display: block; }
    .tc-dd.right { left: auto; right: 0; }
    .tc-dd-item {
        display: flex; align-items: center; gap: 10px; width: 100%;
        border: 0; background: transparent; color: #d5d8db;
        text-align: left; padding: 8px 10px; border-radius: 6px;
        cursor: pointer; font-size: 14px;
    }
    .tc-dd-item:hover, .tc-dd-item.active {
        background: rgba(88, 132, 212, .25);
        box-shadow: inset 3px 0 0 #579dff;
    }
    .tc-dd-item .meta { margin-left: auto; color: #8c9bab; font-size: 12px; }
    .tc-dd-item .sub { display: block; color: #8c9bab; font-size: 12px; font-weight: 400; }
    .tc-dd-item.block { flex-direction: column; align-items: flex-start; gap: 2px; }
    .tc-dd-div { height: 1px; background: #384047; margin: 4px 0; }
    .tc-dd-item.h1 { font-size: 22px; font-weight: 700; }
    .tc-dd-item.h2 { font-size: 18px; font-weight: 700; }
    .tc-dd-item.h3 { font-size: 16px; font-weight: 700; }
    .tc-dd-item.h4 { font-size: 14px; font-weight: 700; }
    .tc-dd-item.h5 { font-size: 13px; font-weight: 600; }
    .tc-dd-item.h6 { font-size: 12px; font-weight: 500; }

    .tc-desc-visual {
        min-height: 160px; max-height: 420px; overflow-y: auto;
        padding: 12px 14px; outline: none;
        font-size: 14px; line-height: 1.5; color: #f1f2f4;
        white-space: pre-wrap; word-break: break-word;
        background: #22272b;
    }
    .tc-desc-visual:empty:before {
        content: attr(data-placeholder);
        color: #738496;
    }
    .tc-desc-visual a { color: var(--tc-link); }
    /* Links in the editors look like Trello's: blue + underlined, long URLs wrap */
    .tc-rich-visual a { color: #579dff; text-decoration: underline; overflow-wrap: anywhere; }
    .tc-desc-view a:not(.tc-smart-link) { color: #579dff; text-decoration: underline; overflow-wrap: anywhere; }
    .tc-desc-visual h1, .tc-desc-visual h2, .tc-desc-visual h3,
    .tc-desc-visual h4, .tc-desc-visual h5, .tc-desc-visual h6 {
        margin: 0 0 8px; color: #f1f2f4; line-height: 1.3;
    }
    .tc-desc-visual ul, .tc-desc-visual ol { margin: 0 0 8px; padding-left: 22px; }
    .tc-desc-visual blockquote {
        margin: 8px 0; padding: 4px 12px; border-left: 3px solid #579dff; color: #b6c2cf;
    }
    .tc-desc-visual code {
        background: #1e2124; padding: 1px 5px; border-radius: 4px; font-size: 13px;
    }
    .tc-desc-visual pre {
        background: #1e2124; padding: 10px 12px; border-radius: 8px; overflow-x: auto;
    }
    .tc-desc-actions {
        display: flex; align-items: center; gap: 8px;
        padding: 10px 12px;
        border-top: 1px solid #384047;
        background: #1e2124;
        border-radius: 0 0 8px 8px;
    }
    .tc-desc-actions .spacer { flex: 1; }

    /* Link / image popovers */
    .tc-pop-backdrop {
        display: none; position: fixed; inset: 0; z-index: 1100;
        background: rgba(0,0,0,.45);
        align-items: center; justify-content: center; padding: 16px;
    }
    .tc-pop-backdrop.show { display: flex; }
    .tc-pop {
        width: min(420px, 100%); background: #282e33; color: #d5d8db;
        border-radius: 10px; padding: 20px; box-shadow: 0 12px 40px rgba(0,0,0,.5);
        position: relative;
    }
    .tc-pop.wide { width: min(520px, 100%); }
    .tc-pop h3 {
        margin: 0 0 16px; text-align: center; font-size: 16px; font-weight: 600; color: #f1f2f4;
    }
    .tc-pop-close {
        position: absolute; top: 12px; right: 12px;
        width: 28px; height: 28px; border-radius: 50%; border: 1px solid #596773;
        background: transparent; color: #b6c2cf; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .tc-pop label {
        display: block; font-size: 12px; color: #9fadbc; margin-bottom: 6px;
    }
    .tc-pop label .req { color: #f87168; }
    .tc-pop .hint { font-size: 12px; color: #738496; margin-top: 4px; margin-bottom: 12px; }
    .tc-pop-field {
        width: 100%; background: #1e2124; border: 1px solid #384047; color: #f1f2f4;
        border-radius: 6px; padding: 8px 10px; font-size: 14px; outline: none; margin-bottom: 12px;
    }
    .tc-pop-field:focus { border-color: #85b8ff; box-shadow: 0 0 0 1px #85b8ff; }
    .tc-pop-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px; }
    .tc-pop.tc-del-pop {
        width: min(304px, 100%); padding: 16px 16px 14px;
    }
    .tc-pop.tc-del-pop h3 {
        margin: 0 28px 10px 0; text-align: left; font-size: 14px; font-weight: 600;
    }
    .tc-pop.tc-del-pop .tc-del-msg {
        margin: 0 0 14px; font-size: 14px; line-height: 1.4; color: #b6c2cf;
    }
    .tc-btn-danger-block {
        display: block; width: 100%; border: 0; border-radius: 3px;
        background: #ef7564; color: #1d2125; font-size: 14px; font-weight: 600;
        padding: 8px 12px; cursor: pointer; text-align: center;
    }
    .tc-btn-danger-block:hover { background: #f18a7c; }
    .tc-img-grid {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;
        margin: 8px 0 16px; max-height: 240px; overflow-y: auto;
    }
    .tc-img-grid button {
        border: 2px solid transparent; padding: 0; border-radius: 8px;
        overflow: hidden; background: #1e2124; cursor: pointer; aspect-ratio: 4/3;
    }
    .tc-img-grid button:hover, .tc-img-grid button.selected { border-color: #579dff; }
    .tc-img-grid img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .tc-img-grid .ph {
        width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
        color: #738496; font-size: 12px; padding: 6px; text-align: center;
    }
    .tc-attach-pick-list {
        display: flex; flex-direction: column; gap: 6px;
        max-height: 260px; overflow-y: auto; margin-bottom: 4px;
    }
    .tc-attach-pick-list button {
        display: flex; align-items: center; gap: 10px; width: 100%;
        border: 1px solid #384047; background: #1e2124; color: #f1f2f4;
        border-radius: 8px; padding: 8px 10px; cursor: pointer; text-align: left;
    }
    .tc-attach-pick-list button:hover { border-color: #579dff; background: #282e33; }
    .tc-attach-pick-list .thumb {
        width: 40px; height: 40px; border-radius: 6px; object-fit: cover;
        background: #2b2c2f; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700; color: #9fadbc; text-transform: uppercase;
    }
    .tc-attach-pick-list .meta { min-width: 0; flex: 1; }
    .tc-attach-pick-list .name {
        font-size: 13px; font-weight: 600; white-space: nowrap;
        overflow: hidden; text-overflow: ellipsis;
    }
    .tc-attach-pick-list .sub { font-size: 11px; color: #9fadbc; }
    .tc-pop-row { display: flex; gap: 8px; align-items: center; margin-bottom: 12px; }
    .tc-pop-row .tc-pop-field { margin: 0; flex: 1; }
    .tc-md-pop {
        width: min(560px, calc(100vw - 32px));
        max-height: min(70vh, 640px);
        display: flex; flex-direction: column;
        padding: 0; overflow: hidden;
    }
    .tc-md-pop-bar {
        display: flex; align-items: center; justify-content: space-between;
        gap: 8px; padding: 10px 12px; border-bottom: 1px solid #384047;
        flex-shrink: 0;
    }
    .tc-md-pop-bar .tc-pop-close {
        position: static; width: 28px; height: 28px; flex-shrink: 0;
    }
    .tc-md-pop-bar .tc-md-copy {
        display: inline-flex; align-items: center; gap: 6px;
        border: 0; background: transparent; color: #d5d8db;
        font-size: 13px; font-weight: 600; cursor: pointer;
        padding: 4px 8px; border-radius: 6px;
    }
    .tc-md-pop-bar .tc-md-copy:hover { background: #33383e; color: #fff; }
    .tc-md-pop-bar .tc-md-copy.copied { color: #4bce97; }
    .tc-md-body {
        margin: 0; padding: 14px 16px; overflow: auto; flex: 1;
        background: #1e2124; color: #c7cdd1;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 13px; line-height: 1.55; white-space: pre-wrap; word-break: break-word;
    }
    .tc-md-body:empty:before {
        content: '(empty description)';
        color: #738496; font-style: italic;
    }
    .tc-help-pop {
        width: min(820px, calc(100vw - 32px));
        max-height: min(80vh, 720px);
        padding: 0; display: flex; flex-direction: column; overflow: hidden;
    }
    .tc-help-pop-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 16px 18px 12px; border-bottom: 1px solid #384047; flex-shrink: 0;
    }
    .tc-help-pop-head h3 {
        margin: 0; text-align: left; font-size: 20px; font-weight: 700; color: #f1f2f4;
    }
    .tc-help-pop-head .tc-pop-close { position: static; }
    .tc-help-grid {
        display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 0;
        overflow: auto; flex: 1; min-height: 0;
    }
    @media (max-width: 700px) {
        .tc-help-grid { grid-template-columns: 1fr; }
    }
    .tc-help-col {
        padding: 16px 18px 20px;
    }
    .tc-help-col + .tc-help-col { border-left: 1px solid #384047; }
    @media (max-width: 700px) {
        .tc-help-col + .tc-help-col { border-left: 0; border-top: 1px solid #384047; }
    }
    .tc-help-col h4 {
        margin: 0 0 14px; font-size: 14px; font-weight: 700; color: #f1f2f4;
    }
    .tc-help-row {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; margin-bottom: 10px; font-size: 13px; color: #c7cdd1;
    }
    .tc-help-row .label { flex: 1; min-width: 0; }
    .tc-help-keys { display: flex; flex-wrap: wrap; gap: 4px; justify-content: flex-end; }
    .tc-help-keys kbd {
        display: inline-block; min-width: 22px; text-align: center;
        padding: 2px 6px; border-radius: 4px;
        background: #1e2124; border: 1px solid #596773;
        color: #d5d8db; font-size: 11px; font-family: inherit; line-height: 18px;
    }
    .tc-help-md .tc-help-row { align-items: flex-start; }
    .tc-help-md code {
        display: inline-block; padding: 2px 6px; border-radius: 4px;
        background: #1e2124; border: 1px solid #596773;
        color: #d5d8db; font-size: 12px; white-space: pre-wrap;
    }
    .tc-help-foot {
        padding: 10px 18px 14px; border-top: 1px solid #384047;
        font-size: 12px; color: #9fadbc; text-align: right; flex-shrink: 0;
    }
    .tc-help-foot kbd {
        padding: 1px 5px; border-radius: 3px; background: #1e2124;
        border: 1px solid #596773; color: #d5d8db; font-size: 11px;
    }
    .tc-files-label {
        font-size: 12px; font-weight: 600; color: #9fadbc;
        margin: 0 0 8px;
    }

    .tc-btn {
        border: 0; border-radius: 3px; padding: 6px 12px;
        font-size: 14px; font-weight: 500; cursor: pointer;
        background: var(--tc-btn); color: var(--tc-text-strong); line-height: 20px;
    }
    .tc-btn:hover { background: var(--tc-hover); }
    .tc-btn-primary { background: var(--tc-accent); color: var(--tc-accent-ink); }
    .tc-btn-primary:hover { background: #85b8ff; }
    .tc-btn-ghost { background: transparent; color: var(--tc-muted); }
    .tc-btn-ghost:hover { background: var(--tc-btn); color: var(--tc-text); }
    .tc-btn-danger { background: transparent; color: var(--tc-danger); padding: 2px 6px; font-size: 12px; }
    .tc-btn-danger:hover { background: rgba(248,113,104,.12); }
    .tc-btn-sm { padding: 4px 10px; font-size: 12px; }

    .tc-chip {
        display: inline-flex; align-items: center; gap: 6px; border: 0;
        border-radius: 3px; padding: 6px 10px; font-size: 12px; font-weight: 600;
        cursor: pointer; margin: 0 6px 6px 0; background: var(--tc-btn); color: var(--tc-text-strong);
    }
    .tc-chip.active { box-shadow: inset 0 0 0 2px var(--tc-accent); }
    .tc-chip .swatch { width: 16px; height: 16px; border-radius: 3px; }

    /* Attachments — Trello row */
    .tc-att {
        display: flex; gap: 12px; align-items: stretch;
        margin-bottom: 8px; border-radius: 8px; overflow: visible;
        background: transparent; position: relative; z-index: 1;
    }
    .tc-att:has(.tc-att-menu.show) { z-index: 20; }
    .tc-att:hover { background: rgba(255,255,255,.03); }
    .tc-att img, .tc-att .ph {
        width: 112px; height: 80px; object-fit: cover; flex-shrink: 0;
        background: #1e2124; border-radius: 8px;
    }
    .tc-att .ph {
        display: flex; align-items: center; justify-content: center;
        font-size: 15px; font-weight: 800; letter-spacing: .04em;
        color: #f1f2f4; text-transform: uppercase;
        user-select: none;
    }
    .tc-att .ph.ext-docx, .tc-att .ph.ext-doc { background: #2a3a4d; color: #85b8ff; }
    .tc-att .ph.ext-pdf { background: #3d2a2a; color: #f87168; }
    .tc-att .ph.ext-xlsx, .tc-att .ph.ext-xls, .tc-att .ph.ext-csv { background: #243a2a; color: #4bce97; }
    .tc-att .ph.ext-pptx, .tc-att .ph.ext-ppt { background: #3d3220; color: #f5cd47; }
    .tc-att .ph.ext-zip, .tc-att .ph.ext-rar, .tc-att .ph.ext-7z { background: #332a3d; color: #9f8fef; }
    .tc-att .ph.ext-txt, .tc-att .ph.ext-md, .tc-att .ph.ext-rtf { background: #2b2c2f; color: #b6c2cf; }
    .tc-att .ph.ext-mp4, .tc-att .ph.ext-mov, .tc-att .ph.ext-avi, .tc-att .ph.ext-webm { background: #2a303d; color: #6cc3e0; }
    .tc-att .ph.ext-mp3, .tc-att .ph.ext-wav, .tc-att .ph.ext-m4a { background: #2a3530; color: #94c748; }
    .tc-att .ph.ext-fig, .tc-att .ph.ext-sketch, .tc-att .ph.ext-ai, .tc-att .ph.ext-psd { background: #3d2a38; color: #e774bb; }
    .tc-att .ph.ext-link { background: #1e2a3d; color: #579dff; font-size: 13px; }
    .tc-att .meta {
        flex: 1; min-width: 0; padding: 4px 0;
        display: flex; flex-direction: column; justify-content: center; gap: 4px;
    }
    .tc-att .name {
        color: var(--tc-text-strong); font-weight: 600; font-size: 14px;
        text-decoration: none; word-break: break-all;
    }
    .tc-att .name:hover { text-decoration: underline; color: var(--tc-link); }
    .tc-att .sub { font-size: 12px; color: var(--tc-muted); display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .tc-cover-badge {
        display: inline-flex; align-items: center; gap: 4px;
        background: #1f845a; color: #fff; font-size: 11px; font-weight: 600;
        padding: 2px 6px; border-radius: 3px;
    }
    .tc-att .ops {
        display: flex; gap: 4px; align-items: flex-start; padding: 4px 4px 0 0;
        position: relative;
    }
    .tc-att .ops .tc-icon-btn {
        width: 28px; height: 28px; background: var(--tc-btn); color: var(--tc-text);
        font-size: 13px; border-radius: 6px;
    }
    .tc-att .ops .tc-icon-btn:hover { background: var(--tc-hover); }
    .tc-att-menu {
        display: none; position: absolute; top: 32px; right: 0; z-index: 25;
        min-width: 180px; padding: 6px; border-radius: 8px;
        background: #282e33; border: 1px solid #384047;
        box-shadow: 0 8px 24px rgba(0,0,0,.45);
    }
    .tc-att-menu.show { display: block; }
    .tc-att-toggle {
        margin-top: 8px; background: transparent; color: #b6c2cf;
        box-shadow: inset 0 0 0 1px #384047;
    }
    .tc-att-toggle:hover { background: #33383e; color: #dee4ea; }
    .tc-att-menu button {
        display: block; width: 100%; text-align: left; border: 0;
        background: transparent; color: #d5d8db; padding: 8px 10px;
        border-radius: 6px; font-size: 14px; cursor: pointer;
    }
    .tc-att-menu button:hover {
        background: rgba(88, 132, 212, .22);
        box-shadow: inset 3px 0 0 #579dff;
    }
    .tc-att-menu button.danger { color: #f87168; }
    .tc-att-menu button.danger:hover { background: rgba(248,113,104,.12); }

    /* Checklists */
    .tc-checklist {
        background: transparent; margin-bottom: 24px;
    }
    .tc-checklist-h {
        display: flex; align-items: center; justify-content: space-between;
        gap: 8px; margin-bottom: 6px; min-height: 32px;
    }
    .tc-checklist-h strong {
        display: inline-flex; align-items: center; gap: 10px; min-width: 0;
        font-size: 14px; font-weight: 600; color: var(--tc-text-strong);
        overflow-wrap: anywhere;
    }
    .tc-checklist-h strong i { font-size: 16px; color: var(--tc-muted); width: 24px; flex: 0 0 24px; text-align: center; }
    .tc-checklist-h .ops { display: flex; gap: 8px; flex-shrink: 0; }
    /* Progress: percent in the icon gutter, bar to the right */
    .tc-check-progress { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
    .tc-check-progress .pct { width: 24px; flex: 0 0 24px; font-size: 11px; color: var(--tc-muted); text-align: center; }
    .tc-check-progress .bar { flex: 1; height: 8px; border-radius: 4px; background: #384047; overflow: hidden; }
    .tc-check-progress .fill { display: block; height: 100%; border-radius: 4px; background: #579dff; transition: width .2s ease; }
    .tc-check-progress.is-done .fill { background: #4bce97; }
    .tc-checkitem {
        display: flex; align-items: flex-start; gap: 10px;
        padding: 6px 4px; font-size: 14px; border-radius: 8px;
        color: var(--tc-text-strong);
    }
    .tc-checkitem:hover { background: rgba(255,255,255,.04); }
    .tc-checkitem input { width: 16px; height: 16px; margin: 2px 4px 0; flex: 0 0 16px; accent-color: var(--tc-accent); cursor: pointer; }
    .tc-checkitem span { overflow-wrap: anywhere; }
    .tc-checkitem.done span { text-decoration: line-through; color: var(--tc-muted); }
    .tc-checkitem .tc-del-item { visibility: hidden; }
    .tc-checkitem:hover .tc-del-item { visibility: visible; }
    .tc-checklist.hide-checked .tc-checkitem.done { display: none; }
    .tc-check-add { margin: 8px 0 0 34px; }
    .tc-check-add-form { display: none; }
    .tc-check-add.is-open .tc-check-add-form { display: block; }
    .tc-check-add.is-open .tc-check-add-open { display: none; }
    .tc-check-add-form textarea {
        width: 100%; box-sizing: border-box; min-height: 56px; resize: vertical;
        background: #22272b; color: var(--tc-text-strong); font: inherit; font-size: 14px;
        border: 1px solid #85b8ff; box-shadow: inset 0 0 0 1px #85b8ff;
        border-radius: 3px; padding: 8px 10px; outline: none;
    }
    .tc-check-add-form .row-actions { display: flex; gap: 6px; margin-top: 8px; }

    /* Comments sidebar */
    .tc-side-h {
        display: flex; align-items: center; justify-content: space-between;
        gap: 8px; margin-bottom: 12px;
    }
    .tc-side-h .left {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: 14px; font-weight: 600; color: var(--tc-text-strong);
    }
    .tc-side-h .left i { color: var(--tc-muted); }
    .tc-hide-btn {
        border: 0; background: #33383e; color: #f1f2f4;
        font-size: 12px; font-weight: 500; cursor: pointer; padding: 4px 10px;
        border-radius: 3px;
    }
    .tc-hide-btn:hover { background: #40464d; }

    .tc-comment-composer {
        margin-bottom: 16px;
    }
    .tc-comment-composer .tc-desc-editor {
        background: #22272b;
        border-color: #384047;
        border-radius: 8px;
    }
    .tc-comment-composer.is-open .tc-desc-editor { border-color: #738496; }
    .tc-comment-composer .tc-desc-editor:focus-within { border-color: #85b8ff; }
    .tc-comment-composer .tc-desc-toolbar { display: none; }
    .tc-comment-composer.is-open .tc-desc-toolbar {
        display: flex; gap: 0; padding: 6px 8px;
        background: transparent; border-bottom: 1px solid #384047;
    }
    /* Trello-style toolbar (comment composer + description editor) */
    .tc-comment-composer .tc-tb,
    #tc-desc-editor .tc-tb { color: #9fadbc; font-weight: 400; height: 32px; padding: 0 6px; border-radius: 4px; }
    .tc-comment-composer .tc-tb i,
    #tc-desc-editor .tc-tb i { font-size: 18px; }
    .tc-comment-composer .tc-tb .chev,
    #tc-desc-editor .tc-tb .chev { font-size: 11px; margin-left: 2px; }
    .tc-comment-composer .tc-tb .tt,
    #tc-desc-editor .tc-tb .tt { font-size: 18px; font-weight: 400; letter-spacing: -.5px; }
    .tc-comment-composer .tc-desc-toolbar .sep,
    #tc-desc-editor .tc-desc-toolbar .sep { height: 20px; margin: 0 6px; background: #454f59; }

    /* Description editor (Trello): gray box, toolbar + text, actions below */
    #tc-desc-editor { background: #22272b; border-color: #738496; border-radius: 8px; }
    #tc-desc-editor:focus-within { border-color: #8c9bab; }
    #tc-desc-editor .tc-desc-toolbar {
        gap: 0; padding: 6px 8px; background: transparent; border-bottom: 1px solid #384047;
    }
    #tc-desc-editor .tc-tb.tc-rich-md strong { font-size: 12px; font-weight: 700; letter-spacing: -.5px; }
    #tc-desc-editor .tc-desc-visual {
        min-height: 200px; max-height: none; overflow: visible;
        padding: 16px 20px; background: transparent; border-radius: 0 0 8px 8px;
        white-space: normal; line-height: 24px; color: #b6c2cf;
    }
    /* WYSIWYG: same Markdown look as the description view */
    :is(#tc-desc-visual, #tc-cmt-visual) > :first-child { margin-top: 0; }
    :is(#tc-desc-visual, #tc-cmt-visual) p { margin: 0 0 12px; }
    :is(#tc-desc-visual, #tc-cmt-visual) h1, :is(#tc-desc-visual, #tc-cmt-visual) h2, :is(#tc-desc-visual, #tc-cmt-visual) h3,
    :is(#tc-desc-visual, #tc-cmt-visual) h4, :is(#tc-desc-visual, #tc-cmt-visual) h5, :is(#tc-desc-visual, #tc-cmt-visual) h6 {
        color: #dee4ea; font-weight: 600; line-height: 1.25; margin: 24px 0 10px;
    }
    :is(#tc-desc-visual, #tc-cmt-visual) h1 { font-size: 24px; }
    :is(#tc-desc-visual, #tc-cmt-visual) h2 { font-size: 22px; }
    :is(#tc-desc-visual, #tc-cmt-visual) h3 { font-size: 18px; }
    :is(#tc-desc-visual, #tc-cmt-visual) h4 { font-size: 16px; }
    :is(#tc-desc-visual, #tc-cmt-visual) h5, :is(#tc-desc-visual, #tc-cmt-visual) h6 { font-size: 14px; }
    :is(#tc-desc-visual, #tc-cmt-visual) ul, :is(#tc-desc-visual, #tc-cmt-visual) ol { margin: 0 0 12px; padding-left: 24px; }
    :is(#tc-desc-visual, #tc-cmt-visual) blockquote {
        margin: 0 0 12px; padding: 0 0 0 16px; border-left: 2px solid #454f59; color: #b6c2cf;
    }
    :is(#tc-desc-visual, #tc-cmt-visual) blockquote > :last-child { margin-bottom: 0; }
    :is(#tc-desc-visual, #tc-cmt-visual) hr { border: 0; border-top: 1px solid #384047; margin: 16px 0; }
    :is(#tc-desc-visual, #tc-cmt-visual) code {
        background: #2c333a; color: #dee4ea; border-radius: 3px; padding: 1px 4px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px;
    }
    :is(#tc-desc-visual, #tc-cmt-visual) pre {
        margin: 0 0 12px; padding: 8px 0; border-radius: 6px; background: #2c333a;
        overflow-x: auto; counter-reset: ln; white-space: pre;
    }
    :is(#tc-desc-visual, #tc-cmt-visual) pre code { background: none; padding: 0; display: block; }
    :is(#tc-desc-visual, #tc-cmt-visual) pre:not(.tc-md-code) code { padding: 0 12px; }
    :is(#tc-desc-visual, #tc-cmt-visual) pre .ln { display: block; white-space: pre; padding-right: 12px; line-height: 21px; }
    :is(#tc-desc-visual, #tc-cmt-visual) pre.is-wrap .ln { white-space: pre-wrap; word-break: break-word; }
    /* Attachment preview lightbox */
    .tc-lightbox {
        position: fixed; inset: 0; z-index: 10080;
        background: rgba(0, 0, 0, .85);
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 56px 72px 150px;
    }
    .tc-lightbox[hidden] { display: none; }
    .tc-lb-stage {
        flex: 1; width: 100%; min-height: 0;
        display: flex; align-items: center; justify-content: center;
    }
    .tc-lb-stage img {
        max-width: 100%; max-height: 100%; object-fit: contain;
        border-radius: 4px; box-shadow: 0 8px 32px rgba(0,0,0,.6); background: #fff;
    }
    .tc-lb-stage iframe { width: min(1000px, 100%); height: 100%; border: 0; border-radius: 4px; background: #fff; }
    .tc-lb-file {
        display: flex; flex-direction: column; align-items: center; gap: 16px; color: #b6c2cf; text-align: center;
    }
    .tc-lb-file .ext {
        width: 160px; height: 120px; border-radius: 8px; background: #2c333a;
        display: flex; align-items: center; justify-content: center;
        font-size: 28px; font-weight: 700; color: #dee4ea;
    }
    .tc-lb-close {
        position: absolute; top: 16px; right: 16px; width: 40px; height: 40px;
        border: 0; border-radius: 50%; background: rgba(255,255,255,.08); color: #fff;
        font-size: 18px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    }
    .tc-lb-close:hover { background: rgba(255,255,255,.18); }
    .tc-lb-nav {
        position: absolute; bottom: 24px; width: 72px; height: 72px; border: 0; border-radius: 8px;
        background: transparent; color: #dee4ea; font-size: 22px; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .tc-lb-nav:hover { background: rgba(255,255,255,.08); }
    .tc-lb-nav.prev { left: 16px; }
    .tc-lb-nav.next { right: 16px; }
    .tc-lb-nav[hidden] { display: none; }
    .tc-lb-info {
        position: absolute; left: 0; right: 0; bottom: 20px;
        display: flex; flex-direction: column; align-items: center; gap: 6px; color: #fff;
        pointer-events: none;
    }
    .tc-lb-info .name { font-size: 22px; font-weight: 700; max-width: 70vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tc-lb-info .meta { font-size: 14px; color: #dee4ea; }
    .tc-lb-info .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 4px; margin-top: 4px; pointer-events: auto; }
    .tc-lb-info .actions a, .tc-lb-info .actions button {
        display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 4px;
        border: 0; background: transparent; color: #fff; font-size: 14px; font-weight: 600;
        text-decoration: none; cursor: pointer;
    }
    .tc-lb-info .actions a:hover, .tc-lb-info .actions button:hover { background: rgba(255,255,255,.12); color: #fff; }
    @media (max-width: 700px) {
        .tc-lightbox { padding: 56px 12px 170px; }
        .tc-lb-nav { width: 48px; height: 48px; }
    }

    /* Selected image in the editors */
    .tc-rich-visual img.is-selected { outline: 2px solid #579dff; outline-offset: 2px; border-radius: 4px; }
    .tc-img-bar { position: fixed; z-index: 10065; }
    .tc-img-bar[hidden], .tc-img-menu[hidden] { display: none; }
    .tc-img-bar .more {
        width: 40px; height: 40px; border: 0; border-radius: 6px; cursor: pointer;
        background: #282e33; color: #b6c2cf; font-size: 18px;
        display: inline-flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-img-bar .more:hover, .tc-img-bar .more.active { background: #33383e; color: #dee4ea; }
    .tc-img-menu {
        position: absolute; top: 46px; left: 50%; transform: translateX(-50%);
        min-width: 180px; padding: 6px 0; border-radius: 6px; background: #282e33;
        box-shadow: 0 8px 24px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-img-menu button {
        display: flex; align-items: center; gap: 12px; width: 100%; border: 0; background: transparent;
        color: #b6c2cf; padding: 8px 14px; font-size: 14px; cursor: pointer; text-align: left;
    }
    .tc-img-menu button i { font-size: 16px; }
    .tc-img-menu button:hover { background: #33383e; color: #dee4ea; }

    /* Link toolbar + edit popover (editors) */
    .tc-link-bar {
        position: fixed; z-index: 10065; display: flex; align-items: center; gap: 2px;
        padding: 4px; border-radius: 6px; background: #282e33;
        box-shadow: 0 4px 12px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-link-bar[hidden], .tc-link-edit[hidden] { display: none; }
    .tc-link-bar button {
        position: relative; width: 32px; height: 32px; border: 0; border-radius: 4px; cursor: pointer;
        background: transparent; color: #b6c2cf; font-size: 16px;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .tc-link-bar button:hover { background: #33383e; color: #dee4ea; }
    .tc-link-bar .sep { width: 1px; height: 20px; background: #454f59; margin: 0 2px; }
    .tc-link-bar .tc-unlink-slash { position: absolute; font-size: 18px; }
    .tc-link-edit {
        position: fixed; z-index: 10066; width: 340px; max-width: calc(100vw - 16px);
        padding: 16px; border-radius: 8px; background: #282e33;
        box-shadow: 0 8px 24px rgba(0,0,0,.5), 0 0 0 1px #384047;
    }
    .tc-link-edit label { display: block; font-size: 12px; font-weight: 700; color: #b6c2cf; margin: 0 0 4px; }
    .tc-link-edit label + .tc-link-edit-field { margin-bottom: 12px; }
    .tc-link-edit .req { color: #f87168; }
    .tc-link-edit-field { position: relative; }
    .tc-link-edit-field input {
        width: 100%; box-sizing: border-box; height: 40px; padding: 8px 36px 8px 10px;
        background: #22272b; color: #dee4ea; font-size: 14px;
        border: 1px solid #738496; border-radius: 4px; outline: none;
    }
    .tc-link-edit-field input:focus { border-color: #85b8ff; box-shadow: inset 0 0 0 1px #85b8ff; }
    .tc-link-edit-field .clear {
        position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
        border: 0; background: transparent; color: #8c9bab; cursor: pointer; font-size: 16px; padding: 0;
    }
    .tc-link-edit .hint { margin: -8px 0 16px; font-size: 12px; color: #8c9bab; }
    .tc-link-edit .actions { display: flex; justify-content: flex-end; gap: 8px; }

    /* Code block bar (language / wrap / more) */
    .tc-code-bar {
        position: fixed; z-index: 10065; display: flex; align-items: center; gap: 4px;
        padding: 4px; border-radius: 6px; background: #282e33;
        box-shadow: 0 4px 12px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-code-bar[hidden] { display: none; }
    .tc-code-lang-wrap { position: relative; }
    .tc-code-lang-wrap select {
        appearance: none; -webkit-appearance: none; height: 32px; min-width: 190px;
        padding: 0 30px 0 10px; border-radius: 4px; border: 1px solid #738496;
        background: #22272b; color: #b6c2cf; font-size: 14px; cursor: pointer; outline: none;
    }
    .tc-code-lang-wrap select:focus { border-color: #85b8ff; }
    .tc-code-lang-wrap select option { background: #22272b; color: #b6c2cf; }
    .tc-code-lang-wrap i {
        position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
        font-size: 12px; color: #b6c2cf; pointer-events: none;
    }
    .tc-code-btn {
        width: 32px; height: 32px; border: 0; border-radius: 4px; cursor: pointer;
        background: transparent; color: #b6c2cf; font-size: 16px;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .tc-code-btn:hover, .tc-code-btn.active { background: #33383e; color: #dee4ea; }
    .tc-code-more-wrap { position: relative; }
    .tc-code-menu {
        position: absolute; top: 36px; right: 0; min-width: 160px; padding: 6px; border-radius: 6px;
        background: #282e33; box-shadow: 0 8px 24px rgba(0,0,0,.45), 0 0 0 1px #384047;
    }
    .tc-code-menu[hidden] { display: none; }
    .tc-code-menu button {
        display: flex; align-items: center; gap: 8px; width: 100%; border: 0; background: transparent;
        color: #b6c2cf; padding: 6px 10px; border-radius: 4px; font-size: 14px; cursor: pointer; text-align: left;
    }
    .tc-code-menu button:hover { background: #33383e; }
    .tc-code-menu button.danger { color: #f87168; }
    :is(#tc-desc-visual, #tc-cmt-visual) pre .ln::before {
        counter-increment: ln; content: counter(ln);
        display: inline-block; width: 32px; padding-right: 10px; margin-right: 10px;
        text-align: right; color: #8c9bab; user-select: none;
    }
    .tc-desc-actions.tc-desc-actions-out {
        padding: 0; margin-top: 8px; background: transparent; border: 0; gap: 8px;
    }
    .tc-desc-actions-out .tc-btn { padding: 6px 12px; }
    .tc-desc-actions-out #tc-cancel-desc { color: #b6c2cf; }
    .tc-desc-actions-out #tc-cancel-desc:hover { background: #33383e; color: #dee4ea; }
    .tc-fmt-help {
        background: transparent; color: #b6c2cf; box-shadow: inset 0 0 0 1px #384047;
    }
    .tc-fmt-help:hover { background: #33383e; color: #dee4ea; }
    .tc-comment-composer .tc-desc-visual {
        min-height: 64px; max-height: none; overflow: visible;
        padding: 12px 14px;
        background: transparent; border-radius: 0 0 8px 8px;
        white-space: normal; line-height: 24px; color: #b6c2cf;
    }
    .tc-comment-composer:not(.is-open) .tc-desc-visual {
        min-height: 36px; max-height: 36px; overflow: hidden; padding: 8px 12px;
    }
    .tc-comment-composer .tc-desc-visual:empty:before { color: #8c9bab; }
    /* Save + Watch below the box (no box chrome) */
    .tc-comment-composer .tc-cmt-actions {
        display: none; padding: 0; margin-top: 12px;
        background: transparent; border: 0;
    }
    .tc-comment-composer.is-open .tc-cmt-actions { display: flex; align-items: center; gap: 16px; }
    .tc-comment-composer #tc-add-comment { padding: 6px 12px; }
    .tc-comment-composer #tc-add-comment:disabled {
        background: #33383e; color: #596773; cursor: not-allowed;
        box-shadow: inset 0 0 0 1px #454f59;
    }
    .tc-watch-label {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: 14px; color: #b6c2cf; cursor: pointer; user-select: none;
        margin: 0;
    }
    .tc-watch-label input {
        width: 16px; height: 16px; margin: 0; accent-color: #579dff; cursor: pointer;
    }

    .tc-comment {
        display: flex; gap: 10px; margin-bottom: 16px;
    }
    .tc-avatar {
        width: 32px; height: 32px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 700; flex-shrink: 0; color: #fff;
    }
    .tc-comment-body { flex: 1; min-width: 0; overflow: hidden; max-width: 100%; }
    .tc-comment-meta {
        font-size: 12px; color: var(--tc-muted); margin-bottom: 4px;
        display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px;
    }
    .tc-comment-meta strong { color: var(--tc-text-strong); font-weight: 600; font-size: 14px; }
    .tc-comment-meta .when {
        color: #579dff; text-decoration: none; font-size: 12px; cursor: default;
    }
    .tc-comment-meta .when:hover { text-decoration: underline; }
    .tc-bubble {
        background: var(--tc-elevated);
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 14px; line-height: 1.45; color: var(--tc-text);
        white-space: normal; word-break: break-word;
        box-shadow: 0 0 0 1px rgba(255,255,255,.04);
        overflow: hidden;
        max-width: 100%;
        box-sizing: border-box;
    }
    .tc-bubble a { color: var(--tc-link); text-decoration: none; }
    /* Comments render Markdown like the description (shared rules use :is(.tc-desc-view, .tc-bubble)) */
    .tc-bubble > :first-child { margin-top: 0; }
    .tc-bubble > :last-child { margin-bottom: 0; }
    .tc-bubble h1, .tc-bubble h2, .tc-bubble h3 { margin-top: 12px; }
    .tc-bubble a:hover { text-decoration: underline; }
    .tc-comment-links {
        margin-top: 6px; font-size: 12px; color: #8c9bab;
        display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
    }
    .tc-comment-links button:not(.tc-react):not(.tc-reaction-chip) {
        border: 0; background: none; color: #8c9bab;
        font-size: 12px; cursor: pointer; padding: 0; line-height: 1.2;
    }
    .tc-comment-links button:not(.tc-react):not(.tc-reaction-chip):hover {
        color: #85b8ff; text-decoration: underline;
    }
    .tc-comment-links .sep { opacity: .55; color: #8c9bab; margin: 0 2px; }
    .tc-comment-links .tc-react {
        display: inline-flex; align-items: center; justify-content: center;
        width: 24px; height: 24px; border-radius: 50%;
        border: 1px solid #384047; background: #22272b; color: #8c9bab;
        text-decoration: none !important; padding: 0; position: relative;
        margin-right: 2px;
    }
    .tc-comment-links .tc-react:hover {
        background: #33383e; color: #d5d8db; border-color: #596773;
        text-decoration: none !important;
    }
    .tc-comment-links .tc-react i { font-size: 13px; }
    .tc-comment-links .tc-react .plus {
        position: absolute; right: 1px; bottom: 0; font-size: 9px;
        font-weight: 700; line-height: 1; color: #8c9bab;
        text-shadow: 0 0 2px #22272b;
    }
    .tc-reaction-chip {
        display: inline-flex; align-items: center; gap: 4px;
        border: 1px solid #579dff; background: rgba(87, 157, 255, .08);
        border-radius: 14px; padding: 1px 8px 1px 6px;
        font-size: 14px; line-height: 1.35; cursor: default;
        color: #d5d8db; height: 24px; box-sizing: border-box;
    }
    .tc-reaction-chip .n {
        color: #579dff; font-size: 12px; font-weight: 600; margin: 0;
    }

    /* Emoji picker (Trello-style) */
    .tc-emoji-pop {
        display: none; position: fixed; z-index: 10070;
        width: min(352px, calc(100vw - 16px));
        background: #282e33; border: 1px solid #384047; border-radius: 10px;
        box-shadow: 0 12px 40px rgba(0,0,0,.55);
        overflow: hidden;
    }
    .tc-emoji-pop.show { display: flex; flex-direction: column; }
    .tc-emoji-cats {
        display: flex; align-items: center; gap: 2px;
        padding: 8px 8px 0; border-bottom: 1px solid #384047;
        overflow-x: auto;
    }
    .tc-emoji-cats button {
        border: 0; background: transparent; color: #8c9bab;
        width: 32px; height: 30px; border-radius: 6px 6px 0 0;
        cursor: pointer; font-size: 15px; flex-shrink: 0;
        border-bottom: 2px solid transparent;
    }
    .tc-emoji-cats button:hover { color: #d5d8db; background: #33383e; }
    .tc-emoji-cats button.active {
        color: #579dff; border-bottom-color: #579dff; background: transparent;
    }
    .tc-emoji-search-wrap {
        padding: 10px 12px 8px; position: relative;
        display: flex; align-items: center; gap: 10px;
        border-bottom: 1px solid #384047;
    }
    .tc-emoji-search-wrap i {
        position: absolute; left: 22px; top: 50%; transform: translateY(-50%);
        color: #8c9bab; font-size: 13px; pointer-events: none;
    }
    .tc-emoji-search {
        flex: 1; min-width: 0; box-sizing: border-box;
        background: #22272b; border: 1px solid #738496; border-radius: 4px;
        color: #f1f2f4; font-size: 14px; padding: 7px 10px 7px 32px; outline: none;
    }
    .tc-emoji-search:focus { border-color: #85b8ff; box-shadow: inset 0 0 0 1px #85b8ff; }
    .tc-emoji-search::placeholder { color: #738496; }
    .tc-emoji-body {
        max-height: 260px; overflow-y: auto; padding: 4px 10px 10px;
        scrollbar-width: thin; scrollbar-color: #596773 transparent;
    }
    .tc-emoji-sec-title {
        font-size: 12px; font-weight: 600; color: #8c9bab;
        margin: 8px 2px 6px; text-transform: none;
    }
    .tc-emoji-grid {
        display: grid; grid-template-columns: repeat(9, 1fr); gap: 2px;
    }
    .tc-emoji-grid button {
        border: 0; background: transparent; border-radius: 6px;
        font-size: 20px; line-height: 1; height: 34px; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .tc-emoji-grid button:hover, .tc-emoji-grid button.is-hover {
        background: #33383e;
    }
    .tc-emoji-foot {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 12px; border-top: 1px solid #384047;
        background: #22272b; min-height: 44px;
    }
    .tc-emoji-preview {
        font-size: 26px; width: 34px; text-align: center; line-height: 1;
    }
    .tc-emoji-hint { flex: 1; font-size: 12px; color: #8c9bab; }
    .tc-emoji-foot[hidden] { display: none; }
    .tc-emoji-skin {
        flex: 0 0 auto; width: 32px; height: 32px; border-radius: 4px; border: 0;
        background: transparent; cursor: pointer; padding: 0; font-size: 20px; line-height: 1;
    }
    .tc-emoji-skin:hover { background: #33383e; }

    /* "+" insert menu (Trello-style) */
    .tc-dd.tc-insert-dd { width: 320px; max-width: calc(100% - 8px); padding: 0 0 6px; overflow: hidden; }
    .tc-ins-search {
        position: relative; display: flex; align-items: center; margin: 12px;
        border: 1px solid #738496; border-radius: 4px; background: #22272b;
    }
    .tc-ins-search:focus-within { border-color: #85b8ff; box-shadow: inset 0 0 0 1px #85b8ff; }
    .tc-ins-search > i { position: absolute; left: 10px; color: #9fadbc; font-size: 14px; pointer-events: none; }
    .tc-ins-q {
        flex: 1; min-width: 0; border: 0; outline: none; background: transparent;
        color: #f1f2f4; font-size: 14px; padding: 8px 8px 8px 32px;
    }
    .tc-ins-q::placeholder { color: #8c9bab; }
    .tc-ins-enter {
        margin-right: 6px; padding: 1px 6px; border-radius: 3px;
        background: #33383e; color: #9fadbc; font-size: 12px; white-space: nowrap;
    }
    .tc-dd-item.tc-ins-item {
        align-items: flex-start; gap: 12px; padding: 10px 12px; border-radius: 0;
    }
    .tc-dd-item.tc-ins-item[hidden] { display: none; }
    .tc-ins-item .ico {
        flex: 0 0 40px; width: 40px; height: 40px; border-radius: 4px;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid #384047; background: #22272b;
        color: #b6c2cf; font-size: 18px; font-weight: 600;
    }
    .tc-ins-item.active .ico, .tc-ins-item:hover .ico { color: #579dff; }
    .tc-ins-item .txt { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .tc-ins-item strong { font-size: 14px; font-weight: 600; color: #dee4ea; }
    .tc-ins-item .sub { font-size: 13px; line-height: 18px; color: #9fadbc; white-space: normal; }
    .tc-ins-item kbd {
        flex: 0 0 auto; margin-top: 2px; padding: 1px 5px; border-radius: 3px;
        background: #33383e; color: #9fadbc; font-family: inherit; font-size: 11px;
    }
    .tc-ins-empty { padding: 8px 14px; }

    /* Activity (non-comment) rows — Trello style */
    .tc-activity-line {
        font-size: 14px; color: #d5d8db; line-height: 1.4; margin-bottom: 2px;
    }
    .tc-activity-line a { color: #579dff; text-decoration: none; font-weight: 600; }
    .tc-activity-line a:hover { text-decoration: underline; }
    .tc-activity-line strong { color: #f1f2f4; font-weight: 600; }
    .tc-comment-body > .when {
        display: inline-block; color: #579dff; font-size: 12px; margin-bottom: 6px;
    }
    .tc-activity-img {
        display: block; max-width: 100%; width: auto; max-height: 180px;
        border-radius: 8px; margin: 6px 0 4px; object-fit: cover;
        background: #1e2124;
    }
    .tc-mention-pill {
        display: inline-block; background: #3b4148; color: #dee4ea;
        border-radius: 12px; padding: 1px 8px; font-size: 13px; margin-right: 2px;
        font-weight: 500; white-space: nowrap;
    }
    /* Mentions of the connected Trello account are highlighted blue, like Trello */
    .tc-mention-pill.is-me { background: #579dff; color: #1d2125; }

    /* Smart link chip in comments: favicon + page title */
    .tc-bubble a.tc-smart-link {
        display: inline-flex; align-items: center; gap: 6px; max-width: 100%;
        vertical-align: middle; margin: 2px 0;
        padding: 1px 6px; border-radius: 3px;
        border: 1px solid #384047; background: #22272b;
        color: #85b8ff; text-decoration: none;
    }
    .tc-bubble a.tc-smart-link:hover { background: #2c333a; text-decoration: none; }
    .tc-smart-link .ico { width: 16px; height: 16px; border-radius: 2px; object-fit: contain; flex: 0 0 16px; }
    .tc-smart-link .t { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* Smart link hover card */
    .tc-link-hover {
        position: fixed; z-index: 10070; width: 360px; max-width: calc(100vw - 16px);
        background: #282e33; color: #b6c2cf; border-radius: 8px; padding: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,.5), 0 0 0 1px #384047;
        font-size: 14px;
    }
    .tc-link-hover[hidden] { display: none; }
    .tc-link-hover .hd { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .tc-link-hover .hd .ico { width: 24px; height: 24px; flex: 0 0 24px; border-radius: 3px; object-fit: contain; }
    .tc-link-hover .ttl {
        color: #579dff; font-weight: 600; text-decoration: underline;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .tc-link-hover .desc {
        margin: 10px 0 0; font-size: 13px; line-height: 18px; color: #b6c2cf;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .tc-link-hover .copy {
        display: flex; align-items: center; gap: 10px; width: calc(100% + 24px);
        margin: 8px -12px 0; padding: 8px 12px; border: 0; background: transparent;
        color: #b6c2cf; font-size: 14px; text-align: left; cursor: pointer;
    }
    .tc-link-hover .copy i { font-size: 18px; }
    .tc-link-hover .copy:hover { background: #33383e; color: #dee4ea; }
    .tc-link-hover .ft {
        display: flex; align-items: center; gap: 8px; margin-top: 6px;
        font-size: 13px; color: #8c9bab;
    }
    .tc-link-hover .ft .ico { width: 16px; height: 16px; border-radius: 2px; object-fit: contain; }
    .tc-mention-menu {
        display: none; position: fixed; z-index: 10060;
        min-width: 280px; max-width: min(360px, calc(100vw - 24px));
        max-height: 280px; overflow-y: auto;
        background: #282e33; border: 1px solid #384047; border-radius: 8px;
        box-shadow: 0 8px 28px rgba(0,0,0,.5); padding: 6px;
    }
    .tc-mention-menu.show { display: block; }
    .tc-mention-menu button {
        display: flex; align-items: center; gap: 10px; width: 100%;
        border: 0; background: transparent; color: #d5d8db;
        padding: 8px 10px; border-radius: 6px; cursor: pointer; text-align: left;
    }
    .tc-mention-menu button:hover,
    .tc-mention-menu button.active {
        background: rgba(88, 132, 212, .22);
        box-shadow: inset 3px 0 0 #579dff;
    }
    .tc-mention-menu .av {
        width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 700; color: #fff;
    }
    .tc-mention-menu .av.board {
        background: #44546f; font-size: 14px;
    }
    .tc-mention-menu .meta { min-width: 0; flex: 1; }
    .tc-mention-menu .name {
        font-size: 14px; font-weight: 600; color: #f1f2f4;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tc-mention-menu .handle {
        font-size: 12px; color: #9fadbc;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tc-mention-menu .empty {
        padding: 12px 10px; font-size: 13px; color: #9fadbc;
    }

</style>

<div class="tc-backdrop" id="trello-drawer-backdrop" aria-hidden="true">
    <div class="tc-modal" id="trello-drawer" role="dialog" aria-modal="true" style="width:min(1100px,calc(100vw - 48px));max-width:min(1100px,calc(100vw - 48px))">
        <div class="tc-cover-wrap" id="tc-cover-wrap">
            <img src="" alt="" id="tc-cover" hidden>
            <div class="tc-cover-list" id="tc-cover-list" hidden>
                <button type="button" class="tc-list-btn" id="tc-list-btn" title="Move card">
                    <span id="tc-list-name"></span>
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </button>
            </div>
            <div class="tc-cover-actions">
                <button type="button" class="tc-icon-btn" id="tc-cover-btn" title="Cover" aria-label="Cover">
                    <i class="bi bi-image"></i>
                </button>
                <button type="button" class="tc-icon-btn" id="tc-close" title="Close" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        {{-- "Cover" popover (opens under the cover button, top-right) --}}
        <div class="tc-cover-pop" id="tc-cover-pop" hidden>
            <div class="tc-add-head">
                <h3>Cover</h3>
                <button type="button" class="tc-add-close" id="tc-cover-close" title="Close" aria-label="Close">&times;</button>
            </div>
            <div class="tc-cover-pop-body">
                <div class="tc-cover-h">Size</div>
                <div class="tc-cover-sizes">
                    <button type="button" class="tc-cover-size" data-size="normal" title="Normal">
                        <span class="pv-top js-cover-pv"></span>
                        <span class="pv-body">
                            <span class="ln w80"></span><span class="ln w60"></span>
                            <span class="pv-foot"><span class="ln w20"></span><span class="ln w20"></span><span class="dot"></span></span>
                        </span>
                    </button>
                    <button type="button" class="tc-cover-size" data-size="full" title="Full">
                        <span class="pv-full js-cover-pv">
                            <span class="ln w80"></span><span class="ln w60"></span>
                        </span>
                    </button>
                </div>
                <button type="button" class="tc-cover-wide-btn" id="tc-cover-remove">Remove cover</button>

                <div class="tc-cover-h">Colors</div>
                <div class="tc-cover-colors" id="tc-cover-colors"></div>

                <div class="tc-cover-h">Attachments</div>
                <div class="tc-cover-atts" id="tc-cover-atts"></div>
                <button type="button" class="tc-cover-wide-btn" id="tc-cover-more" hidden>Show more</button>
                <button type="button" class="tc-cover-wide-btn" id="tc-cover-upload">Upload a cover image</button>
                <input type="file" id="tc-cover-file" accept="image/*" hidden>            </div>
        </div>

        {{-- "Move card" popover (opens under the list button on the cover) --}}
        <div class="tc-move-pop" id="tc-move-pop" hidden>
            <div class="tc-add-head">
                <h3>Move card</h3>
                <button type="button" class="tc-add-close" id="tc-move-close" title="Close" aria-label="Close">&times;</button>
            </div>
            <div class="tc-move-tabs"><span class="active">Board</span></div>
            <div class="tc-cl-form">
                <div class="tc-move-sub">Select destination</div>
                <label class="tc-cl-label" for="tc-move-board">Board</label>
                <div class="tc-cl-select-wrap">
                    <select class="tc-cl-select" id="tc-move-board"></select>
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </div>
                <div class="tc-move-row">
                    <div class="list">
                        <label class="tc-cl-label" for="tc-move-list">List</label>
                        <div class="tc-cl-select-wrap">
                            <select class="tc-cl-select" id="tc-move-list"></select>
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="pos">
                        <label class="tc-cl-label" for="tc-move-pos">Position</label>
                        <div class="tc-cl-select-wrap">
                            <select class="tc-cl-select" id="tc-move-pos"></select>
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
                <button type="button" class="tc-cl-submit" id="tc-move-submit">Move</button>
            </div>
        </div>

        <div class="tc-body">
            <div class="tc-loading" id="tc-loading">
                <div class="tc-spinner" aria-hidden="true"></div>
                <div class="tc-loading-text">Loading card…</div>
                <div class="tc-skeleton" aria-hidden="true">
                    <div class="tc-skel w60"></div>
                    <div class="tc-skel w80"></div>
                    <div class="tc-skel h40"></div>
                    <div class="tc-skel w40"></div>
                </div>
            </div>
            <div id="tc-content" hidden>
                <div class="tc-grid">
                    <div class="tc-main">
                        <div class="tc-title-row">
                            <button type="button" class="tc-title-icon" id="tc-complete-toggle" title="Mark complete" aria-label="Mark complete">
                                <i class="bi bi-circle" aria-hidden="true"></i>
                            </button>
                            <input type="text" class="tc-title" id="tc-name" placeholder="Card title">
                        </div>

                        <div class="tc-actions">
                            <button type="button" class="tc-action" data-panel="add"><i class="bi bi-plus-lg"></i> Add</button>
                            {{-- Labels / Dates / Members hide once the card has them (shown in the badges row instead) --}}
                            <button type="button" class="tc-action" data-panel="labels" id="tc-action-labels" title="Open labels">
                                <i class="bi bi-tag"></i> Labels
                                <span class="tc-tip">Open labels <kbd>1</kbd></span>
                            </button>
                            <button type="button" class="tc-action" data-panel="dates" id="tc-action-dates" title="Open dates">
                                <i class="bi bi-clock"></i> Dates
                                <span class="tc-tip">Open dates <kbd>d</kbd></span>
                            </button>
                            <button type="button" class="tc-action" data-panel="checklist"><i class="bi bi-check2-square"></i> Checklist</button>
                            <button type="button" class="tc-action" data-panel="members" id="tc-action-members" title="Open members">
                                <i class="bi bi-person"></i> Members
                                <span class="tc-tip">Open members <kbd>m</kbd></span>
                            </button>
                            <button type="button" class="tc-action" data-panel="attach" title="Attach">
                                <i class="bi bi-paperclip"></i> Attachment
                            </button>
                        </div>

                        <div class="tc-card-badges" id="tc-card-badges" hidden>
                            <div class="tc-card-members" id="tc-card-members" hidden>
                                <div class="tc-card-members-h">Members</div>
                                <div class="tc-card-members-row" id="tc-card-members-row"></div>
                            </div>
                            <div class="tc-card-labels" id="tc-card-labels" hidden>
                                <div class="tc-card-labels-h">Labels</div>
                                <div class="tc-card-labels-row" id="tc-card-labels-row"></div>
                            </div>
                            <div class="tc-card-due" id="tc-card-due" hidden>
                                <div class="tc-card-due-h">Due date</div>
                                <button type="button" class="tc-card-due-btn" id="tc-card-due-btn" title="Open dates">
                                    <span class="when" id="tc-card-due-when"></span>
                                    <span class="badge" id="tc-card-due-badge" hidden></span>
                                    <i class="bi bi-chevron-down chev" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="tc-panel" id="panel-add">
                            <div class="tc-add-head">
                                <h3>Add to card</h3>
                                <button type="button" class="tc-add-close" data-panel="add" title="Close" aria-label="Close">&times;</button>
                            </div>
                            <div class="tc-add-list">
                                <button type="button" class="tc-add-item" data-panel="labels">
                                    <span class="ico"><i class="bi bi-tag"></i></span>
                                    <span class="txt"><strong>Labels</strong><small>Organize, categorize, and prioritize</small></span>
                                </button>
                                <button type="button" class="tc-add-item" data-panel="dates">
                                    <span class="ico"><i class="bi bi-clock"></i></span>
                                    <span class="txt"><strong>Dates</strong><small>Start dates, due dates, and reminders</small></span>
                                </button>
                                <button type="button" class="tc-add-item" data-panel="checklist">
                                    <span class="ico"><i class="bi bi-check2-square"></i></span>
                                    <span class="txt"><strong>Checklist</strong><small>Add subtasks</small></span>
                                </button>
                                <button type="button" class="tc-add-item" data-panel="members">
                                    <span class="ico"><i class="bi bi-person"></i></span>
                                    <span class="txt"><strong>Members</strong><small>Assign members</small></span>
                                </button>
                                <button type="button" class="tc-add-item" data-panel="attach">
                                    <span class="ico"><i class="bi bi-paperclip"></i></span>
                                    <span class="txt"><strong>Attachment</strong><small>Add links, pages, work items, and more</small></span>
                                </button>
                            </div>
                        </div>
                        <div class="tc-panel" id="panel-labels">
                            <div class="tc-labels-head">
                                <button type="button" class="tc-labels-back" id="tc-labels-back" title="Back" aria-label="Back"><i class="bi bi-chevron-left"></i></button>
                                <h3 id="tc-labels-title">Labels</h3>
                                <button type="button" class="tc-labels-close" id="tc-labels-close" title="Close" aria-label="Close">&times;</button>
                            </div>
                            <div class="tc-labels-body">
                                <div class="tc-labels-browse">
                                    <input type="search" class="tc-labels-search" id="tc-labels-search" placeholder="Search labels…" autocomplete="off">
                                    <div class="tc-labels-sec">Labels</div>
                                    <div class="tc-labels-list" id="tc-labels"></div>
                                    <button type="button" class="tc-labels-create-btn" id="tc-labels-create">Create a new label</button>
                                    <div class="tc-labels-divider"></div>
                                    <button type="button" class="tc-labels-cbf-btn" id="tc-labels-cbf">Enable colorblind friendly mode</button>
                                </div>
                                <div class="tc-labels-form" id="tc-labels-form">
                                    <label for="tc-label-name">Title</label>
                                    <input type="text" class="tc-pop-field" id="tc-label-name" placeholder="Label title" maxlength="100">
                                    <label>Select a color</label>
                                    <div class="tc-label-colors" id="tc-label-colors"></div>
                                    <div class="tc-labels-form-actions">
                                        <button type="button" class="tc-btn tc-btn-primary" id="tc-label-save">Create</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tc-panel" id="panel-dates">
                            <div class="tc-dates-head">
                                <h3>Dates</h3>
                                <button type="button" class="tc-dates-close" id="tc-dates-close" title="Close" aria-label="Close">&times;</button>
                            </div>
                            <div class="tc-dates-body">
                                <div class="tc-cal-nav">
                                    <button type="button" id="tc-cal-prev-y" title="Previous year">&laquo;</button>
                                    <button type="button" id="tc-cal-prev-m" title="Previous month">&lsaquo;</button>
                                    <span class="month" id="tc-cal-month">October 2026</span>
                                    <button type="button" id="tc-cal-next-m" title="Next month">&rsaquo;</button>
                                    <button type="button" id="tc-cal-next-y" title="Next year">&raquo;</button>
                                </div>
                                <div class="tc-cal-week">
                                    <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span>
                                    <span>Thu</span><span>Fri</span><span>Sat</span>
                                </div>
                                <div class="tc-cal-grid" id="tc-cal-grid"></div>

                                <div class="tc-dates-field">
                                    <span>Start date</span>
                                    <div class="tc-dates-row">
                                        <input type="checkbox" id="tc-start-on">
                                        <input type="text" class="date-in" id="tc-start-date" placeholder="M/D/YYYY" disabled>
                                    </div>
                                </div>
                                <div class="tc-dates-field">
                                    <span>Due date</span>
                                    <div class="tc-dates-row">
                                        <input type="checkbox" id="tc-due-on" checked>
                                        <input type="text" class="date-in" id="tc-due-date" placeholder="M/D/YYYY">
                                        <input type="time" class="time-in" id="tc-due-time">
                                    </div>
                                </div>
                                <div class="tc-dates-field">
                                    <span>Recurring</span>
                                    <select class="tc-dates-select" id="tc-due-recur" disabled title="Coming soon">
                                        <option value="never" selected>Never</option>
                                    </select>
                                </div>
                                <div class="tc-dates-field">
                                    <span>Set due date reminder</span>
                                    <select class="tc-dates-select" id="tc-due-reminder">
                                        <option value="-1">None</option>
                                        <option value="0">At time of due date</option>
                                        <option value="5">5 Minutes before</option>
                                        <option value="10">10 Minutes before</option>
                                        <option value="15">15 Minutes before</option>
                                        <option value="60">1 Hour before</option>
                                        <option value="1440" selected>1 Day before</option>
                                        <option value="10080">2 Days before</option>
                                    </select>
                                </div>
                                <p class="tc-dates-hint">Reminders will be sent to all members and watchers of this card.</p>
                                <button type="button" class="tc-dates-save" id="tc-save-due">Save</button>
                                <button type="button" class="tc-dates-remove" id="tc-clear-due">Remove</button>
                                <input type="checkbox" id="tc-due-complete" hidden>
                                <input type="hidden" id="tc-due">
                            </div>
                        </div>
                        <div class="tc-panel" id="panel-checklist">
                            <div class="tc-add-head">
                                <h3>Add checklist</h3>
                                <button type="button" class="tc-add-close" data-panel="checklist" title="Close" aria-label="Close">&times;</button>
                            </div>
                            <div class="tc-cl-form">
                                <label class="tc-cl-label" for="tc-new-checklist">Title</label>
                                <input type="text" class="tc-cl-input" id="tc-new-checklist" value="Checklist" autocomplete="off">
                                <label class="tc-cl-label" for="tc-checklist-source">Copy items from…</label>
                                <div class="tc-cl-select-wrap">
                                    <select class="tc-cl-select" id="tc-checklist-source">
                                        <option value="">(none)</option>
                                    </select>
                                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                </div>
                                <button type="button" class="tc-cl-submit" id="tc-add-checklist">Add</button>
                            </div>
                        </div>
                        <div class="tc-panel" id="panel-del-checklist">
                            <div class="tc-add-head">
                                <h3>Delete Checklist?</h3>
                                <button type="button" class="tc-add-close" data-panel="del-checklist" title="Close" aria-label="Close">&times;</button>
                            </div>
                            <div class="tc-del-cl-body">
                                <p>Deleting a checklist is permanent and there is no way to get it back.</p>
                                <button type="button" class="tc-btn-danger-block" id="tc-del-checklist-confirm">Delete checklist</button>
                            </div>
                        </div>
                        <div class="tc-panel" id="panel-members">
                            <div class="tc-members-head">
                                <h3>Members</h3>
                                <button type="button" class="tc-members-close" id="tc-members-close" title="Close" aria-label="Close">&times;</button>
                            </div>
                            <div class="tc-members-body">
                                <input type="search" class="tc-members-search" id="tc-members-search" placeholder="Search members" autocomplete="off">
                                <div class="tc-members-sec">Board members</div>
                                <div class="tc-members-list" id="tc-members"></div>
                            </div>
                        </div>
                        <div class="tc-panel" id="panel-attach">
                            <div class="tc-attach-head">
                                <h3>Attach</h3>
                                <button type="button" class="tc-attach-close" id="tc-attach-close" title="Close" aria-label="Close">&times;</button>
                            </div>
                            <div class="tc-attach-body">
                                <div class="tc-attach-sec-title">Attach a file from your computer</div>
                                <p class="tc-attach-help">You can also drag and drop files to upload them.</p>
                                <button type="button" class="tc-attach-choose" id="tc-attach-choose">Choose a file</button>
                                <input type="file" id="tc-file" hidden>

                                <div class="tc-attach-divider"></div>

                                <label for="tc-attach-link">Search or paste a link <span class="req">*</span></label>
                                <input type="url" class="tc-pop-field" id="tc-attach-link" placeholder="Find recent links or paste a new link">
                                <label for="tc-attach-link-text">Display text (optional)</label>
                                <input type="text" class="tc-pop-field" id="tc-attach-link-text" placeholder="Text to display">
                                <p class="tc-attach-help">Give this link a title or description</p>

                                <div class="tc-attach-recent-h">Recently viewed</div>
                                <div class="tc-attach-recent" id="tc-attach-recent"></div>

                                <div class="tc-attach-foot">
                                    <button type="button" class="tc-attach-cancel" id="tc-attach-cancel">Cancel</button>
                                    <button type="button" class="tc-attach-insert" id="tc-attach-insert">Insert</button>
                                </div>
                            </div>
                        </div>

                        <div class="tc-sec">
                            <div class="tc-sec-h">
                                <span class="left"><i class="bi bi-text-left"></i> Description</span>
                                <button type="button" class="tc-link-btn" id="tc-edit-desc" hidden>Edit</button>
                            </div>
                            <div class="tc-sec-body">
                                <div class="tc-desc-view is-empty" id="tc-desc-view">Add a more detailed description...</div>
                                <button type="button" class="tc-desc-more" id="tc-desc-more" hidden>
                                    <i class="bi bi-chevron-down"></i> <span>Show more</span>
                                </button>
                                <div id="tc-desc-edit" hidden>
                                    <textarea id="tc-desc" hidden aria-hidden="true"></textarea>
                                    <div class="tc-desc-editor tc-rich-editor" id="tc-desc-editor" data-rich="desc">
                                        <div class="tc-desc-toolbar">
                                            <button type="button" class="tc-tb" data-dd="style" title="Text styles"><span class="tt">Tt</span> <i class="bi bi-chevron-down chev"></i></button>
                                            <span class="sep"></span>
                                            <button type="button" class="tc-tb" data-cmd="bold" title="Bold"><i class="bi bi-type-bold"></i></button>
                                            <button type="button" class="tc-tb" data-dd="morefmt" title="More formatting"><i class="bi bi-type-italic"></i> <i class="bi bi-chevron-down chev"></i></button>
                                            <span class="sep"></span>
                                            <button type="button" class="tc-tb" data-dd="list" title="Lists"><i class="bi bi-list-ul"></i> <i class="bi bi-chevron-down chev"></i></button>
                                            <span class="sep"></span>
                                            <button type="button" class="tc-tb tc-rich-link" title="Link"><i class="bi bi-link-45deg"></i></button>
                                            <button type="button" class="tc-tb tc-rich-image" title="Image"><i class="bi bi-image"></i></button>
                                            <button type="button" class="tc-tb" data-dd="insert" title="Insert"><i class="bi bi-plus-lg"></i> <i class="bi bi-chevron-down chev"></i></button>
                                            <div class="tc-desc-toolbar-right">
                                                <button type="button" class="tc-tb tc-rich-attach" title="Attach and insert link"><i class="bi bi-paperclip"></i></button>
                                                <button type="button" class="tc-tb tc-rich-md" title="View Markdown"><strong>M↓</strong></button>
                                                <button type="button" class="tc-tb tc-rich-help" title="Open help dialog"><i class="bi bi-question-circle"></i></button>
                                            </div>

                                            <div class="tc-dd" data-dd-panel="style">
                                                <button type="button" class="tc-dd-item active" data-block="p">Normal text <span class="meta">Ctrl+Alt+0</span></button>
                                                <button type="button" class="tc-dd-item h1" data-block="h1">Heading 1 <span class="meta">Ctrl+Alt+1</span></button>
                                                <button type="button" class="tc-dd-item h2" data-block="h2">Heading 2 <span class="meta">Ctrl+Alt+2</span></button>
                                                <button type="button" class="tc-dd-item h3" data-block="h3">Heading 3 <span class="meta">Ctrl+Alt+3</span></button>
                                                <button type="button" class="tc-dd-item h4" data-block="h4">Heading 4 <span class="meta">Ctrl+Alt+4</span></button>
                                                <button type="button" class="tc-dd-item h5" data-block="h5">Heading 5 <span class="meta">Ctrl+Alt+5</span></button>
                                                <button type="button" class="tc-dd-item h6" data-block="h6">Heading 6 <span class="meta">Ctrl+Alt+6</span></button>
                                            </div>
                                            <div class="tc-dd" data-dd-panel="morefmt">
                                                <button type="button" class="tc-dd-item" data-cmd="italic"><i>I</i> Italic <span class="meta">Ctrl+I</span></button>
                                                <button type="button" class="tc-dd-item" data-cmd="strikeThrough"><s>S</s> Strikethrough <span class="meta">Ctrl+Shift+S</span></button>
                                                <button type="button" class="tc-dd-item" data-cmd="code">&lt;/&gt; Code <span class="meta">Ctrl+Shift+M</span></button>
                                                <div class="tc-dd-div"></div>
                                                <button type="button" class="tc-dd-item" data-cmd="removeFormat">Clear formatting <span class="meta">Ctrl+\</span></button>
                                            </div>
                                            <div class="tc-dd" data-dd-panel="list">
                                                <button type="button" class="tc-dd-item" data-cmd="insertUnorderedList">Bullet list <span class="meta">Ctrl+Shift+8</span></button>
                                                <button type="button" class="tc-dd-item" data-cmd="insertOrderedList">Numbered list <span class="meta">Ctrl+Shift+7</span></button>
                                            </div>
                                            <div class="tc-dd tc-insert-dd" data-dd-panel="insert">
                                                @include('admin.trello._insert_menu')
                                            </div>
                                        </div>
                                        <div class="tc-desc-visual tc-rich-visual" id="tc-desc-visual" contenteditable="true"
                                             data-placeholder="Add a more detailed description..." role="textbox"></div>
                                    </div>
                                    {{-- Save / Cancel / Formatting help sit below the editor box, like Trello --}}
                                    <div class="tc-desc-actions tc-desc-actions-out">
                                        <button type="button" class="tc-btn tc-btn-primary" id="tc-save-desc">Save</button>
                                        <button type="button" class="tc-btn tc-btn-ghost" id="tc-cancel-desc">Cancel</button>
                                        <span class="spacer"></span>
                                        <button type="button" class="tc-btn tc-fmt-help tc-rich-help">Formatting help</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tc-sec">
                            <div class="tc-sec-h">
                                <span class="left"><i class="bi bi-paperclip"></i> Attachments</span>
                                <button type="button" class="tc-link-btn" data-panel="attach">Add</button>
                            </div>
                            <div class="tc-sec-body">
                                <div class="tc-files-label">Files</div>
                                <div id="tc-attachments"></div>
                                <button type="button" class="tc-btn tc-att-toggle" id="tc-att-toggle" hidden></button>
                            </div>
                        </div>

                        {{-- Each checklist renders as its own section (Trello-style), see renderChecklists() --}}
                        <div class="tc-sec" id="tc-checklists-sec" hidden>
                            <div id="tc-checklists"></div>
                        </div>
                    </div>

                    <div class="tc-side">
                        <div class="tc-side-h">
                            <span class="left"><i class="bi bi-chat-dots"></i> Comments and activity</span>
                            <button type="button" class="tc-hide-btn" id="tc-hide-details">Hide details</button>
                        </div>

                        <div class="tc-comment-composer" id="tc-comment-composer">
                            <div class="tc-desc-editor tc-rich-editor" id="tc-cmt-editor" data-rich="comment">
                                <div class="tc-desc-toolbar">
                                    <button type="button" class="tc-tb" data-dd="style" title="Text styles"><span class="tt">Tt</span> <i class="bi bi-chevron-down chev"></i></button>
                                    <span class="sep"></span>
                                    <button type="button" class="tc-tb" data-cmd="bold" title="Bold"><i class="bi bi-type-bold"></i></button>
                                    <button type="button" class="tc-tb" data-dd="morefmt" title="More formatting"><i class="bi bi-type-italic"></i> <i class="bi bi-chevron-down chev"></i></button>
                                    <span class="sep"></span>
                                    <button type="button" class="tc-tb" data-dd="list" title="Lists"><i class="bi bi-list-ul"></i> <i class="bi bi-chevron-down chev"></i></button>
                                    <span class="sep"></span>
                                    <button type="button" class="tc-tb" data-dd="insert" title="Insert"><i class="bi bi-plus-lg"></i> <i class="bi bi-chevron-down chev"></i></button>
                                    <div class="tc-desc-toolbar-right">
                                        <button type="button" class="tc-tb tc-rich-attach" title="Attach and insert link"><i class="bi bi-paperclip"></i></button>
                                        <button type="button" class="tc-tb tc-rich-help" title="Open help dialog"><i class="bi bi-question-circle"></i></button>
                                    </div>
                                    <div class="tc-dd" data-dd-panel="style">
                                        <button type="button" class="tc-dd-item active" data-block="p">Normal text <span class="meta">Ctrl+Alt+0</span></button>
                                        <button type="button" class="tc-dd-item h1" data-block="h1">Heading 1 <span class="meta">Ctrl+Alt+1</span></button>
                                        <button type="button" class="tc-dd-item h2" data-block="h2">Heading 2 <span class="meta">Ctrl+Alt+2</span></button>
                                        <button type="button" class="tc-dd-item h3" data-block="h3">Heading 3 <span class="meta">Ctrl+Alt+3</span></button>
                                        <button type="button" class="tc-dd-item h4" data-block="h4">Heading 4 <span class="meta">Ctrl+Alt+4</span></button>
                                        <button type="button" class="tc-dd-item h5" data-block="h5">Heading 5 <span class="meta">Ctrl+Alt+5</span></button>
                                        <button type="button" class="tc-dd-item h6" data-block="h6">Heading 6 <span class="meta">Ctrl+Alt+6</span></button>
                                    </div>
                                    <div class="tc-dd" data-dd-panel="morefmt">
                                        <button type="button" class="tc-dd-item" data-cmd="italic"><i>I</i> Italic <span class="meta">Ctrl+I</span></button>
                                        <button type="button" class="tc-dd-item" data-cmd="strikeThrough"><s>S</s> Strikethrough <span class="meta">Ctrl+Shift+S</span></button>
                                        <button type="button" class="tc-dd-item" data-cmd="code">&lt;/&gt; Code <span class="meta">Ctrl+Shift+M</span></button>
                                        <div class="tc-dd-div"></div>
                                        <button type="button" class="tc-dd-item" data-cmd="removeFormat">Clear formatting <span class="meta">Ctrl+\</span></button>
                                    </div>
                                    <div class="tc-dd" data-dd-panel="list">
                                        <button type="button" class="tc-dd-item" data-cmd="insertUnorderedList">Bullet list <span class="meta">Ctrl+Shift+8</span></button>
                                        <button type="button" class="tc-dd-item" data-cmd="insertOrderedList">Numbered list <span class="meta">Ctrl+Shift+7</span></button>
                                    </div>
                                    <div class="tc-dd tc-insert-dd" data-dd-panel="insert">
                                        @include('admin.trello._insert_menu')
                                    </div>
                                </div>
                                <div class="tc-desc-visual tc-rich-visual" id="tc-cmt-visual" contenteditable="true"
                                     data-placeholder="Write a comment…" role="textbox"></div>
                            </div>
                            {{-- Save / Watch sit below the editor box, like Trello --}}
                            <div class="tc-desc-actions tc-cmt-actions">
                                <button type="button" class="tc-btn tc-btn-primary" id="tc-add-comment" disabled>Save</button>
                                <label class="tc-watch-label" title="Watch this card">
                                    <input type="checkbox" id="tc-cmt-watch"> Watch
                                </label>
                            </div>
                            <textarea id="tc-comment-text" hidden aria-hidden="true"></textarea>
                        </div>

                        <div id="tc-comments"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Description editor popovers (above modal) --}}
<div class="tc-pop-backdrop" id="tc-del-comment-pop" aria-hidden="true">
    <div class="tc-pop tc-del-pop" role="dialog" aria-label="Delete comment">
        <button type="button" class="tc-pop-close" data-close-pop aria-label="Close">&times;</button>
        <h3>Delete comment?</h3>
        <p class="tc-del-msg">Deleting a comment is forever. There is no undo.</p>
        <button type="button" class="tc-btn-danger-block" id="tc-del-comment-confirm">Delete comment</button>
    </div>
</div>

<div class="tc-pop-backdrop" id="tc-link-pop" aria-hidden="true">
    <div class="tc-pop" role="dialog" aria-label="Insert link">
        <button type="button" class="tc-pop-close" data-close-pop>&times;</button>
        <h3>Insert link</h3>
        <label>Link <span class="req">*</span></label>
        <input type="url" class="tc-pop-field" id="tc-link-url" placeholder="Paste a link">
        <label>Display text (optional)</label>
        <input type="text" class="tc-pop-field" id="tc-link-text" placeholder="Text to display">
        <div class="hint">Give this link a title or description</div>
        <div class="tc-pop-actions">
            <button type="button" class="tc-btn tc-btn-ghost" data-close-pop>Cancel</button>
            <button type="button" class="tc-btn tc-btn-primary" id="tc-link-insert">Insert</button>
        </div>
    </div>
</div>

<div class="tc-pop-backdrop" id="tc-image-pop" aria-hidden="true">
    <div class="tc-pop wide" role="dialog" aria-label="Select image">
        <button type="button" class="tc-pop-close" data-close-pop>&times;</button>
        <h3>Select image</h3>
        <div style="font-size:13px;color:#9fadbc;margin-bottom:6px">Recently uploaded</div>
        <div class="tc-img-grid" id="tc-img-grid"></div>
        <div style="font-size:13px;color:#9fadbc;margin-bottom:6px">Attach an image link</div>
        <div class="tc-pop-row">
            <input type="url" class="tc-pop-field" id="tc-img-url" placeholder="https://example.com">
            <button type="button" class="tc-btn" id="tc-img-url-submit">Submit</button>
        </div>
        <button type="button" class="tc-btn w-100" id="tc-img-upload-btn" style="width:100%">Upload from your computer</button>
        <input type="file" id="tc-img-file" accept="image/*" hidden>
    </div>
</div>

<div class="tc-pop-backdrop" id="tc-attach-pop" aria-hidden="true">
    <div class="tc-pop wide" role="dialog" aria-label="Attach and insert link">
        <button type="button" class="tc-pop-close" data-close-pop>&times;</button>
        <h3>Attach and insert link</h3>
        <div style="font-size:13px;color:#9fadbc;margin-bottom:6px">Card attachments</div>
        <div class="tc-attach-pick-list" id="tc-attach-pick-list"></div>
        <div style="font-size:13px;color:#9fadbc;margin:12px 0 6px">Or attach a new file</div>
        <button type="button" class="tc-btn w-100" id="tc-attach-upload-btn" style="width:100%">
            <i class="bi bi-upload"></i> Upload from your computer
        </button>
        <input type="file" id="tc-attach-file" hidden>
        <div class="hint" style="margin-top:10px">File is attached to the card and a link is inserted into the description.</div>
    </div>
</div>

<div class="tc-pop-backdrop" id="tc-md-pop" aria-hidden="true">
    <div class="tc-pop tc-md-pop" role="dialog" aria-label="View Markdown">
        <div class="tc-md-pop-bar">
            <button type="button" class="tc-md-copy" id="tc-md-copy" title="Copy markdown">
                <i class="bi bi-copy"></i> Copy
            </button>
            <button type="button" class="tc-pop-close" data-close-pop aria-label="Close">&times;</button>
        </div>
        <pre class="tc-md-body" id="tc-md-body"></pre>
    </div>
</div>

@php
    $helpShortcuts = [
        ['Clear formatting', ['Ctrl', '\\']],
        ['Undo', ['Ctrl', 'Z']],
        ['Redo', ['Ctrl', 'Y']],
        ['Paste plain text', ['Ctrl', 'Shift', 'V']],
        ['Bold', ['Ctrl', 'B']],
        ['Italic', ['Ctrl', 'I']],
        ['Strikethrough', ['Ctrl', 'Shift', 'S']],
        ['Heading 1', ['Ctrl', 'Alt', '1']],
        ['Heading 2', ['Ctrl', 'Alt', '2']],
        ['Heading 3', ['Ctrl', 'Alt', '3']],
        ['Heading 4', ['Ctrl', 'Alt', '4']],
        ['Heading 5', ['Ctrl', 'Alt', '5']],
        ['Heading 6', ['Ctrl', 'Alt', '6']],
        ['Normal text', ['Ctrl', 'Alt', '0']],
        ['Numbered list', ['Ctrl', 'Shift', '7']],
        ['Bullet list', ['Ctrl', 'Shift', '8']],
        ['Quote', ['Ctrl', 'Shift', '9']],
        ['Link', ['Ctrl', 'K']],
        ['Code', ['Ctrl', 'Shift', 'M']],
        ['Emoji', [':']],
        ['Mention', ['@']],
    ];
    $helpMarkdown = [
        ['Bold', '**Bold**'],
        ['Italic', '*Italic*'],
        ['Strikethrough', '~~Strikethrough~~'],
        ['Heading 1', '# Space'],
        ['Heading 2', '## Space'],
        ['Heading 3', '### Space'],
        ['Heading 4', '#### Space'],
        ['Heading 5', '##### Space'],
        ['Heading 6', '###### Space'],
        ['Numbered list', '1. Space'],
        ['Bullet list', '* Space'],
        ['Quote', '> Space'],
        ['Code snippet', '```'],
        ['Divider', '---'],
        ['Link', '[Link](http://a.com)'],
        ['Code', '`Code`'],
        ['Image', '![alt](url)'],
    ];
@endphp
<div class="tc-mention-menu" id="tc-mention-menu" role="listbox" aria-label="Mention someone"></div>

<div class="tc-member-pop" id="tc-member-pop" role="dialog" aria-label="Member" hidden>
    <div class="tc-member-pop-top">
        <span class="tc-member-pop-av" id="tc-member-pop-av">??</span>
        <button type="button" class="tc-member-pop-close" id="tc-member-pop-close" aria-label="Close">&times;</button>
        <h4 class="tc-member-pop-name" id="tc-member-pop-name"></h4>
        <p class="tc-member-pop-handle" id="tc-member-pop-handle"></p>
    </div>
    <div class="tc-member-pop-actions">
        <a href="#" target="_blank" rel="noopener" id="tc-member-pop-profile">View profile</a>
        <div class="sep"></div>
        <button type="button" id="tc-member-pop-remove">Remove from card</button>
    </div>
</div>

{{-- Floating bar under the code block holding the caret, in either editor (see syncCodeBar) --}}
<div class="tc-code-bar" id="tc-code-bar" hidden>
    <div class="tc-code-lang-wrap">
        <select id="tc-code-lang" aria-label="Code language">
            <option value="">Select language</option>
            <option value="plaintext">Plain text</option>
            <option value="bash">Bash</option>
            <option value="c">C</option>
            <option value="cpp">C++</option>
            <option value="csharp">C#</option>
            <option value="css">CSS</option>
            <option value="go">Go</option>
            <option value="html">HTML</option>
            <option value="java">Java</option>
            <option value="javascript">JavaScript</option>
            <option value="json">JSON</option>
            <option value="markdown">Markdown</option>
            <option value="php">PHP</option>
            <option value="python">Python</option>
            <option value="ruby">Ruby</option>
            <option value="sql">SQL</option>
            <option value="typescript">TypeScript</option>
            <option value="xml">XML</option>
            <option value="yaml">YAML</option>
        </select>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </div>
    <button type="button" class="tc-code-btn" id="tc-code-wrap" title="Wrap lines"><i class="bi bi-text-wrap"></i></button>
    <div class="tc-code-more-wrap">
        <button type="button" class="tc-code-btn" id="tc-code-more" title="More"><i class="bi bi-three-dots"></i></button>
        <div class="tc-code-menu" id="tc-code-menu" hidden>
            <button type="button" data-code-act="copy"><i class="bi bi-clipboard"></i> Copy code</button>
            <button type="button" data-code-act="delete" class="danger"><i class="bi bi-trash"></i> Delete</button>
        </div>
    </div>
</div>

{{-- Attachment preview (Trello lightbox): ← / → between attachments, actions at the bottom --}}
<div class="tc-lightbox" id="tc-lightbox" role="dialog" aria-label="Attachment preview" hidden>
    <button type="button" class="tc-lb-close" id="tc-lb-close" title="Close" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    <div class="tc-lb-stage" id="tc-lb-stage"></div>
    <button type="button" class="tc-lb-nav prev" id="tc-lb-prev" title="Previous" aria-label="Previous attachment"><i class="bi bi-chevron-left"></i></button>
    <button type="button" class="tc-lb-nav next" id="tc-lb-next" title="Next" aria-label="Next attachment"><i class="bi bi-chevron-right"></i></button>
    <div class="tc-lb-info">
        <div class="name" id="tc-lb-name"></div>
        <div class="meta" id="tc-lb-meta"></div>
        <div class="actions">
            <a href="#" id="tc-lb-open" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open in new tab</a>
            <button type="button" id="tc-lb-download"><i class="bi bi-download"></i> Download</button>
            <button type="button" id="tc-lb-cover"><i class="bi bi-image"></i> <span>Make cover</span></button>
            <button type="button" id="tc-lb-delete"><i class="bi bi-x-lg"></i> Delete</button>
        </div>
    </div>
</div>

{{-- Selected image in the editors: "•••" under it → Copy / Delete (Trello) --}}
<div class="tc-img-bar" id="tc-img-bar" hidden>
    <button type="button" class="more" id="tc-img-more" title="More actions" aria-label="More actions"><i class="bi bi-three-dots"></i></button>
    <div class="tc-img-menu" id="tc-img-menu" hidden>
        <button type="button" data-img-act="copy"><i class="bi bi-copy"></i> Copy</button>
        <button type="button" data-img-act="delete"><i class="bi bi-trash"></i> Delete</button>
    </div>
</div>

{{-- Link toolbar shown under a link in the editors (Trello: edit / unlink / open / copy) --}}
<div class="tc-link-bar" id="tc-link-bar" hidden>
    <button type="button" data-link-act="edit" title="Edit link"><i class="bi bi-pencil-square"></i></button>
    <button type="button" data-link-act="unlink" title="Unlink"><i class="bi bi-link-45deg"></i><i class="bi bi-slash-lg tc-unlink-slash"></i></button>
    <span class="sep"></span>
    <button type="button" data-link-act="open" title="Open link in a new tab"><i class="bi bi-box-arrow-up-right"></i></button>
    <span class="sep"></span>
    <button type="button" data-link-act="copy" title="Copy link"><i class="bi bi-copy"></i></button>
</div>
<div class="tc-link-edit" id="tc-link-edit" hidden>
    <label for="tc-link-edit-url">Link <span class="req">*</span></label>
    <div class="tc-link-edit-field">
        <input type="url" id="tc-link-edit-url" autocomplete="off">
        <button type="button" class="clear" id="tc-link-edit-clear" title="Clear" aria-label="Clear"><i class="bi bi-x-circle-fill"></i></button>
    </div>
    <label for="tc-link-edit-text">Display text (optional)</label>
    <div class="tc-link-edit-field">
        <input type="text" id="tc-link-edit-text" placeholder="Text to display" autocomplete="off">
    </div>
    <p class="hint">Give this link a title or description</p>
    <div class="actions">
        <button type="button" class="tc-btn tc-btn-ghost" id="tc-link-edit-cancel">Cancel</button>
        <button type="button" class="tc-btn tc-btn-primary" id="tc-link-edit-save">Save</button>
    </div>
</div>

<div class="tc-emoji-pop" id="tc-emoji-pop" role="dialog" aria-label="Emoji picker" hidden>
    <div class="tc-emoji-cats" id="tc-emoji-cats"></div>
    <div class="tc-emoji-search-wrap">
        <i class="bi bi-search"></i>
        <input type="search" class="tc-emoji-search" id="tc-emoji-search" placeholder="Search..." autocomplete="off">
        <button type="button" class="tc-emoji-skin" id="tc-emoji-skin" title="Skin tone" aria-label="Skin tone">✋</button>
    </div>
    <div class="tc-emoji-body" id="tc-emoji-body"></div>
    {{-- Hover preview kept for the JS, hidden like Trello --}}
    <div class="tc-emoji-foot" hidden>
        <span class="tc-emoji-preview" id="tc-emoji-preview">👍</span>
        <span class="tc-emoji-hint" id="tc-emoji-hint">Pick an emoji…</span>
    </div>
</div>

<div class="tc-pop-backdrop" id="tc-help-pop" aria-hidden="true">
    <div class="tc-pop tc-help-pop" role="dialog" aria-label="Editor help">
        <div class="tc-help-pop-head">
            <h3>Editor help</h3>
            <button type="button" class="tc-pop-close" data-close-pop title="Close help dialog" aria-label="Close">&times;</button>
        </div>
        <div class="tc-help-grid">
            <div class="tc-help-col">
                <h4>Keyboard shortcuts</h4>
                @foreach($helpShortcuts as [$label, $keys])
                    <div class="tc-help-row">
                        <span class="label">{{ $label }}</span>
                        <span class="tc-help-keys">
                            @foreach($keys as $k)<kbd>{{ $k }}</kbd>@endforeach
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="tc-help-col tc-help-md">
                <h4>Markdown</h4>
                @foreach($helpMarkdown as [$label, $sample])
                    <div class="tc-help-row">
                        <span class="label">{{ $label }}</span>
                        <code>{{ $sample }}</code>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="tc-help-foot">Press <kbd>Ctrl</kbd> + <kbd>/</kbd> to quickly open this dialog</div>
    </div>
</div>
