<?php

namespace App\Notifications;

/**
 * Daily count of overdue prenatal/postnatal contacts or immunization doses.
 */
class OverdueCareDigest extends HealthNotification
{
    public function category(): string
    {
        return 'maternal';
    }
}
