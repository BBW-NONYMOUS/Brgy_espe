<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\BloodType;
use App\Enums\CivilStatus;
use App\Enums\MaternalStatus;
use App\Enums\RelationshipToHead;
use App\Enums\ResidentStatus;
use App\Enums\Sex;
use App\Models\Concerns\TracksAuthors;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $resident_code
 * @property int $household_id
 * @property string $last_name
 * @property string $first_name
 * @property string|null $middle_name
 * @property string|null $suffix
 * @property Sex $sex
 * @property CarbonImmutable $birthdate
 * @property string|null $birthplace
 * @property CivilStatus $civil_status
 * @property string|null $contact_no
 * @property string|null $philhealth_no
 * @property bool $is_pwd
 * @property BloodType|null $blood_type
 * @property int|null $mother_id
 * @property RelationshipToHead $relationship_to_head
 * @property ResidentStatus $status
 * @property string|null $archive_reason
 * @property-read string $full_name
 * @property-read int $age
 * @property-read Household $household
 * @property-read Resident|null $mother
 */
#[Fillable([
    'resident_code', 'household_id', 'last_name', 'first_name', 'middle_name', 'suffix', 'sex',
    'birthdate', 'birthplace', 'civil_status', 'contact_no', 'philhealth_no', 'is_pwd', 'blood_type', 'mother_id',
    'relationship_to_head', 'status', 'archive_reason', 'created_by', 'updated_by',
])]
#[ObservedBy(AuditableObserver::class)]
class Resident extends Model
{
    /** @use HasFactory<ResidentFactory> */
    use HasFactory, SoftDeletes, TracksAuthors;

    public const SENIOR_AGE = 60;

    public const SUFFIXES = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'birthdate' => 'date',
            'civil_status' => CivilStatus::class,
            'philhealth_no' => 'encrypted',
            'is_pwd' => 'boolean',
            'blood_type' => BloodType::class,
            'relationship_to_head' => RelationshipToHead::class,
            'status' => ResidentStatus::class,
        ];
    }

    /**
     * "Dela Cruz, Juan Santos Jr."
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim(sprintf(
            '%s, %s%s%s',
            $this->last_name,
            $this->first_name,
            $this->middle_name ? ' '.$this->middle_name : '',
            $this->suffix ? ' '.$this->suffix : '',
        )));
    }

    /**
     * Age is always computed from birthdate, never stored.
     *
     * @return Attribute<int, never>
     */
    protected function age(): Attribute
    {
        return Attribute::get(fn () => (int) $this->birthdate->diffInYears(now()));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSeniors(Builder $query): void
    {
        $query->where('birthdate', '<=', now()->subYears(self::SENIOR_AGE)->toDateString());
    }

    /**
     * Residents whose age in whole years is within [$min, $max].
     *
     * @param  Builder<self>  $query
     */
    public function scopeAgedBetween(Builder $query, int $min, ?int $max = null): void
    {
        $query->where('birthdate', '<=', now()->subYears($min)->toDateString());

        if ($max !== null) {
            $query->where('birthdate', '>', now()->subYears($max + 1)->toDateString());
        }
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePregnant(Builder $query): void
    {
        $query->whereHas('pregnancies', fn (Builder $q) => $q->where('status', MaternalStatus::Ongoing));
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * @return HasMany<HealthVisit, $this>
     */
    public function healthVisits(): HasMany
    {
        return $this->hasMany(HealthVisit::class);
    }

    /**
     * @return HasMany<ResidentCondition, $this>
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(ResidentCondition::class);
    }

    /**
     * @return HasMany<Immunization, $this>
     */
    public function immunizations(): HasMany
    {
        return $this->hasMany(Immunization::class);
    }

    /**
     * @return HasMany<Pregnancy, $this>
     */
    public function pregnancies(): HasMany
    {
        return $this->hasMany(Pregnancy::class);
    }

    /**
     * The current (ongoing) pregnancy, if any.
     */
    public function currentPregnancy(): ?Pregnancy
    {
        return $this->relationLoaded('pregnancies')
            ? $this->pregnancies->firstWhere('status', MaternalStatus::Ongoing)
            : $this->pregnancies()->where('status', MaternalStatus::Ongoing)->latest('edd')->first();
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function mother(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'mother_id');
    }

    /**
     * @return HasMany<Resident, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Resident::class, 'mother_id');
    }

    /**
     * @return HasMany<EmergencyContact, $this>
     */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    /**
     * @return HasMany<MedicalAlert, $this>
     */
    public function medicalAlerts(): HasMany
    {
        return $this->hasMany(MedicalAlert::class);
    }

    /**
     * Active critical alerts — the red banner on the profile and health forms.
     *
     * @return HasMany<MedicalAlert, $this>
     */
    public function criticalAlerts(): HasMany
    {
        return $this->medicalAlerts()->where('is_active', true)->where('severity', AlertSeverity::Critical);
    }

    /**
     * @return HasMany<ResidentFamilyPlanning, $this>
     */
    public function familyPlanning(): HasMany
    {
        return $this->hasMany(ResidentFamilyPlanning::class);
    }

    /**
     * @return HasMany<GrowthMeasurement, $this>
     */
    public function growthMeasurements(): HasMany
    {
        return $this->hasMany(GrowthMeasurement::class);
    }

    /**
     * @return HasMany<MicronutrientRecord, $this>
     */
    public function micronutrientRecords(): HasMany
    {
        return $this->hasMany(MicronutrientRecord::class);
    }

    /**
     * @return HasMany<DiseaseCase, $this>
     */
    public function diseaseCases(): HasMany
    {
        return $this->hasMany(DiseaseCase::class);
    }

    /**
     * @return HasMany<Referral, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<ProgramAttendance, $this>
     */
    public function programAttendance(): HasMany
    {
        return $this->hasMany(ProgramAttendance::class);
    }

    /**
     * 4Ps is a household program; members inherit it.
     */
    public function isFourPs(): bool
    {
        return $this->household->is_4ps;
    }

    public function isSenior(): bool
    {
        return $this->age >= self::SENIOR_AGE;
    }
}
