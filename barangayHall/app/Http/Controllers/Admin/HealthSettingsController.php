<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FacilityType;
use App\Enums\MaternalVisitKind;
use App\Enums\OutbreakScope;
use App\Enums\PermissionName;
use App\Enums\PidsrCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DiseaseRequest;
use App\Http\Requests\Admin\FacilityRequest;
use App\Http\Requests\Admin\MaternalVisitRuleRequest;
use App\Http\Requests\Admin\OutbreakThresholdRequest;
use App\Http\Requests\Admin\PurokCenterRequest;
use App\Http\Requests\Admin\VaccineScheduleRuleRequest;
use App\Jobs\DetectOutbreaks;
use App\Models\Disease;
use App\Models\Facility;
use App\Models\MaternalVisitRule;
use App\Models\OutbreakThreshold;
use App\Models\Purok;
use App\Models\VaccineScheduleRule;
use App\Services\AppointmentGenerator;
use App\Services\LookupOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings page: outbreak thresholds, the notifiable disease list,
 * facilities and purok map centres (Admin, lookups.manage); prenatal /
 * postnatal and immunization schedules (Midwife/Admin, schedules.manage).
 */
class HealthSettingsController extends Controller
{
    public function index(Request $request, LookupOptions $lookups): Response
    {
        $user = $request->user();
        $lookupsAllowed = $user?->can(PermissionName::LookupsManage->value) ?? false;
        $schedulesAllowed = $user?->can(PermissionName::SchedulesManage->value) ?? false;

        abort_unless($lookupsAllowed || $schedulesAllowed, 403);

        return Inertia::render('health-settings/index', [
            // Not named `can`: a page prop would replace the shared `can` flags (sidebar).
            'allowed' => ['lookups' => $lookupsAllowed, 'schedules' => $schedulesAllowed],
            'thresholds' => $lookupsAllowed ? OutbreakThreshold::query()->with('disease:id,name')->get()
                ->sortBy('disease.name')->values()
                ->map(fn (OutbreakThreshold $t) => [
                    'id' => $t->id, 'disease_id' => $t->disease_id, 'disease' => $t->disease->name,
                    'scope' => ['value' => $t->scope->value, 'label' => $t->scope->label()],
                    'min_cases' => $t->min_cases, 'days_window' => $t->days_window, 'is_active' => $t->is_active,
                ]) : null,
            'diseases' => $lookupsAllowed ? Disease::query()->orderBy('name')->get()->map(fn (Disease $d) => [
                'id' => $d->id, 'name' => $d->name, 'icd10_code' => $d->icd10_code,
                'pidsr_category' => ['value' => $d->pidsr_category->value, 'label' => $d->pidsr_category->label()],
                'is_notifiable' => $d->is_notifiable, 'is_active' => $d->is_active,
            ]) : null,
            'facilities' => $lookupsAllowed ? Facility::query()->orderBy('name')->get()->map(fn (Facility $f) => [
                'id' => $f->id, 'name' => $f->name, 'type' => ['value' => $f->type->value, 'label' => $f->type->label()],
                'address' => $f->address, 'contact_no' => $f->contact_no, 'is_active' => $f->is_active,
            ]) : null,
            'puroks' => $lookupsAllowed ? Purok::query()->orderBy('sort_order')->get()->map(fn (Purok $p) => [
                'id' => $p->id, 'name' => $p->name,
                'center_latitude' => $p->center_latitude !== null ? (float) $p->center_latitude : null,
                'center_longitude' => $p->center_longitude !== null ? (float) $p->center_longitude : null,
            ]) : null,
            'maternalRules' => $schedulesAllowed ? MaternalVisitRule::query()->orderBy('sequence')->get()
                ->sortBy(fn (MaternalVisitRule $r) => [$r->kind === MaternalVisitKind::Prenatal ? 0 : 1, $r->sequence])->values()
                ->map(fn (MaternalVisitRule $r) => [
                    'id' => $r->id, 'kind' => ['value' => $r->kind->value, 'label' => $r->kind->label()],
                    'sequence' => $r->sequence, 'label' => $r->label, 'offset_days' => $r->offset_days, 'is_active' => $r->is_active,
                ]) : null,
            'vaccineRules' => $schedulesAllowed ? VaccineScheduleRule::query()->with('vaccine:id,code,name')->orderBy('due_age_days')->get()
                ->map(fn (VaccineScheduleRule $r) => [
                    'id' => $r->id, 'vaccine_id' => $r->vaccine_id, 'vaccine' => $r->vaccine->name, 'vaccine_code' => $r->vaccine->code,
                    'dose_no' => $r->dose_no, 'due_age_days' => $r->due_age_days, 'min_interval_days' => $r->min_interval_days,
                ]) : null,
            'options' => [
                'scopes' => OutbreakScope::options(),
                'pidsrCategories' => PidsrCategory::options(),
                'facilityTypes' => FacilityType::options(),
                'visitKinds' => MaternalVisitKind::options(),
                'diseases' => $lookupsAllowed ? $lookups->diseases(false) : [],
                'vaccines' => $schedulesAllowed ? $lookups->vaccines() : [],
            ],
            'map' => ['center' => config('barangay.map.center'), 'zoom' => config('barangay.map.zoom')],
        ]);
    }

    public function storeThreshold(OutbreakThresholdRequest $request): RedirectResponse
    {
        $threshold = OutbreakThreshold::query()->create([...$request->validated(), 'updated_by' => $request->user()?->id]);
        DetectOutbreaks::dispatch($threshold->disease_id);

        return $this->saved('Outbreak threshold added.');
    }

    public function updateThreshold(OutbreakThresholdRequest $request, OutbreakThreshold $threshold): RedirectResponse
    {
        $threshold->update([...$request->validated(), 'updated_by' => $request->user()?->id]);
        // A lower threshold may already be crossed.
        DetectOutbreaks::dispatch($threshold->disease_id);

        return $this->saved('Outbreak threshold updated.');
    }

    public function destroyThreshold(OutbreakThreshold $threshold): RedirectResponse
    {
        Gate::authorize(PermissionName::LookupsManage->value);

        $threshold->delete();

        return $this->saved('Outbreak threshold removed.');
    }

    public function storeDisease(DiseaseRequest $request): RedirectResponse
    {
        Disease::query()->create($request->validated());

        return $this->saved('Disease added to the list.');
    }

    public function updateDisease(DiseaseRequest $request, Disease $disease): RedirectResponse
    {
        $disease->update($request->validated());

        return $this->saved('Disease updated.');
    }

    public function storeFacility(FacilityRequest $request): RedirectResponse
    {
        Facility::query()->create($request->validated());

        return $this->saved('Facility added.');
    }

    public function updateFacility(FacilityRequest $request, Facility $facility): RedirectResponse
    {
        $facility->update($request->validated());

        return $this->saved('Facility updated.');
    }

    public function updatePurokCenter(PurokCenterRequest $request, Purok $purok): RedirectResponse
    {
        $purok->update($request->validated());

        return $this->saved("Map centre of {$purok->name} saved.");
    }

    public function storeMaternalRule(MaternalVisitRuleRequest $request, AppointmentGenerator $appointments): RedirectResponse
    {
        MaternalVisitRule::query()->create($request->validated());

        return $this->scheduleChanged($appointments, 'Contact added to the schedule.');
    }

    public function updateMaternalRule(MaternalVisitRuleRequest $request, MaternalVisitRule $rule, AppointmentGenerator $appointments): RedirectResponse
    {
        $rule->update($request->validated());

        return $this->scheduleChanged($appointments, 'Schedule updated.');
    }

    public function storeVaccineRule(VaccineScheduleRuleRequest $request, AppointmentGenerator $appointments): RedirectResponse
    {
        VaccineScheduleRule::query()->create($request->validated());

        return $this->scheduleChanged($appointments, 'Dose added to the immunization schedule.');
    }

    public function updateVaccineRule(VaccineScheduleRuleRequest $request, VaccineScheduleRule $vaccineRule, AppointmentGenerator $appointments): RedirectResponse
    {
        $vaccineRule->update($request->validated());

        return $this->scheduleChanged($appointments, 'Immunization schedule updated.');
    }

    private function saved(string $message): RedirectResponse
    {
        $this->toast($message);

        return back();
    }

    /**
     * Re-plan generated appointments with the new schedule (staff-edited ones stay).
     */
    private function scheduleChanged(AppointmentGenerator $appointments, string $message): RedirectResponse
    {
        $appointments->syncAll();

        return $this->saved($message.' Upcoming appointments were re-planned.');
    }
}
