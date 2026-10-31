<?php

namespace App\Models;

use App\Enums\MaternalStatus;
use App\Enums\RiskLevel;
use App\Models\Concerns\TracksAuthors;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $resident_id
 * @property CarbonImmutable|null $lmp
 * @property CarbonImmutable $edd
 * @property int $gravida
 * @property int $para
 * @property RiskLevel $risk_level
 * @property MaternalStatus $status
 * @property CarbonImmutable|null $outcome_date
 * @property string|null $notes
 * @property-read Resident $resident
 * @property-read Delivery|null $delivery
 */
#[Fillable([
    'resident_id', 'lmp', 'edd', 'gravida', 'para', 'risk_level', 'status',
    'outcome_date', 'notes', 'created_by', 'updated_by',
])]
#[ObservedBy(AuditableObserver::class)]
class Pregnancy extends Model
{
    use SoftDeletes, TracksAuthors;

    /** Naegele's rule: EDD defaults to LMP + 280 days. */
    public const GESTATION_DAYS = 280;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lmp' => 'date',
            'edd' => 'date',
            'outcome_date' => 'date',
            'risk_level' => RiskLevel::class,
            'status' => MaternalStatus::class,
            'notes' => 'encrypted',
        ];
    }

    /**
     * Age of gestation in days on a given date (derived from the EDD, never stored).
     * Uses the EDD rather than the LMP so ultrasound-dated pregnancies are right.
     */
    public function gestationalAgeDaysOn(CarbonImmutable $date): int
    {
        return self::GESTATION_DAYS - (int) $date->startOfDay()->diffInDays($this->edd, false);
    }

    /**
     * Age of gestation in completed weeks today (ongoing pregnancies only).
     */
    public function gestationalAgeWeeks(): ?int
    {
        if ($this->status !== MaternalStatus::Ongoing) {
            return null;
        }

        return intdiv(max(0, $this->gestationalAgeDaysOn(CarbonImmutable::today())), 7);
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return BelongsToMany<RiskFactor, $this>
     */
    public function riskFactors(): BelongsToMany
    {
        return $this->belongsToMany(RiskFactor::class);
    }

    /**
     * @return HasMany<PrenatalVisit, $this>
     */
    public function prenatalVisits(): HasMany
    {
        return $this->hasMany(PrenatalVisit::class);
    }

    /**
     * @return HasMany<PostnatalVisit, $this>
     */
    public function postnatalVisits(): HasMany
    {
        return $this->hasMany(PostnatalVisit::class);
    }

    /**
     * @return HasOne<Delivery, $this>
     */
    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }
}
