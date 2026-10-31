<?php

namespace App\Notifications;

/**
 * A referral the user created moved to a new status.
 */
class ReferralStatusUpdated extends HealthNotification
{
    public function category(): string
    {
        return 'referral';
    }
}
