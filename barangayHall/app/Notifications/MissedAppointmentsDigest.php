<?php

namespace App\Notifications;

/**
 * Appointments that were automatically marked missed.
 */
class MissedAppointmentsDigest extends HealthNotification
{
    public function category(): string
    {
        return 'appointments';
    }
}
