<?php

use App\Enums\AppointmentStatus;
use App\Enums\MaternalStatus;
use App\Enums\RiskLevel;
use App\Enums\RoleName;
use App\Models\Appointment;
use App\Models\Pregnancy;
use App\Models\Resident;
use App\Models\RiskFactor;
use App\Models\User;
use App\Models\Vaccine;
use App\Models\VisitType;
use App\Services\AppointmentGenerator;
use App\Services\ImmunizationSchedule;
use App\Services\MicronutrientSchedule;
use App\Services\PregnancyScheduleService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    seedReferenceData();
    $this->mother = residentInPurok('Purok 2', ['sex' => 'female', 'birthdate' => now()->subYears(27), 'civil_status' => 'married']);
});

function registerPregnancy(Resident $mother, int $weeksPregnant, array $extra = []): void
{
    test()->post(route('residents.pregnancies.store', $mother), [
        'lmp' => today()->subWeeks($weeksPregnant)->toDateString(),
        'gravida' => 2,
        'para' => 1,
        'risk_level' => 'low',
        ...$extra,
    ])->assertSessionHasNoErrors();
}

test('only midwives and admins register pregnancies; a risk factor makes it high-risk', function () {
    actingAsRole(RoleName::Bhw);
    $this->post(route('residents.pregnancies.store', $this->mother), ['lmp' => today()->subWeeks(10)->toDateString(), 'gravida' => 1, 'para' => 0, 'risk_level' => 'low'])
        ->assertForbidden();

    actingAsRole(RoleName::Midwife);
    registerPregnancy($this->mother, 10, ['risk_factor_ids' => [RiskFactor::query()->where('code', 'previous_cesarean')->value('id')]]);

    $pregnancy = Pregnancy::query()->sole();
    expect($pregnancy->risk_level)->toBe(RiskLevel::High)
        ->and($pregnancy->riskFactors()->pluck('code')->all())->toBe(['previous_cesarean']);
});

test('registration books the ANC contacts due within 60 days, once', function () {
    actingAsRole(RoleName::Midwife);
    registerPregnancy($this->mother, 14); // 12-week contact (due now) and 20-week contact (in 42 days); 26 weeks is beyond 60 days

    $pregnancy = Pregnancy::query()->sole();
    $keys = Appointment::query()->orderBy('scheduled_at')->pluck('source_key')->all();
    expect($keys)->toBe(['anc:1', 'anc:2']);

    app(AppointmentGenerator::class)->syncPregnancy($pregnancy);
    app(AppointmentGenerator::class)->syncAll();
    expect(Appointment::query()->count())->toBe(2);

    // A rescheduled appointment is never moved back by the generator.
    $first = Appointment::query()->where('source_key', 'anc:1')->sole();
    $this->put(route('appointments.update', $first), [
        'appointment_type_id' => $first->appointment_type_id,
        'scheduled_at' => $first->scheduled_at->addDays(3)->toDateTimeString(),
        'status' => 'scheduled',
    ])->assertSessionHasNoErrors();
    app(AppointmentGenerator::class)->syncPregnancy($pregnancy->fresh());

    expect($first->fresh()->edited_manually)->toBeTrue()
        ->and($first->fresh()->scheduled_at->toDateString())->toBe($first->scheduled_at->addDays(3)->toDateString());
});

test('a prenatal visit is linked to its scheduled contact and completes the appointment', function () {
    actingAsRole(RoleName::Midwife);
    registerPregnancy($this->mother, 12);
    $pregnancy = Pregnancy::query()->sole();

    $this->post(route('residents.visits.store', $this->mother), [
        'visit_type_id' => VisitType::query()->where('name', 'Prenatal Check-up')->value('id'),
        'visit_date' => today()->toDateString(),
        'maternal' => ['pregnancy_id' => $pregnancy->id, 'kind' => 'prenatal', 'fetal_heart_rate' => 140],
    ])->assertSessionHasNoErrors();

    $contacts = app(PregnancyScheduleService::class)->contacts($pregnancy->fresh());
    expect($pregnancy->prenatalVisits()->sole()->rule->sequence)->toBe(1)
        ->and($contacts->firstWhere('sequence', 1)['status'])->toBe('done')
        ->and(Appointment::query()->where('source_key', 'anc:1')->sole()->status)->toBe(AppointmentStatus::Completed);
});

test('a contact more than 14 days past due without a visit is overdue', function () {
    $pregnancy = $this->mother->pregnancies()->create([
        'lmp' => today()->subWeeks(30), 'edd' => today()->subWeeks(30)->addDays(280),
        'gravida' => 1, 'para' => 0, 'risk_level' => 'low', 'status' => 'ongoing',
    ]);

    $contacts = app(PregnancyScheduleService::class)->contacts($pregnancy);

    // 12, 20, 26 weeks: overdue; 30 weeks (today): due; 34 weeks: upcoming.
    expect($contacts->where('kind', 'prenatal')->pluck('status', 'sequence')->only([1, 2, 3, 4, 5])->all())
        ->toBe([1 => 'overdue', 2 => 'overdue', 3 => 'overdue', 4 => 'due', 5 => 'upcoming'])
        ->and(app(PregnancyScheduleService::class)->nextContact($pregnancy)['sequence'])->toBe(1);
});

test('recording a delivery registers the newborn, books postnatal and EPI visits and cancels ANC ones', function () {
    actingAsRole(RoleName::Midwife);
    registerPregnancy($this->mother, 37);
    $pregnancy = Pregnancy::query()->sole();
    $openAnc = Appointment::query()->where('status', AppointmentStatus::Scheduled)->where('source_key', 'like', 'anc:%')->count();
    expect($openAnc)->toBeGreaterThan(0);

    $this->post(route('pregnancies.delivery.store', $pregnancy), [
        'delivery_date' => today()->subDay()->toDateString(),
        'place_type' => 'health_facility', 'attendant_type' => 'midwife', 'delivery_mode' => 'normal',
        'live_births' => 1, 'stillbirths' => 0,
        'newborns' => [['first_name' => 'Baby', 'last_name' => 'Cruz', 'sex' => 'female', 'relationship_to_head' => 'grandchild', 'birth_weight_kg' => 2.3]],
    ])->assertSessionHasNoErrors();

    $pregnancy->refresh();
    $baby = Resident::query()->where('first_name', 'Baby')->sole();

    expect($pregnancy->status)->toBe(MaternalStatus::Delivered)
        ->and($pregnancy->outcome_date->toDateString())->toBe(today()->subDay()->toDateString())
        ->and($pregnancy->delivery->isFacilityBased())->toBeTrue()
        ->and($baby->mother_id)->toBe($this->mother->id)
        ->and($baby->household_id)->toBe($this->mother->household_id)
        ->and($baby->growthMeasurements()->sole()->remarks)->toContain('Low birth weight')
        ->and(Appointment::query()->where('status', AppointmentStatus::Scheduled)->where('source_key', 'like', 'anc:%')->count())->toBe(0)
        ->and(Appointment::query()->where('source_key', 'like', 'pnc:%')->count())->toBeGreaterThanOrEqual(3)
        ->and($baby->appointments()->where('source_key', 'like', 'epi:%')->count())->toBeGreaterThan(0);

    // A second delivery for the same pregnancy is refused.
    $this->post(route('pregnancies.delivery.store', $pregnancy), [
        'delivery_date' => today()->toDateString(), 'place_type' => 'home', 'attendant_type' => 'hilot', 'delivery_mode' => 'normal',
        'live_births' => 1, 'stillbirths' => 0,
    ])->assertSessionHasErrors('pregnancy');
});

test('a newborn\'s EPI doses fall due on the DOH schedule by age', function () {
    $child = residentInPurok('Purok 1', ['birthdate' => today()->subDays(20)]);
    $due = fn (int $days) => today()->subDays(20)->addDays($days)->toDateString();

    $schedule = app(ImmunizationSchedule::class)->forResident($child->load('immunizations'))
        ->mapWithKeys(fn (array $d) => ["{$d['vaccine_code']}-{$d['dose_no']}" => [$d['due_date'], $d['status']]]);

    // Same-day doses may come in any order, so compare by key.
    expect($schedule->all())->toEqual([
        'BCG-1' => [$due(0), 'due'],
        'HEPB-1' => [$due(0), 'due'],
        'PENTA-1' => [$due(42), 'upcoming'],
        'OPV-1' => [$due(42), 'upcoming'],
        'PCV-1' => [$due(42), 'upcoming'],
        'PENTA-2' => [$due(70), 'upcoming'],
        'OPV-2' => [$due(70), 'upcoming'],
        'PCV-2' => [$due(70), 'upcoming'],
        'PENTA-3' => [$due(98), 'upcoming'],
        'OPV-3' => [$due(98), 'upcoming'],
        'IPV-1' => [$due(98), 'upcoming'],
        'PCV-3' => [$due(98), 'upcoming'],
        'IPV-2' => [$due(270), 'upcoming'],
        'MMR-1' => [$due(270), 'upcoming'],
        'MMR-2' => [$due(365), 'upcoming'],
    ]);

    // Four weeks past the due date a dose turns overdue.
    $older = residentInPurok('Purok 1', ['birthdate' => today()->subDays(29)]);
    expect(app(ImmunizationSchedule::class)->forResident($older->load('immunizations'))->firstWhere('vaccine_code', 'BCG')['status'])->toBe('overdue');
});

test('a late first dose pushes the next dose back by the minimum interval', function () {
    $child = residentInPurok('Purok 1', ['birthdate' => today()->subDays(120)]);
    $penta = Vaccine::query()->where('code', 'PENTA')->sole();
    $child->immunizations()->create(['vaccine_id' => $penta->id, 'dose_no' => 1, 'date_given' => today()->subDays(10), 'given_by' => actingAsRole(RoleName::Midwife)->id]);

    $dose2 = app(ImmunizationSchedule::class)->forResident($child->load('immunizations'))
        ->first(fn (array $d) => $d['vaccine_code'] === 'PENTA' && $d['dose_no'] === 2);

    // Due at 70 days by age, but only 28 days after dose 1 (given 10 days ago).
    expect($dose2['due_date'])->toBe(today()->addDays(18)->toDateString())
        ->and($dose2['status'])->toBe('upcoming');
});

test('overdue EPI doses are booked on the next EPI day; missed ones are re-booked', function () {
    $child = residentInPurok('Purok 1', ['birthdate' => today()->subDays(150)]);
    $generator = app(AppointmentGenerator::class);
    $generator->syncChild($child);

    $epiDay = $generator->nextEpiDay(CarbonImmutable::today())->toDateString();
    $bcg = $child->appointments()->where('source_key', 'epi:BCG:1')->sole();
    expect($bcg->scheduled_at->toDateString())->toBe($epiDay);

    // Not attended → marked missed the day after → re-booked on the following EPI day.
    $later = CarbonImmutable::parse($epiDay)->addDay();
    $generator->markMissed($later);
    expect($bcg->fresh()->status)->toBe(AppointmentStatus::Missed);

    $generator->syncChild($child->fresh(), $later);
    expect($bcg->fresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and($bcg->fresh()->scheduled_at->toDateString())->toBe($generator->nextEpiDay($later)->toDateString());
});

test('vitamin A is due every 6 months from 6 months of age', function () {
    $child = residentInPurok('Purok 1', ['birthdate' => today()->subMonths(13)]);
    $schedule = app(MicronutrientSchedule::class);

    expect($schedule->vitaminADue($child->load('micronutrientRecords')))->toBeTrue();

    $child->micronutrientRecords()->create(['type' => 'vitamin_a', 'dose' => '200,000 IU', 'date_given' => today()->subMonth(), 'given_by' => User::factory()->create()->id]);

    $status = $schedule->forChild($child->fresh()->load('micronutrientRecords'));
    expect($status['vitamin_a']['due'])->toBeFalse()
        ->and($status['vitamin_a']['due_date'])->toBe(today()->subMonth()->addMonths(6)->toDateString())
        ->and($status['deworming']['due'])->toBeTrue();
});

test('growth measurements are one per child per day and need health.record', function () {
    $child = residentInPurok('Purok 1', ['birthdate' => today()->subMonths(8)]);
    $payload = ['measured_on' => today()->toDateString(), 'weight_kg' => 7.4, 'height_cm' => 68, 'nutritional_status' => 'normal'];

    actingAsRole(RoleName::Official);
    $this->post(route('residents.growth.store', $child), $payload)->assertForbidden();

    actingAsRole(RoleName::Bhw);
    $this->post(route('residents.growth.store', $child), $payload)->assertSessionHasNoErrors();
    $this->post(route('residents.growth.store', $child), $payload)->assertSessionHasErrors('measured_on');
});
