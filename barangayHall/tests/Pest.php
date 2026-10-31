<?php

use App\Enums\ConsentType;
use App\Enums\RoleName;
use App\Models\Consent;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every Feature test runs against a fresh in-memory SQLite database
| (see phpunit.xml). Unit tests use the plain Laravel TestCase.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Roles, permissions, puroks, visit types, conditions, vaccines + EPI schedule.
 */
function seedReferenceData(): void
{
    test()->seed([RbacSeeder::class, LookupSeeder::class]);
}

/**
 * Create a user with the given role and log in as them.
 */
function actingAsRole(RoleName $role, array $attributes = []): User
{
    $user = User::factory()->role($role)->create($attributes);
    test()->actingAs($user);

    return $user;
}

/**
 * A resident living in the given purok (puroks come from seedReferenceData()).
 */
function residentInPurok(string $purok = 'Purok 1', array $attributes = []): Resident
{
    $household = Household::factory()->create([
        'purok_id' => Purok::query()->where('name', $purok)->value('id'),
    ]);

    return Resident::factory()->for($household)->create($attributes);
}

/**
 * Record an active (non-revoked) consent of the given type for a resident.
 */
function giveConsent(Resident $resident, ConsentType $type): Consent
{
    return $resident->consents()->create([
        'consent_type' => $type,
        'consent_date' => today(),
        'obtained_by' => User::factory()->create()->id,
        'purpose' => 'Test consent',
    ]);
}
