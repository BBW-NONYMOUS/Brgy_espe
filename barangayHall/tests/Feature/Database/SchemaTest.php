<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\HealthVisit;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

test('the database seeder builds the sample barangay', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->count())->toBe(4)
        ->and(User::query()->count())->toBe(5)
        ->and(Resident::withTrashed()->count())->toBe(51)
        ->and(Resident::query()->count())->toBe(50)
        ->and(Resident::query()->pregnant()->count())->toBe(3)
        ->and(Resident::query()->seniors()->count())->toBeGreaterThanOrEqual(8)
        ->and(User::query()->where('must_change_password', false)->count())->toBe(0);
});

test('barangay officials only receive aggregate, read-only permissions', function () {
    $official = Role::for(RoleName::Official);

    expect($official->permissionNames()->sort()->values()->all())->toBe([
        PermissionName::MapViewAggregate->value,
        PermissionName::OutbreaksViewSummary->value,
        PermissionName::ProgramsView->value,
        PermissionName::ReportsExport->value,
        PermissionName::ReportsView->value,
    ]);
});

test('only admins may permanently delete residents or manage users', function (RoleName $role, bool $allowed) {
    $user = User::factory()->role($role)->create();

    expect($user->hasPermission(PermissionName::ResidentsForceDelete))->toBe($allowed)
        ->and($user->hasPermission(PermissionName::UsersManage))->toBe($allowed);
})->with([
    'admin' => [RoleName::Admin, true],
    'bhw' => [RoleName::Bhw, false],
    'midwife' => [RoleName::Midwife, false],
    'official' => [RoleName::Official, false],
]);

test('sensitive fields are encrypted at rest', function () {
    $resident = Resident::factory()->create(['philhealth_no' => '02-051234567-1']);
    $visit = HealthVisit::factory()->for($resident)->create(['diagnosis' => 'Essential hypertension']);

    $rawResident = DB::table('residents')->where('id', $resident->id)->value('philhealth_no');
    $rawVisit = DB::table('health_visits')->where('id', $visit->id)->value('diagnosis');

    expect($rawResident)->not->toContain('051234567')
        ->and($rawVisit)->not->toContain('hypertension')
        ->and($resident->fresh()->philhealth_no)->toBe('02-051234567-1')
        ->and($visit->fresh()->diagnosis)->toBe('Essential hypertension');
});

test('age is computed from birthdate, not stored', function () {
    $resident = Resident::factory()->create(['birthdate' => now()->subYears(61)->subDay()]);

    expect($resident->age)->toBe(61)
        ->and(Resident::query()->seniors()->whereKey($resident->id)->exists())->toBeTrue()
        ->and(DB::getSchemaBuilder()->hasColumn('residents', 'age'))->toBeFalse();
});

test('archiving a resident is a soft delete', function () {
    $resident = Resident::factory()->create();

    $resident->delete();

    expect(Resident::query()->find($resident->id))->toBeNull()
        ->and(Resident::withTrashed()->find($resident->id))->not->toBeNull();
});

test('audit logs are append-only', function () {
    $log = AuditLog::query()->create(['action' => AuditAction::Login, 'description' => 'test']);

    expect(fn () => $log->update(['description' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class)
        ->and(AuditLog::query()->find($log->id)->description)->toBe('test');
});
