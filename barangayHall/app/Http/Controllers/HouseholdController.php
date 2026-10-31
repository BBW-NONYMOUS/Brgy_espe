<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\CaseClassification;
use App\Enums\CaseOutcome;
use App\Enums\ConditionStatus;
use App\Enums\ConsentType;
use App\Enums\PermissionName;
use App\Enums\RiskLevel;
use App\Http\Requests\Residents\HouseholdRequest;
use App\Http\Resources\HouseholdResource;
use App\Http\Resources\ResidentResource;
use App\Models\Consent;
use App\Models\Household;
use App\Models\Resident;
use App\Models\ResidentCondition;
use App\Services\AuditLogger;
use App\Services\FamilyHealthSummary;
use App\Services\LookupOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class HouseholdController extends Controller
{
    public function __construct(private readonly LookupOptions $lookups) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Household::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'purok_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $search = trim($filters['search'] ?? '');

        $households = Household::query()
            ->with(['purok', 'head'])
            ->withCount('members')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('household_no', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhereHas('head', fn (Builder $h) => $h->where('last_name', 'like', "{$search}%"))))
            ->when($filters['purok_id'] ?? null, fn (Builder $q, $purokId) => $q->where('purok_id', $purokId))
            ->orderBy('household_no')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return Inertia::render('households/index', [
            'households' => HouseholdResource::collection($households),
            'filters' => [
                'search' => $search,
                'purok_id' => isset($filters['purok_id']) ? (int) $filters['purok_id'] : null,
                'per_page' => (int) ($filters['per_page'] ?? 15),
            ],
            'options' => ['puroks' => $this->lookups->puroks()],
        ]);
    }

    public function show(Request $request, Household $household): Response
    {
        Gate::authorize('view', $household);

        $household->load([
            'purok', 'head', 'waterSource', 'toiletType', 'geotaggedBy:id,name',
            'members' => fn ($q) => $q->with([
                'pregnancies', 'criticalAlerts', 'consents',
                'conditions' => fn ($c) => $c->with('condition')->where('status', '!=', ConditionStatus::Resolved),
                'familyPlanning' => fn ($f) => $f->with('method')->whereNull('ended_on'),
                'diseaseCases' => fn ($d) => $d->where('outcome', CaseOutcome::Ongoing)->where('classification', '!=', CaseClassification::RuledOut),
            ])->orderBy('birthdate'),
        ]);

        $user = $request->user();
        $canViewHealth = $user?->can(PermissionName::HealthViewSensitive->value) ?? false;

        return Inertia::render('households/show', [
            'household' => HouseholdResource::make($household),
            'members' => ResidentResource::collection($household->members),
            // Family health profile: per-member health flags (health roles only).
            'memberHealth' => $canViewHealth ? $household->members->mapWithKeys(fn (Resident $m) => [$m->id => $this->memberHealth($m)]) : null,
            'geotag' => [
                'is_geotagged' => $household->isGeotagged(),
                'geotagged_at' => $household->geotagged_at?->toIso8601String(),
                'geotagged_by' => $household->geotaggedBy?->name,
                'accuracy_m' => $household->geotag_accuracy_m,
                'has_consent' => $household->members->contains(fn (Resident $m) => $m->consents
                    ->contains(fn (Consent $c) => $c->consent_type === ConsentType::Geotagging && $c->revoked_at === null)),
                'can_geotag' => $user?->can('geotag', $household) ?? false,
            ],
            'map' => ['center' => config('barangay.map.center'), 'zoom' => config('barangay.map.zoom')],
            'options' => [
                'puroks' => $this->lookups->puroks(),
                'waterSources' => $this->lookups->waterSources(),
                'toiletTypes' => $this->lookups->toiletTypes(),
            ],
        ]);
    }

    public function store(HouseholdRequest $request): RedirectResponse
    {
        $household = DB::transaction(function () use ($request) {
            $data = $request->safe()->except('head_resident_id');
            $data['household_no'] ??= $this->nextHouseholdNo();

            return Household::query()->create($data);
        });

        $this->toast("Household {$household->household_no} created.");

        // Return to the page that opened the dialog (e.g. resident registration)
        // and tell it which household to select.
        if ($request->boolean('stay')) {
            Inertia::flash('createdHousehold', $this->lookups->households(null, $household->id)[0] ?? null);

            return back();
        }

        return to_route('households.show', $household);
    }

    public function update(HouseholdRequest $request, Household $household): RedirectResponse
    {
        $data = $request->validated();
        $data['household_no'] ??= $household->household_no;

        $household->update($data);

        $this->toast("Household {$household->household_no} updated.");

        return to_route('households.show', $household);
    }

    public function destroy(Household $household): RedirectResponse
    {
        Gate::authorize('delete', $household);

        $household->delete();

        $this->toast("Household {$household->household_no} deleted.");

        return to_route('households.index');
    }

    /**
     * Printable family health summary (PDF). Health roles only; audited.
     */
    public function summary(Request $request, Household $household, FamilyHealthSummary $summary, AuditLogger $audit): HttpResponse
    {
        Gate::authorize('view', $household);
        Gate::authorize(PermissionName::HealthViewSensitive->value);

        $audit->log(AuditAction::Exported, $household, "Printed family health summary of {$household->household_no}");

        return Pdf::loadView('pdf.family-health-summary', [
            ...$summary->build($household),
            'generatedBy' => $request->user()?->name,
            'healthCenter' => config('barangay'),
        ])->setPaper('a4')->stream("family-health-summary-{$household->household_no}.pdf");
    }

    /**
     * Health flags for one member of the family health profile. The family
     * planning method is included only with the member's own FP consent.
     *
     * @return array<string, mixed>
     */
    private function memberHealth(Resident $member): array
    {
        $fpConsent = $member->consents->contains(fn (Consent $c) => $c->consent_type === ConsentType::FamilyPlanning && $c->revoked_at === null);
        $pregnancy = $member->currentPregnancy();

        return [
            'pregnant' => $pregnancy !== null,
            'high_risk' => $pregnancy?->risk_level === RiskLevel::High,
            'under_five' => $member->birthdate->greaterThan(now()->subYears(5)),
            'critical_alerts' => $member->criticalAlerts->count(),
            'conditions' => $member->conditions->map(fn (ResidentCondition $c) => $c->condition->name)->values()->all(),
            'active_cases' => $member->diseaseCases->count(),
            'family_planning' => $fpConsent ? $member->familyPlanning->first()?->method->name : null,
            'family_planning_consent' => $fpConsent,
        ];
    }

    private function nextHouseholdNo(): string
    {
        $last = Household::query()->where('household_no', 'like', 'HH-%')->lockForUpdate()->orderByDesc('household_no')->value('household_no');
        $next = $last ? (int) substr($last, 3) + 1 : 1;

        return 'HH-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
