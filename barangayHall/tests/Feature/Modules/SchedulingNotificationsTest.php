<?php

use App\Enums\AppointmentStatus;
use App\Enums\ProgramTargetGroup;
use App\Enums\RoleName;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\HealthProgram;
use App\Models\NotificationDispatch;
use App\Models\ProgramType;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\MissedAppointmentsDigest;
use App\Notifications\OutbreakDetected;
use App\Notifications\UpcomingProgram;
use App\Services\ProgramService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedReferenceData();
});

function program(array $attributes = []): HealthProgram
{
    return HealthProgram::query()->create([
        'title' => 'Feeding program',
        'program_type_id' => ProgramType::query()->value('id'),
        'start_at' => now()->addDays(2)->setTime(9, 0),
        'end_at' => now()->addDays(2)->setTime(11, 0),
        'venue' => 'Day Care Center',
        'target_group' => ProgramTargetGroup::UnderFive,
        ...$attributes,
    ]);
}

test('the calendar feed gives officials programs only and staff both', function () {
    $resident = residentInPurok();
    Appointment::query()->create([
        'resident_id' => $resident->id, 'appointment_type_id' => AppointmentType::query()->value('id'),
        'scheduled_at' => now()->addDay()->setTime(9, 0), 'status' => 'scheduled',
    ]);
    program();
    $range = ['start' => today()->toDateString(), 'end' => today()->addDays(7)->toDateString()];

    actingAsRole(RoleName::Official);
    $events = $this->getJson(route('calendar.events', $range))->assertOk()->json();
    expect(collect($events)->pluck('extendedProps.kind')->all())->toBe(['program'])
        ->and(json_encode($events))->not->toContain($resident->last_name);

    actingAsRole(RoleName::Bhw);
    expect(collect($this->getJson(route('calendar.events', $range))->json())->pluck('extendedProps.kind')->sort()->values()->all())
        ->toBe(['appointment', 'program']);
});

test('program eligibility follows the target group and puroks; officials see counts, not names', function () {
    $child = residentInPurok('Purok 2', ['birthdate' => today()->subYears(2)]);
    residentInPurok('Purok 4', ['birthdate' => today()->subYears(2)]);
    residentInPurok('Purok 2', ['birthdate' => today()->subYears(30)]);
    $program = program(['start_at' => now()->subHour(), 'end_at' => now()->addHour()]);
    $program->puroks()->sync([$child->household->purok_id]);

    expect(app(ProgramService::class)->eligible($program)->pluck('id')->all())->toBe([$child->id]);

    actingAsRole(RoleName::Bhw);
    $this->post(route('programs.attendance.store', $program), ['resident_ids' => [$child->id, $child->id]])->assertSessionHasNoErrors();
    $this->post(route('programs.attendance.store', $program), ['resident_ids' => [$child->id]])->assertSessionHasNoErrors();
    expect($program->attendance()->count())->toBe(1);

    actingAsRole(RoleName::Official);
    $this->get(route('programs.show', $program))->assertInertia(fn (Assert $page) => $page
        ->where('attendance', null)
        ->where('program.attendance_count', 1)
        ->where('attendanceByPurok.Purok 2', 1));
    $this->post(route('programs.attendance.store', $program), ['resident_ids' => [$child->id]])->assertForbidden();
});

test('attendance cannot be taken before a program starts', function () {
    $program = program();

    actingAsRole(RoleName::Bhw);
    $this->post(route('programs.attendance.store', $program), ['resident_ids' => [residentInPurok()->id]])->assertSessionHasErrors('resident_ids');
});

test('the daily checks mark missed appointments, notify once and skip a second run', function () {
    $this->seed(DatabaseSeeder::class);
    $bhw = User::query()->where('username', 'bhw.rosario')->sole();
    $official = User::query()->where('username', 'kgd.ronaldo')->sole();
    $before = Appointment::query()->where('status', AppointmentStatus::Missed)->count();

    expect(Artisan::call('health:daily-checks'))->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('Outbreak thresholds')->toContain('Medicine expiry')
        // Yesterday's seeded appointment (Felicidad, assigned to Rosario) is already missed;
        // overdue ANC/EPI notifications and the medicine digest went out.
        ->and(NotificationDispatch::query()->where('dedupe_key', 'expiry:'.today()->toDateString())->exists())->toBeTrue()
        ->and(NotificationDispatch::query()->where('dedupe_key', 'like', 'overdue-maternal:%')->exists())->toBeTrue()
        ->and(Appointment::query()->where('status', AppointmentStatus::Missed)->count())->toBeGreaterThanOrEqual($before)
        // Officials hear about programs, never about medicines or missed appointments.
        ->and($official->notifications()->where('type', UpcomingProgram::class)->exists())->toBeTrue()
        ->and($official->notifications()->whereNotIn('type', [UpcomingProgram::class, OutbreakDetected::class])->exists())->toBeFalse();

    $count = User::query()->get()->sum(fn (User $u) => $u->notifications()->count());

    Artisan::call('health:daily-checks');
    expect(Artisan::output())->toContain('already completed');

    Artisan::call('health:daily-checks', ['--force' => true]);
    expect(User::query()->get()->sum(fn (User $u) => $u->notifications()->count()))->toBe($count);
});

test('missed appointments are reported to the assigned staff member', function () {
    $staff = User::factory()->bhw()->create();
    Appointment::query()->create([
        'resident_id' => residentInPurok()->id, 'appointment_type_id' => AppointmentType::query()->value('id'),
        'scheduled_at' => now()->subDays(2), 'status' => 'scheduled', 'assigned_to' => $staff->id,
    ]);

    Artisan::call('health:daily-checks', ['--force' => true]);

    expect(Appointment::query()->sole()->status)->toBe(AppointmentStatus::Missed)
        ->and($staff->notifications()->where('type', MissedAppointmentsDigest::class)->count())->toBe(1);
});

test('users only reach their own notifications; opening one marks it read and follows its link', function () {
    $mine = actingAsRole(RoleName::Bhw);
    $other = User::factory()->bhw()->create();
    foreach ([$mine, $other] as $user) {
        $user->notify(new UpcomingProgram('Upcoming: Feeding program', 'Tomorrow 9 AM', '/programs'));
    }
    $notification = $mine->notifications()->sole();

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('unreadNotifications', 1)
        ->missing('notificationsLatest'));

    $this->get(route('notifications.open', $other->notifications()->sole()->id))->assertNotFound();
    $this->get(route('notifications.open', $notification->id))->assertRedirect('/programs');

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($other->unreadNotifications()->count())->toBe(1);
});

test('generated appointments carry no clinical notes for roles without health access', function () {
    $appointment = Appointment::query()->create([
        'resident_id' => residentInPurok()->id, 'appointment_type_id' => AppointmentType::query()->value('id'),
        'scheduled_at' => now()->addHour(), 'status' => 'scheduled', 'notes' => 'Bring previous BP log',
    ]);

    actingAsRole(RoleName::Bhw);
    $this->get(route('appointments.index'))->assertInertia(fn (Assert $page) => $page
        ->where('appointments.data.0.notes', 'Bring previous BP log'));

    expect(Resident::query()->count())->toBe(1);
    actingAsRole(RoleName::Official);
    $this->get(route('appointments.index'))->assertForbidden();
});
