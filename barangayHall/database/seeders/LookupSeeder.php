<?php

namespace Database\Seeders;

use App\Enums\FacilityType;
use App\Enums\FamilyPlanningCategory;
use App\Enums\MaternalVisitKind;
use App\Enums\OutbreakScope;
use App\Enums\PidsrCategory;
use App\Models\AppointmentType;
use App\Models\Condition;
use App\Models\Disease;
use App\Models\Facility;
use App\Models\FamilyPlanningMethod;
use App\Models\MaternalVisitRule;
use App\Models\ProgramType;
use App\Models\Purok;
use App\Models\RiskFactor;
use App\Models\ToiletType;
use App\Models\Vaccine;
use App\Models\VisitType;
use App\Models\WaterSource;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    /**
     * Placeholder purok names — replace with the official Kalawag II list.
     */
    public const PUROKS = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6', 'Purok 7'];

    /**
     * Purok map centres as offsets (degrees) from config('barangay.map.center').
     * Placeholders until the official purok list and survey points are known.
     *
     * @var list<array{0: float, 1: float}>
     */
    public const PUROK_CENTER_OFFSETS = [
        [0.0040, -0.0045], [0.0042, 0.0005], [0.0038, 0.0052],
        [-0.0002, -0.0048], [0.0000, 0.0040], [-0.0042, -0.0030], [-0.0040, 0.0035],
    ];

    public const VISIT_TYPES = [
        'General Consultation', 'Follow-up', 'Prenatal Check-up', 'Postnatal Check-up',
        'Immunization', 'Family Planning', 'Nutrition / Weighing', 'Dental',
    ];

    /** @var array<string, string> name => ICD-10 code */
    public const CONDITIONS = [
        'Essential hypertension' => 'I10',
        'Type 2 diabetes mellitus' => 'E11',
        'Bronchial asthma' => 'J45',
        'Pulmonary tuberculosis' => 'A15',
        'Chronic obstructive pulmonary disease' => 'J44',
        'Pneumonia' => 'J18',
        'Acute gastroenteritis' => 'A09',
        'Dengue fever' => 'A97',
        'Iron-deficiency anemia' => 'D50',
        'Undernutrition' => 'E46',
        'Urinary tract infection' => 'N39.0',
        'Goiter' => 'E04',
    ];

    /**
     * DOH Expanded Program on Immunization. Each schedule entry is the
     * recommended age in days for that dose. Vaccines without a schedule
     * (Td, HPV, influenza, PPV) are given by campaign/eligibility, not age.
     *
     * @var array<string, array{name: string, schedule: list<int>, doses?: int}>
     */
    public const VACCINES = [
        'BCG' => ['name' => 'Bacille Calmette-Guérin (BCG)', 'schedule' => [0]],
        'HEPB' => ['name' => 'Hepatitis B (birth dose)', 'schedule' => [0]],
        'PENTA' => ['name' => 'Pentavalent (DPT-HepB-Hib)', 'schedule' => [42, 70, 98]],
        'OPV' => ['name' => 'Oral Polio Vaccine (OPV)', 'schedule' => [42, 70, 98]],
        'IPV' => ['name' => 'Inactivated Polio Vaccine (IPV)', 'schedule' => [98, 270]],
        'PCV' => ['name' => 'Pneumococcal Conjugate Vaccine (PCV)', 'schedule' => [42, 70, 98]],
        'MMR' => ['name' => 'Measles-Mumps-Rubella (MMR)', 'schedule' => [270, 365]],
        'TD' => ['name' => 'Tetanus-diphtheria (Td)', 'schedule' => [], 'doses' => 5],
        'HPV' => ['name' => 'Human Papillomavirus (HPV)', 'schedule' => [], 'doses' => 2],
        'FLU' => ['name' => 'Influenza', 'schedule' => [], 'doses' => 1],
        'PPV' => ['name' => 'Pneumococcal Polysaccharide (PPV23)', 'schedule' => [], 'doses' => 1],
    ];

    /** Minimum days after the previous dose of multi-dose EPI vaccines. */
    public const MIN_INTERVAL_DAYS = 28;

    /** @var array<string, bool> name => is_safe (Level I–III or protected source) */
    public const WATER_SOURCES = [
        'Level III — piped water to the house (waterworks)' => true,
        'Level II — communal faucet' => true,
        'Level I — protected well / deep well with pump' => true,
        'Protected spring' => true,
        'Refilling station / bottled water' => true,
        'Unprotected well' => false,
        'Unprotected spring' => false,
        'River, stream or rain water' => false,
    ];

    /** @var array<string, bool> name => is_sanitary */
    public const TOILET_TYPES = [
        'Water-sealed, flush to septic tank (own)' => true,
        'Water-sealed, flush to septic tank (shared)' => true,
        'Pour-flush toilet' => true,
        'Closed pit / ventilated improved pit' => true,
        'Open pit' => false,
        'Overhung / hanging latrine' => false,
        'No toilet (open defecation)' => false,
    ];

    /** @var array<string, FamilyPlanningCategory> DOH Family Planning Program methods */
    public const FAMILY_PLANNING_METHODS = [
        'Combined oral contraceptive pills' => FamilyPlanningCategory::ModernArtificial,
        'Progestin-only pills' => FamilyPlanningCategory::ModernArtificial,
        'DMPA injectable' => FamilyPlanningCategory::ModernArtificial,
        'Progestin subdermal implant' => FamilyPlanningCategory::ModernArtificial,
        'Intrauterine device (IUD)' => FamilyPlanningCategory::ModernArtificial,
        'Condom' => FamilyPlanningCategory::ModernArtificial,
        'Bilateral tubal ligation' => FamilyPlanningCategory::ModernArtificial,
        'Vasectomy' => FamilyPlanningCategory::ModernArtificial,
        'Lactational amenorrhea method (LAM)' => FamilyPlanningCategory::ModernNatural,
        'Standard days method (SDM)' => FamilyPlanningCategory::ModernNatural,
        'Basal body temperature (BBT)' => FamilyPlanningCategory::ModernNatural,
        'Cervical mucus method' => FamilyPlanningCategory::ModernNatural,
        'Sympto-thermal method' => FamilyPlanningCategory::ModernNatural,
        'Withdrawal' => FamilyPlanningCategory::Traditional,
        'Calendar / rhythm' => FamilyPlanningCategory::Traditional,
    ];

    /** @var array<string, string> code => name */
    public const RISK_FACTORS = [
        'age_under_18' => 'Age below 18',
        'age_over_35' => 'Age above 35',
        'height_under_145' => 'Height below 145 cm',
        'grand_multipara' => 'Grand multipara (5 or more previous births)',
        'previous_cesarean' => 'Previous caesarean section',
        'previous_stillbirth' => 'Previous stillbirth or 3+ miscarriages',
        'previous_pph' => 'Previous postpartum hemorrhage',
        'hypertension' => 'Hypertension / pre-eclampsia',
        'diabetes' => 'Diabetes',
        'anemia' => 'Severe anemia',
        'heart_kidney_disease' => 'Heart or kidney disease',
        'tuberculosis' => 'Tuberculosis',
        'multiple_pregnancy' => 'Twins or more',
        'abnormal_presentation' => 'Abnormal presentation (breech / transverse)',
        'vaginal_bleeding' => 'Vaginal bleeding',
    ];

    /**
     * Default schedules (Midwife/Admin can edit them). Prenatal: WHO 2016 / DOH
     * eight ANC contacts, offset = age of gestation in days. Postnatal: within
     * 24 hours, day 3, day 7 and week 6 after delivery.
     *
     * @var array<string, list<array{0: string, 1: int}>>
     */
    public const MATERNAL_VISIT_RULES = [
        'prenatal' => [
            ['1st ANC contact (12 weeks)', 84], ['2nd ANC contact (20 weeks)', 140],
            ['3rd ANC contact (26 weeks)', 182], ['4th ANC contact (30 weeks)', 210],
            ['5th ANC contact (34 weeks)', 238], ['6th ANC contact (36 weeks)', 252],
            ['7th ANC contact (38 weeks)', 266], ['8th ANC contact (40 weeks)', 280],
        ],
        'postnatal' => [
            ['PNC within 24 hours', 1], ['PNC day 3', 3], ['PNC day 7', 7], ['PNC week 6', 42],
        ],
    ];

    /**
     * Notifiable diseases following the DOH PIDSR categories (Category I:
     * immediately notifiable; Category II: weekly). Verify against the current
     * DOH PIDSR manual — the list is editable by the Admin.
     *
     * @var array<string, array{0: string|null, 1: PidsrCategory}>
     */
    public const DISEASES = [
        'Acute flaccid paralysis' => ['G83.9', PidsrCategory::Category1],
        'Adverse event following immunization (AEFI)' => ['T88.1', PidsrCategory::Category1],
        'Anthrax' => ['A22', PidsrCategory::Category1],
        'Human avian influenza' => ['J09', PidsrCategory::Category1],
        'Measles' => ['B05', PidsrCategory::Category1],
        'Meningococcal disease' => ['A39', PidsrCategory::Category1],
        'Neonatal tetanus' => ['A33', PidsrCategory::Category1],
        'Paralytic shellfish poisoning' => ['T61.2', PidsrCategory::Category1],
        'Severe acute respiratory syndrome (SARS)' => ['U04', PidsrCategory::Category1],
        'COVID-19' => ['U07.1', PidsrCategory::Category1],
        'Acute bloody diarrhea' => ['A09', PidsrCategory::Category2],
        'Acute encephalitis syndrome' => ['G04.9', PidsrCategory::Category2],
        'Acute viral hepatitis' => ['B17.9', PidsrCategory::Category2],
        'Bacterial meningitis' => ['G00.9', PidsrCategory::Category2],
        'Chikungunya' => ['A92.0', PidsrCategory::Category2],
        'Cholera' => ['A00', PidsrCategory::Category2],
        'Dengue' => ['A97', PidsrCategory::Category2],
        'Diphtheria' => ['A36', PidsrCategory::Category2],
        'Hand, foot and mouth disease' => ['B08.4', PidsrCategory::Category2],
        'Influenza-like illness' => ['J11.1', PidsrCategory::Category2],
        'Leptospirosis' => ['A27', PidsrCategory::Category2],
        'Malaria' => ['B54', PidsrCategory::Category2],
        'Non-neonatal tetanus' => ['A35', PidsrCategory::Category2],
        'Pertussis' => ['A37', PidsrCategory::Category2],
        'Rabies' => ['A82', PidsrCategory::Category2],
        'Rotavirus' => ['A08.0', PidsrCategory::Category2],
        'Typhoid and paratyphoid fever' => ['A01', PidsrCategory::Category2],
        'Pulmonary tuberculosis' => ['A15', PidsrCategory::Other],
    ];

    /**
     * Default alert thresholds: [scope, min cases, days window]. A single case
     * of a Category I disease warrants immediate investigation.
     *
     * @var array<string, list<array{0: OutbreakScope, 1: int, 2: int}>>
     */
    public const OUTBREAK_THRESHOLDS = [
        'Dengue' => [[OutbreakScope::Purok, 3, 14], [OutbreakScope::Barangay, 5, 14]],
        'Measles' => [[OutbreakScope::Barangay, 1, 30]],
        'Acute flaccid paralysis' => [[OutbreakScope::Barangay, 1, 30]],
        'Cholera' => [[OutbreakScope::Barangay, 1, 14]],
        'Acute bloody diarrhea' => [[OutbreakScope::Purok, 3, 7]],
        'Leptospirosis' => [[OutbreakScope::Barangay, 2, 14]],
        'Typhoid and paratyphoid fever' => [[OutbreakScope::Barangay, 3, 28]],
        'Hand, foot and mouth disease' => [[OutbreakScope::Purok, 3, 14]],
        'Influenza-like illness' => [[OutbreakScope::Barangay, 10, 7]],
    ];

    /** @var array<string, array{0: FacilityType, 1: string}> */
    public const FACILITIES = [
        'Isulan Rural Health Unit' => [FacilityType::Rhu, 'Poblacion, Isulan, Sultan Kudarat'],
        'Sultan Kudarat Provincial Hospital' => [FacilityType::Hospital, 'Isulan, Sultan Kudarat'],
        'Cotabato Regional and Medical Center' => [FacilityType::Hospital, 'Cotabato City'],
    ];

    /**
     * Calendar colours chosen for at least 4.5:1 contrast with white text.
     *
     * @var array<string, array{0: string, 1: string, 2: int}> code => [name, colour, minutes]
     */
    public const APPOINTMENT_TYPES = [
        'prenatal' => ['Prenatal check-up', '#BE185D', 30],
        'postnatal' => ['Postnatal check-up', '#7E22CE', 30],
        'immunization' => ['Immunization', '#1D4ED8', 15],
        'check_up' => ['General check-up', '#047857', 20],
        'follow_up' => ['Follow-up', '#B45309', 20],
        'family_planning' => ['Family planning', '#0F766E', 20],
        'nutrition' => ['Nutrition / weighing', '#4D7C0F', 15],
    ];

    /** @var array<string, string> name => colour */
    public const PROGRAM_TYPES = [
        'Immunization day' => '#1E40AF',
        'Feeding program' => '#C2410C',
        'Medical mission' => '#B91C1C',
        'Health seminar' => '#6D28D9',
        'Clean-up drive' => '#15803D',
        'Other activity' => '#475569',
    ];

    /**
     * Seed every lookup table and the default schedules and thresholds.
     */
    public function run(): void
    {
        [$lat, $lng] = config('barangay.map.center');

        foreach (self::PUROKS as $index => $name) {
            [$dLat, $dLng] = self::PUROK_CENTER_OFFSETS[$index];

            Purok::query()->updateOrCreate(['name' => $name], [
                'sort_order' => $index + 1,
                'center_latitude' => round($lat + $dLat, 7),
                'center_longitude' => round($lng + $dLng, 7),
            ]);
        }

        foreach (self::VISIT_TYPES as $name) {
            VisitType::query()->firstOrCreate(['name' => $name]);
        }

        foreach (self::CONDITIONS as $name => $code) {
            Condition::query()->updateOrCreate(['name' => $name], ['icd10_code' => $code]);
        }

        foreach (self::VACCINES as $code => $vaccine) {
            $model = Vaccine::query()->updateOrCreate(['code' => $code], [
                'name' => $vaccine['name'],
                'doses_required' => $vaccine['doses'] ?? count($vaccine['schedule']),
            ]);

            foreach ($vaccine['schedule'] as $index => $dueAgeDays) {
                $model->schedules()->updateOrCreate(['dose_no' => $index + 1], [
                    'due_age_days' => $dueAgeDays,
                    'min_interval_days' => $index > 0 ? self::MIN_INTERVAL_DAYS : null,
                ]);
            }
        }

        $this->seedHouseholdLookups();
        $this->seedMaternalLookups();
        $this->seedSurveillanceLookups();

        foreach (self::FACILITIES as $name => [$type, $address]) {
            Facility::query()->updateOrCreate(['name' => $name], ['type' => $type, 'address' => $address]);
        }

        foreach (self::APPOINTMENT_TYPES as $code => [$name, $color, $minutes]) {
            AppointmentType::query()->updateOrCreate(['code' => $code], [
                'name' => $name, 'color' => $color, 'default_duration_minutes' => $minutes,
            ]);
        }

        foreach (self::PROGRAM_TYPES as $name => $color) {
            ProgramType::query()->updateOrCreate(['name' => $name], ['color' => $color]);
        }
    }

    private function seedHouseholdLookups(): void
    {
        foreach (array_keys(self::WATER_SOURCES) as $index => $name) {
            WaterSource::query()->updateOrCreate(['name' => $name], ['is_safe' => self::WATER_SOURCES[$name], 'sort_order' => $index + 1]);
        }

        foreach (array_keys(self::TOILET_TYPES) as $index => $name) {
            ToiletType::query()->updateOrCreate(['name' => $name], ['is_sanitary' => self::TOILET_TYPES[$name], 'sort_order' => $index + 1]);
        }

        foreach (self::FAMILY_PLANNING_METHODS as $name => $category) {
            FamilyPlanningMethod::query()->updateOrCreate(['name' => $name], ['category' => $category]);
        }
    }

    private function seedMaternalLookups(): void
    {
        foreach (self::RISK_FACTORS as $code => $name) {
            RiskFactor::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach (self::MATERNAL_VISIT_RULES as $kind => $rules) {
            foreach ($rules as $index => [$label, $offsetDays]) {
                MaternalVisitRule::query()->updateOrCreate(
                    ['kind' => MaternalVisitKind::from($kind), 'sequence' => $index + 1],
                    ['label' => $label, 'offset_days' => $offsetDays],
                );
            }
        }
    }

    private function seedSurveillanceLookups(): void
    {
        foreach (self::DISEASES as $name => [$icd10, $category]) {
            Disease::query()->updateOrCreate(['name' => $name], [
                'icd10_code' => $icd10,
                'pidsr_category' => $category,
                'is_notifiable' => $category !== PidsrCategory::Other,
            ]);
        }

        foreach (self::OUTBREAK_THRESHOLDS as $name => $thresholds) {
            $disease = Disease::query()->where('name', $name)->firstOrFail();

            foreach ($thresholds as [$scope, $minCases, $daysWindow]) {
                $disease->thresholds()->updateOrCreate(['scope' => $scope], [
                    'min_cases' => $minCases, 'days_window' => $daysWindow,
                ]);
            }
        }
    }
}
