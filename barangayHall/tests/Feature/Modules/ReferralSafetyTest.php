<?php

use App\Enums\ReferralStatus;
use App\Enums\RoleName;
use App\Models\Facility;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\ReferralStatusUpdated;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedReferenceData();
    $this->resident = residentInPurok('Purok 1');
    $this->facility = Facility::query()->where('type', 'hospital')->firstOrFail();
});

test('midwives create numbered referrals that move forward only', function () {
    $midwife = actingAsRole(RoleName::Midwife);

    $this->post(route('referrals.store'), [
        'resident_id' => $this->resident->id, 'facility_id' => $this->facility->id,
        'reason' => 'Severe anemia in pregnancy', 'urgency' => 'urgent',
    ])->assertSessionHasNoErrors();

    $referral = Referral::query()->sole();
    expect($referral->referral_no)->toBe('REF-'.now()->year.'-00001')
        ->and($referral->status)->toBe(ReferralStatus::Pending)
        ->and($referral->referredBy->is($midwife))->toBeTrue();

    $this->patch(route('referrals.status.update', $referral), ['status' => 'completed'])->assertSessionHasErrors('status');

    foreach (['sent', 'received', 'completed'] as $status) {
        $this->patch(route('referrals.status.update', $referral), ['status' => $status, 'feedback_note' => $status === 'completed' ? 'Seen by OB; iron sucrose given.' : null])
            ->assertSessionHasNoErrors();
    }

    $referral->refresh();
    expect($referral->status)->toBe(ReferralStatus::Completed)
        ->and($referral->completed_at)->not->toBeNull()
        ->and($referral->feedback_note)->toBe('Seen by OB; iron sucrose given.')
        ->and($referral->statusChanges()->count())->toBe(4);

    $this->patch(route('referrals.status.update', $referral), ['status' => 'cancelled'])->assertSessionHasErrors('status');
});

test('the referring midwife is notified when someone else updates the status', function () {
    $midwife = actingAsRole(RoleName::Midwife);
    $this->post(route('referrals.store'), [
        'resident_id' => $this->resident->id, 'facility_id' => $this->facility->id, 'reason' => 'Dengue warning signs', 'urgency' => 'emergency',
    ]);

    actingAsRole(RoleName::Admin);
    $this->patch(route('referrals.status.update', Referral::query()->sole()), ['status' => 'sent'])->assertSessionHasNoErrors();

    $notification = $midwife->notifications()->sole();
    expect($notification->type)->toBe(ReferralStatusUpdated::class)
        ->and(json_encode($notification->data))->not->toContain('Dengue')
        ->and(json_encode($notification->data))->not->toContain($this->resident->last_name);
});

test('BHWs see referrals without changing them; lists never include the reason; officials are refused', function () {
    actingAsRole(RoleName::Midwife);
    $this->post(route('referrals.store'), ['resident_id' => $this->resident->id, 'facility_id' => $this->facility->id, 'reason' => 'Chest pain', 'urgency' => 'urgent']);
    $referral = Referral::query()->sole();

    actingAsRole(RoleName::Bhw);
    $this->get(route('referrals.index'))->assertInertia(fn (Assert $page) => $page
        ->component('referrals/index')
        ->has('referrals.data', 1)
        ->missing('referrals.data.0.reason'));
    $this->get(route('referrals.show', $referral))->assertInertia(fn (Assert $page) => $page
        ->where('referral.reason', 'Chest pain')
        ->where('nextStatuses', []));
    $this->patch(route('referrals.status.update', $referral), ['status' => 'sent'])->assertForbidden();

    actingAsRole(RoleName::Official);
    $this->get(route('referrals.index'))->assertForbidden();
});

test('critical medical alerts form the profile banner and only one emergency contact is primary', function () {
    actingAsRole(RoleName::Bhw);

    $this->post(route('residents.medical-alerts.store', $this->resident), ['alert_type' => 'allergy', 'description' => 'Penicillin anaphylaxis', 'severity' => 'critical'])->assertSessionHasNoErrors();
    $this->post(route('residents.medical-alerts.store', $this->resident), ['alert_type' => 'chronic', 'description' => 'Asthma', 'severity' => 'warning'])->assertSessionHasNoErrors();

    $this->get(route('residents.show', $this->resident))->assertInertia(fn (Assert $page) => $page
        ->has('criticalAlerts', 1)
        ->where('criticalAlerts.0.description', 'Penicillin anaphylaxis'));

    foreach ([['Ana Cruz', true], ['Ben Cruz', true], ['Carla Cruz', false]] as [$name, $primary]) {
        $this->post(route('residents.emergency-contacts.store', $this->resident), [
            'name' => $name, 'relationship' => 'Sibling', 'phone' => '0917 123 4567', 'is_primary' => $primary,
        ])->assertSessionHasNoErrors();
    }

    expect($this->resident->emergencyContacts()->where('is_primary', true)->pluck('name')->all())->toBe(['Ben Cruz']);

    $this->post(route('residents.emergency-contacts.store', $this->resident), ['name' => 'X', 'relationship' => 'Friend', 'phone' => '12345'])
        ->assertSessionHasErrors('phone');
});

test('officials never receive critical alerts or health tabs', function () {
    $this->resident->medicalAlerts()->create(['alert_type' => 'allergy', 'description' => 'Sulfa', 'severity' => 'critical', 'recorded_by' => User::factory()->create()->id]);

    actingAsRole(RoleName::Official);
    $this->get(route('residents.show', $this->resident))->assertForbidden();
});
