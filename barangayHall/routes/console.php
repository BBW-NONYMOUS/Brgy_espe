<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schedule;

/*
| Runs via Windows Task Scheduler calling `php artisan schedule:run` every
| minute (deploy/windows/register-scheduler.ps1; see docs/deployment.md).
*/

Schedule::command('backup:run')
    ->dailyAt(config('barangay.backup.time'))
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Nightly encrypted database backup + rotation');

Schedule::command('model:prune')->daily();

// Alerts and schedules. Hourly within the working window; the command skips
// itself once it has completed for the day (see RunDailyChecksCommand).
Schedule::command('health:daily-checks')
    ->hourly()
    ->between(config('barangay.alerts.daily_checks_from'), config('barangay.alerts.daily_checks_until'))
    ->withoutOverlapping()
    ->description('Daily alerts: outbreaks, medicine expiry/stock, overdue care, missed appointments, program reminders');

// Queue worker for notifications and outbreak checks. No Supervisor on
// Windows: a short-lived worker each minute survives reboots and uses no
// memory when idle.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Process queued notifications and outbreak checks');

// Read notifications older than 90 days are removed (unread ones stay).
Schedule::call(fn () => DatabaseNotification::query()
    ->whereNotNull('read_at')
    ->where('read_at', '<', now()->subDays(90))
    ->delete())
    ->daily()
    ->description('Prune read notifications older than 90 days');
