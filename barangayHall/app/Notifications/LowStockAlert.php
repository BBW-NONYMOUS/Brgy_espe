<?php

namespace App\Notifications;

/**
 * A medicine fell to or below its reorder level.
 */
class LowStockAlert extends HealthNotification
{
    public function category(): string
    {
        return 'inventory';
    }
}
