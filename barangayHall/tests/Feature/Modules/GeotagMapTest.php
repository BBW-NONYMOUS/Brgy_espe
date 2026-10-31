<?php

use App\Enums\AuditAction;
use App\Enums\ConsentType;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Resident;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedReferenceData();
    $this->resident = residentInPurok('Purok 3');
    $this->household = $this->resident->household;
    [$this->lat, $this->lng] = config('barangay.map.center');
});

test('a household cannot be geo-tagged without a geo-tagging consent', function () {
    actingAsRole(RoleName::Bhw);

    $this->put(route('households.geotag.update', $this->household), ['latitude' => $this->lat, 'longitude' => $this->lng])
        ->assertSessionHasErrors('consent');

    giveConsent($this->resident, ConsentType::Geotagging);

    $this->put(route('households.geotag.update', $this->household), ['latitude' => $this->lat, 'longitude' => $this->lng, 'accuracy_m' => 8])
        ->assertSessionHasNoErrors();

    $household = $this->household->fresh();
    expect($household->isGeotagged())->toBeTrue()
        ->and((float) $household->latitude)->toEqualWithDelta($this->lat, 0.0000001)
        ->and($household->geotag_accuracy_m)->toBe(8);
});

test('points outside Isulan are refused and officials cannot geo-tag', function () {
    giveConsent($this->resident, ConsentType::Geotagging);

    actingAsRole(RoleName::Bhw);
    $this->put(route('households.geotag.update', $this->household), ['latitude' => 14.5995, 'longitude' => 120.9842]) // Manila
        ->assertSessionHasErrors(['latitude', 'longitude']);

    actingAsRole(RoleName::Official);
    $this->put(route('households.geotag.update', $this->household), ['latitude' => $this->lat, 'longitude' => $this->lng])->assertForbidden();
});

test('withdrawing the last geo-tagging consent removes the location', function () {
    $consent = giveConsent($this->resident, ConsentType::Geotagging);
    $this->household->update(['latitude' => $this->lat, 'longitude' => $this->lng, 'geotagged_at' => now()]);

    actingAsRole(RoleName::Bhw);
    $this->patch(route('consents.revoke', $consent))->assertRedirect();

    expect($this->household->fresh()->isGeotagged())->toBeFalse();
});

test('health staff get exact markers with flags; loading them is audit-logged', function () {
    $this->household->update(['latitude' => $this->lat, 'longitude' => $this->lng, 'geotagged_at' => now(), 'is_4ps' => true]);
    Resident::factory()->senior()->for($this->household)->create();

    actingAsRole(RoleName::Midwife);
    $this->get(route('map.index'))->assertInertia(fn (Assert $page) => $page
        ->component('map/index')
        ->where('mode', 'exact')
        ->loadDeferredProps('markers', fn (Assert $reload) => $reload
            ->has('markers', 1)
            ->where('markers.0.household_no', $this->household->household_no)
            ->where('markers.0.flags', fn ($flags) => collect($flags)->contains('senior') && collect($flags)->contains('four_ps'))));

    expect(AuditLog::query()->where('action', AuditAction::ViewedSensitive)->where('description', 'like', 'Viewed the household map%')->exists())->toBeTrue();
});

test('barangay officials get purok counts at the purok centre, never household points', function () {
    $this->household->update(['latitude' => $this->lat, 'longitude' => $this->lng, 'geotagged_at' => now()]);

    actingAsRole(RoleName::Official);
    $this->get(route('map.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('mode', 'aggregate')
        ->where('markers', null)
        ->where('notGeotagged', null)
        ->loadDeferredProps('summary', fn (Assert $reload) => $reload
            ->has('purokSummary', 7)
            ->where('purokSummary.2.name', 'Purok 3')
            ->where('purokSummary.2.households', 1)
            ->where('purokSummary.2.lat', fn ($lat) => abs($lat - $this->lat) > 0.001)));
});

test('household coordinates are only in props for roles with map.view_exact', function () {
    $this->household->update(['latitude' => $this->lat, 'longitude' => $this->lng, 'geotagged_at' => now()]);

    actingAsRole(RoleName::Bhw);
    $this->get(route('households.show', $this->household))->assertInertia(fn (Assert $page) => $page
        ->where('household.is_geotagged', true)
        ->has('household.latitude')
        ->where('geotag.has_consent', false));

    $user = User::factory()->bhw()->create();
    $user->role->permissions()->detach(Permission::query()->where('name', 'map.view_exact')->value('id'));
    $this->actingAs($user->fresh())->get(route('households.show', $this->household))->assertInertia(fn (Assert $page) => $page
        ->where('household.is_geotagged', true)
        ->missing('household.latitude')
        ->missing('household.longitude'));
});
