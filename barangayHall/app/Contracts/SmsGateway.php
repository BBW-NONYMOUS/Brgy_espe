<?php

namespace App\Contracts;

/**
 * Outgoing SMS (appointment reminders). Version 1 ships LogSmsGateway only;
 * a Semaphore (or other PH aggregator) driver can implement this later and
 * be selected with SMS_DRIVER. Callers must check the resident's
 * sms_reminders consent first: phone numbers leave the system.
 */
interface SmsGateway
{
    /**
     * @return bool whether the gateway accepted the message
     */
    public function send(string $phoneNumber, string $message): bool;
}
