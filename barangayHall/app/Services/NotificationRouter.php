<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Models\NotificationDispatch;
use App\Models\User;
use App\Notifications\HealthNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Sends bell notifications to the right people only, at most once per
 * dedupe key (so the hourly re-runs of the daily checks never repeat one).
 */
class NotificationRouter
{
    /**
     * Everyone (active) whose role has any of the permissions.
     *
     * @param  PermissionName|list<PermissionName>  $permissions
     * @return int recipients notified (0 when the key was already used)
     */
    public function toPermission(PermissionName|array $permissions, HealthNotification $notification, ?string $dedupeKey = null): int
    {
        return $this->send(fn () => User::query()->withPermission($permissions)->get(), $notification, $dedupeKey);
    }

    /**
     * Specific users (e.g. the referring midwife, assigned staff).
     *
     * @param  iterable<User>|User  $users
     */
    public function toUsers(iterable|User $users, HealthNotification $notification, ?string $dedupeKey = null): int
    {
        $list = collect($users instanceof User ? [$users] : $users)->filter(fn (User $u) => $u->is_active)->unique('id')->values();

        return $this->send(fn () => $list, $notification, $dedupeKey);
    }

    /**
     * Whether a dedupe key has been used (lets callers skip expensive work).
     */
    public function alreadySent(string $dedupeKey): bool
    {
        return NotificationDispatch::query()->where('dedupe_key', $dedupeKey)->exists();
    }

    /**
     * @param  callable(): Collection<int, User>  $recipients
     */
    private function send(callable $recipients, HealthNotification $notification, ?string $dedupeKey): int
    {
        $dispatch = null;

        if ($dedupeKey !== null) {
            // Claim the key first; a concurrent run hitting the unique index backs off.
            try {
                $dispatch = NotificationDispatch::query()->create([
                    'dedupe_key' => $dedupeKey,
                    'notification_type' => $notification::class,
                    'recipients_count' => 0,
                ]);
            } catch (UniqueConstraintViolationException) {
                return 0;
            }
        }

        $users = $recipients();

        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }

        $dispatch?->update(['recipients_count' => $users->count()]);

        return $users->count();
    }
}
