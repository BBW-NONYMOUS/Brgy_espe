<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside the dispensing transaction (which is then rolled back) when
 * the usable, non-expired stock is smaller than the quantity asked for.
 */
class InsufficientStock extends RuntimeException
{
    public function __construct(public readonly int $requested, public readonly int $available)
    {
        parent::__construct("Only {$available} left in usable (non-expired) stock; {$requested} requested.");
    }
}
