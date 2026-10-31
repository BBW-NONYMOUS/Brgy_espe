<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => seedReferenceData());

test('an admin creates an account with a one-time temporary password', function () {
    actingAsRole(RoleName::Admin);

    $response = $this->post(route('users.store'), [
        'username' => 'BHW.Maria',
        'name' => 'Maria Clara Santos',
        'role_id' => Role::for(RoleName::Bhw)->id,
    ]);

    $response->assertRedirect(route('users.index'))->assertInertiaFlash('temporaryPassword.username', 'bhw.maria');

    $user = User::query()->where('username', 'bhw.maria')->sole();
    $temporary = session('inertia.flash_data')['temporaryPassword']['password'];

    expect($user->must_change_password)->toBeTrue()
        ->and(Hash::check($temporary, $user->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::Created)->where('table_name', 'users')->value('new_values'))->not->toHaveKey('password');
});

test('an admin cannot change their own role or deactivate themselves', function () {
    $admin = actingAsRole(RoleName::Admin);

    $this->put(route('users.update', $admin), [
        'username' => $admin->username,
        'name' => $admin->name,
        'role_id' => Role::for(RoleName::Bhw)->id,
        'is_active' => false,
    ])->assertSessionHasErrors('role_id');

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('resetting a password unlocks the account and forces a change', function () {
    actingAsRole(RoleName::Admin);
    $user = User::factory()->create(['locked_until' => now()->addMinutes(10), 'failed_attempts' => 3]);

    $this->post(route('users.reset-password', $user))->assertInertiaFlash('temporaryPassword.username', $user->username);

    $user->refresh();
    expect($user->isLocked())->toBeFalse()
        ->and($user->must_change_password)->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::PasswordReset)->exists())->toBeTrue();
});

test('non-admins cannot manage accounts', function (RoleName $role) {
    actingAsRole($role);
    $other = User::factory()->create();

    $this->post(route('users.store'), ['username' => 'x.y', 'name' => 'X', 'role_id' => 1])->assertForbidden();
    $this->post(route('users.reset-password', $other))->assertForbidden();
})->with([RoleName::Bhw, RoleName::Midwife, RoleName::Official]);

test('audit logs are read-only: there are no routes to change them', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'audit-logs'));

    expect($routes->flatMap->methods()->unique()->values()->all())->toEqualCanonicalizing(['GET', 'HEAD']);
});
