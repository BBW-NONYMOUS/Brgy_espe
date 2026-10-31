<?php

namespace App\Services;

use App\Enums\ConditionStatus;
use App\Enums\MaternalStatus;
use App\Enums\ResidentStatus;
use App\Enums\Sex;
use App\Models\Household;
use App\Models\Pregnancy;
use App\Models\Purok;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Aggregated health center reports.
 *
 * summary() returns COUNTS ONLY and is safe for Barangay Officials.
 * lists() returns named residents and must only be called for users with
 * the residents.view permission.
 *
 * All age math is done in PHP (boundary dates passed as bindings) so the
 * same queries run on MySQL and on SQLite in tests, and stay sargable.
 */
class ReportService
{
    /** Age brackets used in the age/sex pyramid: [label, min age, max age|null]. */
    public const AGE_BRACKETS = [
        ['0–11 months', 0, 0],
        ['1–4', 1, 4],
        ['5–9', 5, 9],
        ['10–14', 10, 14],
        ['15–19', 15, 19],
        ['20–29', 20, 29],
        ['30–39', 30, 39],
        ['40–49', 40, 49],
        ['50–59', 50, 59],
        ['60–69', 60, 69],
        ['70–79', 70, 79],
        ['80+', 80, null],
    ];

    public function __construct(private readonly ImmunizationSchedule $schedule) {}

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, purok_id?: int|string|null}  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $purokId = ($filters['purok_id'] ?? null) ?: null;

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'purok' => $purokId ? Purok::query()->whereKey($purokId)->value('name') : null,
            'totals' => $this->totals($purokId),
            'population' => $this->populationPerPurok($purokId),
            'age_sex' => $this->ageSexDistribution($purokId),
            'conditions' => $this->commonConditions($purokId),
            'visits' => $this->visitsByType($from, $to, $purokId),
            'immunizations' => $this->immunizationsGiven($from, $to, $purokId),
        ];
    }

    /**
     * Named line lists for health workers (never for Barangay Officials).
     *
     * @param  array{purok_id?: int|string|null}  $filters
     * @return array{children_due: array<int, array<string, mixed>>, pregnant: array<int, array<string, mixed>>}
     */
    public function lists(array $filters): array
    {
        $purokId = ($filters['purok_id'] ?? null) ?: null;

        return [
            'children_due' => $this->childrenDue($purokId),
            'pregnant' => $this->pregnantWomen($purokId),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function totals(int|string|null $purokId = null): array
    {
        $base = fn () => $this->activeResidents($purokId);
        $seniorCutoff = today()->subYears(Resident::SENIOR_AGE)->toDateString();

        return [
            'residents' => $base()->count(),
            'households' => Household::query()->when($purokId, fn (Builder $q) => $q->where('purok_id', $purokId))->count(),
            'male' => $base()->where('sex', Sex::Male)->count(),
            'female' => $base()->where('sex', Sex::Female)->count(),
            'seniors' => $base()->where('birthdate', '<=', $seniorCutoff)->count(),
            'pwd' => $base()->where('is_pwd', true)->count(),
            // 4Ps is a household program: members of 4Ps households.
            'four_ps' => $base()->whereHas('household', fn (Builder $h) => $h->where('is_4ps', true))->count(),
            'infants' => $base()->where('birthdate', '>', today()->subYear()->toDateString())->count(),
            'pregnant' => $base()->pregnant()->count(),
            'children_due' => $this->childrenDueQuery($purokId)->get()
                ->filter(fn (Resident $r) => $this->schedule->pendingFor($r)->isNotEmpty())
                ->count(),
        ];
    }

    /**
     * @return array<int, array{purok: string, households: int, residents: int, male: int, female: int}>
     */
    public function populationPerPurok(int|string|null $purokId = null): array
    {
        return DB::table('puroks')
            ->leftJoin('households', 'households.purok_id', '=', 'puroks.id')
            ->leftJoin('residents', function ($join) {
                $join->on('residents.household_id', '=', 'households.id')
                    ->whereNull('residents.deleted_at')
                    ->where('residents.status', ResidentStatus::Active->value);
            })
            ->when($purokId, fn (QueryBuilder $q) => $q->where('puroks.id', $purokId))
            ->groupBy('puroks.id', 'puroks.name', 'puroks.sort_order')
            ->orderBy('puroks.sort_order')
            ->selectRaw('puroks.name as purok')
            ->selectRaw('COUNT(DISTINCT households.id) as households')
            ->selectRaw('COUNT(residents.id) as residents')
            ->selectRaw("SUM(CASE WHEN residents.sex = 'male' THEN 1 ELSE 0 END) as male")
            ->selectRaw("SUM(CASE WHEN residents.sex = 'female' THEN 1 ELSE 0 END) as female")
            ->get()
            ->map(fn ($row) => [
                'purok' => (string) $row->purok,
                'households' => (int) $row->households,
                'residents' => (int) $row->residents,
                'male' => (int) $row->male,
                'female' => (int) $row->female,
            ])
            ->all();
    }

    /**
     * @return array<int, array{age_group: string, male: int, female: int, total: int}>
     */
    public function ageSexDistribution(int|string|null $purokId = null): array
    {
        $today = today();
        $cases = [];
        $bindings = [];

        // birthdate > (today - (max+1) years) AND birthdate <= (today - min years)
        foreach (self::AGE_BRACKETS as [$label, $min, $max]) {
            $condition = 'birthdate <= ?';
            $bindings[] = $today->subYears($min)->toDateString();

            if ($max !== null) {
                $condition .= ' AND birthdate > ?';
                $bindings[] = $today->subYears($max + 1)->toDateString();
            }

            $cases[] = "WHEN {$condition} THEN ?";
            $bindings[] = $label;
        }

        $rows = $this->activeResidents($purokId)
            ->toBase()
            ->selectRaw('CASE '.implode(' ', $cases).' END as age_group', $bindings)
            ->addSelect('sex')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('age_group', 'sex')
            ->get();

        return collect(self::AGE_BRACKETS)
            ->map(function (array $bracket) use ($rows) {
                $label = $bracket[0];
                $male = (int) $rows->where('age_group', $label)->where('sex', Sex::Male->value)->sum('total');
                $female = (int) $rows->where('age_group', $label)->where('sex', Sex::Female->value)->sum('total');

                return ['age_group' => $label, 'male' => $male, 'female' => $female, 'total' => $male + $female];
            })
            ->all();
    }

    /**
     * @return array<int, array{condition: string, icd10_code: string|null, residents: int}>
     */
    public function commonConditions(int|string|null $purokId = null, int $limit = 10): array
    {
        return DB::table('resident_conditions')
            ->join('conditions', 'conditions.id', '=', 'resident_conditions.condition_id')
            ->join('residents', 'residents.id', '=', 'resident_conditions.resident_id')
            ->join('households', 'households.id', '=', 'residents.household_id')
            ->whereNull('resident_conditions.deleted_at')
            ->whereNull('residents.deleted_at')
            ->whereIn('resident_conditions.status', [ConditionStatus::Active->value, ConditionStatus::Controlled->value])
            ->when($purokId, fn (QueryBuilder $q) => $q->where('households.purok_id', $purokId))
            ->groupBy('conditions.id', 'conditions.name', 'conditions.icd10_code')
            ->selectRaw('conditions.name as `condition`, conditions.icd10_code, COUNT(DISTINCT resident_conditions.resident_id) as residents')
            ->orderByDesc('residents')
            ->orderBy('conditions.name')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'condition' => (string) $row->condition,
                'icd10_code' => $row->icd10_code !== null ? (string) $row->icd10_code : null,
                'residents' => (int) $row->residents,
            ])
            ->all();
    }

    /**
     * @return array<int, array{visit_type: string, visits: int}>
     */
    public function visitsByType(CarbonImmutable $from, CarbonImmutable $to, int|string|null $purokId = null): array
    {
        return DB::table('health_visits')
            ->join('visit_types', 'visit_types.id', '=', 'health_visits.visit_type_id')
            ->join('residents', 'residents.id', '=', 'health_visits.resident_id')
            ->join('households', 'households.id', '=', 'residents.household_id')
            ->whereNull('health_visits.deleted_at')
            ->whereBetween('health_visits.visit_date', [$from->toDateString(), $to->toDateString()])
            ->when($purokId, fn (QueryBuilder $q) => $q->where('households.purok_id', $purokId))
            ->groupBy('visit_types.id', 'visit_types.name')
            ->selectRaw('visit_types.name as visit_type, COUNT(*) as visits')
            ->orderByDesc('visits')
            ->get()
            ->map(fn ($row) => ['visit_type' => (string) $row->visit_type, 'visits' => (int) $row->visits])
            ->all();
    }

    /**
     * @return array<int, array{vaccine: string, doses: int}>
     */
    public function immunizationsGiven(CarbonImmutable $from, CarbonImmutable $to, int|string|null $purokId = null): array
    {
        return DB::table('immunizations')
            ->join('vaccines', 'vaccines.id', '=', 'immunizations.vaccine_id')
            ->join('residents', 'residents.id', '=', 'immunizations.resident_id')
            ->join('households', 'households.id', '=', 'residents.household_id')
            ->whereNull('immunizations.deleted_at')
            ->whereBetween('immunizations.date_given', [$from->toDateString(), $to->toDateString()])
            ->when($purokId, fn (QueryBuilder $q) => $q->where('households.purok_id', $purokId))
            ->groupBy('vaccines.id', 'vaccines.code')
            ->selectRaw('vaccines.code as vaccine, COUNT(*) as doses')
            ->orderBy('vaccines.code')
            ->get()
            ->map(fn ($row) => ['vaccine' => (string) $row->vaccine, 'doses' => (int) $row->doses])
            ->all();
    }

    /**
     * @return array<int, array{resident_id: int, resident_code: string, name: string, birthdate: string, age_months: int, purok: string, household_no: string, pending: array<int, string>, earliest_due: string, overdue: bool}>
     */
    public function childrenDue(int|string|null $purokId = null): array
    {
        return $this->childrenDueQuery($purokId)
            ->get()
            ->map(function (Resident $child) {
                $pending = $this->schedule->pendingFor($child);

                if ($pending->isEmpty()) {
                    return null;
                }

                return [
                    'resident_id' => $child->id,
                    'resident_code' => $child->resident_code,
                    'name' => $child->full_name,
                    'birthdate' => $child->birthdate->toDateString(),
                    'age_months' => (int) $child->birthdate->diffInMonths(today()),
                    'purok' => $child->household->purok->name,
                    'household_no' => $child->household->household_no,
                    'pending' => $pending->map(fn (array $d) => "{$d['vaccine_code']} {$d['dose_no']}")->values()->all(),
                    'earliest_due' => (string) $pending->min('due_date'),
                    'overdue' => $pending->contains('status', 'overdue'),
                ];
            })
            ->filter()
            ->sortBy('earliest_due')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{resident_id: int, resident_code: string, name: string, age: int, purok: string, edd: string, aog_weeks: int|null, gravida: int, para: int, risk_level: string}>
     */
    public function pregnantWomen(int|string|null $purokId = null): array
    {
        return Pregnancy::query()
            ->with('resident.household.purok')
            ->where('status', MaternalStatus::Ongoing)
            ->whereHas('resident', fn (Builder $q) => $q
                ->when($purokId, fn (Builder $r) => $r->whereHas('household', fn (Builder $h) => $h->where('purok_id', $purokId))))
            ->orderBy('edd')
            ->get()
            ->map(fn (Pregnancy $record) => [
                'resident_id' => $record->resident->id,
                'resident_code' => $record->resident->resident_code,
                'name' => $record->resident->full_name,
                'age' => $record->resident->age,
                'purok' => $record->resident->household->purok->name,
                'edd' => $record->edd->toDateString(),
                'aog_weeks' => $record->gestationalAgeWeeks(),
                'gravida' => $record->gravida,
                'para' => $record->para,
                'risk_level' => $record->risk_level->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null}  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(array $filters): array
    {
        $to = filled($filters['date_to'] ?? null) ? CarbonImmutable::parse($filters['date_to']) : today();
        $from = filled($filters['date_from'] ?? null) ? CarbonImmutable::parse($filters['date_from']) : $to->startOfYear();

        return [$from->startOfDay(), $to->startOfDay()];
    }

    /**
     * @return Builder<Resident>
     */
    private function activeResidents(int|string|null $purokId): Builder
    {
        return Resident::query()
            ->where('status', ResidentStatus::Active)
            ->when($purokId, fn (Builder $q) => $q->whereHas('household', fn (Builder $h) => $h->where('purok_id', $purokId)));
    }

    /**
     * @return Builder<Resident>
     */
    private function childrenDueQuery(int|string|null $purokId): Builder
    {
        $oldest = today()->subMonths(config('barangay.epi_max_age_months') + 1)->toDateString();

        return $this->activeResidents($purokId)
            ->with(['immunizations', 'household.purok'])
            ->where('birthdate', '>', $oldest)
            ->orderBy('birthdate');
    }
}
