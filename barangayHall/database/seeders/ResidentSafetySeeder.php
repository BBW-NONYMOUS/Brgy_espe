<?php

namespace Database\Seeders;

use App\Enums\AlertSeverity;
use App\Enums\ConsentType;
use App\Enums\MedicalAlertType;
use App\Models\FamilyPlanningMethod;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\FindsSeededResidents;
use Illuminate\Database\Seeder;

/**
 * Emergency contacts, medical alerts (one critical allergy for the red
 * banner) and family planning methods for residents who consented.
 */
class ResidentSafetySeeder extends Seeder
{
    use FindsSeededResidents;

    /**
     * [last, first] => list of [name, relationship, phone, primary]
     *
     * @var array<string, list<array{0: string, 1: string, 2: string, 3: bool}>>
     */
    private const CONTACTS = [
        'Villanueva|Lourdes' => [['Marites Dela Cruz', 'Daughter', '09181234502', true], ['Joshua Dela Cruz', 'Grandson', '09991234503', false]],
        'Gallardo|Ernesto' => [['Ramon Gallardo', 'Son', '09171234505', true]],
        'Gallardo|Kristine Mae' => [['Ramon Gallardo', 'Husband', '09171234505', true], ['Remedios Gallardo', 'Mother-in-law', '09171234599', false]],
        'Bautista|Felicidad' => [['Lorna Pescadero', 'Daughter', '09461234508', true]],
        'Ocampo|Rhealyn' => [['Jun Rey Santos', 'Partner', '09561234509', true]],
        'Lumabao|Benjamin' => [['Rosalie Lumabao', 'Daughter', '09661234511', true]],
        'Tolentino|Pedro' => [['Carmelita Tolentino', 'Daughter', '09281234516', true]],
        'Reyes|Joy Ann' => [['Michael Angelo Reyes', 'Husband', '09391234517', true]],
        'Ramos|Teodoro' => [['Paolo Ramos', 'Son', '09451234520', true], ['Nenita Ramos', 'Wife', '09451234521', false]],
        'Panganiban|Maricel' => [['Jose Rizalino Panganiban', 'Husband', '09561234521', true]],
        'Ignacio|Asuncion' => [['Rogelio Ignacio', 'Son (Koronadal City)', '09171239876', true]],
        'Abdulrahman|Jamil' => [['Mohammad Abdulrahman', 'Father', '09351234507', true]],
    ];

    /**
     * [last|first, type, description, severity, active]
     *
     * @var list<array{0: string, 1: MedicalAlertType, 2: string, 3: AlertSeverity, 4: bool}>
     */
    private const ALERTS = [
        ['Villanueva|Kyle', MedicalAlertType::Allergy, 'Penicillin allergy: hives and difficulty breathing after amoxicillin (2019). Do not give penicillins.', AlertSeverity::Critical, true],
        ['Villanueva|Lourdes', MedicalAlertType::Allergy, 'Sulfa drug allergy (cotrimoxazole): severe rash.', AlertSeverity::Critical, true],
        ['Villanueva|Lourdes', MedicalAlertType::Chronic, 'Type 2 diabetes on maintenance medication; check blood sugar before procedures.', AlertSeverity::Warning, true],
        ['Ocampo|Rhealyn', MedicalAlertType::Other, 'Previous caesarean section: facility-based delivery only.', AlertSeverity::Critical, true],
        ['Tolentino|Pedro', MedicalAlertType::Disability, 'Bedridden after stroke (2022); home visits only.', AlertSeverity::Warning, true],
        ['Abdulrahman|Sittie Aisha', MedicalAlertType::Chronic, 'Bronchial asthma; keep salbutamol available during visits.', AlertSeverity::Warning, true],
        ['Lumabao|Benjamin', MedicalAlertType::Chronic, 'COPD; avoid sedating cough medicines.', AlertSeverity::Info, true],
        ['Pescadero|Mark Anthony', MedicalAlertType::Disability, 'Hearing impairment; give written instructions.', AlertSeverity::Info, true],
        ['Kamid|Faisal', MedicalAlertType::Allergy, 'Shrimp and crab allergy (itching, lip swelling).', AlertSeverity::Warning, true],
        ['Navarro|Ella Mae', MedicalAlertType::Allergy, 'Suspected mefenamic acid allergy; later ruled out by the RHU physician.', AlertSeverity::Warning, false],
    ];

    /**
     * [last|first, method, started (relative), ended (relative)|null]
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string|null}>
     */
    private const FAMILY_PLANNING = [
        ['Abdulrahman|Normina', 'DMPA injectable', '-4 years', null],
        ['Navarro|Ella Mae', 'Combined oral contraceptive pills', '-7 months', null],
        ['Pescadero|Lorna', 'Bilateral tubal ligation', '2011-06-15', null],
        ['Reyes|Joy Ann', 'Lactational amenorrhea method (LAM)', '-5 weeks', null],
        ['Panganiban|Maricel', 'Combined oral contraceptive pills', '-2 years', '-5 months'],
        ['Villanueva|Jennifer', 'Lactational amenorrhea method (LAM)', '-3 weeks', null],
    ];

    public function run(): void
    {
        $bhw = $this->user('bhw.analiza');
        $midwife = $this->user('mw.cristina');

        foreach (self::CONTACTS as $key => $contacts) {
            $resident = $this->resident(...explode('|', $key));

            foreach ($contacts as [$name, $relationship, $phone, $primary]) {
                $resident->emergencyContacts()->create([
                    'name' => $name, 'relationship' => $relationship, 'phone' => $phone, 'is_primary' => $primary,
                ]);
            }
        }

        foreach (self::ALERTS as [$key, $type, $description, $severity, $active]) {
            $this->resident(...explode('|', $key))->medicalAlerts()->create([
                'alert_type' => $type,
                'description' => $description,
                'severity' => $severity,
                'is_active' => $active,
                'recorded_by' => $midwife->id,
            ]);
        }

        $methods = FamilyPlanningMethod::query()->pluck('id', 'name');

        foreach (self::FAMILY_PLANNING as [$key, $method, $started, $ended]) {
            $resident = $this->resident(...explode('|', $key));
            $startedOn = CarbonImmutable::parse($started)->startOfDay();

            // Family planning data is only kept with the resident's specific consent.
            $resident->consents()->create([
                'consent_type' => ConsentType::FamilyPlanning,
                'consent_date' => $startedOn->max(CarbonImmutable::today()->subMonths(9)),
                'obtained_by' => $midwife->id,
                'purpose' => 'Recording the family planning method for FP program services and reports (RA 10173)',
            ]);

            $resident->familyPlanning()->create([
                'family_planning_method_id' => $methods[$method],
                'started_on' => $startedOn,
                'ended_on' => $ended !== null ? CarbonImmutable::parse($ended)->startOfDay() : null,
                'recorded_by' => $bhw->id,
            ]);
        }
    }
}
