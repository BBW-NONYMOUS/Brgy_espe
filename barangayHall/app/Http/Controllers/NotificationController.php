<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's own notifications only (scoped through
 * $request->user()->notifications(); another user's ID is a 404).
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['unread' => ['nullable', 'boolean']]);
        $user = $request->user();
        abort_unless($user !== null, 403);

        $notifications = $user->notifications()
            ->when($filters['unread'] ?? false, fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                ...$n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
            'filters' => ['unread' => (bool) ($filters['unread'] ?? false)],
        ]);
    }

    /**
     * Mark read and follow the notification's link (a normal page visit).
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $this->find($request, $notification);
        $record->markAsRead();

        /** @var string|null $url */
        $url = $record->data['url'] ?? null;

        // Only same-site relative links are followed.
        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : to_route('notifications.index');
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $this->find($request, $notification)->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()?->unreadNotifications()->update(['read_at' => now()]);

        $this->toast('All notifications marked as read.');

        return back();
    }

    private function find(Request $request, string $id): DatabaseNotification
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return $user->notifications()->whereKey($id)->firstOrFail();
    }
}
