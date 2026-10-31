<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\MaternalStatus;
use App\Enums\MaternalVisitKind;
use App\Enums\ProgramStatus;
use App\Enums\ProgramTargetGroup;
use App\Models\AppointmentType;
use App\Models\HealthProgram;
use App\Models\MaternalVisitRule;
use App\Models\Pregnancy;
use App\Models\ProgramType;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Services\ImmunizationSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\FindsSeededResidents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Appointments as AppointmentGenerator will produce them (ANC/PNC from the
 * maternal schedule, EPI from the immunization schedule) plus manual ones
 * for today, and community health programs on the shared calendar.
 */
class SchedulingSeeder extends Seeder
{
    use FindsSeededResidents;

    /** @var Collection<string, int> */
    private Collection $types;

    private User $midwife;

    public function run(ImmunizationSchedule $immunizations): void
    {
        $this->types = AppointmentType::query()->pluck('id', 'code');
        $this->midwife = $this->user('mw.cristina');

        $this->maternalAppointments();
        $this->immunizationAppointments($immunizations);
        $this->manualAppointments();
        $this->programs();
    }

    private function maternalAppointments(): void
    {
        $rules = MaternalVisitRule::query()->orderBy('sequence')->get();
        $today = CarbonImmutable::today();

        $pregnancies = Pregnancy::query()
            ->with(['prenatalVisits.healthVisit', 'postnatalVisits.healthVisit'])
            ->whereIn('status', [MaternalStatus::Ongoing, MaternalStatus::Delivered])
            ->get();

        foreach ($pregnancies as $pregnancy) {
            $lmp = $pregnancy->edd->subDays(Pregnancy::GESTATION_DAYS);

            foreach ($rules as $rule) {
                $prenatal = $rule->kind === MaternalVisitKind::Prenatal;

                if ($prenatal) {
                    $date = $lmp->addDays($rule->offset_days);
                    $visit = $pregnancy->prenatalVisits->firstWhere('maternal_visit_rule_id', $rule->id)?->healthVisit;

                    // Contacts after the delivery date were never needed.
                    if ($pregnancy->outcome_date !== null && $date->greaterThanOrEqualTo($pregnancy->outcome_date)) {
                        continue;
                    }
                } else {
                    if ($pregnancy->outcome_date === null) {
                        continue;
                    }

                    $date = $pregnancy->outcome_date->addDays($rule->offset_days);
                    $visit = $pregnancy->postnatalVisits->firstWhere('maternal_visit_rule_id', $rule->id)?->healthVisit;
                }

                $status = match (true) {
                    $visit !== null => AppointmentStatus::Completed,
                    $date->lessThan($today) => AppointmentStatus::Missed,
                    default => AppointmentStatus::Scheduled,
                };

                $pregnancy->resident->appointments()->create([
                    'appointment_type_id' => $this->types[$prenatal ? 'prenatal' : 'postnatal'],
                    'scheduled_at' => ($visit->visit_date ?? $date)->setTime(9, 0),
                    'assigned_to' => $this->midwife->id,
                    'status' => $status,
                    'source_type' => $pregnancy->getMorphClass(),
                    'source_id' => $pregnancy->id,
                    'source_key' => ($prenatal ? 'anc:' : 'pnc:').$rule->sequence,
                    'health_visit_id' => $visit?->id,
                    'created_by' => $this->midwife->id,
                    'updated_by' => $this->midwife->id,
                ]);
            }
        }
    }

    /**
     * Due/overdue doses are booked on the next EPI day (Wednesday); doses
     * falling due within 60 days are booked on their due date.
     */
    private function immunizationAppointments(ImmunizationSchedule $schedule): void
    {
        $today = CarbonImmutable::today();
        $nextEpiDay = $today->next(CarbonImmutable::WEDNESDAY);

        $children = Resident::query()
            ->with('immunizations')
            ->where('birthdate', '>', $today->subMonths(24)->toDateString())
            ->get();

        foreach ($children as $child) {
            foreach ($schedule->forResident($child) as $dose) {
                $due = CarbonImmutable::parse($dose['due_date']);

                $date = match ($dose['status']) {
                    'due', 'overdue' => $nextEpiDay,
                    'upcoming' => $due->lessThanOrEqualTo($today->addDays(60)) ? $this->weekday($due) : null,
                    default => null,
                };

                if ($date === null) {
                    continue;
                }

                $child->appointments()->create([
                    'appointment_type_id' => $this->types['immunization'],
                    'scheduled_at' => $date->setTime(8, 0),
                    'assigned_to' => $this->midwife->id,
                    'status' => AppointmentStatus::Scheduled,
                    'source_type' => $child->getMorphClass(),
                    'source_id' => $child->id,
                    'source_key' => "epi:{$dose['vaccine_code']}:{$dose['dose_no']}",
                    'created_by' => $this->midwife->id,
                    'updated_by' => $this->midwife->id,
                ]);
            }
        }
    }

    private function manualAppointments(): void
    {
        $today = CarbonImmutable::today();
        $bhw = $this->user('bhw.analiza');
        $bhw2 = $this->user('bhw.rosario');

        $appointments = [
            // [last|first, type, day offset, hour, staff, status, notes]
            ['Gallardo|Ernesto', 'follow_up', 0, '09:00', $bhw, AppointmentStatus::Scheduled, 'Monthly BP monitoring and maintenance medicines.'],
            ['Ramos|Teodoro', 'follow_up', 0, '10:00', $bhw2, AppointmentStatus::Scheduled, 'Blood sugar check; bring glucometer log.'],
            ['Navarro|Ella Mae', 'family_planning', 0, '14:00', $this->midwife, AppointmentStatus::Scheduled, 'Pill refill and counselling.'],
            ['Bautista|Felicidad', 'follow_up', -1, '09:30', $bhw2, AppointmentStatus::Missed, 'Hypertension and diabetes follow-up.'],
            ['Ramos|Nenita', 'check_up', -3, '08:30', $bhw, AppointmentStatus::Completed, 'Hypertension check-up.'],
            ['Lumabao|Benjamin', 'check_up', 1, '09:00', $bhw, AppointmentStatus::Scheduled, 'Review after RHU referral for chest X-ray.'],
            ['Abdulrahman|Jamil', 'nutrition', 2, '08:00', $bhw2, AppointmentStatus::Scheduled, 'Monthly weighing (underweight).'],
            ['Kamid|Saidamen', 'check_up', 5, '10:30', $bhw, AppointmentStatus::Cancelled, 'Rescheduled by family; working in Koronadal this week.'],
        ];

        foreach ($appointments as [$key, $type, $offset, $time, $staff, $status, $notes]) {
            [$hour, $minute] = array_map('intval', explode(':', $time));
            $resident = $this->resident(...explode('|', $key));
            $date = $today->addDays($offset);

            $resident->appointments()->create([
                'appointment_type_id' => $this->types[$type],
                'scheduled_at' => $date->setTime($hour, $minute),
                'assigned_to' => $staff->id,
                'status' => $status,
                'health_visit_id' => $status === AppointmentStatus::Completed
                    ? $resident->healthVisits()->whereDate('visit_date', $date->toDateString())->value('id')
                    : null,
                'notes' => $notes,
                'created_by' => $staff->id,
                'updated_by' => $staff->id,
            ]);
        }
    }

    private function programs(): void
    {
        $today = CarbonImmutable::today();
        $types = ProgramType::query()->pluck('id', 'name');
        $puroks = Purok::query()->orderBy('sort_order')->pluck('id', 'name');
        $staff = User::query()->whereIn('username', ['bhw.analiza', 'bhw.rosario', 'mw.cristina'])->pluck('id', 'username');

        $programs = [
            [
                'title' => 'Supplemental immunization day: measles-rubella and polio',
                'type' => 'Immunization day', 'start' => $today->addDays(3)->setTime(8, 0), 'hours' => 6,
                'venue' => 'Kalawag II Barangay Health Station', 'target' => ProgramTargetGroup::UnderFive,
                'puroks' => $puroks->keys()->all(), 'staff' => ['mw.cristina', 'bhw.analiza', 'bhw.rosario'],
                'description' => 'House-to-house and fixed-post vaccination of all children 0-59 months regardless of previous doses.',
            ],
            [
                'title' => 'Search-and-destroy of mosquito breeding sites',
                'type' => 'Clean-up drive', 'start' => $today->addDays(2)->setTime(7, 0), 'hours' => 3,
                'venue' => 'Purok 3 covered court (assembly point)', 'target' => ProgramTargetGroup::Households,
                'puroks' => ['Purok 3'], 'staff' => ['bhw.analiza'],
                'description' => 'Response to the dengue alert in Purok 3: 4S strategy, removal of standing water containers.',
            ],
            [
                'title' => 'Dengue prevention and the 4S strategy',
                'type' => 'Health seminar', 'start' => $today->addDays(5)->setTime(14, 0), 'hours' => 2,
                'venue' => 'Kalawag II Barangay Hall', 'target' => ProgramTargetGroup::Everyone,
                'puroks' => ['Purok 3', 'Purok 4'], 'staff' => ['mw.cristina'],
                'description' => 'Signs of severe dengue, when to go to the hospital, and household clean-up.',
            ],
            [
                'title' => 'Supplementary feeding program, cycle 1',
                'type' => 'Feeding program', 'start' => $today->addDays(8)->setTime(9, 0), 'hours' => 2,
                'venue' => 'Kalawag II Day Care Center', 'target' => ProgramTargetGroup::UnderFive,
                'puroks' => ['Purok 2', 'Purok 4'], 'staff' => ['bhw.rosario'],
                'description' => '120-day feeding for underweight and wasted children identified in Operation Timbang.',
            ],
            [
                'title' => 'Buntis Congress',
                'type' => 'Health seminar', 'start' => $today->subDays(14)->setTime(9, 0), 'hours' => 4,
                'venue' => 'Kalawag II Barangay Hall', 'target' => ProgramTargetGroup::Pregnant,
                'puroks' => $puroks->keys()->all(), 'staff' => ['mw.cristina'], 'status' => ProgramStatus::Completed,
                'description' => 'Birth planning, danger signs in pregnancy, breastfeeding and newborn care.',
                'attendees' => [['Gallardo', 'Kristine Mae'], ['Ocampo', 'Rhealyn'], ['Panganiban', 'Maricel'], ['Reyes', 'Joy Ann']],
            ],
            [
                'title' => 'Medical and dental mission',
                'type' => 'Medical mission', 'start' => $today->subDays(21)->setTime(8, 0), 'hours' => 8,
                'venue' => 'Kalawag II Elementary School', 'target' => ProgramTargetGroup::Everyone,
                'puroks' => $puroks->keys()->all(), 'staff' => ['mw.cristina', 'bhw.analiza', 'bhw.rosario'], 'status' => ProgramStatus::Completed,
                'description' => 'Free consultation, tooth extraction and medicines with the Provincial Health Office.',
                'attendees' => 12,
            ],
        ];

        foreach ($programs as $data) {
            $program = HealthProgram::query()->create([
                'title' => $data['title'],
                'program_type_id' => $types[$data['type']],
                'description' => $data['description'],
                'start_at' => $data['start'],
                'end_at' => $data['start']->addHours($data['hours']),
                'venue' => $data['venue'],
                'target_group' => $data['target'],
                'status' => $data['status'] ?? ProgramStatus::Scheduled,
                'created_by' => $staff['mw.cristina'],
                'updated_by' => $staff['mw.cristina'],
            ]);

            $program->puroks()->attach($puroks->only($data['puroks'])->values());
            $program->staff()->attach($staff->only($data['staff'])->values());

            $attendees = match (true) {
                is_array($data['attendees'] ?? null) => collect($data['attendees'])->map(fn (array $n) => $this->resident(...$n)),
                is_int($data['attendees'] ?? null) => Resident::query()->where('birthdate', '<=', $today->subYears(5)->toDateString())->orderBy('id')->get()->nth(4)->take($data['attendees']),
                default => collect(),
            };

            foreach ($attendees->values() as $i => $resident) {
                $program->attendance()->create([
                    'resident_id' => $resident->id,
                    'attended_at' => $data['start']->addMinutes(10 + $i * 7),
                    'recorded_by' => $staff['bhw.analiza'],
                ]);
            }
        }
    }

    /**
     * Move weekend dates to the next Monday (the health station is closed).
     */
    private function weekday(CarbonImmutable $date): CarbonImmutable
    {
        return $date->isWeekend() ? $date->next(CarbonImmutable::MONDAY) : $date;
    }
}
