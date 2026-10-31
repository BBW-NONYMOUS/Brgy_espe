<?php

namespace App\Services;

use App\Enums\CaseClassification;
use App\Enums\CaseOutcome;
use App\Enums\MaternalStatus;
use App\Models\DiseaseCase;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data for the household map.
 *
 * Exact markers (health staff only) are compact arrays, not resources, and
 * the four health flags come from four grouped queries over indexed columns
 * instead of correlated sub-queries per household.
 * Officials get purok-level aggregates drawn at the Admin-set purok centre.
 */
class HouseholdMapService
{
    public const FLAGS = ['pregnant', 'senior', 'active_case', 'pwd', 'four_ps'];

    /**
     * @return array<int, array{id: int, household_no: string, lat: float, lng: float, purok_id: int, purok: string, head: string|null, members: int, flags: array<int, string>}>
     */
    public function markers(?int $purokId = null, ?string $flag = null): array
    {
        $households = Household::query()
            ->geotagged()
            ->with(['purok:id,name', 'head:id,last_name,first_name,middle_name,suffix'])
            ->withCount('members')
            ->when($purokId, fn ($q, int $id) => $q->where('purok_id', $id))
            ->get(['id', 'household_no', 'purok_id', 'head_resident_id', 'latitude', 'longitude', 'is_4ps']);

        $flags = $this->flagSets();

        return $households
            ->map(function (Household $h) use ($flags) {
                $set = collect(self::FLAGS)
                    ->filter(fn (string $f) => $f === 'four_ps' ? $h->is_4ps : $flags[$f]->has($h->id))
                    ->values()
                    ->all();

                return [
                    'id' => $h->id,
                    'household_no' => $h->household_no,
                    'lat' => (float) $h->latitude,
                    'lng' => (float) $h->longitude,
                    'purok_id' => $h->purok_id,
                    'purok' => $h->purok->name,
                    'head' => $h->head?->full_name,
                    'members' => (int) $h->getAttribute('members_count'),
                    'flags' => $set,
                ];
            })
            ->filter(fn (array $m) => $flag === null || in_array($flag, $m['flags'], true))
            ->values()
            ->all();
    }

    /**
     * Households still to geo-tag (the BHW's field to-do list).
     *
     * @return array<int, array{id: int, household_no: string, purok: string, address: string}>
     */
    public function notGeotagged(?int $purokId = null): array
    {
        return Household::query()
            ->with('purok:id,name')
            ->whereNull('latitude')
            ->when($purokId, fn ($q, int $id) => $q->where('purok_id', $id))
            ->orderBy('household_no')
            ->limit(100)
            ->get()
            ->map(fn (Household $h) => ['id' => $h->id, 'household_no' => $h->household_no, 'purok' => $h->purok->name, 'address' => $h->address])
            ->all();
    }

    /**
     * Purok-level counts for Barangay Officials (and the disease map): never
     * a household, never a name.
     *
     * @return array<int, array{id: int, name: string, lat: float|null, lng: float|null, households: int, residents: int, cases: int, cases_by_disease: array<string, int>}>
     */
    public function purokSummary(int $days = 30, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $from = $today->subDays($days - 1)->toDateString();

        $cases = DiseaseCase::query()
            ->countable()
            ->join('diseases', 'diseases.id', '=', 'disease_cases.disease_id')
            ->where('onset_date', '>=', $from)
            ->groupBy('disease_cases.purok_id', 'diseases.name')
            ->get([DB::raw('disease_cases.purok_id AS purok_id'), DB::raw('diseases.name AS disease'), DB::raw('COUNT(*) AS cases')])
            ->groupBy('purok_id');

        $residents = Resident::query()
            ->join('households', 'households.id', '=', 'residents.household_id')
            ->groupBy('households.purok_id')
            ->selectRaw('households.purok_id AS purok_id, COUNT(*) AS residents')
            ->pluck('residents', 'purok_id');

        return Purok::query()
            ->withCount('households')
            ->orderBy('sort_order')
            ->get()
            ->map(function (Purok $p) use ($cases, $residents) {
                /** @var Collection<int, DiseaseCase> $rows */
                $rows = $cases->get($p->id, collect());

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'lat' => $p->center_latitude !== null ? (float) $p->center_latitude : null,
                    'lng' => $p->center_longitude !== null ? (float) $p->center_longitude : null,
                    'households' => (int) $p->getAttribute('households_count'),
                    'residents' => (int) ($residents[$p->id] ?? 0),
                    'cases' => (int) $rows->sum(fn (DiseaseCase $r) => (int) $r->getAttribute('cases')),
                    'cases_by_disease' => $rows->mapWithKeys(fn (DiseaseCase $r) => [(string) $r->getAttribute('disease') => (int) $r->getAttribute('cases')])->all(),
                ];
            })
            ->all();
    }

    /**
     * Household IDs per flag.
     *
     * @return array<string, Collection<int, bool>>
     */
    private function flagSets(): array
    {
        $ids = fn ($query) => $query->distinct()->pluck('residents.household_id')->flip()->map(fn () => true);

        return [
            'pregnant' => $ids(Resident::query()
                ->join('pregnancies', 'pregnancies.resident_id', '=', 'residents.id')
                ->where('pregnancies.status', MaternalStatus::Ongoing)
                ->whereNull('pregnancies.deleted_at')),
            'senior' => $ids(Resident::query()->seniors()),
            'active_case' => $ids(Resident::query()
                ->join('disease_cases', 'disease_cases.resident_id', '=', 'residents.id')
                ->where('disease_cases.outcome', CaseOutcome::Ongoing)
                ->where('disease_cases.classification', '!=', CaseClassification::RuledOut)
                ->whereNull('disease_cases.deleted_at')),
            'pwd' => $ids(Resident::query()->where('is_pwd', true)),
        ];
    }
}
