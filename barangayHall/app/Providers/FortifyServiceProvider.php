<?php

namespace App\Providers;

use App\Enums\AuditAction;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasskeyLoginResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(PasskeyLoginResponseContract::class, PasskeyLoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, TwoFactorLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Username + password check with account lockout:
     * after N consecutive failures the account is locked for M minutes
     * (config/barangay.php). Deactivated accounts cannot log in.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $audit = app(AuditLogger::class);
            $username = Str::lower(trim((string) $request->input('username')));
            $user = User::query()->with('role')->where('username', $username)->first();

            if ($user === null) {
                $audit->log(AuditAction::LoginFailed, description: "Failed login for unknown username \"{$username}\"");

                return null;
            }

            if ($user->isLocked()) {
                $minutes = (int) ceil(now()->diffInMinutes($user->locked_until));

                throw ValidationException::withMessages([
                    Fortify::username() => __('This account is locked after too many failed attempts. Try again in :minutes minute(s) or ask the administrator to unlock it.', ['minutes' => max($minutes, 1)]),
                ]);
            }

            if (! Hash::check((string) $request->input('password'), $user->password)) {
                $this->recordFailedAttempt($user, $audit);

                return null;
            }

            if (! $user->is_active) {
                $audit->log(AuditAction::LoginFailed, $user, 'Login refused: account is deactivated', user: $user);

                throw ValidationException::withMessages([
                    Fortify::username() => __('This account has been deactivated. Please contact the administrator.'),
                ]);
            }

            $user->forceFill(['failed_attempts' => 0, 'locked_until' => null])->save();

            return $user;
        });
    }

    private function recordFailedAttempt(User $user, AuditLogger $audit): void
    {
        $max = (int) config('barangay.lockout.max_attempts');
        $attempts = $user->failed_attempts + 1;

        $user->forceFill(['failed_attempts' => $attempts])->save();
        $audit->log(AuditAction::LoginFailed, $user, "Wrong password (attempt {$attempts} of {$max})", user: $user);

        if ($attempts >= $max) {
            $minutes = (int) config('barangay.lockout.minutes');

            $user->forceFill(['locked_until' => now()->addMinutes($minutes), 'failed_attempts' => 0])->save();
            $audit->log(AuditAction::Locked, $user, "Account locked for {$minutes} minutes after {$max} failed attempts", user: $user);

            throw ValidationException::withMessages([
                Fortify::username() => __('Too many failed attempts. This account is locked for :minutes minutes.', ['minutes' => $minutes]),
            ]);
        }
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
            'healthCenter' => [
                'name' => config('barangay.health_center'),
                'barangay' => config('barangay.barangay'),
                'municipality' => config('barangay.municipality'),
                'province' => config('barangay.province'),
            ],
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
