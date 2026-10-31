<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Default "gateway": writes the message to the log instead of sending it.
 * The number is masked so the log never holds a full phone number.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $phoneNumber, string $message): bool
    {
        Log::info('SMS (not sent — log driver)', [
            'to' => substr($phoneNumber, 0, 4).str_repeat('•', max(0, strlen($phoneNumber) - 6)).substr($phoneNumber, -2),
            'message' => $message,
        ]);

        return true;
    }
}
