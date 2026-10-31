<?php

use App\Enums\RoleName;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedReferenceData();
    actingAsRole(RoleName::Bhw);

    $purok1 = Purok::query()->where('name', 'Purok 1')->sole();
    $purok2 = Purok::query()->where('name', 'Purok 2')->sole();

    $this->juan = Resident::factory()->for(Household::factory()->for($purok1))->create([
        'last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'sex' => 'male', 'birthdate' => now()->subYears(70),
    ]);
    $this->maria = Resident::factory()->for(Household::factory()->for($purok2))->create([
        'last_name' => 'Santos', 'first_name' => 'Maria', 'sex' => 'female', 'birthdate' => now()->subYears(30),
    ]);
    $this->rosa = Resident::factory()->for(Household::factory()->for($purok2))->create([
        'last_name' => 'Delos Reyes', 'first_name' => 'Rosa', 'sex' => 'female', 'birthdate' => now()->subMonths(5),
    ]);
});

function searchResidents(array $query): array
{
    $codes = [];

    test()->get(route('residents.index', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$codes) {
            $page->component('residents/index');
            $codes = collect($page->toArray()['props']['residents']['data'])->pluck('resident_code')->sort()->values()->all();
        });

    return $codes;
}

test('partial name search matches the start of last or first names', function () {
    expect(searchResidents(['search' => 'del']))->toBe(collect([$this->juan, $this->rosa])->pluck('resident_code')->sort()->values()->all())
        ->and(searchResidents(['search' => 'mar']))->toBe([$this->maria->resident_code])
        ->and(searchResidents(['search' => 'Dela Cruz, Ju']))->toBe([$this->juan->resident_code]);
});

test('search by resident code, household number and birthdate', function () {
    expect(searchResidents(['search' => strtolower($this->maria->resident_code)]))->toBe([$this->maria->resident_code])
        ->and(searchResidents(['search' => $this->rosa->household->household_no]))->toBe([$this->rosa->resident_code])
        ->and(searchResidents(['search' => $this->juan->birthdate->toDateString()]))->toBe([$this->juan->resident_code]);
});

test('filters by purok, sex and age group', function () {
    $purok2 = Purok::query()->where('name', 'Purok 2')->value('id');

    expect(searchResidents(['purok_id' => $purok2]))->toHaveCount(2)
        ->and(searchResidents(['purok_id' => $purok2, 'sex' => 'female', 'age_group' => 'infant']))->toBe([$this->rosa->resident_code])
        ->and(searchResidents(['age_group' => 'senior']))->toBe([$this->juan->resident_code]);
});

test('results are paginated on the server and filters stay in the query string', function () {
    Resident::factory()->count(20)->create();

    $this->get(route('residents.index', ['per_page' => 10, 'page' => 2, 'sort' => 'birthdate', 'direction' => 'desc']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('residents.data', 10)
            ->where('residents.meta.current_page', 2)
            ->where('residents.meta.total', 23)
            ->where('filters.sort', 'birthdate')
            ->where('filters.direction', 'desc'));
});

test('unsafe sort columns are rejected', function () {
    $this->get(route('residents.index', ['sort' => 'philhealth_no']))->assertSessionHasErrors('sort');
});

test('archived residents are hidden unless the archive filter is on', function () {
    $this->maria->delete();

    expect(searchResidents([]))->not->toContain($this->maria->resident_code)
        ->and(searchResidents(['archived' => 1]))->toBe([$this->maria->resident_code]);
});
