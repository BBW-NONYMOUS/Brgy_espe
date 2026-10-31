<?php

namespace App\Services;

use App\Models\Resident;

/**
 * Issues resident codes in the form KII-2026-00042 (prefix-year-sequence).
 * Must be called inside a DB transaction so the row lock prevents two
 * BHWs from receiving the same number at the same time.
 */
class ResidentCodeGenerator
{
    public function next(): string
    {
        $prefix = config('barangay.resident_code_prefix').'-'.now()->year.'-';

        $last = Resident::withTrashed()
            ->where('resident_code', 'like', $prefix.'%')
            ->orderByDesc('resident_code')
            ->lockForUpdate()
            ->value('resident_code');

        $sequence = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
