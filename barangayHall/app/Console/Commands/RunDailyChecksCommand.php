<?php

namespace App\Console\Commands;

use App\Enums\PermissionName;
use App\Enums\ProgramStatus;
use App\Models\HealthProgram;
use App\Notifications\UpcomingProgram;
use App\Services\AppointmentGenerator;
use App\Services\InventoryAlertService;
use App\Services\NotificationRouter;
use App\Services\OutbreakDetectionService;
use App\Services\OverdueCareService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The daily alert run. Scheduled hourly 06:00–18:00: the barangay PC may be
 * switched on late, so it retries until one run has completed that day.
 * Every step is idempotent (dedupe keys, unique appointment sources), so a
 * forced re-run is safe.
 */
class RunDailyChecksCommand extends Command
{
    protected $signature = 'health:daily-checks {--force : Run even if today\'s checks already completed}';

    protected $description = 'Mark missed appointments, detect outbreaks, check medicine expiry and stock, send overdue-care digests and program reminders, re-plan appointments';

    public function handle(
        AppointmentGenerator $appointments,
        OutbreakDetectionService $outbreaks,
        InventoryAlertService $inventory,
        OverdueCareService $overdue,
        NotificationRouter $notifications,
    ): int {
        $today = CarbonImmutable::today();
        $doneKey = 'daily-checks:'.$today->toDateString();

        if (! $this->option('force') && Cache::has($doneKey)) {
            $this->components->info('Daily checks already completed today.');

            return self::SUCCESS;
        }

        $steps = [
            'Missed appointments' => function () use ($appointments, $overdue, $today) {
                $missed = $appointments->markMissed($today);
                $overdue->sendMissedDigest($missed, $today);

                return "{$missed->count()} marked missed";
            },
            'Outbreak thresholds' => fn () => $outbreaks->sweep($today)->count().' alert(s) raised or escalated',
            'Medicine expiry' => fn () => collect($inventory->expiryDigest($today))
                ->map(fn (int $n, int $band) => $band === 0 ? "{$n} expired" : "{$n} ≤ {$band} days")->implode(', ') ?: 'nothing new',
            'Low stock' => fn () => $inventory->checkLowStock(null, $today)->count().' medicine(s) at/below reorder level',
            'Overdue care' => function () use ($overdue, $today) {
                $c = $overdue->sendDigests($today);

                return "{$c['prenatal']} prenatal, {$c['postnatal']} postnatal contact(s); {$c['immunization']} child(ren) with overdue doses";
            },
            'Program reminders' => fn () => $this->programReminders($notifications, $today).' reminder(s)',
            'Appointment planning' => function () use ($appointments, $today) {
                $appointments->syncAll($today);

                return 'up to date';
            },
        ];

        $failed = false;

        foreach ($steps as $label => $step) {
            try {
                $this->components->twoColumnDetail($label, (string) $step());
            } catch (Throwable $e) {
                // One failing step must not stop the others; the next hourly run retries.
                $failed = true;
                report($e);
                $this->components->twoColumnDetail($label, '<fg=red>failed: '.$e->getMessage().'</>');
            }
        }

        if ($failed) {
            return self::FAILURE;
        }

        Cache::put($doneKey, now()->toIso8601String(), $today->endOfDay());

        return self::SUCCESS;
    }

    /**
     * Programs starting within the reminder window (and today): assigned
     * staff plus everyone who can view programs (Officials included).
     */
    private function programReminders(NotificationRouter $notifications, CarbonImmutable $today): int
    {
        $sent = 0;

        HealthProgram::query()
            ->with('staff')
            ->where('status', ProgramStatus::Scheduled)
            ->whereBetween('start_at', [$today->startOfDay(), $today->addDays((int) config('barangay.alerts.program_reminder_days'))->endOfDay()])
            ->get()
            ->each(function (HealthProgram $program) use ($notifications, $today, &$sent) {
                $isToday = $program->start_at->isSameDay($today);
                $notification = new UpcomingProgram(
                    title: ($isToday ? 'Today: ' : 'Upcoming: ').$program->title,
                    message: $program->start_at->format('D, M j, g:i A')." at {$program->venue}.",
                    url: route('programs.show', $program, absolute: false),
                );
                $key = "program:{$program->id}:".($isToday ? 'today' : 'soon');

                $sent += $notifications->toPermission(PermissionName::ProgramsView, $notification, $key);
                // Assigned staff always hear about it, even without programs.view.
                $sent += $notifications->toUsers($program->staff->reject(fn ($u) => $u->hasPermission(PermissionName::ProgramsView)), $notification, "{$key}:staff");
            });

        return $sent;
    }
}
