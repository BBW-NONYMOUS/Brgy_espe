<?php

use App\Enums\RoleName;
use App\Models\Resident;

beforeEach(fn () => seedReferenceData());

test('a denied Inertia action returns to the page with the policy message as a toast', function () {
    actingAsRole(RoleName::Admin);
    $resident = Resident::factory()->create();

    $this->from(route('residents.show', $resident))
        ->withHeaders(['X-Inertia' => 'true'])
        ->delete(route('residents.force-destroy', $resident))
        ->assertRedirect(route('residents.show', $resident))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'Archive the resident before deleting permanently.');

    expect(Resident::query()->find($resident->id))->not->toBeNull();
});

test('a generic denial uses a plain-language message', function () {
    actingAsRole(RoleName::Official);
    $resident = Resident::factory()->create();

    $this->from(route('dashboard'))
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('residents.visits.store', $resident), [])
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast.message', 'You are not allowed to do that.');
});

test('normal (non-Inertia) requests still receive a 403 page', function () {
    actingAsRole(RoleName::Official);

    $this->get(route('residents.index'))->assertForbidden();
});
