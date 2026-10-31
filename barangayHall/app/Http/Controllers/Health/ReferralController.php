<?php

namespace App\Http\Controllers\Health;

use App\Enums\AlertSeverity;
use App\Enums\AuditAction;
use App\Enums\MaternalStatus;
use App\Enums\ReferralStatus;
use App\Enums\ReferralUrgency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Health\ReferralRequest;
use App\Http\Requests\Health\ReferralStatusRequest;
use App\Http\Resources\HealthVisitResource;
use App\Http\Resources\MedicalAlertResource;
use App\Http\Resources\ReferralResource;
use App\Models\Referral;
use App\Services\AuditLogger;
use App\Services\LookupOptions;
use App\Services\ReferralService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referrals,
        private readonly LookupOptions $lookups,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Referral::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ReferralStatus::class)],
            'urgency' => ['nullable', Rule::enum(ReferralUrgency::class)],
            'facility_id' => ['nullable', 'integer'],
            'resident' => ['nullable', 'string', 'max:100'],
        ]);

        $referrals = Referral::query()
            ->with(['resident', 'facility', 'referredBy:id,name'])
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w
                ->where('referral_no', 'like', "%{$s}%")
                ->orWhereHas('resident', fn (Builder $r) => $r->where('last_name', 'like', "{$s}%")->orWhere('first_name', 'like', "{$s}%"))))
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->when($filters['urgency'] ?? null, fn (Builder $q, $u) => $q->where('urgency', $u))
            ->when($filters['facility_id'] ?? null, fn (Builder $q, $id) => $q->where('facility_id', $id))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'sent' THEN 1 WHEN 'received' THEN 2 ELSE 3 END")
            ->latest('referred_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('referrals/index', [
            'referrals' => ReferralResource::collection($referrals),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? null,
                'urgency' => $filters['urgency'] ?? null,
                'facility_id' => isset($filters['facility_id']) ? (int) $filters['facility_id'] : null,
            ],
            'options' => [
                'statuses' => ReferralStatus::options(),
                'urgencies' => ReferralUrgency::options(),
                'facilities' => $this->lookups->facilities(),
            ],
            'residentOptions' => Inertia::optional(fn () => $this->lookups->residents($filters['resident'] ?? null)),
            'canCreate' => Gate::allows('create', Referral::class),
        ]);
    }

    public function store(ReferralRequest $request): RedirectResponse
    {
        $referral = $this->referrals->create($request->resident(), $request->safe()->except('resident_id'), $request->user());

        $this->toast("Referral {$referral->referral_no} created. Print the slip for the patient to bring.");

        return to_route('referrals.show', $referral);
    }

    public function show(Referral $referral, AuditLogger $audit): Response
    {
        Gate::authorize('view', $referral);

        $referral->load([
            'resident.household.purok', 'resident.criticalAlerts', 'facility', 'referredBy:id,name',
            'statusChanges' => fn ($q) => $q->with('changedBy:id,name')->orderBy('id'),
            'healthVisit' => fn ($q) => $q->with(['vitalSign', 'visitType', 'attendedBy']),
        ]);

        $audit->log(AuditAction::ViewedSensitive, $referral, "Viewed referral {$referral->referral_no}");

        return Inertia::render('referrals/show', [
            'referral' => ReferralResource::make($referral)->withDetails(),
            'criticalAlerts' => MedicalAlertResource::collection($referral->resident->criticalAlerts),
            'visit' => $referral->healthVisit ? HealthVisitResource::make($referral->healthVisit) : null,
            'nextStatuses' => Gate::allows('update', $referral)
                ? array_map(fn (ReferralStatus $s) => ['value' => $s->value, 'label' => $s->label()], $this->referrals->allowedNext($referral))
                : [],
        ]);
    }

    /**
     * Printable referral slip with a tear-off return slip (PDF, opens in the
     * browser for printing). Every print is an audited export.
     */
    public function slip(Referral $referral, AuditLogger $audit): HttpResponse
    {
        Gate::authorize('view', $referral);

        $referral->load([
            'resident.household', 'resident.emergencyContacts', 'facility', 'referredBy:id,name',
            'resident.medicalAlerts' => fn ($q) => $q->where('is_active', true)->whereIn('severity', [AlertSeverity::Critical, AlertSeverity::Warning]),
            'resident.pregnancies' => fn ($q) => $q->with('riskFactors')->where('status', MaternalStatus::Ongoing),
            'healthVisit.vitalSign',
        ]);

        $audit->log(AuditAction::Exported, $referral, "Printed referral slip {$referral->referral_no}");

        return Pdf::loadView('pdf.referral-slip', [
            'referral' => $referral,
            'alerts' => $referral->resident->medicalAlerts->sortByDesc(fn ($a) => $a->severity === AlertSeverity::Critical),
            'contact' => $referral->resident->emergencyContacts->sortByDesc('is_primary')->first(),
            'pregnancy' => $referral->resident->pregnancies->first(),
            'visit' => $referral->healthVisit,
            'generatedBy' => request()->user()?->name,
            'healthCenter' => config('barangay'),
        ])->setPaper('a4')->stream("referral-slip-{$referral->referral_no}.pdf");
    }

    public function updateStatus(ReferralStatusRequest $request, Referral $referral): RedirectResponse
    {
        $status = ReferralStatus::from($request->validated('status'));

        $this->referrals->transition($referral, $status, $request->user(), $request->validated('note'), $request->validated('feedback_note'));

        $this->toast("Referral {$referral->referral_no} marked {$status->label()}.");

        return back();
    }
}
