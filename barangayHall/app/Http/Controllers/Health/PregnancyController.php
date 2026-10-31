<?php

namespace App\Http\Controllers\Health;

use App\Enums\AuditAction;
use App\Enums\BirthAttendant;
use App\Enums\BreastfeedingStatus;
use App\Enums\DeliveryMode;
use App\Enums\DeliveryPlace;
use App\Enums\MaternalStatus;
use App\Enums\RelationshipToHead;
use App\Enums\RiskLevel;
use App\Enums\Sex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Health\PregnancyRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\HealthVisitResource;
use App\Http\Resources\MedicalAlertResource;
use App\Http\Resources\PregnancyResource;
use App\Models\Pregnancy;
use App\Models\Resident;
use App\Services\AuditLogger;
use App\Services\LookupOptions;
use App\Services\PregnancyScheduleService;
use App\Services\PregnancyService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Maternal tracker. Viewing: everyone with health access; changing:
 * Midwife/Admin (PregnancyPolicy, maternal.manage).
 */
class PregnancyController extends Controller
{
    public function __construct(
        private readonly PregnancyScheduleService $schedule,
        private readonly PregnancyService $pregnancies,
    ) {}

    public function index(Request $request, LookupOptions $lookups): Response
    {
        Gate::authorize('view', new Pregnancy);

        $filters = $request->validate([
            'purok_id' => ['nullable', 'integer'],
            'risk_level' => ['nullable', Rule::enum(RiskLevel::class)],
            // overdue = a contact past due + grace; due_soon = EDD within 30 days; postnatal = delivered within 6 weeks
            'status' => ['nullable', Rule::in(['overdue', 'due_soon', 'postnatal'])],
        ]);

        $today = CarbonImmutable::today();
        $postnatal = ($filters['status'] ?? null) === 'postnatal';

        $rows = Pregnancy::query()
            ->with(['resident.household.purok', 'riskFactors', 'prenatalVisits.healthVisit', 'postnatalVisits.healthVisit', 'delivery'])
            ->whereHas('resident')
            ->when(
                $postnatal,
                fn (Builder $q) => $q->where('status', MaternalStatus::Delivered)->where('outcome_date', '>=', $today->subDays(42)->toDateString()),
                fn (Builder $q) => $q->where('status', MaternalStatus::Ongoing),
            )
            ->when($filters['purok_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('resident.household', fn (Builder $h) => $h->where('purok_id', $id)))
            ->when($filters['risk_level'] ?? null, fn (Builder $q, $r) => $q->where('risk_level', $r))
            ->when(($filters['status'] ?? null) === 'due_soon', fn (Builder $q) => $q->where('edd', '<', $today->addDays(31)->toDateString()))
            ->orderBy($postnatal ? 'outcome_date' : 'edd')
            ->get()
            ->map(fn (Pregnancy $p) => [
                ...PregnancyResource::make($p)->resolve($request),
                'next_contact' => $this->schedule->nextContact($p, $today),
                'overdue_contacts' => $this->schedule->overdueCount($p, $today),
            ])
            ->when(($filters['status'] ?? null) === 'overdue', fn ($c) => $c->filter(fn (array $row) => $row['overdue_contacts'] > 0))
            ->values();

        return Inertia::render('maternal/index', [
            'pregnancies' => $rows,
            'filters' => [
                'purok_id' => isset($filters['purok_id']) ? (int) $filters['purok_id'] : null,
                'risk_level' => $filters['risk_level'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'options' => ['puroks' => $lookups->puroks(), 'riskLevels' => RiskLevel::options()],
        ]);
    }

    public function show(Request $request, Pregnancy $pregnancy, AuditLogger $audit, LookupOptions $lookups): Response
    {
        Gate::authorize('view', $pregnancy);

        $pregnancy->load([
            'resident.household.purok', 'resident.criticalAlerts', 'riskFactors', 'delivery',
            'prenatalVisits.healthVisit' => fn ($q) => $q->with(['vitalSign', 'attendedBy', 'visitType', 'prenatalVisit']),
            'postnatalVisits.healthVisit' => fn ($q) => $q->with(['vitalSign', 'attendedBy', 'visitType', 'postnatalVisit']),
        ]);

        $audit->log(AuditAction::ViewedSensitive, $pregnancy->resident, "Viewed pregnancy record of {$pregnancy->resident->resident_code}");

        $visits = $pregnancy->prenatalVisits->map->healthVisit
            ->merge($pregnancy->postnatalVisits->map->healthVisit)
            ->sortByDesc('visit_date')
            ->values();

        return Inertia::render('maternal/show', [
            'pregnancy' => PregnancyResource::make($pregnancy),
            'criticalAlerts' => MedicalAlertResource::collection($pregnancy->resident->criticalAlerts),
            'contacts' => $this->schedule->contacts($pregnancy),
            'visits' => HealthVisitResource::collection($visits),
            'appointments' => AppointmentResource::collection($pregnancy->resident->appointments()
                ->with(['type', 'assignedTo:id,name'])
                ->where('source_type', $pregnancy->getMorphClass())
                ->where('source_id', $pregnancy->id)
                ->orderBy('scheduled_at')
                ->get()),
            'newborns' => $pregnancy->outcome_date
                ? $pregnancy->resident->children()->whereDate('birthdate', $pregnancy->outcome_date->toDateString())->get(['id', 'resident_code', 'last_name', 'first_name', 'middle_name', 'suffix', 'sex'])
                    ->map(fn (Resident $c) => ['id' => $c->id, 'resident_code' => $c->resident_code, 'full_name' => $c->full_name, 'sex' => $c->sex->label()])
                : [],
            'options' => Gate::allows('update', $pregnancy) ? [
                'riskFactors' => $lookups->riskFactors(),
                'riskLevels' => RiskLevel::options(),
                'statuses' => MaternalStatus::options(),
                'deliveryPlaces' => DeliveryPlace::options(),
                'attendants' => BirthAttendant::options(),
                'deliveryModes' => DeliveryMode::options(),
                'breastfeeding' => BreastfeedingStatus::options(),
                'sexes' => Sex::options(),
                'relationships' => RelationshipToHead::options(),
                'visitTypes' => $lookups->visitTypes(),
            ] : null,
        ]);
    }

    public function store(PregnancyRequest $request, Resident $resident): RedirectResponse
    {
        $this->pregnancies->register(
            $resident,
            $request->safe()->except('risk_factor_ids'),
            array_values(array_map('intval', $request->validated('risk_factor_ids', []))),
        );

        $this->toast('Pregnancy registered. Prenatal appointments were scheduled.');

        return back();
    }

    public function update(PregnancyRequest $request, Pregnancy $pregnancy): RedirectResponse
    {
        $data = $request->safe()->except('risk_factor_ids');

        if ($data['status'] === MaternalStatus::Ongoing->value) {
            $data['outcome_date'] = null;
        }

        $this->pregnancies->update(
            $pregnancy,
            $data,
            $request->has('risk_factor_ids') ? array_values(array_map('intval', $request->validated('risk_factor_ids', []))) : null,
        );

        $this->toast('Pregnancy record updated.');

        return back();
    }
}
