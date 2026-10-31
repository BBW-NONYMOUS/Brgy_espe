<?php

namespace App\Models;

use App\Enums\ConditionStatus;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $resident_id
 * @property int $condition_id
 * @property CarbonImmutable|null $diagnosed_date
 * @property ConditionStatus $status
 * @property string|null $notes
 * @property int $recorded_by
 * @property-read Condition $condition
 */
#[Fillable(['resident_id', 'condition_id', 'diagnosed_date', 'status', 'notes', 'recorded_by'])]
#[ObservedBy(AuditableObserver::class)]
class ResidentCondition extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'diagnosed_date' => 'date',
            'status' => ConditionStatus::class,
            'notes' => 'encrypted',
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
     * @return BelongsTo<Condition, $this>
     */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
