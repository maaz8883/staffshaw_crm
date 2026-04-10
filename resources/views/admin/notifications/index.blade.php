@extends('admin.layout')

@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('page-icon', 'bell')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>All notifications</span>
        @if(auth()->user()->unreadNotifications()->count() > 0)
            <form action="{{ route('admin.notifications.read-all') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary">Mark all as read</button>
            </form>
        @endif
    </div>
    <div class="list-group list-group-flush">
        @forelse($notifications as $n)
            @php
                $data = $n->data ?? [];
                $body = $data['body'] ?? 'Notification';
            @endphp
            <a href="{{ route('admin.notifications.follow', $n->id) }}"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-3 {{ $n->read_at ? '' : 'list-group-item-light' }}">
                <div>
                    <div class="{{ $n->read_at ? '' : 'fw-semibold' }}">{{ $body }}</div>
                    <div class="text-muted small">{{ $n->created_at->format('d M Y, H:i') }}</div>
                </div>
                @if(!$n->read_at)
                    <span class="badge bg-primary rounded-pill align-self-center">New</span>
                @endif
            </a>
        @empty
            <div class="list-group-item text-muted">You have no notifications.</div>
        @endforelse
    </div>
    @if($notifications->hasPages())
        <div class="card-body">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection
