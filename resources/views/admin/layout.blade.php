<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    @stack('styles')
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="brand">
            <h4><i class="bi bi-layout-text-window-reverse"></i> CRM</h4>
        </div>
        <ul class="nav flex-column">
            @php $isAgent = auth()->user()?->hasRole('Agent'); @endphp

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                    <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
                </a>
            </li>

            @if(!$isAgent)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                    <i class="bi bi-people-fill"></i> <span>Users</span>
                </a>
            </li>
            @endif
            @if(!$isAgent)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.companies.*') ? 'active' : '' }}" href="{{ route('admin.companies.index') }}">
                    <i class="bi bi-buildings"></i> <span>Companies</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.teams.*') ? 'active' : '' }}" href="{{ route('admin.teams.index') }}">
                    <i class="bi bi-people"></i> <span>Teams</span>
                </a>
            </li>
            @endif
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.targets.*') ? 'active' : '' }}" href="{{ route('admin.targets.index') }}">
                    <i class="bi bi-bullseye"></i> <span>Targets</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.sales.*') ? 'active' : '' }}" href="{{ route('admin.sales.index') }}">
                    <i class="bi bi-cash-stack"></i> <span>Sales</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">
                    <i class="bi bi-graph-up-arrow"></i> <span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}" href="{{ route('admin.profile.show') }}">
                    <i class="bi bi-person-circle"></i> <span>Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <form action="{{ route('admin.logout') }}" method="POST" class="d-inline w-100">
                    @csrf
                    <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start">
                        <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
                    </button>
                </form>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main>
        <div class="page-header fade-in d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h1 class="mb-0">
                @hasSection('page-icon')
                    <i class="bi bi-@yield('page-icon')"></i>
                @else
                    <i class="bi bi-speedometer2"></i>
                @endif
                @yield('page-title', 'Admin')
            </h1>
            @include('admin.partials.notifications')
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show fade-in" role="alert">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            var pollMs = 5000;
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

            function poll() {
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
                    .catch(function () { /* offline */ });
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
    @yield('scripts')
</body>
</html>
