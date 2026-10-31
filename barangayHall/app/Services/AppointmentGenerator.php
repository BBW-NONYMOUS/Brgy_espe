<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\MaternalStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Pregnancy;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Creates and maintains appointments from the maternal and EPI schedules.
 *
 * Idempotent: each scheduled contact/dose has one appointment, identified by
 * (source_type, source_id, source_key) — e.g. a Pregnancy + "anc:3", a
 * Resident + "epi:PENTA:2". Running it again only fills gaps and moves dates
 * that changed. Appointments staff have rescheduled (edited_manually),
 * completed or cancelled are never touched.
 */
class AppointmentGenerator
{
    public function __construct(
        private readonly PregnancyScheduleService $maternal,
        private readonly ImmunizationSchedule $immunizations,
    ) {}

    /**
     * ANC contacts while ongoing; PNC contacts after delivery; open ANC
     * appointments are cancelled once the pregnancy has ended.
     */
    public function syncPregnancy(Pregnancy $pregnancy, ?CarbonImmutable $today = null): void
    {
        $today ??= CarbonImmutable::today();
        $horizon = $today->addDays($this->horizonDays());
        $resident = $pregnancy->resident;

        foreach ($this->maternal->contacts($pregnancy, $today) as $contact) {
            $key = ($contact['kind'] === 'prenatal' ? 'anc:' : 'pnc:').$contact['sequence'];
            $existing = $this->find($pregnancy, $key);
            $due = CarbonImmutable::parse($contact['due_date']);

            match ($contact['status']) {
                'done' => $this->complete($existing, $contact['health_visit_id']),
                'not_applicable', 'missed' => $this->cancel($existing),
                'overdue' => null, // shown by the tracker; a missed appointment already records it
                default => $due->lessThanOrEqualTo($horizon)
                    ? $this->upsert($pregnancy, $key, $resident, $contact['kind'], $this->weekday($due->max($today))->setTime(9, 0), $existing)
                    : null,
            };
        }

        if ($pregnancy->status === MaternalStatus::Terminated) {
            $this->openFor($pregnancy)->each(fn (Appointment $a) => $this->cancel($a));
        }
    }

    /**
     * EPI doses: due/overdue ones on the next EPI day, upcoming ones on their
     * due date (weekdays only), given ones completed.
     */
    public function syncChild(Resident $child, ?CarbonImmutable $today = null): void
    {
        $today ??= CarbonImmutable::today();
        $horizon = $today->addDays($this->horizonDays());
        $child->loadMissing('immunizations');

        foreach ($this->immunizations->forResident($child, $today) as $dose) {
            $key = "epi:{$dose['vaccine_code']}:{$dose['dose_no']}";
            $existing = $this->find($child, $key);
            $due = CarbonImmutable::parse($dose['due_date']);

            $date = match ($dose['status']) {
                'due', 'overdue' => $this->nextEpiDay($today),
                'upcoming' => $due->lessThanOrEqualTo($horizon) ? $this->weekday($due) : null,
                default => null,
            };

            if ($dose['status'] === 'given') {
                $this->complete($existing, null);
            } elseif ($date !== null) {
                // A missed EPI appointment is re-booked on the next EPI day.
                $this->upsert($child, $key, $child, 'immunization', $date->setTime(8, 0), $existing, rebookMissed: true);
            }
        }
    }

    /**
     * Daily: every ongoing / recently delivered pregnancy and every child in the EPI age range.
     */
    public function syncAll(?CarbonImmutable $today = null): void
    {
        $today ??= CarbonImmutable::today();

        Pregnancy::query()
            ->with('resident')
            ->whereHas('resident')
            ->where(fn ($q) => $q->where('status', MaternalStatus::Ongoing)
                ->orWhere(fn ($d) => $d->where('status', MaternalStatus::Delivered)->where('outcome_date', '>=', $today->subDays(60)->toDateString())))
            ->each(fn (Pregnancy $p) => $this->syncPregnancy($p, $today));

        Resident::query()
            ->with('immunizations')
            ->where('birthdate', '>', $today->subMonths((int) config('barangay.epi_max_age_months') + 1)->toDateString())
            ->each(fn (Resident $child) => $this->syncChild($child, $today));
    }

    /**
     * Scheduled appointments from before today become "missed".
     *
     * @return Collection<int, Appointment> the appointments just marked
     */
    public function markMissed(?CarbonImmutable $today = null): Collection
    {
        $today ??= CarbonImmutable::today();

        $missed = Appointment::query()
            ->where('status', AppointmentStatus::Scheduled)
            ->where('scheduled_at', '<', $today->startOfDay())
            ->get();

        // Saved one by one so each change is audit-logged.
        $missed->each(fn (Appointment $a) => $a->update(['status' => AppointmentStatus::Missed]));

        return $missed;
    }

    private function find(Model $source, string $key): ?Appointment
    {
        return Appointment::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('source_key', $key)
            ->first();
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function openFor(Model $source): Collection
    {
        return Appointment::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('status', AppointmentStatus::Scheduled)
            ->get();
    }

    /**
     * @param  bool  $rebookMissed  EPI: invite again on the next EPI day. Maternal contacts
     *                              stay "missed" for the midwife to follow up personally.
     */
    private function upsert(Model $source, string $key, Resident $resident, string $typeCode, CarbonImmutable $at, ?Appointment $existing, bool $rebookMissed = false): void
    {
        if ($existing === null) {
            Appointment::query()->create([
                'resident_id' => $resident->id,
                'appointment_type_id' => $this->typeId($typeCode),
                'scheduled_at' => $at,
                'status' => AppointmentStatus::Scheduled,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'source_key' => $key,
            ]);

            return;
        }

        $reschedulable = ! $existing->edited_manually
            && ($existing->status === AppointmentStatus::Scheduled || ($rebookMissed && $existing->status === AppointmentStatus::Missed));

        if ($reschedulable && ! $existing->scheduled_at->equalTo($at)) {
            $existing->update(['scheduled_at' => $at, 'status' => AppointmentStatus::Scheduled]);
        }
    }

    private function complete(?Appointment $appointment, ?int $healthVisitId): void
    {
        if ($appointment !== null && in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::Missed], true)) {
            $appointment->update(['status' => AppointmentStatus::Completed, 'health_visit_id' => $healthVisitId ?? $appointment->health_visit_id]);
        }
    }

    private function cancel(?Appointment $appointment): void
    {
        if ($appointment?->status === AppointmentStatus::Scheduled) {
            $appointment->update(['status' => AppointmentStatus::Cancelled]);
        }
    }

    private function typeId(string $code): int
    {
        return (int) AppointmentType::query()->where('code', $code)->value('id');
    }

    public function nextEpiDay(CarbonImmutable $today): CarbonImmutable
    {
        $weekday = (int) config('barangay.epi_weekday');

        return $today->dayOfWeek === $weekday ? $today : $today->next($weekday);
    }

    /**
     * Weekend dates move to the next Monday (the health station is closed).
     */
    private function weekday(CarbonImmutable $date): CarbonImmutable
    {
        return $date->isWeekend() ? $date->next(CarbonImmutable::MONDAY) : $date;
    }

    private function horizonDays(): int
    {
        return (int) config('barangay.alerts.appointment_horizon_days');
    }
}
