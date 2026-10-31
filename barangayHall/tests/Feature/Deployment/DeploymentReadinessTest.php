<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Models\Vaccine;
use App\Notifications\HealthNotification;
use App\Providers\AppServiceProvider;
use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\URL;

/**
 * The scheduled commands, as `php artisan schedule:run` sees them.
 *
 * @return array<string, Event>
 */
function scheduledEvents(): array
{
    return collect(app(Schedule::class)->events())
        ->keyBy(fn (Event $e) => $e->command !== null ? trim(preg_replace('/^.*artisan[\'"]?\s+/', '', $e->command)) : (string) $e->description)
        ->all();
}

test('the scheduler runs backups, daily checks and the queue worker', function () {
    $events = scheduledEvents();

    expect($events)->toHaveKeys(['backup:run', 'health:daily-checks', 'queue:work --stop-when-empty --max-time=55 --tries=3', 'model:prune'])
        ->and($events['backup:run']->expression)->toBe('0 23 * * *')
        ->and($events['queue:work --stop-when-empty --max-time=55 --tries=3']->expression)->toBe('* * * * *')
        ->and($events['health:daily-checks']->expression)->toBe('0 * * * *');

    // Overlapping runs would double-send notifications or start two backups.
    foreach (['backup:run', 'health:daily-checks', 'queue:work --stop-when-empty --max-time=55 --tries=3'] as $command) {
        expect($events[$command]->withoutOverlapping)->toBeTrue("{$command} may overlap");
    }
});

test('every health notification goes through the queue', function () {
    $classes = collect(glob(app_path('Notifications/*.php')))
        ->map(fn (string $file) => 'App\\Notifications\\'.basename($file, '.php'))
        ->reject(fn (string $class) => (new ReflectionClass($class))->isAbstract());

    expect($classes)->not->toBeEmpty();

    foreach ($classes as $class) {
        expect(is_subclass_of($class, HealthNotification::class))->toBeTrue("{$class} is not a HealthNotification")
            ->and(is_subclass_of($class, ShouldQueue::class))->toBeTrue("{$class} is sent inline");
    }
});

test('in production, links are HTTPS and destructive database commands are refused', function () {
    app()->instance('env', 'production');
    app()->getProvider(AppServiceProvider::class)->boot();

    expect(url('/login'))->toStartWith('https://');

    $this->artisan('migrate:fresh')->assertFailed();
    $this->artisan('db:wipe')->assertFailed();

    URL::forceHttps(false);
});

test('the production environment template is safe to copy', function () {
    $env = collect(file(base_path('.env.production.example'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
        ->reject(fn (string $line) => str_starts_with($line, '#'))
        ->mapWithKeys(function (string $line) {
            [$key, $value] = explode('=', $line, 2);

            return [$key => trim($value, '"')];
        });

    expect($env->only(['APP_ENV', 'APP_DEBUG', 'SESSION_SECURE_COOKIE', 'SESSION_ENCRYPT', 'QUEUE_CONNECTION', 'MAIL_MAILER'])->all())->toBe([
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'SESSION_ENCRYPT' => 'true',
        'SESSION_SECURE_COOKIE' => 'true',
        'QUEUE_CONNECTION' => 'database',
        'MAIL_MAILER' => 'log',
    ])
        ->and($env['APP_URL'])->toStartWith('https://')
        ->and($env['APP_KEY'])->toBe('') // generated on the server, never shipped
        ->and($env['DB_USERNAME'])->not->toBe('root');
});

test('a new server gets reference data only, and the first admin from the command line', function () {
    $this->seed(ProductionSeeder::class);

    expect(User::query()->count())->toBe(0)
        ->and(Resident::query()->count())->toBe(0)
        ->and(Purok::query()->count())->toBe(7)
        ->and(Vaccine::query()->where('code', 'BCG')->exists())->toBeTrue();

    $this->artisan('user:create-admin', ['username' => 'Admin.Teresa', '--name' => 'Ma. Teresa Alonzo'])
        ->expectsOutputToContain('Temporary password')
        ->assertSuccessful();

    $admin = User::query()->sole();
    expect($admin->username)->toBe('admin.teresa')
        ->and($admin->role->name)->toBe(RoleName::Admin->value)
        ->and($admin->must_change_password)->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::Created)->where('table_name', 'users')->where('record_id', $admin->id)->exists())->toBeTrue();

    $this->artisan('user:create-admin', ['username' => 'admin.teresa', '--name' => 'Someone Else'])->assertFailed();
    $this->artisan('user:create-admin', ['username' => 'Bad Name!', '--name' => 'X'])->assertFailed();
});

test('the sample-data seeder refuses to run in production', function () {
    app()->instance('env', 'production');

    $this->artisan('db:seed', ['--force' => true])
        ->expectsOutputToContain('Sample data is not allowed in production')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0)
        ->and(Resident::query()->count())->toBe(0);
});

test('pages allow the GPS for this site only, so geo-tagging works on tablets', function () {
    $this->get(route('login'))->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
});
