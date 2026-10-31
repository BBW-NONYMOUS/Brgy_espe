<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

function attemptLogin(User $user, string $password): TestResponse
{
    return test()->post(route('login.store'), ['username' => $user->username, 'password' => $password]);
}

test('a successful login records the time and an audit entry', function () {
    $user = User::factory()->create();

    attemptLogin($user, 'password')->assertRedirect(route('dashboard'));

    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::Login)->where('user_id', $user->id)->exists())->toBeTrue();
});

test('the account locks after five wrong passwords', function () {
    $user = User::factory()->create();

    foreach (range(1, 4) as $_) {
        attemptLogin($user, 'wrong-password')->assertSessionHasErrors('username');
    }

    attemptLogin($user, 'wrong-password')->assertSessionHasErrors(['username' => 'Too many failed attempts. This account is locked for 15 minutes.']);

    expect($user->fresh()->isLocked())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::Locked)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::LoginFailed)->count())->toBe(5);

    // Fortify's per-minute throttle is a separate layer; clear it so this
    // checks the account lock itself.
    RateLimiter::clear(md5('login'.$user->username.'|127.0.0.1'));

    // Even the right password is refused while locked.
    attemptLogin($user, 'password')->assertSessionHasErrors('username');
    $this->assertGuest();

    // ...until the lock expires.
    $this->travel(16)->minutes();
    attemptLogin($user, 'password')->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('a deactivated account cannot log in', function () {
    $user = User::factory()->inactive()->create();

    attemptLogin($user, 'password')->assertSessionHasErrors(['username' => 'This account has been deactivated. Please contact the administrator.']);

    $this->assertGuest();
});

test('a deactivated user is logged out on their next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->update(['is_active' => false]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('users must change their temporary password before using the system', function () {
    $user = User::factory()->mustChangePassword()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertRedirect(route('password.change'));
    $this->get(route('password.change'))->assertOk()->assertInertia(fn ($page) => $page->component('auth/change-password'));

    $this->put(route('password.change.store'), [
        'current_password' => 'password',
        'password' => 'Kalawag-Health-2026',
        'password_confirmation' => 'Kalawag-Health-2026',
    ])->assertRedirect(route('dashboard'));

    expect($user->fresh()->must_change_password)->toBeFalse()
        ->and(AuditLog::query()->where('action', AuditAction::PasswordChanged)->exists())->toBeTrue();

    $this->get(route('dashboard'))->assertOk();
});

test('the new password must meet the password policy', function () {
    $this->actingAs(User::factory()->mustChangePassword()->create());

    $this->put(route('password.change.store'), [
        'current_password' => 'password',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});

test('the login page shows the health center details for the privacy notice', function () {
    $this->get(route('login'))->assertInertia(fn ($page) => $page
        ->component('auth/login')
        ->where('healthCenter.barangay', 'Kalawag II'));
});

test('responses carry security headers and are not cached when logged in', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'no-store, private');
});
