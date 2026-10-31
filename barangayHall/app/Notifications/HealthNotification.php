<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base for every bell notification. Stored in the `notifications` table,
 * which is NOT encrypted, so the payload carries only counts, codes and a
 * link — never resident names, diagnoses or coordinates. The page behind
 * the link re-checks the reader's permissions.
 */
abstract class HealthNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'info'|'warning'|'critical'  $level
     */
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $url = null,
        public readonly string $level = 'info',
    ) {}

    /**
     * Groups the bell list and picks its icon (outbreak, inventory, maternal, ...).
     */
    abstract public function category(): string;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{category: string, title: string, message: string, url: string|null, level: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category(),
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'level' => $this->level,
        ];
    }
}
