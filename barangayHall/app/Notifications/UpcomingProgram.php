<?php

namespace App\Notifications;

/**
 * A community health program starts soon.
 */
class UpcomingProgram extends HealthNotification
{
    public function category(): string
    {
        return 'program';
    }
}
