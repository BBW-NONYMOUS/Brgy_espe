<?php

use App\Enums\CaseClassification;
use App\Enums\OutbreakAlertStatus;
use App\Enums\RoleName;
use App\Models\Disease;
use App\Models\DiseaseCase;
use App\Models\OutbreakAlert;
use App\Models\User;
use App\Notifications\OutbreakDetected;
use App\Services\OutbreakDetectionService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedReferenceData();
    $this->dengue = Disease::query()->where('name', 'Dengue')->firstOrFail(); // 3 in a purok / 14 days; 5 barangay-wide
    $this->official = User::factory()->official()->create();
});

function reportCase(Disease $disease, string $purok, int $daysAgo, string $classification = 'suspected'): DiseaseCase
{
    $resident = residentInPurok($purok);

    return $resident->diseaseCases()->create([
        'disease_id' => $disease->id,
        'purok_id' => $resident->household->purok_id,
        'onset_date' => today()->subDays($daysAgo),
        'reported_date' => today(),
        'classification' => $classification,
        'reported_by' => User::factory()->bhw()->create()->id,
    ]);
}

test('three dengue cases in one purok within 14 days raise one alert and notify officials without names', function () {
    $cases = collect([10, 5, 1])->map(fn (int $d) => reportCase($this->dengue, 'Purok 3', $d));

    $alerts = app(OutbreakDetectionService::class)->sweep();

    $alert = OutbreakAlert::query()->sole();
    expect($alerts)->toHaveCount(1)
        ->and($alert->status)->toBe(OutbreakAlertStatus::Active)
        ->and($alert->case_count)->toBe(3)
        ->and($alert->purok->name)->toBe('Purok 3')
        ->and($alert->period_start->toDateString())->toBe(today()->subDays(10)->toDateString());

    $notification = $this->official->notifications()->sole();
    expect($notification->type)->toBe(OutbreakDetected::class)
        ->and($notification->data['message'])->toContain('3 Dengue cases in Purok 3');

    foreach ($cases as $case) {
        expect(json_encode($notification->data))->not->toContain($case->resident->last_name);
    }
});

test('ruled-out cases, cases outside the window and cases in other puroks do not count', function () {
    reportCase($this->dengue, 'Purok 3', 2);
    reportCase($this->dengue, 'Purok 3', 3);
    reportCase($this->dengue, 'Purok 3', 4, CaseClassification::RuledOut->value);
    reportCase($this->dengue, 'Purok 3', 20);
    reportCase($this->dengue, 'Purok 4', 1);

    expect(app(OutbreakDetectionService::class)->sweep())->toBeEmpty()
        ->and(OutbreakAlert::query()->count())->toBe(0);
});

test('detection is idempotent and only re-notifies when the count rises', function () {
    collect([6, 4, 2])->each(fn (int $d) => reportCase($this->dengue, 'Purok 2', $d));
    $detector = app(OutbreakDetectionService::class);

    $detector->sweep();
    $detector->sweep();
    expect(OutbreakAlert::query()->count())->toBe(1)
        ->and($this->official->notifications()->count())->toBe(1);

    reportCase($this->dengue, 'Purok 2', 0);
    $detector->sweep();

    expect(OutbreakAlert::query()->sole()->case_count)->toBe(4)
        ->and($this->official->notifications()->count())->toBe(2);
});

test('five cases across puroks trip the barangay-wide threshold', function () {
    foreach (['Purok 1', 'Purok 2', 'Purok 4', 'Purok 5', 'Purok 6'] as $i => $purok) {
        reportCase($this->dengue, $purok, $i + 1);
    }

    app(OutbreakDetectionService::class)->sweep();

    expect(OutbreakAlert::query()->whereNull('purok_id')->sole()->case_count)->toBe(5);
});

test('a BHW report is saved as suspected, captures the purok and runs the outbreak check', function () {
    actingAsRole(RoleName::Bhw);
    reportCase($this->dengue, 'Purok 5', 3);
    reportCase($this->dengue, 'Purok 5', 2);
    $resident = residentInPurok('Purok 5');

    $this->post(route('disease-cases.store'), [
        'resident_id' => $resident->id,
        'disease_id' => $this->dengue->id,
        'onset_date' => today()->subDay()->toDateString(),
        'classification' => 'confirmed',
    ])->assertSessionHasNoErrors();

    $case = DiseaseCase::query()->latest('id')->firstOrFail();
    expect($case->classification)->toBe(CaseClassification::Suspected)
        ->and($case->purok_id)->toBe($resident->household->purok_id)
        // The queued check ran (sync queue in tests) and found 3 cases in Purok 5.
        ->and(OutbreakAlert::query()->open()->count())->toBe(1);
});

test('only midwives and admins may classify cases; alert workflow needs outbreaks.manage', function () {
    $case = reportCase($this->dengue, 'Purok 1', 2);
    $payload = ['classification' => 'confirmed', 'outcome' => 'ongoing'];

    actingAsRole(RoleName::Bhw);
    $this->put(route('disease-cases.update', $case), $payload)->assertForbidden();

    actingAsRole(RoleName::Midwife);
    $this->put(route('disease-cases.update', $case), $payload)->assertSessionHasNoErrors();
    expect($case->fresh()->classification)->toBe(CaseClassification::Confirmed);

    $this->put(route('disease-cases.update', $case), ['classification' => 'confirmed', 'outcome' => 'recovered'])
        ->assertSessionHasErrors('outcome_date');

    $alert = OutbreakAlert::query()->create([
        'disease_id' => $this->dengue->id, 'threshold_min_cases' => 3, 'threshold_days_window' => 14,
        'case_count' => 3, 'period_start' => today()->subDays(5), 'period_end' => today(), 'status' => 'active',
    ]);
    $this->post(route('outbreak-alerts.close', $alert), [])->assertSessionHasErrors('closing_note');
    $this->post(route('outbreak-alerts.close', $alert), ['closing_note' => 'Clean-up done; no new cases.'])->assertSessionHasNoErrors();
    expect($alert->fresh()->status)->toBe(OutbreakAlertStatus::Closed);

    $this->actingAs($this->official)->post(route('outbreak-alerts.acknowledge', $alert))->assertForbidden();
});

test('officials see the alert summary but not the case list', function () {
    $this->actingAs($this->official);

    $this->get(route('outbreak-alerts.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('diseases/alerts')->where('canManage', false));
    $this->get(route('disease-cases.index'))->assertForbidden();
});
