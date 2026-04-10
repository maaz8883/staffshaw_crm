@php
    $user = auth()->user();
    $unreadCount = $user->unreadNotifications()->count();
    $recent = $user->notifications()->latest()->limit(12)->get();
@endphp

<div class="dropdown notification-bell" id="js-notification-bell">
    <button class="btn btn-outline-secondary position-relative d-flex align-items-center gap-2"
            type="button" data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="Notifications">
        <i class="bi bi-bell fs-5"></i>
        <span id="js-notification-badge"
              class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge {{ $unreadCount > 0 ? '' : 'd-none' }}">
            {{ $unreadCount > 99 ? '99+' : max(0, $unreadCount) }}
        </span>
    </button>
    <div class="dropdown-menu dropdown-menu-end notification-dropdown shadow">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <span class="fw-semibold small">Notifications</span>
            <span id="js-notification-mark-wrap" class="{{ $unreadCount > 0 ? '' : 'd-none' }}">
                <form action="{{ route('admin.notifications.read-all') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm p-0">Mark all read</button>
                </form>
            </span>
        </div>
        <div class="notification-list" id="js-notification-list">
            @forelse($recent as $n)
                @php
                    $data = $n->data ?? [];
                    $body = $data['body'] ?? 'Notification';
                @endphp
                <a href="{{ route('admin.notifications.follow', $n->id) }}"
                   class="dropdown-item notification-item {{ $n->read_at ? '' : 'fw-semibold bg-light' }} py-2 px-3 small">
                    <div class="text-wrap">{{ $body }}</div>
                    <div class="text-muted" style="font-size: 0.75rem;">{{ $n->created_at->diffForHumans() }}</div>
                </a>
            @empty
                <div class="dropdown-item-text text-muted small py-3 px-3 js-notification-empty">No notifications yet.</div>
            @endforelse
        </div>
        <div class="border-top px-3 py-2 text-center">
            <a href="{{ route('admin.notifications.index') }}" class="small">View all</a>
        </div>
    </div>
</div>
