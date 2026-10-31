<?php

namespace App\Http\Controllers;

use App\Enums\AlertSeverity;
use App\Enums\AuditAction;
use App\Enums\BreastfeedingStatus;
use App\Enums\CivilStatus;
use App\Enums\ConditionStatus;
use App\Enums\ConsentType;
use App\Enums\MaternalStatus;
use App\Enums\MaternalVisitKind;
use App\Enums\MedicalAlertType;
use App\Enums\MicronutrientType;
use App\Enums\NutritionalStatus;
use App\Enums\PermissionName;
use App\Enums\RelationshipToHead;
use App\Enums\ResidentStatus;
use App\Enums\RiskLevel;
use App\Enums\Sex;
use App\Http\Requests\Residents\ArchiveResidentRequest;
use App\Http\Requests\Residents\StoreResidentRequest;
use App\Http\Requests\Residents\UpdateResidentRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\ConsentResource;
use App\Http\Resources\DiseaseCaseResource;
use App\Http\Resources\EmergencyContactResource;
use App\Http\Resources\GrowthMeasurementResource;
use App\Http\Resources\HealthVisitResource;
use App\Http\Resources\ImmunizationResource;
use App\Http\Resources\MedicalAlertResource;
use App\Http\Resources\MicronutrientRecordResource;
use App\Http\Resources\PregnancyResource;
use App\Http\Resources\ReferralResource;
use App\Http\Resources\ResidentConditionResource;
use App\Http\Resources\ResidentResource;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Resident;
use App\Services\AuditLogger;
use App\Services\DuplicateDetector;
use App\Services\ImmunizationSchedule;
use App\Services\LookupOptions;
use App\Services\ResidentCodeGenerator;
use App\Services\ResidentSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ResidentController extends Controller
{
    /**
     * Fields copied from the validated request onto the model.
     */
    private const RESIDENT_FIELDS = [
        'household_id', 'last_name', 'first_name', 'middle_name', 'suffix', 'sex', 'birthdate', 'birthplace',
        'civil_status', 'contact_no', 'philhealth_no', 'is_pwd', 'relationship_to_head',
    ];

    public function __construct(private readonly LookupOptions $lookups) {}

    public function index(Request $request, ResidentSearch $search): Response
    {
        Gate::authorize('viewAny', Resident::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'purok_id' => ['nullable', 'integer'],
            'sex' => ['nullable', Rule::enum(Sex::class)],
            'age_group' => ['nullable', Rule::in(array_keys(ResidentSearch::AGE_GROUPS))],
            'status' => ['nullable', Rule::enum(ResidentStatus::class)],
            'archived' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(ResidentSearch::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        if ($request->boolean('archived')) {
            Gate::authorize(PermissionName::ResidentsArchive->value);
        }

        return Inertia::render('residents/index', [
            'residents' => ResidentResource::collection($search->paginate($filters)),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'purok_id' => isset($filters['purok_id']) ? (int) $filters['purok_id'] : null,
                'sex' => $filters['sex'] ?? null,
                'age_group' => $filters['age_group'] ?? null,
                'status' => $filters['status'] ?? null,
                'archived' => $request->boolean('archived'),
                'sort' => $filters['sort'] ?? 'last_name',
                'direction' => $filters['direction'] ?? 'asc',
                'per_page' => (int) ($filters['per_page'] ?? 15),
            ],
            'options' => [
                'puroks' => $this->lookups->puroks(),
                'sexes' => Sex::options(),
                'statuses' => ResidentStatus::options(),
                'ageGroups' => [
                    ['value' => 'infant', 'label' => 'Infant (under 1)'],
                    ['value' => 'child', 'label' => 'Child (1–9)'],
                    ['value' => 'adolescent', 'label' => 'Adolescent (10–19)'],
                    ['value' => 'adult', 'label' => 'Adult (20–59)'],
                    ['value' => 'senior', 'label' => 'Senior (60+)'],
                ],
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Resident::class);

        $householdId = $request->integer('household_id') ?: null;

        return Inertia::render('residents/create', [
            'options' => $this->formOptions(),
            'defaultConsentPurpose' => 'Barangay health profiling and delivery of primary health care services (RA 10173)',
            'selectedHousehold' => $householdId ? $this->lookups->households(null, $householdId)[0] ?? null : null,
            // Loaded on demand by the household Combobox via a partial reload.
            'householdOptions' => Inertia::optional(fn () => $this->lookups->households($request->string('household_search')->toString())),
        ]);
    }

    public function store(StoreResidentRequest $request, DuplicateDetector $duplicates, ResidentCodeGenerator $codes): RedirectResponse
    {
        $data = $request->validated();

        if (! $data['confirm_duplicate'] && $this->flashDuplicates($duplicates, $data)) {
            return back()->withErrors([
                'duplicate' => 'A resident with the same name and birthdate is already registered. Review the match, or tick "This is a different person" to save anyway.',
            ]);
        }

        $resident = DB::transaction(function () use ($data, $codes, $request) {
            $resident = Resident::query()->create([
                ...array_intersect_key($data, array_flip(self::RESIDENT_FIELDS)),
                'resident_code' => $codes->next(),
                'status' => ResidentStatus::Active,
            ]);

            $resident->consents()->create([
                'consent_date' => $data['consent_date'],
                'purpose' => $data['consent_purpose'],
                'obtained_by' => $request->user()?->id,
            ]);

            $this->syncHouseholdHead($resident);

            return $resident;
        });

        $this->toast("Resident {$resident->resident_code} registered.");

        return to_route('residents.show', $resident);
    }

    public function show(Request $request, Resident $resident, ImmunizationSchedule $schedule, AuditLogger $audit): Response
    {
        Gate::authorize('view', $resident);

        $resident->load(['household.purok', 'household.head', 'creator', 'updater', 'pregnancies']);

        $user = $request->user();
        $canViewHealth = $user?->can('viewSensitive', $resident) ?? false;
        $canRecordHealth = $user?->can('recordHealth', $resident) ?? false;

        return Inertia::render('residents/show', [
            'resident' => ResidentResource::make($resident)->withSensitive(),
            'householdMembers' => Resident::query()
                ->where('household_id', $resident->household_id)
                ->whereKeyNot($resident->id)
                ->orderBy('birthdate')
                ->get()
                ->map(fn (Resident $m) => [
                    'id' => $m->id,
                    'resident_code' => $m->resident_code,
                    'full_name' => $m->full_name,
                    'age' => $m->age,
                    'sex' => $m->sex->label(),
                    'relationship_to_head' => $m->relationship_to_head->label(),
                ]),
            'consents' => ConsentResource::collection($resident->consents()->with('obtainedBy')->latest('consent_date')->get()),

            // Red banner at the top of the profile: loaded with the page, not deferred.
            'criticalAlerts' => $canViewHealth ? MedicalAlertResource::collection($resident->criticalAlerts()->get()) : [],

            // Health tabs load after first paint (deferred) and only for roles that may see them.
            // Opening them is a "view of sensitive data" and is audit-logged (RA 10173).
            'health' => $canViewHealth ? Inertia::defer(function () use ($resident, $schedule, $audit) {
                $audit->log(AuditAction::ViewedSensitive, $resident, "Viewed health records of {$resident->resident_code}");

                $resident->load([
                    'healthVisits' => fn ($q) => $q->with(['visitType', 'attendedBy', 'vitalSign', 'prenatalVisit', 'postnatalVisit'])->latest('visit_date')->latest('id'),
                    'conditions' => fn ($q) => $q->with(['condition', 'recordedBy'])->latest('diagnosed_date'),
                    'immunizations' => fn ($q) => $q->with(['vaccine', 'givenBy'])->orderBy('date_given'),
                    'pregnancies' => fn ($q) => $q->withCount(['prenatalVisits', 'postnatalVisits'])->with('riskFactors')->latest('edd'),
                    'emergencyContacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name'),
                    'medicalAlerts' => fn ($q) => $q->with('recordedBy:id,name')->orderByDesc('is_active')->latest(),
                    'growthMeasurements' => fn ($q) => $q->orderBy('measured_on'),
                    'micronutrientRecords' => fn ($q) => $q->latest('date_given'),
                    'diseaseCases' => fn ($q) => $q->with(['disease', 'purok'])->latest('onset_date'),
                    'referrals' => fn ($q) => $q->with(['facility', 'referredBy:id,name'])->latest('referred_at'),
                    'appointments' => fn ($q) => $q->with(['type', 'assignedTo:id,name'])->where('scheduled_at', '>=', now()->subMonths(6))->orderBy('scheduled_at'),
                ]);

                return [
                    'timeline' => HealthVisitResource::collection($resident->healthVisits)->resolve(),
                    'conditions' => ResidentConditionResource::collection($resident->conditions)->resolve(),
                    'immunizations' => ImmunizationResource::collection($resident->immunizations)->resolve(),
                    'immunizationSchedule' => $schedule->isInTargetAge($resident) || $resident->age < 5
                        ? $schedule->forResident($resident)->all()
                        : [],
                    'pregnancies' => PregnancyResource::collection($resident->pregnancies)->resolve(),
                    'emergencyContacts' => EmergencyContactResource::collection($resident->emergencyContacts)->resolve(),
                    'medicalAlerts' => MedicalAlertResource::collection($resident->medicalAlerts)->resolve(),
                    'growth' => GrowthMeasurementResource::collection($resident->growthMeasurements)->resolve(),
                    'micronutrients' => MicronutrientRecordResource::collection($resident->micronutrientRecords)->resolve(),
                    'diseaseCases' => DiseaseCaseResource::collection($resident->diseaseCases)->resolve(),
                    'referrals' => ReferralResource::collection($resident->referrals)->resolve(),
                    'appointments' => AppointmentResource::collection($resident->appointments)->resolve(),
                ];
            }, 'health') : null,

            'history' => ($user?->can('update', $resident) || $user?->can(PermissionName::AuditView->value))
                ? Inertia::defer(fn () => AuditLog::query()
                    ->with('user:id,name')
                    ->where('table_name', 'residents')
                    ->where('record_id', $resident->id)
                    ->latest('id')
                    ->limit(20)
                    ->get()
                    ->map(fn (AuditLog $log) => [
                        'id' => $log->id,
                        'action' => $log->action->label(),
                        'description' => $log->description,
                        'old_values' => $log->old_values,
                        'new_values' => $log->new_values,
                        'user' => $log->user?->name,
                        'created_at' => $log->created_at->toIso8601String(),
                    ]), 'history')
                : null,

            'healthOptions' => $canRecordHealth ? [
                'visitTypes' => $this->lookups->visitTypes(),
                'vaccines' => $this->lookups->vaccines(),
                'conditions' => $this->lookups->conditions(),
                'conditionStatuses' => ConditionStatus::options(),
                'riskLevels' => RiskLevel::options(),
                'maternalStatuses' => MaternalStatus::options(),
                'maternalVisitKinds' => MaternalVisitKind::options(),
                'alertTypes' => MedicalAlertType::options(),
                'alertSeverities' => AlertSeverity::options(),
                'riskFactors' => $this->lookups->riskFactors(),
                'nutritionalStatuses' => NutritionalStatus::options(),
                'micronutrientTypes' => MicronutrientType::options(),
                'breastfeeding' => BreastfeedingStatus::options(),
                'consentTypes' => ConsentType::options(),
            ] : null,
            'archiveStatuses' => ResidentStatus::options(),
        ]);
    }

    public function edit(Request $request, Resident $resident): Response
    {
        Gate::authorize('update', $resident);

        $resident->load('household.purok');

        return Inertia::render('residents/edit', [
            'resident' => ResidentResource::make($resident)->withSensitive(),
            'options' => $this->formOptions(),
            'selectedHousehold' => $this->lookups->households(null, $resident->household_id)[0] ?? null,
            'householdOptions' => Inertia::optional(fn () => $this->lookups->households($request->string('household_search')->toString())),
        ]);
    }

    public function update(UpdateResidentRequest $request, Resident $resident, DuplicateDetector $duplicates): RedirectResponse
    {
        $data = $request->validated();

        $identityChanged = $resident->last_name !== $data['last_name']
            || $resident->first_name !== $data['first_name']
            || $resident->birthdate->toDateString() !== $data['birthdate'];

        if ($identityChanged && ! $data['confirm_duplicate'] && $this->flashDuplicates($duplicates, $data, $resident->id)) {
            return back()->withErrors([
                'duplicate' => 'Another resident already has this name and birthdate. Review the match, or tick "This is a different person" to save anyway.',
            ]);
        }

        // Only users who may see the full PhilHealth number may change it.
        if (! $request->user()?->can('viewSensitive', $resident)) {
            unset($data['philhealth_no']);
        }

        DB::transaction(function () use ($resident, $data) {
            $previousHousehold = $resident->household_id;

            $resident->update(array_intersect_key($data, array_flip(self::RESIDENT_FIELDS)));

            if ($previousHousehold !== $resident->household_id) {
                Household::query()->whereKey($previousHousehold)->where('head_resident_id', $resident->id)->update(['head_resident_id' => null]);
            }

            $this->syncHouseholdHead($resident);
        });

        $this->toast('Resident profile updated.');

        return to_route('residents.show', $resident);
    }

    /**
     * Archive (soft delete) with a reason — never a hard delete.
     */
    public function destroy(ArchiveResidentRequest $request, Resident $resident): RedirectResponse
    {
        DB::transaction(function () use ($request, $resident) {
            $resident->update($request->validated());
            Household::query()->where('head_resident_id', $resident->id)->update(['head_resident_id' => null]);
            $resident->delete();
        });

        $this->toast("{$resident->full_name} was archived.");

        return to_route('residents.index');
    }

    public function restore(Resident $resident): RedirectResponse
    {
        Gate::authorize('restore', $resident);

        DB::transaction(function () use ($resident) {
            $resident->restore();
            $resident->update(['status' => ResidentStatus::Active, 'archive_reason' => null]);
        });

        $this->toast("{$resident->full_name} was restored.");

        return to_route('residents.show', $resident);
    }

    /**
     * Permanent deletion after the retention period (Admin only).
     */
    public function forceDestroy(Resident $resident): RedirectResponse
    {
        Gate::authorize('forceDelete', $resident);

        $code = $resident->resident_code;
        $resident->forceDelete();

        $this->toast("Record {$code} was permanently deleted.");

        return to_route('residents.index', ['archived' => 1]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function flashDuplicates(DuplicateDetector $duplicates, array $data, ?int $ignoreId = null): bool
    {
        $matches = $duplicates->find($data['last_name'], $data['first_name'], $data['birthdate'], $ignoreId);

        if ($matches->isEmpty()) {
            return false;
        }

        Inertia::flash('duplicates', $matches->map(fn (Resident $r) => [
            'id' => $r->id,
            'resident_code' => $r->resident_code,
            'full_name' => $r->full_name,
            'birthdate' => $r->birthdate->toDateString(),
            'household_no' => $r->household->household_no,
            'purok' => $r->household->purok->name,
            'archived' => $r->trashed(),
        ])->all());

        return true;
    }

    /**
     * A resident marked as head becomes the household head if it has none.
     */
    private function syncHouseholdHead(Resident $resident): void
    {
        if ($resident->relationship_to_head === RelationshipToHead::Head) {
            Household::query()->whereKey($resident->household_id)->whereNull('head_resident_id')->update(['head_resident_id' => $resident->id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'puroks' => $this->lookups->puroks(),
            'sexes' => Sex::options(),
            'civilStatuses' => CivilStatus::options(),
            'relationships' => RelationshipToHead::options(),
            'suffixes' => Resident::SUFFIXES,
        ];
    }
}
