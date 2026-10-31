<?php

namespace App\Services;

use App\Enums\CaseClassification;
use App\Enums\CaseOutcome;
use App\Enums\ConditionStatus;
use App\Enums\ConsentType;
use App\Enums\RiskLevel;
use App\Models\Consent;
use App\Models\EmergencyContact;
use App\Models\Household;
use App\Models\MedicalAlert;
use App\Models\Resident;
use App\Models\ResidentCondition;
use Illuminate\Support\Collection;

/**
 * Data for the printable family health summary (one household). Leaves out
 * diagnoses and visit notes; family planning only with the member's consent.
 */
class FamilyHealthSummary
{
    public function __construct(
        private readonly ImmunizationSchedule $immunizations,
        private readonly MicronutrientSchedule $micronutrients,
    ) {}

    /**
     * @return array{household: Household, members: Collection<int, array{resident: Resident, age: string, flags: list<array{0: string, 1: bool}>, details: list<array{0: string, 1: string}>}>, contacts: Collection<int, EmergencyContact>}
     */
    public function build(Household $household): array
    {
        $household->load([
            'purok', 'head', 'waterSource', 'toiletType',
            'members' => fn ($q) => $q->orderBy('birthdate')->with([
                'pregnancies.riskFactors', 'consents', 'immunizations', 'micronutrientRecords',
                'growthMeasurements' => fn ($g) => $g->latest('measured_on'),
                'conditions' => fn ($c) => $c->with('condition')->where('status', '!=', ConditionStatus::Resolved),
                'medicalAlerts' => fn ($a) => $a->where('is_active', true),
                'familyPlanning' => fn ($f) => $f->with('method')->whereNull('ended_on'),
                'diseaseCases' => fn ($d) => $d->with('disease')->where('outcome', CaseOutcome::Ongoing)->where('classification', '!=', CaseClassification::RuledOut),
                'emergencyContacts',
            ]),
        ]);

        $members = $household->members->map(fn (Resident $m) => [
            'resident' => $m,
            'age' => $m->age > 0 ? "{$m->age} yrs" : ((int) $m->birthdate->diffInMonths(now())).' mo',
            'flags' => $this->flags($m),
            'details' => $this->details($m),
        ]);

        return [
            'household' => $household,
            'members' => $members,
            'contacts' => $household->members->flatMap->emergencyContacts->sortByDesc('is_primary')->values(),
        ];
    }

    /**
     * @return list<array{0: string, 1: bool}> label, highlight in red
     */
    private function flags(Resident $m): array
    {
        $pregnancy = $m->currentPregnancy();
        $flags = [];

        if ($m->medicalAlerts->contains(fn (MedicalAlert $a) => $a->severity->value === 'critical')) {
            $flags[] = ['Critical alert', true];
        }
        if ($pregnancy) {
            $flags[] = [$pregnancy->risk_level === RiskLevel::High ? 'High-risk pregnancy' : 'Pregnant', $pregnancy->risk_level === RiskLevel::High];
        }
        if ($m->age < 5) {
            $flags[] = ['Under 5', false];
        }
        if ($m->isSenior()) {
            $flags[] = ['Senior', false];
        }
        if ($m->is_pwd) {
            $flags[] = ['PWD', false];
        }
        if ($m->diseaseCases->isNotEmpty()) {
            $flags[] = ['Active disease case', true];
        }

        return $flags;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function details(Resident $m): array
    {
        $rows = [];

        foreach ($m->medicalAlerts as $alert) {
            $rows[] = ["{$alert->severity->label()} alert", "{$alert->alert_type->label()}: {$alert->description}"];
        }

        if ($m->conditions->isNotEmpty()) {
            $rows[] = ['Conditions', $m->conditions->map(fn (ResidentCondition $c) => "{$c->condition->name} ({$c->status->label()})")->implode('; ')];
        }

        if ($pregnancy = $m->currentPregnancy()) {
            $rows[] = ['Pregnancy', "EDD {$pregnancy->edd->format('M j, Y')} · {$pregnancy->gestationalAgeWeeks()} weeks · G{$pregnancy->gravida}P{$pregnancy->para} · {$pregnancy->risk_level->label()}"];
        }

        if ($m->age < 5) {
            $doses = $this->immunizations->forResident($m);
            $overdue = $doses->where('status', 'overdue')->pluck('vaccine_code')->unique()->implode(', ');
            $rows[] = ['Immunization', "{$doses->where('status', 'given')->count()} of {$doses->count()} scheduled doses given".($overdue !== '' ? " — overdue: {$overdue}" : '')];

            if ($latest = $m->growthMeasurements->first()) {
                $rows[] = ['Latest weighing', "{$latest->measured_on->format('M j, Y')}: {$latest->weight_kg} kg".($latest->height_cm ? ", {$latest->height_cm} cm" : '').($latest->nutritional_status ? " — {$latest->nutritional_status->label()}" : '')];
            }

            $micro = $this->micronutrients->forChild($m);
            if ($micro['vitamin_a']['due']) {
                $rows[] = ['Vitamin A', 'Due now'];
            }
        }

        foreach ($m->diseaseCases as $case) {
            $rows[] = ['Disease case', "{$case->disease->name} (onset {$case->onset_date->format('M j, Y')}, {$case->classification->label()})"];
        }

        $fpConsent = $m->consents->contains(fn (Consent $c) => $c->consent_type === ConsentType::FamilyPlanning && $c->revoked_at === null);
        if ($fpConsent && ($method = $m->familyPlanning->first())) {
            $rows[] = ['Family planning', "{$method->method->name} since {$method->started_on->format('M Y')}"];
        }

        return $rows;
    }
}
