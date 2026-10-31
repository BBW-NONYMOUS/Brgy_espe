<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Resident;
use App\Services\AuditLogger;

beforeEach(function () {
    seedReferenceData();
    $this->household = Household::factory()->create();
});

function residentPayload(array $overrides = []): array
{
    return [
        'household_id' => test()->household->id,
        'last_name' => 'Dela Cruz',
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'sex' => 'male',
        'birthdate' => '1990-05-12',
        'civil_status' => 'married',
        'contact_no' => '0917 123 4567',
        'philhealth_no' => '020512345671',
        'relationship_to_head' => 'head',
        'is_pwd' => false,
        'consent_given' => true,
        'consent_date' => today()->toDateString(),
        'consent_purpose' => 'Barangay health profiling',
        ...$overrides,
    ];
}

test('a BHW registers a resident with consent, a generated code and normalized fields', function () {
    $bhw = actingAsRole(RoleName::Bhw);

    $response = $this->post(route('residents.store'), residentPayload());

    $resident = Resident::query()->sole();
    $response->assertRedirect(route('residents.show', $resident));

    expect($resident->resident_code)->toBe('KII-'.now()->year.'-00001')
        ->and($resident->contact_no)->toBe('09171234567')
        ->and($resident->philhealth_no)->toBe('02-051234567-1')
        ->and($resident->created_by)->toBe($bhw->id)
        ->and($resident->consents()->sole()->obtained_by)->toBe($bhw->id)
        ->and($this->household->fresh()->head_resident_id)->toBe($resident->id);
});

test('registration is refused without consent', function () {
    actingAsRole(RoleName::Bhw);

    $this->post(route('residents.store'), residentPayload(['consent_given' => false]))
        ->assertSessionHasErrors('consent_given');

    expect(Resident::query()->count())->toBe(0);
});

test('a possible duplicate (same name and birthdate) is flagged before saving', function () {
    actingAsRole(RoleName::Bhw);
    $existing = Resident::factory()->create(['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'birthdate' => '1990-05-12']);

    $this->post(route('residents.store'), residentPayload())
        ->assertSessionHasErrors('duplicate')
        ->assertInertiaFlash('duplicates.0.resident_code', $existing->resident_code);

    expect(Resident::query()->count())->toBe(1);

    // The BHW confirms it is a different person.
    $this->post(route('residents.store'), residentPayload(['confirm_duplicate' => true]))->assertSessionHasNoErrors();

    expect(Resident::query()->count())->toBe(2);
});

test('the live duplicate check returns matches as JSON', function () {
    actingAsRole(RoleName::Bhw);
    $existing = Resident::factory()->create(['last_name' => 'Gallardo', 'first_name' => 'Ramon', 'birthdate' => '1990-04-27']);

    $this->getJson(route('residents.duplicate-check', ['last_name' => 'Gallardo', 'first_name' => 'Ramon', 'birthdate' => '1990-04-27']))
        ->assertOk()
        ->assertJsonPath('matches.0.resident_code', $existing->resident_code)
        ->assertJsonMissingPath('matches.0.philhealth_no');
});

test('midwives and officials cannot register residents', function (RoleName $role) {
    actingAsRole($role);

    $this->post(route('residents.store'), residentPayload())->assertForbidden();
})->with([RoleName::Midwife, RoleName::Official]);

test('invalid input shows errors on each field', function () {
    actingAsRole(RoleName::Bhw);

    $this->post(route('residents.store'), residentPayload([
        'last_name' => 'D3la Cruz!',
        'birthdate' => today()->addDay()->toDateString(),
        'contact_no' => '12345',
        'philhealth_no' => '123',
        'sex' => 'other',
    ]))->assertSessionHasErrors(['last_name', 'birthdate', 'contact_no', 'philhealth_no', 'sex']);
});

test('updating a profile keeps a change history in the audit log with encrypted fields redacted', function () {
    actingAsRole(RoleName::Bhw);
    $resident = Resident::factory()->for($this->household)->create(['contact_no' => '09170000000', 'philhealth_no' => '02-000000000-1']);

    $this->put(route('residents.update', $resident), residentPayload([
        'last_name' => $resident->last_name,
        'first_name' => $resident->first_name,
        'birthdate' => $resident->birthdate->toDateString(),
        'contact_no' => '09179999999',
        'philhealth_no' => '02-999999999-9',
    ]))->assertRedirect(route('residents.show', $resident));

    $log = AuditLog::query()->where('action', AuditAction::Updated)->where('table_name', 'residents')->latest('id')->first();

    expect($log->old_values['contact_no'])->toBe('09170000000')
        ->and($log->new_values['contact_no'])->toBe('09179999999')
        ->and($log->new_values['philhealth_no'])->toBe(AuditLogger::REDACTED)
        ->and(json_encode($log->new_values))->not->toContain('999999999');
});
