<?php

namespace Database\Seeders;

use App\Enums\BirthAttendant;
use App\Enums\BloodType;
use App\Enums\BreastfeedingStatus;
use App\Enums\ConditionStatus;
use App\Enums\ConsentType;
use App\Enums\DeliveryMode;
use App\Enums\DeliveryPlace;
use App\Enums\MaternalStatus;
use App\Enums\MaternalVisitKind;
use App\Enums\RelationshipToHead;
use App\Enums\ResidentStatus;
use App\Enums\RiskLevel;
use App\Models\Condition;
use App\Models\HealthVisit;
use App\Models\Household;
use App\Models\MaternalVisitRule;
use App\Models\Pregnancy;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\RiskFactor;
use App\Models\ToiletType;
use App\Models\User;
use App\Models\VaccineScheduleRule;
use App\Models\VisitType;
use App\Models\WaterSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * ~50 realistic residents of Barangay Kalawag II. Infant birthdates and
 * pregnancy dates are relative to today so the "children due for
 * immunization" and "pregnant women" reports always have data.
 *
 * Member keys: last, first, middle, suffix?, sex, born (date or relative
 * string), civil, rel, contact?, philhealth?, pwd?, 4ps? (makes the whole
 * household 4Ps), mother? (first name of the mother in the same household),
 * conditions? [name => [diagnosed, status]], consults? [[when, complaint,
 * diagnosis]], epi? ['until' => days] | ['skip' => ['CODE-dose']],
 * pregnancy?, archived?
 *
 * Households HH-0013 and HH-0014 are deliberately not geo-tagged yet.
 */
class ResidentSeeder extends Seeder
{
    private const CONSENT_PURPOSE = 'Barangay health profiling and delivery of primary health care services (RA 10173)';

    private const GEOTAG_PURPOSE = 'Recording the household location for health service delivery and disease surveillance (RA 10173)';

    /** Households still waiting for a geo-tag visit. */
    private const NOT_GEOTAGGED = ['HH-0013', 'HH-0014'];

    private int $sequence = 0;

    private User $bhw;

    private User $bhw2;

    private User $midwife;

    /** @var Collection<string, int> */
    private Collection $visitTypes;

    /** @var Collection<string, int> */
    private Collection $conditions;

    /** @var Collection<int, MaternalVisitRule> */
    private Collection $prenatalRules;

    /** @var Collection<int, MaternalVisitRule> */
    private Collection $postnatalRules;

    /**
     * @return list<array{no: string, purok: string, address: string, members: list<array<string, mixed>>}>
     */
    private function households(): array
    {
        return [
            ['no' => 'HH-0001', 'purok' => 'Purok 1', 'address' => 'Near Kalawag II Elementary School', 'members' => [
                ['last' => 'Dela Cruz', 'first' => 'Rodrigo', 'middle' => 'Santos', 'sex' => 'male', 'born' => '1968-03-14', 'civil' => 'married', 'rel' => 'head', 'contact' => '09171234501', 'philhealth' => '02-051234567-1'],
                ['last' => 'Dela Cruz', 'first' => 'Marites', 'middle' => 'Villanueva', 'sex' => 'female', 'born' => '1972-07-22', 'civil' => 'married', 'rel' => 'spouse', 'contact' => '09181234502', 'philhealth' => '02-051234568-9',
                    'conditions' => ['Essential hypertension' => ['2021-05-10', 'controlled']]],
                ['last' => 'Dela Cruz', 'first' => 'Joshua', 'middle' => 'Villanueva', 'sex' => 'male', 'born' => '2001-11-05', 'civil' => 'single', 'rel' => 'child', 'contact' => '09991234503'],
                ['last' => 'Dela Cruz', 'first' => 'Angelica', 'middle' => 'Villanueva', 'sex' => 'female', 'born' => '2004-02-18', 'civil' => 'single', 'rel' => 'child',
                    'consults' => [['-3 weeks', 'Fever and body aches for 3 days', 'Dengue fever, suspected']]],
                ['last' => 'Villanueva', 'first' => 'Lourdes', 'middle' => 'Pascual', 'sex' => 'female', 'born' => '1945-09-30', 'civil' => 'widowed', 'rel' => 'in_law', 'pwd' => true, 'philhealth' => '02-000456789-3',
                    'conditions' => ['Type 2 diabetes mellitus' => ['2015-02-01', 'active'], 'Essential hypertension' => ['2012-08-20', 'controlled']]],
            ]],
            ['no' => 'HH-0002', 'purok' => 'Purok 1', 'address' => 'Along National Highway, beside Gallardo Sari-sari Store', 'members' => [
                ['last' => 'Gallardo', 'first' => 'Ernesto', 'middle' => 'Tolentino', 'sex' => 'male', 'born' => '1958-01-09', 'civil' => 'married', 'rel' => 'head', 'contact' => '09281234504', 'philhealth' => '02-000987654-2',
                    'conditions' => ['Essential hypertension' => ['2018-04-02', 'active']]],
                ['last' => 'Gallardo', 'first' => 'Remedios', 'middle' => 'Abad', 'sex' => 'female', 'born' => '1961-06-12', 'civil' => 'married', 'rel' => 'spouse', 'philhealth' => '02-000987655-0'],
                ['last' => 'Gallardo', 'first' => 'Ramon', 'middle' => 'Abad', 'sex' => 'male', 'born' => '1990-04-27', 'civil' => 'married', 'rel' => 'child', 'contact' => '09171234505'],
                ['last' => 'Gallardo', 'first' => 'Kristine Mae', 'middle' => 'Lopez', 'sex' => 'female', 'born' => '1995-08-03', 'civil' => 'married', 'rel' => 'in_law', 'contact' => '09171234506', 'philhealth' => '12-201234567-4',
                    'pregnancy' => ['weeks' => 20, 'gravida' => 2, 'para' => 1, 'risk' => 'low']],
                ['last' => 'Gallardo', 'first' => 'Ethan', 'middle' => 'Lopez', 'sex' => 'male', 'born' => '-4 months', 'civil' => 'single', 'rel' => 'grandchild', 'epi' => ['until' => 45]],
            ]],
            ['no' => 'HH-0003', 'purok' => 'Purok 2', 'address' => 'Purok 2, near the Masjid', 'members' => [
                ['last' => 'Abdulrahman', 'first' => 'Mohammad', 'middle' => 'Kusain', 'sex' => 'male', 'born' => '1975-12-01', 'civil' => 'married', 'rel' => 'head', 'contact' => '09351234507', '4ps' => true],
                ['last' => 'Abdulrahman', 'first' => 'Normina', 'middle' => 'Mamalinta', 'sex' => 'female', 'born' => '1980-05-15', 'civil' => 'married', 'rel' => 'spouse', '4ps' => true,
                    'conditions' => ['Goiter' => ['2020-09-14', 'active']]],
                ['last' => 'Abdulrahman', 'first' => 'Ahmad', 'middle' => 'Mamalinta', 'sex' => 'male', 'born' => '2008-09-10', 'civil' => 'single', 'rel' => 'child', '4ps' => true, 'mother' => 'Normina'],
                ['last' => 'Abdulrahman', 'first' => 'Sittie Aisha', 'middle' => 'Mamalinta', 'sex' => 'female', 'born' => '2012-03-21', 'civil' => 'single', 'rel' => 'child', '4ps' => true, 'mother' => 'Normina',
                    'conditions' => ['Bronchial asthma' => ['2016-07-01', 'controlled']]],
                ['last' => 'Abdulrahman', 'first' => 'Jamil', 'middle' => 'Mamalinta', 'sex' => 'male', 'born' => '-14 months', 'civil' => 'single', 'rel' => 'child', '4ps' => true, 'mother' => 'Normina', 'epi' => ['skip' => ['MMR-2']]],
            ]],
            ['no' => 'HH-0004', 'purok' => 'Purok 2', 'address' => 'Purok 2, Sitio Riverside', 'members' => [
                ['last' => 'Pescadero', 'first' => 'Lorna', 'middle' => 'Bautista', 'sex' => 'female', 'born' => '1983-10-19', 'civil' => 'separated', 'rel' => 'head', 'contact' => '09461234508', '4ps' => true],
                ['last' => 'Pescadero', 'first' => 'Mark Anthony', 'middle' => 'Bautista', 'sex' => 'male', 'born' => '2006-01-30', 'civil' => 'single', 'rel' => 'child', 'pwd' => true, '4ps' => true, 'mother' => 'Lorna'],
                ['last' => 'Pescadero', 'first' => 'Princess Joy', 'middle' => 'Bautista', 'sex' => 'female', 'born' => '2010-07-07', 'civil' => 'single', 'rel' => 'child', '4ps' => true, 'mother' => 'Lorna',
                    'conditions' => ['Iron-deficiency anemia' => ['2025-06-11', 'active']]],
                ['last' => 'Bautista', 'first' => 'Felicidad', 'middle' => 'Ramos', 'sex' => 'female', 'born' => '1950-02-11', 'civil' => 'widowed', 'rel' => 'parent', 'philhealth' => '02-000112233-5',
                    'conditions' => ['Essential hypertension' => ['2010-03-03', 'active'], 'Type 2 diabetes mellitus' => ['2017-11-20', 'active']]],
            ]],
            ['no' => 'HH-0005', 'purok' => 'Purok 3', 'address' => 'Purok 3, behind the Barangay Hall', 'members' => [
                ['last' => 'Santos', 'first' => 'Jun Rey', 'middle' => 'Aquino', 'sex' => 'male', 'born' => '1992-05-05', 'civil' => 'live_in', 'rel' => 'head', 'contact' => '09561234509'],
                ['last' => 'Ocampo', 'first' => 'Rhealyn', 'middle' => 'Diaz', 'sex' => 'female', 'born' => '1997-12-12', 'civil' => 'live_in', 'rel' => 'spouse', 'contact' => '09561234510', 'philhealth' => '12-209876543-1',
                    'pregnancy' => ['weeks' => 30, 'gravida' => 2, 'para' => 1, 'risk' => 'high', 'risk_factors' => ['previous_cesarean'], 'skip_contacts' => [3], 'notes' => 'Previous delivery by caesarean section. Refer to Sultan Kudarat Provincial Hospital for delivery.']],
                ['last' => 'Santos', 'first' => 'Zia', 'middle' => 'Ocampo', 'sex' => 'female', 'born' => '-3 years -2 months', 'civil' => 'single', 'rel' => 'child', 'mother' => 'Rhealyn', 'epi' => ['skip' => []]],
            ]],
            ['no' => 'HH-0006', 'purok' => 'Purok 3', 'address' => 'Purok 3, near the rice mill', 'members' => [
                ['last' => 'Lumabao', 'first' => 'Benjamin', 'middle' => 'Cortez', 'sex' => 'male', 'born' => '1955-08-08', 'civil' => 'widowed', 'rel' => 'head', 'pwd' => true, 'philhealth' => '02-000334455-7',
                    'conditions' => ['Chronic obstructive pulmonary disease' => ['2019-01-15', 'active']]],
                ['last' => 'Lumabao', 'first' => 'Rosalie', 'middle' => 'Cortez', 'sex' => 'female', 'born' => '1985-03-03', 'civil' => 'single', 'rel' => 'child', 'contact' => '09661234511'],
                ['last' => 'Lumabao', 'first' => 'Gabriel', 'middle' => 'Cortez', 'sex' => 'male', 'born' => '2015-06-06', 'civil' => 'single', 'rel' => 'grandchild',
                    'consults' => [['-2 months', 'Loose bowel movement, 4 episodes', 'Acute gastroenteritis']]],
                ['last' => 'Lumabao', 'first' => 'Corazon', 'middle' => 'Medina', 'sex' => 'female', 'born' => '1957-04-21', 'civil' => 'married', 'rel' => 'spouse', 'status' => 'deceased', 'archived' => 'Deceased — death certificate presented by family (2025-08-14)'],
            ]],
            ['no' => 'HH-0007', 'purok' => 'Purok 4', 'address' => 'Purok 4, Villanueva Compound', 'members' => [
                ['last' => 'Villanueva', 'first' => 'Arnel', 'middle' => 'Garcia', 'sex' => 'male', 'born' => '1979-09-09', 'civil' => 'married', 'rel' => 'head', 'contact' => '09771234512', 'philhealth' => '02-051112223-6'],
                ['last' => 'Villanueva', 'first' => 'Jennifer', 'middle' => 'Ortiz', 'sex' => 'female', 'born' => '1981-11-11', 'civil' => 'married', 'rel' => 'spouse', 'contact' => '09771234513',
                    'conditions' => ['Type 2 diabetes mellitus' => ['2022-10-05', 'controlled']]],
                ['last' => 'Villanueva', 'first' => 'Kyle', 'middle' => 'Ortiz', 'sex' => 'male', 'born' => '2011-01-01', 'civil' => 'single', 'rel' => 'child', 'mother' => 'Jennifer'],
                ['last' => 'Villanueva', 'first' => 'Kaye', 'middle' => 'Ortiz', 'sex' => 'female', 'born' => '2014-04-04', 'civil' => 'single', 'rel' => 'child', 'mother' => 'Jennifer'],
                ['last' => 'Villanueva', 'first' => 'Liam', 'middle' => 'Ortiz', 'sex' => 'male', 'born' => '-3 weeks', 'civil' => 'single', 'rel' => 'child', 'mother' => 'Jennifer', 'epi' => ['until' => 0]],
            ]],
            ['no' => 'HH-0008', 'purok' => 'Purok 4', 'address' => 'Purok 4, near Kalawag II Chapel', 'members' => [
                ['last' => 'Kamid', 'first' => 'Saidamen', 'middle' => 'Ali', 'sex' => 'male', 'born' => '1966-04-14', 'civil' => 'married', 'rel' => 'head', 'contact' => '09181234514'],
                ['last' => 'Kamid', 'first' => 'Rohaida', 'middle' => 'Usman', 'sex' => 'female', 'born' => '1970-10-10', 'civil' => 'married', 'rel' => 'spouse',
                    'conditions' => ['Pulmonary tuberculosis' => ['2024-02-12', 'resolved']]],
                ['last' => 'Kamid', 'first' => 'Faisal', 'middle' => 'Usman', 'sex' => 'male', 'born' => '1998-02-02', 'civil' => 'single', 'rel' => 'child', 'contact' => '09181234515'],
                ['last' => 'Kamid', 'first' => 'Hanna', 'middle' => 'Usman', 'sex' => 'female', 'born' => '2003-03-03', 'civil' => 'single', 'rel' => 'child'],
            ]],
            ['no' => 'HH-0009', 'purok' => 'Purok 5', 'address' => 'Purok 5, Tolentino Street', 'members' => [
                ['last' => 'Tolentino', 'first' => 'Pedro', 'middle' => 'Javier', 'sex' => 'male', 'born' => '1940-12-25', 'civil' => 'widowed', 'rel' => 'head', 'pwd' => true, 'philhealth' => '02-000010203-8',
                    'conditions' => ['Essential hypertension' => ['2005-06-30', 'controlled']]],
                ['last' => 'Tolentino', 'first' => 'Carmelita', 'middle' => 'Javier', 'sex' => 'female', 'born' => '1970-01-15', 'civil' => 'single', 'rel' => 'child', 'contact' => '09281234516'],
            ]],
            ['no' => 'HH-0010', 'purok' => 'Purok 5', 'address' => 'Purok 5, near the basketball court', 'members' => [
                ['last' => 'Reyes', 'first' => 'Michael Angelo', 'middle' => 'Cruz', 'sex' => 'male', 'born' => '1988-07-17', 'civil' => 'married', 'rel' => 'head', 'contact' => '09391234517', '4ps' => true],
                ['last' => 'Reyes', 'first' => 'Joy Ann', 'middle' => 'Manalo', 'sex' => 'female', 'born' => '1990-09-19', 'civil' => 'married', 'rel' => 'spouse', 'contact' => '09391234518', '4ps' => true, 'philhealth' => '12-205554443-2',
                    'pregnancy' => ['delivered_weeks_ago' => 5, 'gravida' => 2, 'para' => 2, 'risk' => 'low']],
                ['last' => 'Reyes', 'first' => 'Althea', 'middle' => 'Manalo', 'sex' => 'female', 'born' => '2018-10-10', 'civil' => 'single', 'rel' => 'child', '4ps' => true, 'mother' => 'Joy Ann'],
                ['last' => 'Reyes', 'first' => 'Matteo', 'middle' => 'Manalo', 'sex' => 'male', 'born' => '-5 weeks', 'civil' => 'single', 'rel' => 'child', '4ps' => true, 'mother' => 'Joy Ann', 'epi' => ['until' => 0]],
            ]],
            ['no' => 'HH-0011', 'purok' => 'Purok 6', 'address' => 'Purok 6, Ramos Subdivision', 'members' => [
                ['last' => 'Ramos', 'first' => 'Teodoro', 'middle' => 'Lim', 'sex' => 'male', 'born' => '1963-05-25', 'civil' => 'married', 'rel' => 'head', 'contact' => '09451234519', 'philhealth' => '02-000778899-4',
                    'conditions' => ['Type 2 diabetes mellitus' => ['2016-09-09', 'active']]],
                ['last' => 'Ramos', 'first' => 'Nenita', 'middle' => 'Sison', 'sex' => 'female', 'born' => '1965-08-30', 'civil' => 'married', 'rel' => 'spouse', 'philhealth' => '02-000778900-1',
                    'conditions' => ['Essential hypertension' => ['2014-01-22', 'active']]],
                ['last' => 'Ramos', 'first' => 'Paolo', 'middle' => 'Sison', 'sex' => 'male', 'born' => '1996-12-03', 'civil' => 'single', 'rel' => 'child', 'contact' => '09451234520'],
            ]],
            ['no' => 'HH-0012', 'purok' => 'Purok 7', 'address' => 'Purok 7, Sitio Mabuhay', 'members' => [
                ['last' => 'Panganiban', 'first' => 'Jose Rizalino', 'middle' => 'Diaz', 'sex' => 'male', 'born' => '1986-06-19', 'civil' => 'married', 'rel' => 'head', 'contact' => '09561234521'],
                ['last' => 'Panganiban', 'first' => 'Maricel', 'middle' => 'Torres', 'sex' => 'female', 'born' => '1987-02-14', 'civil' => 'married', 'rel' => 'spouse', 'contact' => '09561234522',
                    'pregnancy' => ['weeks' => 12, 'gravida' => 4, 'para' => 3, 'risk' => 'low']],
                ['last' => 'Panganiban', 'first' => 'Jose Rizalino', 'middle' => 'Torres', 'suffix' => 'Jr.', 'sex' => 'male', 'born' => '2013-12-30', 'civil' => 'single', 'rel' => 'child', 'mother' => 'Maricel'],
                ['last' => 'Panganiban', 'first' => 'Mika Ella', 'middle' => 'Torres', 'sex' => 'female', 'born' => '-10 months', 'civil' => 'single', 'rel' => 'child', 'mother' => 'Maricel', 'epi' => ['skip' => ['MMR-1', 'IPV-2']],
                    'consults' => [['-10 days', 'Cough and colds, low-grade fever', 'Acute upper respiratory infection']]],
            ]],
            ['no' => 'HH-0013', 'purok' => 'Purok 6', 'address' => 'Purok 6, near the water pump', 'members' => [
                ['last' => 'Ignacio', 'first' => 'Asuncion', 'middle' => 'Bello', 'sex' => 'female', 'born' => '1948-11-02', 'civil' => 'widowed', 'rel' => 'head', 'pwd' => true, '4ps' => true, 'philhealth' => '02-000221100-6',
                    'conditions' => ['Essential hypertension' => ['2009-07-07', 'active']]],
            ]],
            ['no' => 'HH-0014', 'purok' => 'Purok 7', 'address' => 'Purok 7, Navarro Apartment', 'members' => [
                ['last' => 'Navarro', 'first' => 'Christian Dale', 'middle' => 'Hilario', 'sex' => 'male', 'born' => '1999-04-09', 'civil' => 'married', 'rel' => 'head', 'contact' => '09661234523'],
                ['last' => 'Navarro', 'first' => 'Ella Mae', 'middle' => 'Reyes', 'sex' => 'female', 'born' => '2000-10-25', 'civil' => 'married', 'rel' => 'spouse', 'contact' => '09661234524',
                    'consults' => [['-1 month', 'Painful urination', 'Urinary tract infection']]],
            ]],
        ];
    }

    public function run(): void
    {
        fake()->seed(2026);

        $this->bhw = User::query()->where('username', 'bhw.analiza')->firstOrFail();
        $this->bhw2 = User::query()->where('username', 'bhw.rosario')->firstOrFail();
        $this->midwife = User::query()->where('username', 'mw.cristina')->firstOrFail();
        $this->visitTypes = VisitType::query()->pluck('id', 'name');
        $this->conditions = Condition::query()->pluck('id', 'name');
        $rules = MaternalVisitRule::query()->orderBy('sequence')->get();
        $this->prenatalRules = $rules->where('kind', MaternalVisitKind::Prenatal)->values();
        $this->postnatalRules = $rules->where('kind', MaternalVisitKind::Postnatal)->values();
        $puroks = Purok::query()->get()->keyBy('name');
        $waterSources = WaterSource::query()->orderBy('sort_order')->pluck('id')->all();
        $toiletTypes = ToiletType::query()->orderBy('sort_order')->pluck('id')->all();

        foreach ($this->households() as $index => $data) {
            $staff = $index % 2 === 0 ? $this->bhw : $this->bhw2;
            $purok = $puroks[$data['purok']];
            $geotagged = ! in_array($data['no'], self::NOT_GEOTAGGED, true);

            $household = Household::query()->create([
                'household_no' => $data['no'],
                'purok_id' => $purok->id,
                'address' => $data['address'].', '.$data['purok'].', Brgy. Kalawag II, Isulan, Sultan Kudarat',
                'is_4ps' => collect($data['members'])->contains(fn (array $m) => $m['4ps'] ?? false),
                // Mostly safe water and sanitary toilets; a few households without, so reports show both.
                'water_source_id' => $waterSources[$index % 5 === 4 ? 5 + $index % 3 : $index % 5],
                'toilet_type_id' => $toiletTypes[$index % 6 === 5 ? 4 + $index % 3 : $index % 4],
                // Within ~250 m of the purok centre.
                'latitude' => $geotagged ? round((float) $purok->center_latitude + fake()->randomFloat(7, -0.0022, 0.0022), 7) : null,
                'longitude' => $geotagged ? round((float) $purok->center_longitude + fake()->randomFloat(7, -0.0022, 0.0022), 7) : null,
                'geotag_accuracy_m' => $geotagged ? fake()->numberBetween(4, 25) : null,
                'geotagged_at' => $geotagged ? CarbonImmutable::now()->subMonths(8)->addDays($index * 3) : null,
                'geotagged_by' => $geotagged ? $staff->id : null,
                'created_by' => $staff->id,
                'updated_by' => $staff->id,
            ]);

            /** @var array<string, Resident> $byFirstName */
            $byFirstName = [];

            foreach ($data['members'] as $member) {
                $resident = $this->createResident($household, $member, $staff);
                $byFirstName[$member['first']] = $resident;

                if (isset($member['mother'])) {
                    $resident->update(['mother_id' => $byFirstName[$member['mother']]->id]);
                }

                if ($resident->relationship_to_head === RelationshipToHead::Head) {
                    $household->update(['head_resident_id' => $resident->id]);

                    if ($geotagged) {
                        $resident->consents()->create([
                            'consent_type' => ConsentType::Geotagging,
                            'consent_date' => $household->geotagged_at,
                            'obtained_by' => $staff->id,
                            'purpose' => self::GEOTAG_PURPOSE,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $m
     */
    private function createResident(Household $household, array $m, User $staff): Resident
    {
        $birthdate = CarbonImmutable::parse($m['born'])->startOfDay();
        $registeredOn = CarbonImmutable::today()->subMonths(9)->max($birthdate->addDay());

        $resident = Resident::query()->create([
            'resident_code' => sprintf('KII-%d-%05d', now()->year, ++$this->sequence),
            'household_id' => $household->id,
            'last_name' => $m['last'],
            'first_name' => $m['first'],
            'middle_name' => $m['middle'] ?? null,
            'suffix' => $m['suffix'] ?? null,
            'sex' => $m['sex'],
            'birthdate' => $birthdate,
            'birthplace' => 'Isulan, Sultan Kudarat',
            'civil_status' => $m['civil'],
            'contact_no' => $m['contact'] ?? null,
            'philhealth_no' => $m['philhealth'] ?? null,
            'is_pwd' => $m['pwd'] ?? false,
            // Known for about two in three residents (from a past test or blood donation).
            'blood_type' => fake()->boolean(65) ? fake()->randomElement(BloodType::cases()) : null,
            'relationship_to_head' => $m['rel'],
            'status' => $m['status'] ?? ResidentStatus::Active->value,
            'archive_reason' => $m['archived'] ?? null,
            'created_by' => $staff->id,
            'updated_by' => $staff->id,
        ]);

        $resident->consents()->create([
            'consent_type' => ConsentType::HealthProfiling,
            'consent_date' => $registeredOn,
            'obtained_by' => $staff->id,
            'purpose' => self::CONSENT_PURPOSE.($resident->age < 18 ? ' — given by parent/guardian' : ''),
        ]);

        foreach ($m['conditions'] ?? [] as $name => [$diagnosed, $status]) {
            $this->seedCondition($resident, $name, CarbonImmutable::parse($diagnosed), ConditionStatus::from($status));
        }

        foreach ($m['consults'] ?? [] as [$when, $complaint, $diagnosis]) {
            $this->visit($resident, 'General Consultation', CarbonImmutable::parse($when), $complaint, $diagnosis, $this->bhw);
        }

        if (isset($m['epi'])) {
            $this->seedImmunizations($resident, $birthdate, $m['epi']);
        }

        if (isset($m['pregnancy'])) {
            $this->seedPregnancy($resident, $m['pregnancy']);
        }

        if (isset($m['archived'])) {
            $resident->delete();
        }

        return $resident;
    }

    private function seedCondition(Resident $resident, string $name, CarbonImmutable $diagnosed, ConditionStatus $status): void
    {
        $resident->conditions()->create([
            'condition_id' => $this->conditions[$name],
            'diagnosed_date' => $diagnosed,
            'status' => $status,
            'notes' => $status === ConditionStatus::Resolved ? 'Completed treatment; declared cured.' : 'Maintenance medication; monthly monitoring.',
            'recorded_by' => $this->midwife->id,
        ]);

        // Initial consultation (if recent enough to be in the system) plus two follow-ups.
        $visitDates = collect([$diagnosed, CarbonImmutable::today()->subMonths(2), CarbonImmutable::today()->subDays(fake()->numberBetween(3, 20))])
            ->filter(fn (CarbonImmutable $date) => $date->greaterThan(CarbonImmutable::today()->subYear()));

        foreach ($visitDates as $i => $date) {
            $this->visit(
                $resident,
                $i === 0 && $date->equalTo($diagnosed) ? 'General Consultation' : 'Follow-up',
                $date,
                $status === ConditionStatus::Resolved ? 'Post-treatment check' : 'Maintenance check-up',
                $name,
                $i % 2 === 0 ? $this->bhw : $this->midwife,
                hypertensive: $name === 'Essential hypertension' && $status === ConditionStatus::Active,
            );
        }
    }

    /**
     * @param  array{until?: int, skip?: list<string>}  $epi
     */
    private function seedImmunizations(Resident $resident, CarbonImmutable $birthdate, array $epi): void
    {
        $ageDays = (int) $birthdate->diffInDays(CarbonImmutable::today());
        $limit = min($ageDays, $epi['until'] ?? $ageDays);
        $skip = $epi['skip'] ?? [];

        VaccineScheduleRule::query()
            ->with('vaccine')
            ->where('due_age_days', '<=', $limit)
            ->orderBy('due_age_days')
            ->get()
            ->reject(fn (VaccineScheduleRule $s) => in_array("{$s->vaccine->code}-{$s->dose_no}", $skip, true))
            ->each(function (VaccineScheduleRule $schedule) use ($resident, $birthdate) {
                $resident->immunizations()->create([
                    'vaccine_id' => $schedule->vaccine_id,
                    'dose_no' => $schedule->dose_no,
                    'date_given' => $birthdate->addDays($schedule->due_age_days + fake()->numberBetween(0, 3)),
                    'given_by' => $this->midwife->id,
                    'lot_no' => strtoupper(fake()->bothify('??####')),
                ]);
            });
    }

    /**
     * Prenatal contacts follow the configured ANC schedule; `skip_contacts`
     * leaves a past contact unattended so the tracker shows an overdue visit.
     *
     * @param  array{weeks?: int, delivered_weeks_ago?: int, gravida: int, para: int, risk: string, risk_factors?: list<string>, skip_contacts?: list<int>, notes?: string}  $p
     */
    private function seedPregnancy(Resident $resident, array $p): void
    {
        $today = CarbonImmutable::today();

        if (isset($p['delivered_weeks_ago'])) {
            $delivered = true;
            $outcomeDate = $today->subWeeks($p['delivered_weeks_ago']);
            $lmp = $outcomeDate->subDays(Pregnancy::GESTATION_DAYS - 7);
        } elseif (isset($p['weeks'])) {
            $delivered = false;
            $outcomeDate = null;
            $lmp = $today->subWeeks($p['weeks']);
        } else {
            throw new InvalidArgumentException("Pregnancy for {$resident->full_name} needs 'weeks' or 'delivered_weeks_ago'.");
        }

        $pregnancy = $resident->pregnancies()->create([
            'lmp' => $lmp,
            'edd' => $lmp->addDays(Pregnancy::GESTATION_DAYS),
            'gravida' => $p['gravida'],
            'para' => $p['para'],
            'risk_level' => RiskLevel::from($p['risk']),
            'status' => $delivered ? MaternalStatus::Delivered : MaternalStatus::Ongoing,
            'outcome_date' => $outcomeDate,
            'notes' => $p['notes'] ?? null,
            'created_by' => $this->midwife->id,
            'updated_by' => $this->midwife->id,
        ]);

        if (isset($p['risk_factors'])) {
            $pregnancy->riskFactors()->attach(RiskFactor::query()->whereIn('code', $p['risk_factors'])->pluck('id'));
        }

        $lastPrenatal = $delivered ? $outcomeDate->subDay() : $today;

        foreach ($this->prenatalRules as $rule) {
            $date = $lmp->addDays($rule->offset_days);

            if ($date->greaterThan($lastPrenatal) || in_array($rule->sequence, $p['skip_contacts'] ?? [], true)) {
                continue;
            }

            $weeks = intdiv($rule->offset_days, 7);
            $visit = $this->visit($resident, 'Prenatal Check-up', $date, "Prenatal check-up, {$weeks} weeks AOG", 'Normal pregnancy', $this->midwife);

            $pregnancy->prenatalVisits()->create([
                'health_visit_id' => $visit->id,
                'maternal_visit_rule_id' => $rule->id,
                'fundal_height_cm' => $weeks >= 20 ? $weeks - 1 + fake()->numberBetween(0, 2) : null,
                'fetal_heart_rate' => $weeks >= 12 ? fake()->numberBetween(130, 155) : null,
                'remarks' => 'Advised iron with folic acid; reminded of the next ANC contact.',
            ]);
        }

        if (! $delivered) {
            return;
        }

        $pregnancy->delivery()->create([
            'delivery_time' => '04:35:00',
            'place_type' => DeliveryPlace::HealthFacility,
            'place_name' => 'Isulan Rural Health Unit birthing facility',
            'attendant_type' => BirthAttendant::Midwife,
            'delivery_mode' => DeliveryMode::Normal,
            'live_births' => 1,
            'remarks' => 'Uncomplicated delivery; newborn screening and hearing screening done.',
            'created_by' => $this->midwife->id,
            'updated_by' => $this->midwife->id,
        ]);

        foreach ($this->postnatalRules as $rule) {
            $date = $outcomeDate->addDays($rule->offset_days);

            if ($date->greaterThan($today)) {
                continue;
            }

            $visit = $this->visit($resident, 'Postnatal Check-up', $date, "Postnatal check-up, day {$rule->offset_days} postpartum", 'Normal puerperium', $this->midwife);

            $pregnancy->postnatalVisits()->create([
                'health_visit_id' => $visit->id,
                'maternal_visit_rule_id' => $rule->id,
                'breastfeeding' => BreastfeedingStatus::Exclusive,
                'family_planning_counseled' => $rule->sequence >= 3,
                'remarks' => 'Exclusive breastfeeding; lochia normal; no signs of infection.',
            ]);
        }
    }

    private function visit(
        Resident $resident,
        string $type,
        CarbonImmutable $date,
        string $complaint,
        ?string $diagnosis,
        User $staff,
        bool $hypertensive = false,
    ): HealthVisit {
        $visit = $resident->healthVisits()->create([
            'visit_type_id' => $this->visitTypes[$type],
            'visit_date' => $date,
            'attended_by' => $staff->id,
            'chief_complaint' => $complaint,
            'diagnosis' => $diagnosis,
            'notes' => fake()->boolean(50) ? 'Health teaching given. Advised to return if symptoms persist.' : null,
            'created_by' => $staff->id,
            'updated_by' => $staff->id,
        ]);

        $visit->vitalSign()->create($this->vitals($resident, $date, $hypertensive));

        return $visit;
    }

    /**
     * @return array<string, int|float|null>
     */
    private function vitals(Resident $resident, CarbonImmutable $date, bool $hypertensive): array
    {
        $ageMonths = (int) $resident->birthdate->diffInMonths($date);

        if ($ageMonths < 24) {
            return [
                'weight_kg' => round(3.3 + min($ageMonths, 6) * 0.65 + max($ageMonths - 6, 0) * 0.3, 2),
                'height_cm' => round(50 + min($ageMonths, 6) * 2.5 + max($ageMonths - 6, 0) * 1.3, 1),
                'temperature_c' => fake()->randomFloat(1, 36.5, 37.6),
                'pulse_rate' => fake()->numberBetween(110, 150),
                'respiratory_rate' => fake()->numberBetween(30, 45),
            ];
        }

        $adult = $ageMonths >= 18 * 12;

        return [
            'bp_systolic' => $adult ? ($hypertensive ? fake()->numberBetween(140, 165) : fake()->numberBetween(105, 130)) : null,
            'bp_diastolic' => $adult ? ($hypertensive ? fake()->numberBetween(90, 105) : fake()->numberBetween(65, 85)) : null,
            'weight_kg' => $adult ? fake()->randomFloat(2, 48, 78) : round(12 + ($ageMonths / 12) * 2.6, 2),
            'height_cm' => $adult ? fake()->randomFloat(1, 148, 172) : round(85 + ($ageMonths / 12) * 5.8, 1),
            'temperature_c' => fake()->randomFloat(1, 36.3, 37.4),
            'pulse_rate' => fake()->numberBetween(64, 96),
            'respiratory_rate' => fake()->numberBetween(14, 22),
        ];
    }
}
