<?php

use App\Enums\RoleName;
use App\Models\HealthVisit;
use App\Models\VisitType;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedReferenceData();
});

test('health roles can list visits; officials cannot', function (RoleName $role, bool $allowed) {
    actingAsRole($role);

    $response = $this->get(route('visits.index'));

    $allowed
        ? $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('health/visits'))
        : $response->assertForbidden();
})->with([
    'admin' => [RoleName::Admin, true],
    'bhw' => [RoleName::Bhw, true],
    'midwife' => [RoleName::Midwife, true],
    'official' => [RoleName::Official, false],
]);

test('the list never carries the complaint, diagnosis or notes', function () {
    $resident = residentInPurok('Purok 2');
    HealthVisit::factory()->for($resident)->create([
        'chief_complaint' => 'Severe headache',
        'diagnosis' => 'Essential hypertension',
        'notes' => 'Advised low-salt diet',
    ]);

    actingAsRole(RoleName::Midwife);

    $response = $this->get(route('visits.index'));
    $response->assertInertia(fn (Assert $page) => $page
        ->has('visits.data', 1)
        ->where('visits.data.0.resident.id', $resident->id)
        ->where('visits.data.0.purok', 'Purok 2')
        ->missing('visits.data.0.diagnosis')
        ->missing('visits.data.0.chief_complaint')
        ->missing('visits.data.0.notes'));

    expect($response->getContent())
        ->not->toContain('Essential hypertension')
        ->not->toContain('Severe headache')
        ->not->toContain('low-salt');
});

test('visits filter by visit type, purok, date range and attending staff', function () {
    $me = actingAsRole(RoleName::Bhw);
    $prenatal = VisitType::query()->where('name', 'Prenatal Check-up')->value('id');
    $consult = VisitType::query()->where('name', 'General Consultation')->value('id');

    $mine = HealthVisit::factory()->for(residentInPurok('Purok 1'))->create([
        'visit_type_id' => $prenatal, 'visit_date' => today()->subDays(3), 'attended_by' => $me->id,
    ]);
    HealthVisit::factory()->for(residentInPurok('Purok 4'))->create([
        'visit_type_id' => $consult, 'visit_date' => today()->subDays(40),
    ]);

    $ids = fn (array $query) => collect($this->get(route('visits.index', $query))->viewData('page')['props']['visits']['data'])->pluck('id')->all();

    expect($ids([]))->toHaveCount(2)
        ->and($ids(['visit_type_id' => $prenatal]))->toBe([$mine->id])
        ->and($ids(['purok_id' => $mine->resident->household->purok_id]))->toBe([$mine->id])
        ->and($ids(['date_from' => today()->subDays(7)->toDateString()]))->toBe([$mine->id])
        ->and($ids(['mine' => 1]))->toBe([$mine->id]);
});
