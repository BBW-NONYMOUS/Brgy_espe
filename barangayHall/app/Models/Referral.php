<?php

namespace App\Models;

use App\Enums\ReferralStatus;
use App\Enums\ReferralUrgency;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Referral from the barangay health center to the RHU / a hospital.
 *
 * @property int $id
 * @property string $referral_no
 * @property int $resident_id
 * @property int $facility_id
 * @property int|null $health_visit_id
 * @property int $referred_by
 * @property CarbonImmutable $referred_at
 * @property string $reason
 * @property ReferralUrgency $urgency
 * @property ReferralStatus $status
 * @property string|null $feedback_note
 * @property CarbonImmutable|null $completed_at
 * @property-read Resident $resident
 * @property-read Facility $facility
 */
#[Fillable(['referral_no', 'resident_id', 'facility_id', 'health_visit_id', 'referred_by', 'referred_at', 'reason', 'urgency', 'status', 'feedback_note', 'completed_at'])]
#[ObservedBy(AuditableObserver::class)]
class Referral extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'referred_at' => 'datetime',
            'reason' => 'encrypted',
            'urgency' => ReferralUrgency::class,
            'status' => ReferralStatus::class,
            'feedback_note' => 'encrypted',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<HealthVisit, $this>
     */
    public function healthVisit(): BelongsTo
    {
        return $this->belongsTo(HealthVisit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * @return HasMany<ReferralStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(ReferralStatusChange::class);
    }
}
