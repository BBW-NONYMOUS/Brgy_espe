<?php

namespace App\Notifications;

/**
 * An outbreak threshold was reached (disease, purok, case count).
 */
class OutbreakDetected extends HealthNotification
{
    public function category(): string
    {
        return 'outbreak';
    }
}
