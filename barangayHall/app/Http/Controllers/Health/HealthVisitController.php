<?php

namespace App\Http\Controllers\Health;

use App\Enums\MaternalVisitKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Health\HealthVisitRequest;
use App\Models\HealthVisit;
use App\Models\Pregnancy;
use App\Models\Resident;
use App\Services\AppointmentGenerator;
use App\Services\PregnancyScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Quick-entry visits from the resident profile (Sheet/Dialog).
 */
class HealthVisitController extends Controller
{
    public function __construct(
        private readonly PregnancyScheduleService $schedule,
        private readonly AppointmentGenerator $appointments,
    ) {}

    public function store(HealthVisitRequest $request, Resident $resident): RedirectResponse
    {
        DB::transaction(function () use ($request, $resident) {
            $visit = $resident->healthVisits()->create([
                ...$request->safe()->only(['visit_type_id', 'visit_date', 'chief_complaint', 'diagnosis', 'notes']),
                'attended_by' => $request->user()?->id,
            ]);

            $this->syncDetails($visit, $request);
        });

        $this->toast('Health visit recorded.');

        return to_route('residents.show', $resident);
    }

    public function update(HealthVisitRequest $request, HealthVisit $visit): RedirectResponse
    {
        Gate::authorize('update', $visit);

        DB::transaction(function () use ($request, $visit) {
            $visit->update($request->safe()->only(['visit_type_id', 'visit_date', 'chief_complaint', 'diagnosis', 'notes']));
            $this->syncDetails($visit, $request);
        });

        $this->toast('Health visit updated.');

        return to_route('residents.show', $visit->resident_id);
    }

    public function destroy(HealthVisit $visit): RedirectResponse
    {
        Gate::authorize('delete', $visit);

        $visit->delete();

        $this->toast('Health visit removed.');

        return to_route('residents.show', $visit->resident_id);
    }

    private function syncDetails(HealthVisit $visit, HealthVisitRequest $request): void
    {
        $vitals = $request->vitals();

        if ($vitals !== []) {
            $visit->vitalSign()->updateOrCreate([], $vitals);
        } else {
            $visit->vitalSign()->delete();
        }

        /** @var array<string, mixed>|null $maternal */
        $maternal = $request->validated('maternal');

        $kind = empty($maternal['pregnancy_id']) ? null : MaternalVisitKind::from($maternal['kind']);
        $pregnancy = $kind !== null ? Pregnancy::query()->whereKey($maternal['pregnancy_id'])->first() : null;
        // Which scheduled contact this visit fulfils (keeps an existing link when re-saved).
        $rule = fn (MaternalVisitKind $k, ?int $current) => $current
            ?? ($pregnancy ? $this->schedule->ruleForVisit($pregnancy, $k, CarbonImmutable::parse($visit->visit_date), $visit->id)?->id : null);

        // A visit is prenatal or postnatal (or neither); switching kind moves the row.
        if ($kind === MaternalVisitKind::Prenatal) {
            $visit->prenatalVisit()->updateOrCreate([], [
                'pregnancy_id' => $maternal['pregnancy_id'],
                'maternal_visit_rule_id' => $rule(MaternalVisitKind::Prenatal, $visit->prenatalVisit?->maternal_visit_rule_id),
                'fundal_height_cm' => $maternal['fundal_height_cm'] ?? null,
                'fetal_heart_rate' => $maternal['fetal_heart_rate'] ?? null,
                'remarks' => $maternal['remarks'] ?? null,
            ]);
        } else {
            $visit->prenatalVisit?->delete();
        }

        if ($kind === MaternalVisitKind::Postnatal) {
            $visit->postnatalVisit()->updateOrCreate([], [
                'pregnancy_id' => $maternal['pregnancy_id'],
                'maternal_visit_rule_id' => $rule(MaternalVisitKind::Postnatal, $visit->postnatalVisit?->maternal_visit_rule_id),
                'breastfeeding' => $maternal['breastfeeding'] ?? null,
                'family_planning_counseled' => (bool) ($maternal['family_planning_counseled'] ?? false),
                'remarks' => $maternal['remarks'] ?? null,
            ]);
        } else {
            $visit->postnatalVisit?->delete();
        }

        // The visit completes the matching scheduled appointment.
        if ($pregnancy !== null) {
            $this->appointments->syncPregnancy($pregnancy->unsetRelations());
        }
    }
}
