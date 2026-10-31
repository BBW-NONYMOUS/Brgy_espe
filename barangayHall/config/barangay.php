<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Health center identity
    |--------------------------------------------------------------------------
    */

    'health_center' => env('BARANGAY_HEALTH_CENTER', 'Barangay Health Center'),
    'barangay' => env('BARANGAY_NAME', 'Kalawag II'),
    'municipality' => env('BARANGAY_MUNICIPALITY', 'Isulan'),
    'province' => env('BARANGAY_PROVINCE', 'Sultan Kudarat'),
    'resident_code_prefix' => env('BARANGAY_CODE_PREFIX', 'KII'),

    /*
    |--------------------------------------------------------------------------
    | Account security
    |--------------------------------------------------------------------------
    */

    'lockout' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data retention (RA 10173)
    |--------------------------------------------------------------------------
    |
    | Archived residents may only be permanently deleted by an Admin once they
    | have been archived for at least this many years.
    |
    */

    'retention_years' => (int) env('RETENTION_YEARS', 5),

    /*
    |--------------------------------------------------------------------------
    | Health rules
    |--------------------------------------------------------------------------
    */

    // DOH EPI target group for "children due for immunization".
    'epi_max_age_months' => 23,

    // Weekly EPI (immunization) day at the health station: 0 = Sunday … 3 = Wednesday.
    'epi_weekday' => (int) env('EPI_WEEKDAY', 3),

    /*
    |--------------------------------------------------------------------------
    | Map / geo-tagging
    |--------------------------------------------------------------------------
    |
    | Centre and zoom for the household map. Geo-tags outside `bounds`
    | (roughly the Municipality of Isulan) are rejected as mis-clicks / GPS
    | errors; GPS fixes less accurate than `max_accuracy_m` trigger a warning.
    | Replace the centre with the health center's surveyed coordinates.
    |
    */

    'map' => [
        'center' => [(float) env('MAP_CENTER_LAT', 6.6290), (float) env('MAP_CENTER_LNG', 124.6060)],
        'zoom' => (int) env('MAP_ZOOM', 16),
        'bounds' => [[6.50, 124.45], [6.75, 124.75]],
        'max_accuracy_m' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerts, schedules and inventory
    |--------------------------------------------------------------------------
    */

    'alerts' => [
        // Daily checks run hourly in this window until they have completed once that day.
        'daily_checks_from' => env('DAILY_CHECKS_FROM', '06:00'),
        'daily_checks_until' => env('DAILY_CHECKS_UNTIL', '18:00'),
        // Medicine expiry notification bands, in days (each band notified once per batch).
        'expiry_bands' => [90, 60, 30],
        // A scheduled prenatal/postnatal contact or EPI dose is "due soon" within this many days.
        'due_soon_days' => 14,
        // Days after the due date before a prenatal/postnatal contact counts as overdue.
        'maternal_grace_days' => 14,
        // Appointments are generated this far ahead.
        'appointment_horizon_days' => 60,
        // Program reminders this many days before the start.
        'program_reminder_days' => 3,
    ],

    'sms' => [
        // 'log' only for now; add a Semaphore driver behind App\Contracts\SmsGateway later.
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database backups
    |--------------------------------------------------------------------------
    */

    'backup' => [
        'disk' => env('BACKUP_DISK', 'local'),
        'directory' => 'backups',
        'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'time' => env('BACKUP_TIME', '23:00'),
        'keep_daily' => (int) env('BACKUP_KEEP_DAILY', 14),
        'keep_weekly' => (int) env('BACKUP_KEEP_WEEKLY', 8),
        'keep_manual' => (int) env('BACKUP_KEEP_MANUAL', 10),
    ],

];
