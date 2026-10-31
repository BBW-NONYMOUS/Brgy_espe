<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Purok;
use Database\Seeders\ResidentSeeder;
use Database\Seeders\UserSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedReferenceData();
    $this->seed([UserSeeder::class, ResidentSeeder::class]);
});

test('report totals match the sample barangay', function () {
    actingAsRole(RoleName::Bhw);

    $this->get(route('reports.index'))->assertInertia(fn (Assert $page) => $page
        ->component('reports/index')
        ->loadDeferredProps('summary', fn (Assert $reload) => $reload
            ->where('summary.totals.residents', 50)
            ->where('summary.totals.households', 14)
            ->where('summary.totals.seniors', 10)
            ->where('summary.totals.pwd', 5)
            ->where('summary.totals.pregnant', 3)
            ->where('summary.totals.children_due', 3)
            ->has('summary.population', 7)
            ->has('summary.age_sex', 12)));
});

test('health workers receive named lists; barangay officials get aggregates only', function () {
    actingAsRole(RoleName::Midwife);
    $this->get(route('reports.index'))->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps('lists', fn (Assert $reload) => $reload->has('lists.children_due', 3)->has('lists.pregnant', 3)));

    actingAsRole(RoleName::Official);
    $this->get(route('reports.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('lists', null));
});

test('filtering by purok narrows the totals', function () {
    actingAsRole(RoleName::Bhw);
    $purok1 = Purok::query()->where('name', 'Purok 1')->value('id');

    $this->get(route('reports.index', ['purok_id' => $purok1]))->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps('summary', fn (Assert $reload) => $reload
            ->where('summary.purok', 'Purok 1')
            ->where('summary.totals.residents', 10)
            ->has('summary.population', 1)));
});

test('reports export as PDF, Excel and CSV, and each export is audit-logged', function (string $format, string $contentType) {
    actingAsRole(RoleName::Bhw);

    $response = $this->get(route('reports.export', ['format' => $format]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain($contentType)
        ->and(AuditLog::query()->where('action', AuditAction::Exported)->exists())->toBeTrue();
})->with([
    'pdf' => ['pdf', 'application/pdf'],
    'xlsx' => ['xlsx', 'spreadsheetml'],
    'csv' => ['csv', 'text/csv'],
]);

test('an official\'s export contains no resident names', function () {
    actingAsRole(RoleName::Official);

    $response = $this->get(route('reports.export', ['format' => 'csv']));
    $csv = $response->streamedContent() ?: file_get_contents($response->baseResponse->getFile()->getPathname());

    expect($csv)->toContain('POPULATION PER PUROK')
        ->not->toContain('CHILDREN DUE FOR IMMUNIZATION')
        ->not->toContain('Gallardo');

    expect(AuditLog::query()->where('action', AuditAction::Exported)->value('description'))->toContain('aggregates only');
});

test('report date ranges are validated', function () {
    actingAsRole(RoleName::Bhw);

    $this->get(route('reports.index', ['date_from' => '2026-05-01', 'date_to' => '2026-01-01']))->assertSessionHasErrors('date_to');
});
