<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = auth()->user()
            ->notifications()
            ->paginate(25);

        return view('admin.notifications.index', compact('notifications'));
    }

    /** Live polling for unread count + latest items (near real-time without WebSockets). */
    public function poll(): JsonResponse
    {
        $user = auth()->user();

        // Release session lock quickly so Trello card/tick requests are not queued behind poll.
        try {
            if (request()->hasSession()) {
                request()->session()->save();
            }
        } catch (\Throwable) {
            // ignore
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $recent = $user->notifications()->latest()->limit(12)->get();
        $unreadCount = $recent->whereNull('read_at')->count();
        // If the unread set may extend past the latest 12, fall back to a count query.
        if ($unreadCount >= 12) {
            $unreadCount = $user->unreadNotifications()->count();
        }

        $items = $recent->map(function ($n) {
            $data = $n->data ?? [];

            return [
                'id'             => $n->id,
                'body'           => $data['body'] ?? 'Notification',
                'read'           => $n->read_at !== null,
                'created_human'  => $n->created_at->diffForHumans(),
                'follow_url'     => route('admin.notifications.follow', $n->id),
            ];
        });

        return response()->json([
            'unread_count' => $unreadCount,
            'newest_id'    => $recent->first()?->id,
            'items'        => $items,
        ]);
    }

    public function follow(string $id): RedirectResponse
    {
        $notification = auth()->user()->notifications()->where('id', $id)->firstOrFail();
        $data         = $notification->data;
        $url          = is_array($data) && isset($data['action_url'])
            ? $data['action_url']
            : route('admin.dashboard');

        $notification->markAsRead();

        return redirect()->to($url);
    }

    public function markAllRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
