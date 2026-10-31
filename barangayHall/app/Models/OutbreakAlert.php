<?php

namespace App\Models;

use App\Enums\OutbreakAlertStatus;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raised by OutbreakDetectionService. Lifecycle: active -> monitoring -> closed.
 * The threshold in force when it fired is copied onto the alert.
 *
 * @property int $id
 * @property int $disease_id
 * @property int|null $purok_id
 * @property int $threshold_min_cases
 * @property int $threshold_days_window
 * @property int $case_count
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property OutbreakAlertStatus $status
 * @property int|null $acknowledged_by
 * @property CarbonImmutable|null $acknowledged_at
 * @property int|null $closed_by
 * @property CarbonImmutable|null $closed_at
 * @property string|null $closing_note
 * @property-read Disease $disease
 * @property-read Purok|null $purok
 */
#[Fillable(['disease_id', 'purok_id', 'threshold_min_cases', 'threshold_days_window', 'case_count', 'period_start', 'period_end', 'status', 'acknowledged_by', 'acknowledged_at', 'closed_by', 'closed_at', 'closing_note'])]
#[ObservedBy(AuditableObserver::class)]
class OutbreakAlert extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => OutbreakAlertStatus::class,
            'acknowledged_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [OutbreakAlertStatus::Active, OutbreakAlertStatus::Monitoring]);
    }

    /**
     * @return BelongsTo<Disease, $this>
     */
    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }

    /**
     * @return BelongsTo<Purok, $this>
     */
    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
