<?php

namespace App\Http\Controllers\Health;

use App\Enums\AuditAction;
use App\Enums\MicronutrientType;
use App\Enums\NutritionalStatus;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\GrowthMeasurementResource;
use App\Http\Resources\MedicalAlertResource;
use App\Http\Resources\MicronutrientRecordResource;
use App\Models\GrowthMeasurement;
use App\Models\Resident;
use App\Services\AuditLogger;
use App\Services\ImmunizationSchedule;
use App\Services\LookupOptions;
use App\Services\MicronutrientSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Children under five: EPI checklist, growth monitoring, vitamin A and
 * deworming.
 */
class ChildHealthController extends Controller
{
    public function __construct(
        private readonly ImmunizationSchedule $immunizations,
        private readonly MicronutrientSchedule $micronutrients,
    ) {}

    public function index(Request $request, LookupOptions $lookups): Response
    {
        Gate::authorize(PermissionName::HealthViewSensitive->value);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'purok_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['overdue', 'due', 'malnourished', 'vitamin_a_due'])],
        ]);

        $today = CarbonImmutable::today();

        $children = Resident::query()
            ->with(['household.purok', 'immunizations', 'micronutrientRecords', 'growthMeasurements' => fn ($q) => $q->latest('measured_on')])
            ->where('birthdate', '>', $today->subMonths(60)->toDateString())
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w
                ->where('last_name', 'like', "{$s}%")->orWhere('first_name', 'like', "{$s}%")->orWhere('resident_code', 'like', "{$s}%")))
            ->when($filters['purok_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('household', fn (Builder $h) => $h->where('purok_id', $id)))
            ->orderByDesc('birthdate')
            ->get()
            ->map(fn (Resident $child) => $this->summary($child, $today))
            ->filter(fn (array $row) => match ($filters['status'] ?? null) {
                'overdue' => $row['epi']['overdue'] > 0,
                'due' => $row['epi']['due'] > 0,
                'malnourished' => $row['nutrition'] !== null && $row['nutrition']['value'] !== NutritionalStatus::Normal->value,
                'vitamin_a_due' => $row['vitamin_a_due'],
                default => true,
            })
            ->values();

        return Inertia::render('child-health/index', [
            'childrenUnderFive' => $children,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'purok_id' => isset($filters['purok_id']) ? (int) $filters['purok_id'] : null,
                'status' => $filters['status'] ?? null,
            ],
            'options' => ['puroks' => $lookups->puroks()],
        ]);
    }

    public function show(Request $request, Resident $resident, AuditLogger $audit, LookupOptions $lookups): Response
    {
        Gate::authorize('viewSensitive', $resident);

        $resident->load([
            'household.purok', 'mother:id,resident_code,last_name,first_name,middle_name,suffix', 'criticalAlerts', 'immunizations.vaccine',
            'growthMeasurements' => fn ($q) => $q->with('measuredBy:id,name')->orderBy('measured_on'),
            'micronutrientRecords' => fn ($q) => $q->with('givenBy:id,name')->latest('date_given'),
        ]);

        $audit->log(AuditAction::ViewedSensitive, $resident, "Viewed child health record of {$resident->resident_code}");

        return Inertia::render('child-health/show', [
            'child' => [
                'id' => $resident->id,
                'resident_code' => $resident->resident_code,
                'full_name' => $resident->full_name,
                'sex' => ['value' => $resident->sex->value, 'label' => $resident->sex->label()],
                'birthdate' => $resident->birthdate->toDateString(),
                'age_months' => (int) $resident->birthdate->diffInMonths(CarbonImmutable::today()),
                'purok' => $resident->household->purok->name,
                'household_id' => $resident->household_id,
                'mother' => $resident->mother ? ['id' => $resident->mother->id, 'full_name' => $resident->mother->full_name] : null,
            ],
            'criticalAlerts' => MedicalAlertResource::collection($resident->criticalAlerts),
            'immunizationSchedule' => $this->immunizations->forResident($resident),
            'growth' => GrowthMeasurementResource::collection($resident->growthMeasurements),
            'micronutrients' => MicronutrientRecordResource::collection($resident->micronutrientRecords),
            'micronutrientStatus' => $this->micronutrients->forChild($resident),
            'options' => Gate::allows('recordHealth', $resident) ? [
                'nutritionalStatuses' => NutritionalStatus::options(),
                'micronutrientTypes' => MicronutrientType::options(),
                'vaccines' => $lookups->vaccines(),
            ] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Resident $child, CarbonImmutable $today): array
    {
        $doses = $this->immunizations->forResident($child, $today);
        /** @var GrowthMeasurement|null $latest */
        $latest = $child->growthMeasurements->first();

        return [
            'id' => $child->id,
            'resident_code' => $child->resident_code,
            'full_name' => $child->full_name,
            'sex' => $child->sex->label(),
            'age_months' => (int) $child->birthdate->diffInMonths($today),
            'purok' => $child->household->purok->name,
            'epi' => [
                'given' => $doses->where('status', 'given')->count(),
                'due' => $doses->where('status', 'due')->count(),
                'overdue' => $doses->where('status', 'overdue')->count(),
                'total' => $doses->count(),
            ],
            'nutrition' => $latest?->nutritional_status ? ['value' => $latest->nutritional_status->value, 'label' => $latest->nutritional_status->label()] : null,
            'last_weighed' => $latest?->measured_on->toDateString(),
            'vitamin_a_due' => $this->micronutrients->vitaminADue($child, $today),
        ];
    }
}
