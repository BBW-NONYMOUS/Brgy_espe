<?php

use App\Enums\RoleName;
use App\Models\HealthVisit;
use App\Models\Pregnancy;
use App\Models\Resident;
use App\Models\Vaccine;
use App\Models\VisitType;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedReferenceData();
    $this->resident = Resident::factory()->female()->create(['birthdate' => now()->subYears(28)]);
    $this->visitType = VisitType::query()->where('name', 'General Consultation')->value('id');
});

test('a midwife records a visit with vital signs; clinical text is encrypted at rest', function () {
    $midwife = actingAsRole(RoleName::Midwife);

    $this->post(route('residents.visits.store', $this->resident), [
        'visit_type_id' => $this->visitType,
        'visit_date' => today()->toDateString(),
        'chief_complaint' => 'Headache for 2 days',
        'diagnosis' => 'Tension headache',
        'vitals' => ['bp_systolic' => 120, 'bp_diastolic' => 80, 'weight_kg' => 55.5, 'height_cm' => 155, 'temperature_c' => 36.8],
    ])->assertRedirect(route('residents.show', $this->resident));

    $visit = HealthVisit::query()->with('vitalSign')->sole();

    expect($visit->attended_by)->toBe($midwife->id)
        ->and($visit->vitalSign->bp_systolic)->toBe(120)
        ->and($visit->vitalSign->bmi())->toBe(23.1)
        ->and(DB::table('health_visits')->value('diagnosis'))->not->toContain('headache');
});

test('impossible vital signs are rejected', function () {
    actingAsRole(RoleName::Bhw);

    $this->post(route('residents.visits.store', $this->resident), [
        'visit_type_id' => $this->visitType,
        'visit_date' => today()->toDateString(),
        'vitals' => ['bp_systolic' => 80, 'bp_diastolic' => 120, 'temperature_c' => 50],
    ])->assertSessionHasErrors(['vitals.bp_diastolic', 'vitals.temperature_c']);
});

test('a vaccine dose cannot be recorded twice or beyond its number of doses', function () {
    actingAsRole(RoleName::Midwife);
    $bcg = Vaccine::query()->where('code', 'BCG')->value('id');

    $payload = ['vaccine_id' => $bcg, 'dose_no' => 1, 'date_given' => today()->toDateString()];

    $this->post(route('residents.immunizations.store', $this->resident), $payload)->assertSessionHasNoErrors();
    $this->post(route('residents.immunizations.store', $this->resident), $payload)->assertSessionHasErrors('dose_no');
    $this->post(route('residents.immunizations.store', $this->resident), [...$payload, 'dose_no' => 2])->assertSessionHasErrors('dose_no');
});

test('a pregnancy record defaults the EDD from the LMP and links prenatal visits', function () {
    actingAsRole(RoleName::Midwife);
    $lmp = today()->subWeeks(12);

    $this->post(route('residents.pregnancies.store', $this->resident), [
        'lmp' => $lmp->toDateString(),
        'gravida' => 2,
        'para' => 1,
        'risk_level' => 'low',
    ])->assertSessionHasNoErrors();

    $record = Pregnancy::query()->sole();
    expect($record->edd->toDateString())->toBe($lmp->addDays(280)->toDateString())
        ->and($record->gestationalAgeWeeks())->toBe(12)
        ->and(Resident::query()->pregnant()->count())->toBe(1);

    $this->post(route('residents.visits.store', $this->resident), [
        'visit_type_id' => VisitType::query()->where('name', 'Prenatal Check-up')->value('id'),
        'visit_date' => today()->toDateString(),
        'maternal' => ['pregnancy_id' => $record->id, 'kind' => 'prenatal', 'fetal_heart_rate' => 145],
    ])->assertSessionHasNoErrors();

    $prenatal = $record->prenatalVisits()->sole();
    expect($prenatal->fetal_heart_rate)->toBe(145)
        ->and($record->postnatalVisits()->count())->toBe(0);

    // Re-saving the same visit as postnatal moves it to the postnatal table.
    $this->put(route('visits.update', $prenatal->health_visit_id), [
        'visit_type_id' => VisitType::query()->where('name', 'Postnatal Check-up')->value('id'),
        'visit_date' => today()->toDateString(),
        'maternal' => ['pregnancy_id' => $record->id, 'kind' => 'postnatal', 'remarks' => 'Exclusive breastfeeding'],
    ])->assertSessionHasNoErrors();

    expect($record->prenatalVisits()->count())->toBe(0)
        ->and($record->postnatalVisits()->sole()->remarks)->toBe('Exclusive breastfeeding');
});

test('pregnancy records are refused for male residents and when para exceeds gravida', function () {
    actingAsRole(RoleName::Midwife);
    $male = Resident::factory()->male()->create(['birthdate' => now()->subYears(30)]);

    $this->post(route('residents.pregnancies.store', $male), ['edd' => today()->addMonths(5)->toDateString(), 'gravida' => 1, 'para' => 0, 'risk_level' => 'low'])
        ->assertSessionHasErrors('resident');

    $this->post(route('residents.pregnancies.store', $this->resident), ['edd' => today()->addMonths(5)->toDateString(), 'gravida' => 1, 'para' => 3, 'risk_level' => 'low'])
        ->assertSessionHasErrors('para');
});

test('barangay officials cannot record health information', function () {
    actingAsRole(RoleName::Official);

    $this->post(route('residents.visits.store', $this->resident), [
        'visit_type_id' => $this->visitType,
        'visit_date' => today()->toDateString(),
    ])->assertForbidden();
});
