<?php

namespace App\Services;

use App\Models\Resident;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds residents with the same last name, first name and birthdate.
 * Uses the (last_name, first_name, birthdate) index; the MySQL collation
 * is case-insensitive, so "dela cruz" matches "Dela Cruz".
 */
class DuplicateDetector
{
    /**
     * @return Collection<int, Resident>
     */
    public function find(string $lastName, string $firstName, CarbonInterface|string $birthdate, ?int $ignoreId = null): Collection
    {
        $birthdate = $birthdate instanceof CarbonInterface ? $birthdate->toDateString() : today()->parse($birthdate)->toDateString();

        return Resident::withTrashed()
            ->with('household.purok')
            ->where('last_name', trim($lastName))
            ->where('first_name', trim($firstName))
            ->whereDate('birthdate', $birthdate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->limit(5)
            ->get();
    }
}
