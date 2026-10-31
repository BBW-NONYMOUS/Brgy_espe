<?php

namespace App\Support;

use Random\Randomizer;

/**
 * One-time passwords handed to new or reset accounts (the user must change it
 * at first login).
 */
final class TemporaryPassword
{
    /**
     * 12 characters with upper, lower and digits (meets the password policy);
     * ambiguous characters (0/O, 1/l/I) removed so it can be read aloud.
     */
    public static function generate(): string
    {
        $random = new Randomizer; // cryptographically secure engine
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';

        $chars = [
            ...str_split($random->getBytesFromString($upper, 1)),
            ...str_split($random->getBytesFromString($lower, 1)),
            ...str_split($random->getBytesFromString($digits, 1)),
            ...str_split($random->getBytesFromString($upper.$lower.$digits, 9)),
        ];

        return implode('', $random->shuffleArray($chars));
    }
}
