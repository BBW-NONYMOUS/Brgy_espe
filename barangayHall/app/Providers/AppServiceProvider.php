<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Enums\PermissionName;
use App\Models\EmergencyContact;
use App\Models\GrowthMeasurement;
use App\Models\HealthVisit;
use App\Models\Immunization;
use App\Models\MedicalAlert;
use App\Models\MicronutrientRecord;
use App\Models\ResidentCondition;
use App\Models\User;
use App\Policies\HealthRecordPolicy;
use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\MysqlDumper;
use App\Services\Backup\SqliteDumper;
use App\Services\Sms\LogSmsGateway;
use App\Support\MysqlSchema;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DatabaseDumper::class, fn () => MysqlSchema::supported()
            ? new MysqlDumper
            : new SqliteDumper);

        // Only the log driver exists in version 1 (see App\Contracts\SmsGateway).
        $this->app->bind(SmsGateway::class, fn () => match (config('barangay.sms.driver')) {
            default => new LogSmsGateway,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Single resources render as plain objects; paginated collections keep data/links/meta.
        JsonResource::withoutWrapping();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        if (app()->isProduction()) {
            URL::forceHttps();
        }

        // No uncompromised() check: the health center runs offline on a LAN.
        Password::defaults(fn (): Password => Password::min(10)
            ->letters()
            ->mixedCase()
            ->numbers());
    }

    /**
     * One Gate per permission (e.g. Gate::allows('reports.export')), plus the
     * shared policy for clinical records. Model policies for Resident,
     * Household and User are auto-discovered from app/Policies.
     */
    protected function configureAuthorization(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->is_active && $user->hasPermission($permission));
        }

        foreach ([
            HealthVisit::class, Immunization::class, ResidentCondition::class,
            EmergencyContact::class, MedicalAlert::class, GrowthMeasurement::class, MicronutrientRecord::class,
        ] as $model) {
            Gate::policy($model, HealthRecordPolicy::class);
        }
    }
}
