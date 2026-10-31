<?php

namespace App\Notifications;

/**
 * Daily summary of batches entering the 90/60/30-day bands or expiring.
 */
class MedicineExpiryDigest extends HealthNotification
{
    public function category(): string
    {
        return 'inventory';
    }
}
