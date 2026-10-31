<?php

use App\Enums\RoleName;
use App\Models\MaternalVisitRule;
use App\Models\OutbreakThreshold;

/*
| Least privilege for every new page: the server refuses what the role may
| not see, whatever the UI shows (featured.md "Users and roles").
*/

beforeEach(function () {
    seedReferenceData();
});

test('page access per role', function (string $route, array $allowed) {
    foreach (RoleName::cases() as $role) {
        actingAsRole($role);
        $response = $this->get(route($route));

        in_array($role, $allowed, true)
            ? $response->assertOk()
            : $response->assertForbidden();
    }
})->with([
    'household map (exact or purok-level)' => ['map.index', RoleName::cases()],
    'outbreak alert summary' => ['outbreak-alerts.index', RoleName::cases()],
    'program schedule' => ['programs.index', RoleName::cases()],
    'notifications' => ['notifications.index', RoleName::cases()],
    'disease cases' => ['disease-cases.index', [RoleName::Admin, RoleName::Bhw, RoleName::Midwife]],
    'maternal tracker' => ['pregnancies.index', [RoleName::Admin, RoleName::Bhw, RoleName::Midwife]],
    'child health' => ['child-health.index', [RoleName::Admin, RoleName::Bhw, RoleName::Midwife]],
    'referrals' => ['referrals.index', [RoleName::Admin, RoleName::Bhw, RoleName::Midwife]],
    'inventory' => ['medicines.index', [RoleName::Admin, RoleName::Bhw, RoleName::Midwife]],
    'appointments' => ['appointments.index', [RoleName::Admin, RoleName::Bhw, RoleName::Midwife]],
    'health settings' => ['health-settings.index', [RoleName::Admin, RoleName::Midwife]],
]);

test('midwives edit schedules but not outbreak thresholds; admins edit both', function () {
    $threshold = OutbreakThreshold::query()->firstOrFail();
    $rule = MaternalVisitRule::query()->where('kind', 'prenatal')->where('sequence', 1)->sole();
    $thresholdPayload = ['disease_id' => $threshold->disease_id, 'scope' => $threshold->scope->value, 'min_cases' => 2, 'days_window' => 14];
    $rulePayload = ['kind' => 'prenatal', 'sequence' => 1, 'label' => '1st ANC contact (13 weeks)', 'offset_days' => 91];

    actingAsRole(RoleName::Midwife);
    $this->put(route('health-settings.thresholds.update', $threshold), $thresholdPayload)->assertForbidden();
    $this->put(route('health-settings.maternal-rules.update', $rule), $rulePayload)->assertSessionHasNoErrors();
    expect($rule->fresh()->offset_days)->toBe(91);

    actingAsRole(RoleName::Admin);
    $this->put(route('health-settings.thresholds.update', $threshold), $thresholdPayload)->assertSessionHasNoErrors();
    expect($threshold->fresh()->min_cases)->toBe(2);

    actingAsRole(RoleName::Bhw);
    $this->put(route('health-settings.maternal-rules.update', $rule), $rulePayload)->assertForbidden();
});
