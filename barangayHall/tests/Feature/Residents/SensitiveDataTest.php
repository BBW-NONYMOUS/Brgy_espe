<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\HealthVisit;
use App\Models\Permission;
use App\Models\Resident;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedReferenceData();
    $this->resident = Resident::factory()->create(['philhealth_no' => '02-051234567-1']);
    HealthVisit::factory()->for($this->resident)->create(['diagnosis' => 'Essential hypertension', 'notes' => 'Advised low-salt diet']);
});

test('the residents list only ever contains the masked PhilHealth number', function () {
    actingAsRole(RoleName::Admin);

    $this->get(route('residents.index'))->assertInertia(fn (Assert $page) => $page
        ->where('residents.data.0.philhealth_no_masked', '••-•••••••67-1')
        ->missing('residents.data.0.philhealth_no'));
});

test('health workers see full details on the profile, and opening health records is logged', function () {
    $bhw = actingAsRole(RoleName::Bhw);

    $this->get(route('residents.show', $this->resident))->assertInertia(fn (Assert $page) => $page
        ->component('residents/show')
        ->where('resident.philhealth_no', '02-051234567-1')
        ->missing('health') // deferred: loaded after first paint
        ->loadDeferredProps('health', fn (Assert $reload) => $reload
            ->where('health.timeline.0.diagnosis', 'Essential hypertension')
            ->where('health.timeline.0.notes', 'Advised low-salt diet')
            ->where('health.timeline.0.restricted', false)));

    expect(AuditLog::query()->where('action', AuditAction::ViewedSensitive)->where('user_id', $bhw->id)->where('record_id', $this->resident->id)->exists())->toBeTrue();
});

test('a role without health.view_sensitive never receives sensitive fields', function () {
    // Remove the permission from the BHW role to simulate a restricted role.
    Role::for(RoleName::Bhw)->permissions()->detach(Permission::query()->where('name', PermissionName::HealthViewSensitive->value)->value('id'));
    actingAsRole(RoleName::Bhw);

    $this->get(route('residents.show', $this->resident))->assertInertia(fn (Assert $page) => $page
        ->missing('resident.philhealth_no')
        ->where('resident.philhealth_no_masked', '••-•••••••67-1')
        ->where('health', null));

    expect(AuditLog::query()->where('action', AuditAction::ViewedSensitive)->exists())->toBeFalse();
});

test('barangay officials cannot open individual residents at all', function () {
    actingAsRole(RoleName::Official);

    $this->get(route('residents.show', $this->resident))->assertForbidden();
    $this->getJson(route('residents.duplicate-check', ['last_name' => 'x', 'first_name' => 'y', 'birthdate' => '2000-01-01']))->assertForbidden();
});

test('sensitive values are never present in the page source of other pages', function () {
    actingAsRole(RoleName::Bhw);

    $html = $this->get(route('residents.index'))->getContent();

    expect($html)->not->toContain('051234567')->not->toContain('hypertension');
});
