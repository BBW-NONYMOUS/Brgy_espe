<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Auto-discovered event listener: records logins and logouts.
 */
class RecordAuthenticationActivity
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now(), 'failed_attempts' => 0, 'locked_until' => null])->saveQuietly();

        $this->audit->log(AuditAction::Login, $event->user, 'Logged in', user: $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->audit->log(AuditAction::Logout, $event->user, 'Logged out', user: $event->user);
    }
}
