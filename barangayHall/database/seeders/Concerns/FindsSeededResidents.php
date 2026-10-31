<?php

namespace Database\Seeders\Concerns;

use App\Models\Resident;
use App\Models\User;

/**
 * Lookups shared by the module seeders, which build on ResidentSeeder's
 * named residents and UserSeeder's accounts.
 */
trait FindsSeededResidents
{
    /**
     * "Dela Cruz, Angelica" style lookup by last and first name.
     */
    protected function resident(string $lastName, string $firstName): Resident
    {
        return Resident::query()
            ->with('household')
            ->where('last_name', $lastName)
            ->where('first_name', $firstName)
            ->firstOrFail();
    }

    protected function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
