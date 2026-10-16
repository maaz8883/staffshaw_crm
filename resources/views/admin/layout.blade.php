<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') - Admin</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    @stack('styles')
    <script>
        if(localStorage.getItem('crm_sidebar_collapsed')==='1')document.documentElement.classList.add('sidebar-collapsed-init');
        if(localStorage.getItem('crm_theme')==='dark'){
            document.documentElement.setAttribute('data-theme','dark');
            document.documentElement.style.colorScheme='dark';
        }
    </script>
    <style>.sidebar-collapsed-init .sidebar{width:64px}.sidebar-collapsed-init main{margin-left:64px}.sidebar-collapsed-init .sidebar-user-meta,.sidebar-collapsed-init .brand-name{display:none}</style>
</head>
<body>
    <header class="topbar">
        <a class="topbar-logo" href="{{ route('admin.dashboard') }}">
            <span class="topbar-mark">CRM</span>
        </a>
        {{-- Searches customers / leads / sales: hidden for Production (they don't see those) --}}
        <div class="topbar-search" id="global-search" @if(auth()->user()?->hasRole('Production')) style="display:none" @endif>
            <i class="bi bi-search"></i>
            <input id="top-search" type="search" placeholder="Search people, customers, leads, sales..." autocomplete="off" aria-autocomplete="list" aria-controls="global-search-results">
            <div id="global-search-results" class="global-search-dropdown" hidden role="listbox"></div>
        </div>
        <div class="topbar-actions">
            <button id="sidebar-toggle" class="topbar-icon" title="Toggle sidebar" type="button">
                <i class="bi bi-list"></i>
            </button>
            <button id="theme-toggle" class="topbar-icon" title="Toggle theme" type="button">
                <i class="bi bi-moon" id="theme-icon"></i>
            </button>
            @include('admin.partials.notifications')
        </div>
    </header>

    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-user">
            <div class="sidebar-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <div class="sidebar-user-meta">
                <div class="sidebar-user-name">{{ auth()->user()->name }}</div>
                <div class="sidebar-user-email">{{ auth()->user()->email }}</div>
            </div>
        </div>
        <ul class="nav flex-column sidebar-nav">
            @php
                $isAdmin = auth()->user()?->hasRole('Admin');
                $isAdminOrManager = auth()->user()?->hasRole(['Admin', 'Manager']);
                $isPpc = auth()->user()?->hasRole('PPC');
                $isProjectManager = auth()->user()?->hasRole('Project Manager');
                // Production teams: Dashboard + Trello only (no customers / leads / sales)
                $isProduction = auth()->user()?->hasRole('Production');
                $isTeamHeadUser = \App\Models\Team::where('team_head_id', (int) auth()->id())->exists();
                $headedTeamForNav = $isTeamHeadUser
                    ? \App\Models\Team::where('team_head_id', (int) auth()->id())->orderBy('name')->first()
                    : null;
                // Production team heads have no sales join requests / reports (those pages are sales-only)
                $canReviewJoinRequests = ! $isProjectManager && ! $isProduction && ($isAdminOrManager || $isTeamHeadUser);
                $pendingJoinRequestCount = 0;
                if ($canReviewJoinRequests) {
                    $jrQuery = \App\Models\TeamMembership::where('status', \App\Models\TeamMembership::STATUS_PENDING);
                    if (! $isAdminOrManager) {
                        $headedTeamIds = \App\Models\Team::where('team_head_id', (int) auth()->id())->pluck('id');
                        $jrQuery->whereIn('team_id', $headedTeamIds);
                    }
                    $pendingJoinRequestCount = $jrQuery->count();
                }
                $salesGroupActive = request()->routeIs(['admin.sales.*', 'admin.invoices.*']);
            @endphp

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}" title="Dashboard">
                    <i class="bi bi-speedometer2"></i> <span class="nav-label">Dashboard</span>
                </a>
            </li>

            @if(env('HRM_APP_URL'))
            <li class="nav-item">
                <a class="nav-link" href="{{ rtrim(env('HRM_APP_URL'), '/') }}/dashboard" title="HRM System">
                    <i class="bi bi-person-badge"></i> <span class="nav-label">HRM System</span>
                </a>
            </li>
            @endif

            @if(! $isPpc)
            @if(! $isProduction)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.clients.*') ? 'active' : '' }}" href="{{ route('admin.clients.index') }}" title="Customers">
                    <i class="bi bi-people"></i> <span class="nav-label">Customers</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.leads.*') ? 'active' : '' }}" href="{{ route('admin.leads.index') }}" title="Leads">
                    <i class="bi bi-funnel"></i> <span class="nav-label">Leads</span>
                </a>
            </li>

            {{-- Only Sales is a toggle group (like Perfex) --}}
            <li class="nav-item nav-group">
                <button type="button" class="nav-link nav-group-toggle {{ $salesGroupActive ? 'active-group' : '' }}"
                    data-bs-toggle="collapse" data-bs-target="#nav-group-sales"
                    aria-expanded="{{ $salesGroupActive ? 'true' : 'false' }}" title="Sales">
                    <i class="bi bi-lightning-charge"></i>
                    <span class="nav-label">Sales</span>
                    <i class="bi bi-chevron-down nav-group-caret"></i>
                </button>
                <ul id="nav-group-sales" class="nav flex-column nav-group-menu collapse {{ $salesGroupActive ? 'show' : '' }}" data-group-key="sales">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.sales.*') ? 'active' : '' }}" href="{{ route('admin.sales.index') }}" title="Sales">
                            <i class="bi bi-bag-check"></i> <span class="nav-label">Sales</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}" href="{{ route('admin.invoices.index') }}" title="Invoices">
                            <i class="bi bi-receipt"></i> <span class="nav-label">Invoices</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endif

            @if($isAdmin)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.expenses.*') ? 'active' : '' }}" href="{{ route('admin.expenses.index') }}" title="Expenses">
                    <i class="bi bi-wallet2"></i> <span class="nav-label">Expenses</span>
                </a>
            </li>
            @endif

            @if(! $isProjectManager && ! $isProduction)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.targets.*') ? 'active' : '' }}" href="{{ route('admin.targets.index') }}" title="Sales Performance">
                    <i class="bi bi-graph-up"></i> <span class="nav-label">Sales Performance</span>
                </a>
            </li>
            @endif

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.trello.*') ? 'active' : '' }}" href="{{ route('admin.trello.index') }}" title="Trello">
                    <i class="bi bi-kanban"></i> <span class="nav-label">Trello</span>
                </a>
            </li>
            @endif

            @if($isProjectManager)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.team-memberships.*') ? 'active' : '' }}" href="{{ route('admin.team-memberships.index') }}" title="Team Directory">
                    <i class="bi bi-diagram-3"></i> <span class="nav-label">Team Directory</span>
                </a>
            </li>
            @endif

            @if($isPpc || $isAdmin)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.ppc.*') ? 'active' : '' }}" href="{{ route('admin.ppc.index') }}" title="PPC Spending">
                    <i class="bi bi-megaphone"></i> <span class="nav-label">PPC Spending</span>
                </a>
            </li>
            @endif

            @if($isTeamHeadUser && $headedTeamForNav)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.teams.show') && (int) (optional(request()->route('team'))->id ?? request()->route('team')) === (int) $headedTeamForNav->id ? 'active' : '' }}"
                   href="{{ route('admin.teams.show', $headedTeamForNav) }}" title="My Team">
                    <i class="bi bi-people"></i> <span class="nav-label">My Team</span>
                </a>
            </li>
            @endif

            @if($isAdminOrManager || ($isTeamHeadUser && ! $isProduction))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}" title="Reports">
                    <i class="bi bi-graph-up-arrow"></i> <span class="nav-label">Reports</span>
                </a>
            </li>
            @endif
            @if($isAdminOrManager)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}" href="{{ route('admin.activity-logs.index') }}" title="Activity Logs">
                    <i class="bi bi-clock-history"></i> <span class="nav-label">Activity Logs</span>
                </a>
            </li>
            @endif

            @if($isAdmin)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}" title="Users">
                    <i class="bi bi-people-fill"></i> <span class="nav-label">Users</span>
                </a>
            </li>
            @unless($singleCompany)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.companies.*') ? 'active' : '' }}" href="{{ route('admin.companies.index') }}" title="Companies">
                    <i class="bi bi-buildings"></i> <span class="nav-label">Companies</span>
                </a>
            </li>
            @endunless
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.brands.*') ? 'active' : '' }}" href="{{ route('admin.brands.index') }}" title="Brands">
                    <i class="bi bi-building"></i> <span class="nav-label">Brands</span>
                </a>
            </li>
            @endif

            @if($isAdminOrManager)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.teams.*') && ! request()->routeIs('admin.teams.join-requests.*') ? 'active' : '' }}" href="{{ route('admin.teams.index') }}" title="Teams">
                    <i class="bi bi-people"></i> <span class="nav-label">Teams</span>
                </a>
            </li>
            @endif

            @if($canReviewJoinRequests)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.teams.join-requests.*') ? 'active' : '' }}" href="{{ route('admin.teams.join-requests.index') }}" title="Join Requests">
                    <i class="bi bi-person-plus"></i> <span class="nav-label">Join Requests</span>
                    @if($pendingJoinRequestCount > 0)
                        <span class="badge bg-danger ms-1">{{ $pendingJoinRequestCount }}</span>
                    @endif
                </a>
            </li>
            @endif

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}" href="{{ route('admin.profile.show') }}" title="Profile">
                    <i class="bi bi-person-circle"></i> <span class="nav-label">Profile</span>
                </a>
            </li>

            @if($isAdmin)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}" title="Settings">
                    <i class="bi bi-gear"></i> <span class="nav-label">Settings</span>
                </a>
            </li>
            @endif

            <li class="nav-item">
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="nav-link w-100" title="Logout">
                        <i class="bi bi-box-arrow-right"></i> <span class="nav-label">Logout</span>
                    </button>
                </form>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main>
        <div class="page-title-row">
            <h1>@yield('page-title', 'Admin')</h1>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show fade-in" role="alert">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
                <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Live notification toast (near real-time via polling) --}}
    <div class="toast-container position-fixed bottom-0 end-0 p-3 live-notification-toast-wrap" style="z-index: 1080;">
        <div id="js-live-notification-toast" class="toast align-items-center text-bg-light border-0 shadow" role="alert" aria-live="polite" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <div class="fw-semibold small mb-1"><i class="bi bi-bell-fill text-primary me-1"></i> New notification</div>
                    <div id="js-live-notification-toast-text" class="small"></div>
                    <a href="#" id="js-live-notification-toast-link" class="small d-inline-block mt-1">Open</a>
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        (function ($) {
            function initSelect2(root) {
                var $root = root ? $(root) : $(document);
                $root.find('select.js-select2-multi').each(function () {
                    var $el = $(this);
                    if ($el.hasClass('select2-hidden-accessible')) return;
                    var placeholder = $el.data('placeholder') || 'Select options';
                    $el.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: placeholder,
                        allowClear: false,
                        closeOnSelect: false,
                        dropdownParent: $el.closest('.mb-3, .col-md-6, .col-md-4, .form-group, td, .card-body').length
                            ? $el.closest('.mb-3, .col-md-6, .col-md-4, .form-group, td, .card-body')
                            : $(document.body)
                    });
                });
            }
            window.initCrmSelect2 = initSelect2;
            $(function () { initSelect2(document); });
            window.addEventListener('crm:themechange', function () {
                $('select.js-select2-multi').each(function () {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                });
                initSelect2(document);
            });
        })(jQuery);
    </script>
    <script>
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || form.tagName !== 'FORM' || !form.classList.contains('js-admin-delete-form')) {
                return;
            }
            e.preventDefault();
            var title = form.dataset.swalTitle || 'Are you sure?';
            var text = form.dataset.swalText || '';
            Swal.fire({
                title: title,
                text: text || undefined,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    </script>
    <script>
        (function () {
            var pollUrl = @json(route('admin.notifications.poll'));
            var pollPrimed = false;
            var knownNewestId = null;
            // Slower on Trello board pages — avoids stacking behind Trello API calls on artisan serve.
            var pollMs = document.querySelector('.trello-board-stage') ? 15000 : 5000;
            var timer = null;

            function playNotificationSound() {
                var Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                try {
                    var ctx = window.__crmNotifAudioCtx;
                    if (!ctx) {
                        ctx = new Ctx();
                        window.__crmNotifAudioCtx = ctx;
                    }
                    if (ctx.state === 'suspended') ctx.resume();

                    function tone(freq, start, dur) {
                        var osc = ctx.createOscillator();
                        var gain = ctx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = freq;
                        gain.gain.setValueAtTime(0.0001, ctx.currentTime + start);
                        gain.gain.exponentialRampToValueAtTime(0.12, ctx.currentTime + start + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + start + dur);
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(ctx.currentTime + start);
                        osc.stop(ctx.currentTime + start + dur + 0.05);
                    }
                    tone(784, 0, 0.11);
                    tone(1047, 0.13, 0.14);
                } catch (e) { /* ignore */ }
            }

            document.addEventListener('click', function once() {
                var Ctx = window.AudioContext || window.webkitAudioContext;
                if (Ctx && !window.__crmNotifAudioCtx) {
                    var c = new Ctx();
                    c.resume();
                    window.__crmNotifAudioCtx = c;
                }
            }, { once: true });

            function escapeHtml(s) {
                var d = document.createElement('div');
                d.textContent = s;
                return d.innerHTML;
            }

            function renderList(items) {
                var el = document.getElementById('js-notification-list');
                if (!el) return;
                if (!items || !items.length) {
                    el.innerHTML = '<div class="dropdown-item-text text-muted small py-3 px-3 js-notification-empty">No notifications yet.</div>';
                    return;
                }
                el.innerHTML = items.map(function (n) {
                    var readCls = n.read ? '' : 'fw-semibold bg-light';
                    return '<a href="' + escapeHtml(n.follow_url) + '" class="dropdown-item notification-item ' + readCls + ' py-2 px-3 small">' +
                        '<div class="text-wrap">' + escapeHtml(n.body) + '</div>' +
                        '<div class="text-muted" style="font-size:0.75rem">' + escapeHtml(n.created_human) + '</div></a>';
                }).join('');
            }

            function updateBell(data) {
                var badge = document.getElementById('js-notification-badge');
                var mark = document.getElementById('js-notification-mark-wrap');
                var c = data.unread_count || 0;
                if (badge) {
                    badge.textContent = c > 99 ? '99+' : String(c);
                    badge.classList.toggle('d-none', c < 1);
                }
                if (mark) mark.classList.toggle('d-none', c < 1);
            }

            function showToast(item) {
                if (!item) return;
                var toastEl = document.getElementById('js-live-notification-toast');
                var txt = document.getElementById('js-live-notification-toast-text');
                var link = document.getElementById('js-live-notification-toast-link');
                if (!toastEl || !txt || !window.bootstrap) return;
                txt.textContent = item.body || 'Notification';
                if (link) {
                    link.href = item.follow_url || '#';
                }
                var t = window.bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 6500 });
                t.show();
            }

            var pollInFlight = false;
            function poll() {
                if (pollInFlight || document.hidden) return;
                pollInFlight = true;
                fetch(pollUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!pollPrimed) {
                            knownNewestId = data.newest_id || null;
                            pollPrimed = true;
                            renderList(data.items);
                            updateBell(data);
                            return;
                        }
                        if (data.newest_id && data.newest_id !== knownNewestId) {
                            playNotificationSound();
                            if (data.items && data.items[0]) showToast(data.items[0]);
                        }
                        knownNewestId = data.newest_id || null;
                        renderList(data.items);
                        updateBell(data);
                    })
                    .catch(function () { /* offline */ })
                    .finally(function () { pollInFlight = false; });
            }

            function schedule() {
                if (timer) clearInterval(timer);
                timer = setInterval(poll, pollMs);
            }

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    if (timer) clearInterval(timer);
                    timer = null;
                } else {
                    poll();
                    schedule();
                }
            });

            setTimeout(function () {
                poll();
                schedule();
            }, 800);
        })();
    </script>
    <script>
        (function () {
            var STORAGE_KEY = 'crm_sidebar_collapsed';
            var body = document.body;

            // Restore saved state instantly (before paint)
            if (localStorage.getItem(STORAGE_KEY) === '1') {
                body.classList.add('sidebar-collapsed');
            }
            // Remove init class (already handled by body class now)
            document.documentElement.classList.remove('sidebar-collapsed-init');

            document.getElementById('sidebar-toggle').addEventListener('click', function () {
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem(STORAGE_KEY, body.classList.contains('sidebar-collapsed') ? '1' : '0');
                // Re-init tooltips on state change
                initTooltips();
            });

            function initTooltips() {
                var collapsed = body.classList.contains('sidebar-collapsed');
                document.querySelectorAll('.sidebar .nav-link[title]').forEach(function (el) {
                    var existing = bootstrap.Tooltip.getInstance(el);
                    if (existing) existing.dispose();
                    if (collapsed) {
                        new bootstrap.Tooltip(el, { placement: 'right', trigger: 'hover' });
                    }
                });
            }

            // Persist collapsible nav groups (unless route already forced open)
            (function () {
                var GROUP_KEY = 'crm_nav_groups';
                var saved = {};
                try { saved = JSON.parse(localStorage.getItem(GROUP_KEY) || '{}') || {}; } catch (e) { saved = {}; }

                document.querySelectorAll('.nav-group-menu[data-group-key]').forEach(function (menu) {
                    var key = menu.getAttribute('data-group-key');
                    var toggle = document.querySelector('[data-bs-target="#' + menu.id + '"]');
                    var routeOpen = menu.classList.contains('show');

                    if (!routeOpen && Object.prototype.hasOwnProperty.call(saved, key)) {
                        if (saved[key]) {
                            menu.classList.add('show');
                            if (toggle) toggle.setAttribute('aria-expanded', 'true');
                        } else {
                            menu.classList.remove('show');
                            if (toggle) toggle.setAttribute('aria-expanded', 'false');
                        }
                    }

                    menu.addEventListener('shown.bs.collapse', function () {
                        saved[key] = true;
                        localStorage.setItem(GROUP_KEY, JSON.stringify(saved));
                        if (toggle) toggle.setAttribute('aria-expanded', 'true');
                    });
                    menu.addEventListener('hidden.bs.collapse', function () {
                        saved[key] = false;
                        localStorage.setItem(GROUP_KEY, JSON.stringify(saved));
                        if (toggle) toggle.setAttribute('aria-expanded', 'false');
                    });
                });
            })();

            // Init after bootstrap loads
            setTimeout(initTooltips, 100);
        })();

        // Theme toggle — instant apply (no animated color crawl)
        (function () {
            var THEME_KEY = 'crm_theme';
            var html = document.documentElement;
            var themeBtn = document.getElementById('theme-toggle');
            var themeIcon = document.getElementById('theme-icon');
            var switchingTimer = null;

            function applyTheme(theme, animate) {
                var dark = theme === 'dark';

                if (!animate) {
                    if (dark) {
                        html.setAttribute('data-theme', 'dark');
                        html.style.colorScheme = 'dark';
                    } else {
                        html.removeAttribute('data-theme');
                        html.style.colorScheme = 'light';
                    }
                } else {
                    html.classList.add('theme-switching');
                    if (switchingTimer) clearTimeout(switchingTimer);

                    // Force style flush, then swap theme without transitions
                    void html.offsetHeight;
                    if (dark) {
                        html.setAttribute('data-theme', 'dark');
                        html.style.colorScheme = 'dark';
                    } else {
                        html.removeAttribute('data-theme');
                        html.style.colorScheme = 'light';
                    }
                    void html.offsetHeight;

                    switchingTimer = setTimeout(function () {
                        html.classList.remove('theme-switching');
                        switchingTimer = null;
                    }, 50);
                }

                if (themeIcon) {
                    themeIcon.className = dark ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
                }
                localStorage.setItem(THEME_KEY, theme);
                try {
                    window.dispatchEvent(new CustomEvent('crm:themechange', { detail: { theme: theme } }));
                } catch (e) {}
            }

            // Icon only — theme already applied in <head> for first paint
            var saved = localStorage.getItem(THEME_KEY) || 'light';
            if (themeIcon) {
                themeIcon.className = saved === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
            }
            if (saved === 'dark' && html.getAttribute('data-theme') !== 'dark') {
                applyTheme('dark', false);
            } else if (saved !== 'dark' && html.getAttribute('data-theme') === 'dark') {
                applyTheme('light', false);
            }

            themeBtn.addEventListener('click', function () {
                var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                applyTheme(next, true);
            });
        })();

        (function () {
            var input = document.getElementById('top-search');
            var box = document.getElementById('global-search-results');
            var wrap = document.getElementById('global-search');
            if (!input || !box || !wrap) return;

            var timer = null;
            var seq = 0;
            var searchUrl = @json(route('admin.search'));

            function hide() {
                box.hidden = true;
                box.innerHTML = '';
            }

            function showMessage(text) {
                box.innerHTML = '<div class="global-search-empty">' + text + '</div>';
                box.hidden = false;
            }

            function render(results) {
                if (!results.length) {
                    showMessage('No matches found');
                    return;
                }
                box.innerHTML = results.map(function (item) {
                    return '<a class="global-search-item" href="' + item.url + '" role="option">' +
                        '<span class="global-search-icon"><i class="bi bi-' + item.icon + '"></i></span>' +
                        '<span class="global-search-text">' +
                        '<span class="global-search-title"></span>' +
                        '<span class="global-search-sub"></span>' +
                        '</span>' +
                        '<span class="global-search-type"></span>' +
                        '</a>';
                }).join('');
                Array.prototype.forEach.call(box.querySelectorAll('.global-search-item'), function (el, i) {
                    var item = results[i];
                    el.querySelector('.global-search-title').textContent = item.title || '';
                    el.querySelector('.global-search-sub').textContent = item.subtitle || '';
                    el.querySelector('.global-search-type').textContent = item.type || '';
                });
                box.hidden = false;
            }

            function runSearch(q) {
                var my = ++seq;
                showMessage('Searching…');
                fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (my !== seq) return;
                        render(data.results || []);
                    })
                    .catch(function () {
                        if (my !== seq) return;
                        showMessage('Search failed');
                    });
            }

            input.addEventListener('input', function () {
                var q = (this.value || '').trim();
                clearTimeout(timer);
                if (q.length < 2) {
                    hide();
                    return;
                }
                timer = setTimeout(function () { runSearch(q); }, 280);
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    hide();
                    input.blur();
                }
            });

            document.addEventListener('click', function (e) {
                if (!wrap.contains(e.target)) hide();
            });
        })();
    </script>
    @yield('scripts')
</body>
</html>
