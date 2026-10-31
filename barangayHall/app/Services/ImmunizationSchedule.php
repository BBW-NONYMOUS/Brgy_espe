<?php

namespace App\Services;

use App\Models\Immunization;
use App\Models\Resident;
use App\Models\VaccineScheduleRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Compares a child's immunizations against the configurable EPI schedule
 * (vaccine_schedule_rules). This is the "ImmunizationScheduleService" of
 * the spec.
 *
 * A dose is due at birth + due_age_days, but never earlier than
 * min_interval_days after the previous dose of the same vaccine (given or,
 * if not yet given, its own due date) — a late dose 1 pushes dose 2 back.
 */
class ImmunizationSchedule
{
    /** A due dose becomes "overdue" after this many days. */
    public const GRACE_DAYS = 28;

    /** @var Collection<int, VaccineScheduleRule>|null */
    private ?Collection $schedules = null;

    /**
     * Every scheduled dose with its status for this resident.
     * Expects `immunizations` to be loaded (or loads it).
     *
     * @return Collection<int, array{vaccine_id: int, vaccine_code: string, vaccine_name: string, dose_no: int, due_date: string, status: string, date_given: string|null}>
     */
    public function forResident(Resident $resident, ?CarbonImmutable $today = null): Collection
    {
        $given = $resident->immunizations
            ->keyBy(fn (Immunization $i) => $i->vaccine_id.'-'.$i->dose_no);

        $today ??= CarbonImmutable::today();

        /** @var array<string, CarbonImmutable> $anchors date each dose was given, or else is due */
        $anchors = [];

        return $this->schedules()
            ->sortBy([['due_age_days', 'asc'], ['dose_no', 'asc']])
            ->map(function (VaccineScheduleRule $schedule) use ($resident, $given, $today, &$anchors) {
                $dueDate = $resident->birthdate->addDays($schedule->due_age_days);
                $previous = $anchors[$schedule->vaccine_id.'-'.($schedule->dose_no - 1)] ?? null;

                if ($previous !== null && $schedule->min_interval_days !== null) {
                    $dueDate = $dueDate->max($previous->addDays($schedule->min_interval_days));
                }

                $dose = $given->get($schedule->vaccine_id.'-'.$schedule->dose_no);
                $anchors[$schedule->vaccine_id.'-'.$schedule->dose_no] = $dose !== null ? $dose->date_given : $dueDate;

                $status = match (true) {
                    $dose !== null => 'given',
                    $dueDate->lt($today->subDays(self::GRACE_DAYS)) => 'overdue',
                    $dueDate->lte($today) => 'due',
                    default => 'upcoming',
                };

                return [
                    'vaccine_id' => $schedule->vaccine_id,
                    'vaccine_code' => $schedule->vaccine->code,
                    'vaccine_name' => $schedule->vaccine->name,
                    'dose_no' => $schedule->dose_no,
                    'due_date' => $dueDate->toDateString(),
                    'status' => $status,
                    'date_given' => $dose?->date_given->toDateString(),
                ];
            })
            ->values();
    }

    /**
     * Doses that are due or overdue (not yet given) for this resident.
     *
     * @return Collection<int, array{vaccine_id: int, vaccine_code: string, vaccine_name: string, dose_no: int, due_date: string, status: string, date_given: string|null}>
     */
    public function pendingFor(Resident $resident): Collection
    {
        return $this->forResident($resident)->whereIn('status', ['due', 'overdue'])->values();
    }

    /**
     * Whether the resident is within the EPI target age (0–23 months).
     */
    public function isInTargetAge(Resident $resident): bool
    {
        return $resident->birthdate->gt(today()->subMonths(config('barangay.epi_max_age_months') + 1));
    }

    /**
     * @return Collection<int, VaccineScheduleRule>
     */
    private function schedules(): Collection
    {
        return $this->schedules ??= VaccineScheduleRule::query()->with('vaccine')->orderBy('due_age_days')->orderBy('id')->get();
    }
}
