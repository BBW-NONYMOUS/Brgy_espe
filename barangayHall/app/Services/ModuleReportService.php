<?php

namespace App\Services;

use App\Enums\CaseClassification;
use App\Enums\CaseOutcome;
use App\Enums\MaternalStatus;
use App\Enums\MicronutrientType;
use App\Enums\NutritionalStatus;
use App\Enums\PermissionName;
use App\Enums\ProgramStatus;
use App\Enums\ReferralStatus;
use App\Enums\ReferralUrgency;
use App\Enums\RiskLevel;
use App\Enums\StockMovementType;
use App\Models\Delivery;
use App\Models\DiseaseCase;
use App\Models\GrowthMeasurement;
use App\Models\HealthProgram;
use App\Models\Medicine;
use App\Models\MicronutrientRecord;
use App\Models\OutbreakAlert;
use App\Models\Pregnancy;
use App\Models\PrenatalVisit;
use App\Models\Purok;
use App\Models\Referral;
use App\Models\Resident;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Module reports (featured.md: disease cases, referrals, program attendance,
 * medicine stock, maternal & child). Every report has the same shape so the
 * page, the PDF and the Excel/CSV export render any of them generically:
 *
 *   totals: [{label, value, hint?}]
 *   tables: [{key, title, description?, columns: [{key, label, numeric?}],
 *             rows: [...], named: bool, chart?: {label, values}}]
 *
 * Tables marked `named` list residents and are only built for roles allowed
 * to see them; everything else is counts, safe for Barangay Officials.
 */
class ModuleReportService
{
    public const REPORTS = [
        'diseases' => 'Disease cases',
        'referrals' => 'Referrals',
        'programs' => 'Program attendance',
        'inventory' => 'Medicine stock',
        'maternal_child' => 'Maternal & child health',
    ];

    public function __construct(private readonly ReportService $reports) {}

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, purok_id?: int|string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(string $report, array $filters, User $user): array
    {
        [$from, $to] = $this->reports->period($filters);
        $purokId = ($filters['purok_id'] ?? null) ? (int) $filters['purok_id'] : null;
        $until = $to->addDay(); // exclusive upper bound (works for DATE and DATETIME columns)

        $body = match ($report) {
            'diseases' => $this->diseases($from, $until, $purokId, $user->hasPermission(PermissionName::DiseasesView)),
            'referrals' => $this->referrals($from, $until, $purokId, $user->hasPermission(PermissionName::HealthViewSensitive)),
            'programs' => $this->programs($from, $until, $purokId),
            'inventory' => $this->inventory($from, $until),
            'maternal_child' => $this->maternalChild($from, $until, $purokId, $user->hasPermission(PermissionName::HealthViewSensitive)),
            default => throw new InvalidArgumentException("Unknown report [{$report}]."),
        };

        return [
            'key' => $report,
            'title' => self::REPORTS[$report],
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'purok' => $purokId ? Purok::query()->whereKey($purokId)->value('name') : null,
            ...$body,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function diseases(CarbonImmutable $from, CarbonImmutable $until, ?int $purokId, bool $named): array
    {
        $cases = DiseaseCase::query()
            ->with(['disease:id,name,pidsr_category', 'purok:id,name'])
            ->where('onset_date', '>=', $from->toDateString())
            ->where('onset_date', '<', $until->toDateString())
            ->when($purokId, fn (Builder $q, int $id) => $q->where('purok_id', $id))
            ->orderByDesc('onset_date')
            ->get();

        $countable = $cases->where('classification', '!=', CaseClassification::RuledOut);

        $byDisease = $cases->groupBy('disease_id')->map(fn (Collection $g) => [
            'disease' => $g->first()->disease->name,
            'category' => $g->first()->disease->pidsr_category->label(),
            'cases' => $g->where('classification', '!=', CaseClassification::RuledOut)->count(),
            'confirmed' => $g->where('classification', CaseClassification::Confirmed)->count(),
            'ruled_out' => $g->where('classification', CaseClassification::RuledOut)->count(),
            'deaths' => $g->where('outcome', CaseOutcome::Died)->count(),
        ])->sortByDesc('cases')->values()->all();

        $byPurok = Purok::query()->orderBy('sort_order')->get(['id', 'name'])
            ->when($purokId, fn (Collection $p) => $p->where('id', $purokId))
            ->map(fn (Purok $p) => [
                'purok' => $p->name,
                'cases' => $countable->where('purok_id', $p->id)->count(),
                'active' => $countable->where('purok_id', $p->id)->where('outcome', CaseOutcome::Ongoing)->count(),
            ])->values()->all();

        $alerts = OutbreakAlert::query()
            ->with(['disease:id,name', 'purok:id,name'])
            ->where('created_at', '>=', $from->startOfDay())
            ->where('created_at', '<', $until->startOfDay())
            ->when($purokId, fn (Builder $q, int $id) => $q->where('purok_id', $id))
            ->latest('id')
            ->get()
            ->map(fn (OutbreakAlert $a) => [
                'disease' => $a->disease->name,
                'place' => $a->purok->name ?? 'Whole barangay',
                'cases' => $a->case_count,
                'period' => $a->period_start->format('M j').' – '.$a->period_end->format('M j, Y'),
                'status' => $a->status->label(),
            ])->all();

        $tables = [
            $this->table('by_disease', 'Cases by disease', [
                ['disease', 'Disease'], ['category', 'PIDSR category'], ['cases', 'Cases', true], ['confirmed', 'Confirmed', true],
                ['deaths', 'Deaths', true], ['ruled_out', 'Ruled out', true],
            ], $byDisease, chart: ['label' => 'disease', 'values' => ['cases']], description: 'Suspected, probable and confirmed cases; ruled-out cases are shown separately.'),
            $this->table('by_purok', 'Cases by purok', [['purok', 'Purok'], ['cases', 'Cases', true], ['active', 'Still ongoing', true]], $byPurok, chart: ['label' => 'purok', 'values' => ['cases']]),
            $this->table('alerts', 'Outbreak alerts raised', [['disease', 'Disease'], ['place', 'Place'], ['cases', 'Cases', true], ['period', 'Period'], ['status', 'Status']], $alerts, empty: 'No outbreak alerts in this period.'),
        ];

        if ($named) {
            $tables[] = $this->table('line_list', 'Case line list', [
                ['onset', 'Onset'], ['resident_code', 'Resident ID'], ['name', 'Name'], ['age', 'Age', true], ['purok', 'Purok'],
                ['disease', 'Disease'], ['classification', 'Classification'], ['outcome', 'Outcome'],
            ], $cases->load('resident')->map(fn (DiseaseCase $c) => [
                'onset' => $c->onset_date->toDateString(),
                'resident_code' => $c->resident->resident_code,
                'name' => $c->resident->full_name,
                'age' => $c->resident->age,
                'purok' => $c->purok->name,
                'disease' => $c->disease->name,
                'classification' => $c->classification->label(),
                'outcome' => $c->outcome->label(),
            ])->all(), named: true, description: 'PIDSR-style line listing for the RHU disease surveillance officer.');
        }

        return [
            'totals' => [
                ['label' => 'Cases', 'value' => $countable->count(), 'hint' => 'Suspected, probable and confirmed'],
                ['label' => 'Confirmed', 'value' => $cases->where('classification', CaseClassification::Confirmed)->count()],
                ['label' => 'Deaths', 'value' => $cases->where('outcome', CaseOutcome::Died)->count()],
                ['label' => 'Outbreak alerts', 'value' => count($alerts)],
            ],
            'tables' => $tables,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function referrals(CarbonImmutable $from, CarbonImmutable $until, ?int $purokId, bool $named): array
    {
        $referrals = Referral::query()
            ->with(['facility:id,name', 'resident.household'])
            ->where('referred_at', '>=', $from->startOfDay())
            ->where('referred_at', '<', $until->startOfDay())
            ->when($purokId, fn (Builder $q, int $id) => $q->whereHas('resident.household', fn (Builder $h) => $h->where('purok_id', $id)))
            ->latest('referred_at')
            ->get();

        $completed = $referrals->where('status', ReferralStatus::Completed);
        $avgDays = $completed->isEmpty() ? null : round($completed->avg(fn (Referral $r) => $r->referred_at->diffInDays($r->completed_at)), 1);

        $tables = [
            $this->table('by_status', 'By status', [['status', 'Status'], ['referrals', 'Referrals', true]],
                collect(ReferralStatus::cases())->map(fn (ReferralStatus $s) => ['status' => $s->label(), 'referrals' => $referrals->where('status', $s)->count()])->all(),
                chart: ['label' => 'status', 'values' => ['referrals']]),
            $this->table('by_facility', 'By facility', [['facility', 'Facility'], ['referrals', 'Referrals', true], ['completed', 'Completed', true]],
                $referrals->groupBy('facility_id')->map(fn (Collection $g) => [
                    'facility' => $g->first()->facility->name,
                    'referrals' => $g->count(),
                    'completed' => $g->where('status', ReferralStatus::Completed)->count(),
                ])->sortByDesc('referrals')->values()->all()),
            $this->table('by_urgency', 'By urgency', [['urgency', 'Urgency'], ['referrals', 'Referrals', true]],
                collect(ReferralUrgency::cases())->map(fn (ReferralUrgency $u) => ['urgency' => $u->label(), 'referrals' => $referrals->where('urgency', $u)->count()])->all()),
        ];

        if ($named) {
            // Reason and feedback are clinical text: never in reports, only on the referral page.
            $tables[] = $this->table('list', 'Referral list', [
                ['referral_no', 'Referral no.'], ['date', 'Referred'], ['name', 'Patient'], ['facility', 'Facility'], ['urgency', 'Urgency'], ['status', 'Status'],
            ], $referrals->map(fn (Referral $r) => [
                'referral_no' => $r->referral_no,
                'date' => $r->referred_at->toDateString(),
                'name' => $r->resident->full_name,
                'facility' => $r->facility->name,
                'urgency' => $r->urgency->label(),
                'status' => $r->status->label(),
            ])->all(), named: true);
        }

        return [
            'totals' => [
                ['label' => 'Referrals', 'value' => $referrals->count()],
                ['label' => 'Completed (return slip)', 'value' => $completed->count()],
                ['label' => 'Still open', 'value' => $referrals->whereIn('status', [ReferralStatus::Pending, ReferralStatus::Sent, ReferralStatus::Received])->count()],
                ['label' => 'Average days to complete', 'value' => $avgDays ?? '—'],
            ],
            'tables' => $tables,
        ];
    }

    /**
     * Never lists attendees by name (counts per program, type and purok only).
     *
     * @return array<string, mixed>
     */
    private function programs(CarbonImmutable $from, CarbonImmutable $until, ?int $purokId): array
    {
        $programs = HealthProgram::query()
            ->with(['type:id,name', 'puroks:id,name', 'attendance.resident.household.purok'])
            ->where('start_at', '>=', $from->startOfDay())
            ->where('start_at', '<', $until->startOfDay())
            ->when($purokId, fn (Builder $q, int $id) => $q->where(fn (Builder $w) => $w
                ->whereHas('puroks', fn (Builder $p) => $p->whereKey($id))
                ->orWhereDoesntHave('puroks')))
            ->orderBy('start_at')
            ->get();

        $attendanceIn = fn (HealthProgram $p) => $purokId
            ? $p->attendance->filter(fn ($a) => $a->resident?->household?->purok_id === $purokId)->count()
            : $p->attendance->count();

        $byPurok = Purok::query()->orderBy('sort_order')->get(['id', 'name'])
            ->when($purokId, fn (Collection $p) => $p->where('id', $purokId))
            ->map(fn (Purok $p) => [
                'purok' => $p->name,
                'attendees' => $programs->sum(fn (HealthProgram $prog) => $prog->attendance->filter(fn ($a) => $a->resident?->household?->purok_id === $p->id)->count()),
            ])->values()->all();

        return [
            'totals' => [
                ['label' => 'Programs held or scheduled', 'value' => $programs->count()],
                ['label' => 'Completed', 'value' => $programs->where('status', ProgramStatus::Completed)->count()],
                ['label' => 'Attendance (people)', 'value' => $programs->sum($attendanceIn)],
            ],
            'tables' => [
                $this->table('programs', 'Programs', [
                    ['date', 'Date'], ['title', 'Program'], ['type', 'Type'], ['target', 'Target group'], ['puroks', 'Puroks'], ['status', 'Status'], ['attended', 'Attended', true],
                ], $programs->map(fn (HealthProgram $p) => [
                    'date' => $p->start_at->toDateString(),
                    'title' => $p->title,
                    'type' => $p->type->name,
                    'target' => $p->target_group->label(),
                    'puroks' => $p->puroks->isEmpty() ? 'All puroks' : $p->puroks->pluck('name')->implode(', '),
                    'status' => $p->status->label(),
                    'attended' => $attendanceIn($p),
                ])->all(), empty: 'No programs in this period.'),
                $this->table('by_type', 'By program type', [['type', 'Type'], ['programs', 'Programs', true], ['attended', 'Attended', true]],
                    $programs->groupBy(fn (HealthProgram $p) => $p->type->name)->map(fn (Collection $g, string $type) => [
                        'type' => $type, 'programs' => $g->count(), 'attended' => $g->sum($attendanceIn),
                    ])->values()->all(), chart: ['label' => 'type', 'values' => ['attended']]),
                $this->table('by_purok', 'Attendance by purok', [['purok', 'Purok'], ['attendees', 'Attendees', true]], $byPurok, chart: ['label' => 'purok', 'values' => ['attendees']]),
            ],
        ];
    }

    /**
     * Medicine stock: current position plus movements in the period. Not
     * personal data (no residents), so the purok filter does not apply.
     *
     * @return array<string, mixed>
     */
    private function inventory(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $today = CarbonImmutable::today();

        $medicines = Medicine::query()
            ->with('batches')
            ->where('is_active', true)
            ->orderBy('generic_name')
            ->orderBy('strength')
            ->get();

        $movements = StockMovement::query()
            ->join('medicine_batches', 'medicine_batches.id', '=', 'stock_movements.medicine_batch_id')
            ->where('stock_movements.created_at', '>=', $from->startOfDay())
            ->where('stock_movements.created_at', '<', $until->startOfDay())
            ->selectRaw('medicine_batches.medicine_id AS medicine_id, stock_movements.type AS type, SUM(stock_movements.quantity) AS total')
            ->groupBy('medicine_batches.medicine_id', 'stock_movements.type')
            ->get()
            ->groupBy('medicine_id');

        // `type` is cast to StockMovementType on the model, so compare enums.
        $moved = fn (int $medicineId, StockMovementType $type) => abs((int) ($movements->get($medicineId)?->first(fn (StockMovement $m) => $m->type === $type)?->getAttribute('total') ?? 0));

        $rows = $medicines->map(function (Medicine $m) use ($today, $moved) {
            $usable = (int) $m->batches->filter(fn ($b) => $b->expiry_date->greaterThanOrEqualTo($today))->sum('quantity_on_hand');
            $nearest = $m->batches->filter(fn ($b) => $b->quantity_on_hand > 0 && $b->expiry_date->greaterThanOrEqualTo($today))->min('expiry_date');

            return [
                'medicine' => $m->display_name,
                'usable' => $usable,
                'expired' => (int) $m->batches->filter(fn ($b) => $b->expiry_date->lessThan($today))->sum('quantity_on_hand'),
                'reorder' => $m->reorder_level,
                'stock_status' => $usable === 0 ? 'Out of stock' : ($usable <= $m->reorder_level ? 'Low' : 'OK'),
                'nearest_expiry' => $nearest?->toDateString() ?? '—',
                'received' => $moved($m->id, StockMovementType::In),
                'dispensed' => $moved($m->id, StockMovementType::Dispensed),
                'written_off' => $moved($m->id, StockMovementType::Expired),
            ];
        });

        $expiring = $medicines->flatMap(fn (Medicine $m) => $m->batches
            ->filter(fn ($b) => $b->quantity_on_hand > 0 && $b->expiry_date->lessThan($today->addDays(91)))
            ->map(fn ($b) => [
                'medicine' => $m->display_name,
                'batch' => $b->batch_no,
                'expiry' => $b->expiry_date->toDateString(),
                'band' => match (InventoryAlertService::bandFor($b->expiry_date, $today)) {
                    0 => 'Expired', 30 => 'Within 30 days', 60 => 'Within 60 days', default => 'Within 90 days',
                },
                'on_hand' => $b->quantity_on_hand,
            ]))->sortBy('expiry')->values()->all();

        return [
            'scope_note' => 'Medicine stock covers the whole health station; the purok filter does not apply.',
            'totals' => [
                ['label' => 'Medicines', 'value' => $rows->count()],
                ['label' => 'Low or out of stock', 'value' => $rows->whereIn('stock_status', ['Low', 'Out of stock'])->count()],
                ['label' => 'Batches expiring ≤ 90 days or expired', 'value' => count($expiring)],
                ['label' => 'Units dispensed in period', 'value' => $rows->sum('dispensed')],
            ],
            'tables' => [
                $this->table('stock', 'Stock position', [
                    ['medicine', 'Medicine'], ['usable', 'Usable stock', true], ['reorder', 'Reorder level', true], ['stock_status', 'Status'],
                    ['nearest_expiry', 'Nearest expiry'], ['expired', 'Expired, not written off', true],
                ], $rows->all()),
                $this->table('movements', 'Stock movements in period', [
                    ['medicine', 'Medicine'], ['received', 'Received', true], ['dispensed', 'Dispensed', true], ['written_off', 'Written off (expired)', true],
                ], $rows->filter(fn (array $r) => $r['received'] + $r['dispensed'] + $r['written_off'] > 0)->values()->all(),
                    chart: ['label' => 'medicine', 'values' => ['dispensed']], empty: 'No stock movements in this period.'),
                $this->table('expiring', 'Batches expiring within 90 days or expired', [
                    ['medicine', 'Medicine'], ['batch', 'Batch'], ['expiry', 'Expiry'], ['band', 'Band'], ['on_hand', 'On hand', true],
                ], $expiring, empty: 'No batches expiring within 90 days.'),
            ],
        ];
    }

    /**
     * DOH FHSIS-style maternal and child indicators.
     *
     * @return array<string, mixed>
     */
    private function maternalChild(CarbonImmutable $from, CarbonImmutable $until, ?int $purokId, bool $named): array
    {
        $deliveries = Delivery::query()
            ->with('pregnancy.resident.household.purok')
            ->whereHas('pregnancy', fn (Builder $p) => $this->inPurok($purokId, $p
                ->where('outcome_date', '>=', $from->toDateString())
                ->where('outcome_date', '<', $until->toDateString())))
            ->get();

        $registered = $this->inPurok($purokId, Pregnancy::query()->where('created_at', '>=', $from->startOfDay())->where('created_at', '<', $until->startOfDay()))->count();
        $ongoing = $this->inPurok($purokId, Pregnancy::query()->with('resident.household.purok')->where('status', MaternalStatus::Ongoing))->orderBy('edd')->get();
        $ancContacts = $this->inPurok($purokId, PrenatalVisit::query()->whereHas('healthVisit', fn (Builder $v) => $v
            ->where('visit_date', '>=', $from->toDateString())->where('visit_date', '<', $until->toDateString())), 'pregnancy.resident')->count();

        // Latest weighing in the period for each child under five.
        $latest = $this->inPurok($purokId, GrowthMeasurement::query()
            ->where('measured_on', '>=', $from->toDateString())
            ->where('measured_on', '<', $until->toDateString())
            ->whereHas('resident', fn (Builder $r) => $r->where('birthdate', '>', CarbonImmutable::today()->subYears(5)->toDateString())))
            ->orderBy('measured_on')
            ->get()
            ->keyBy('resident_id');

        $children = Resident::query()
            ->where('birthdate', '>', CarbonImmutable::today()->subYears(5)->toDateString())
            ->when($purokId, fn (Builder $q) => $q->whereHas('household', fn (Builder $h) => $h->where('purok_id', $purokId)))
            ->count();

        // Vitamin A is given from 6 months, so coverage is over children 6–59 months.
        $eligibleVitaminA = Resident::query()
            ->where('birthdate', '>', CarbonImmutable::today()->subYears(5)->toDateString())
            ->where('birthdate', '<=', CarbonImmutable::today()->subMonths(6)->toDateString())
            ->when($purokId, fn (Builder $q) => $q->whereHas('household', fn (Builder $h) => $h->where('purok_id', $purokId)))
            ->count();

        $vitaminA = $this->inPurok($purokId, MicronutrientRecord::query()
            ->where('type', MicronutrientType::VitaminA)
            ->where('date_given', '>=', $from->toDateString())
            ->where('date_given', '<', $until->toDateString()))
            ->distinct()
            ->count('resident_id');

        $facilityBased = $deliveries->filter(fn (Delivery $d) => $d->isFacilityBased())->count();
        $skilled = $deliveries->filter(fn (Delivery $d) => $d->isSkilledAttendant())->count();
        $pct = fn (int $n, int $of) => $of > 0 ? round(100 * $n / $of).'%' : '—';

        $tables = [
            $this->table('deliveries', 'Deliveries in period', [['indicator', 'Indicator'], ['count', 'Count', true], ['share', 'Share']], [
                ['indicator' => 'Deliveries', 'count' => $deliveries->count(), 'share' => ''],
                ['indicator' => 'Facility-based deliveries', 'count' => $facilityBased, 'share' => $pct($facilityBased, $deliveries->count())],
                ['indicator' => 'Attended by skilled health professionals', 'count' => $skilled, 'share' => $pct($skilled, $deliveries->count())],
                ['indicator' => 'Live births', 'count' => $deliveries->sum('live_births'), 'share' => ''],
                ['indicator' => 'Stillbirths', 'count' => $deliveries->sum('stillbirths'), 'share' => ''],
            ]),
            $this->table('nutrition', 'Nutritional status of children under five (latest weighing in period)', [['status', 'Status'], ['children', 'Children', true]],
                collect(NutritionalStatus::cases())
                    ->map(fn (NutritionalStatus $s) => ['status' => $s->label(), 'children' => $latest->filter(fn (GrowthMeasurement $m) => $m->nutritional_status === $s)->count()])
                    ->filter(fn (array $r) => $r['children'] > 0 || $r['status'] === NutritionalStatus::Normal->label())
                    ->values()->all(),
                chart: ['label' => 'status', 'values' => ['children']], description: "{$latest->count()} of {$children} children under five were weighed in the period."),
            $this->table('supplements', 'Supplementation in period', [['indicator', 'Indicator'], ['count', 'Children', true], ['share', 'Coverage']], [
                ['indicator' => "Children 6–59 months given vitamin A (of {$eligibleVitaminA})", 'count' => $vitaminA, 'share' => $pct($vitaminA, $eligibleVitaminA)],
            ]),
        ];

        if ($named) {
            $tables[] = $this->table('ongoing', 'Ongoing pregnancies', [
                ['resident_code', 'Resident ID'], ['name', 'Name'], ['purok', 'Purok'], ['edd', 'EDD'], ['aog', 'AOG (weeks)', true], ['risk', 'Risk'],
            ], $ongoing->map(fn (Pregnancy $p) => [
                'resident_code' => $p->resident->resident_code,
                'name' => $p->resident->full_name,
                'purok' => $p->resident->household->purok->name,
                'edd' => $p->edd->toDateString(),
                'aog' => $p->gestationalAgeWeeks(),
                'risk' => $p->risk_level->label(),
            ])->all(), named: true);
        }

        return [
            'totals' => [
                ['label' => 'Pregnancies registered', 'value' => $registered],
                ['label' => 'Ongoing pregnancies', 'value' => $ongoing->count(), 'hint' => $ongoing->where('risk_level', RiskLevel::High)->count().' high-risk'],
                ['label' => 'Prenatal contacts attended', 'value' => $ancContacts],
                ['label' => 'Deliveries', 'value' => $deliveries->count(), 'hint' => $pct($facilityBased, $deliveries->count()).' facility-based'],
                ['label' => 'Children under five', 'value' => $children],
            ],
            'tables' => $tables,
        ];
    }

    /**
     * Limits a query to residents of one purok (through `$relation`).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function inPurok(?int $purokId, Builder $query, string $relation = 'resident'): Builder
    {
        return $query->when($purokId, fn (Builder $q) => $q->whereHas("{$relation}.household", fn (Builder $h) => $h->where('purok_id', $purokId)));
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: bool}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array{label: string, values: list<string>}|null  $chart
     * @return array<string, mixed>
     */
    private function table(string $key, string $title, array $columns, array $rows, bool $named = false, ?array $chart = null, ?string $description = null, ?string $empty = null): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'columns' => array_map(fn (array $c) => ['key' => $c[0], 'label' => $c[1], 'numeric' => $c[2] ?? false], $columns),
            'rows' => array_values($rows),
            'named' => $named,
            'chart' => $chart,
            'empty' => $empty ?? 'Nothing to show for this period.',
        ];
    }
}
