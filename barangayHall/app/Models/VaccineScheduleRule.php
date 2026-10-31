<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vaccine_id
 * @property int $dose_no
 * @property int $due_age_days
 * @property int|null $min_interval_days
 * @property-read Vaccine $vaccine
 */
#[Fillable(['vaccine_id', 'dose_no', 'due_age_days', 'min_interval_days'])]
#[ObservedBy(AuditableObserver::class)]
class VaccineScheduleRule extends Model
{
    /**
     * @return BelongsTo<Vaccine, $this>
     */
    public function vaccine(): BelongsTo
    {
        return $this->belongsTo(Vaccine::class);
    }
}
