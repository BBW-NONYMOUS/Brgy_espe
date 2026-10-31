<?php

namespace App\Services;

use App\Enums\MaternalStatus;
use App\Enums\PermissionName;
use App\Models\Appointment;
use App\Models\Pregnancy;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\MissedAppointmentsDigest;
use App\Notifications\OverdueCareDigest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Daily digests of care that fell behind: overdue prenatal/postnatal
 * contacts (→ midwife/admin), overdue EPI doses (→ everyone who records
 * health data) and missed appointments (→ assigned staff, or the
 * appointment managers when unassigned). Counts and links only.
 */
class OverdueCareService
{
    public function __construct(
        private readonly PregnancyScheduleService $maternal,
        private readonly ImmunizationSchedule $immunizations,
        private readonly NotificationRouter $notifications,
    ) {}

    /**
     * @return array{prenatal: int, postnatal: int}
     */
    public function maternalCounts(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $counts = ['prenatal' => 0, 'postnatal' => 0];

        Pregnancy::query()
            ->whereHas('resident')
            ->where(fn ($q) => $q->where('status', MaternalStatus::Ongoing)
                ->orWhere(fn ($d) => $d->where('status', MaternalStatus::Delivered)->where('outcome_date', '>=', $today->subDays(60)->toDateString())))
            ->each(function (Pregnancy $p) use (&$counts, $today) {
                foreach ($this->maternal->contacts($p, $today)->where('status', 'overdue') as $contact) {
                    $contact['kind'] === 'prenatal' ? $counts['prenatal']++ : $counts['postnatal']++;
                }
            });

        return $counts;
    }

    /**
     * Children in the EPI age range with at least one overdue dose.
     */
    public function childrenWithOverdueDoses(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();

        return Resident::query()
            ->with('immunizations')
            ->where('birthdate', '>', $today->subMonths((int) config('barangay.epi_max_age_months') + 1)->toDateString())
            ->get()
            ->filter(fn (Resident $child) => $this->immunizations->forResident($child, $today)->contains('status', 'overdue'))
            ->count();
    }

    /**
     * @return array{prenatal: int, postnatal: int, immunization: int}
     */
    public function sendDigests(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $date = $today->toDateString();
        $maternal = $this->maternalCounts($today);
        $children = $this->childrenWithOverdueDoses($today);

        if ($maternal['prenatal'] + $maternal['postnatal'] > 0) {
            $this->notifications->toPermission(PermissionName::MaternalManage, new OverdueCareDigest(
                title: 'Overdue maternal care',
                message: collect([
                    $maternal['prenatal'] > 0 ? "{$maternal['prenatal']} prenatal contact(s)" : null,
                    $maternal['postnatal'] > 0 ? "{$maternal['postnatal']} postnatal contact(s)" : null,
                ])->filter()->implode(' and ').' are overdue.',
                url: route('pregnancies.index', ['status' => 'overdue'], absolute: false),
                level: 'warning',
            ), "overdue-maternal:{$date}");
        }

        if ($children > 0) {
            $this->notifications->toPermission(PermissionName::HealthRecord, new OverdueCareDigest(
                title: 'Overdue immunizations',
                message: "{$children} child(ren) under 2 have overdue vaccine doses.",
                url: route('child-health.index', ['status' => 'overdue'], absolute: false),
                level: 'warning',
            ), "overdue-epi:{$date}");
        }

        return [...$maternal, 'immunization' => $children];
    }

    /**
     * One digest per assigned staff member; unassigned ones go to everyone
     * who manages appointments.
     *
     * @param  Collection<int, Appointment>  $missed
     */
    public function sendMissedDigest(Collection $missed, ?CarbonImmutable $today = null): void
    {
        if ($missed->isEmpty()) {
            return;
        }

        $date = ($today ?? CarbonImmutable::today())->toDateString();
        $url = route('appointments.index', ['status' => 'missed'], absolute: false);

        foreach ($missed->groupBy(fn (Appointment $a) => $a->assigned_to ?? 0) as $userId => $group) {
            $notification = new MissedAppointmentsDigest(
                title: 'Missed appointments',
                message: "{$group->count()} appointment(s) were not attended and are now marked missed.",
                url: $url,
                level: 'warning',
            );

            $assignee = $userId === 0 ? null : User::query()->find($userId);

            $assignee !== null
                ? $this->notifications->toUsers($assignee, $notification, "missed:{$date}:user:{$userId}")
                : $this->notifications->toPermission(PermissionName::AppointmentsManage, $notification, "missed:{$date}:unassigned");
        }
    }
}
