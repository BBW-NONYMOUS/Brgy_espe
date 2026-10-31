<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\MedicalAlertType;
use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Allergy / chronic condition / disability flag. Active critical alerts are
 * shown as a red banner on the profile and on every health form.
 *
 * @property int $id
 * @property int $resident_id
 * @property MedicalAlertType $alert_type
 * @property string $description
 * @property AlertSeverity $severity
 * @property bool $is_active
 * @property int $recorded_by
 */
#[Fillable(['resident_id', 'alert_type', 'description', 'severity', 'is_active', 'recorded_by'])]
#[ObservedBy(AuditableObserver::class)]
class MedicalAlert extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alert_type' => MedicalAlertType::class,
            'description' => 'encrypted',
            'severity' => AlertSeverity::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
