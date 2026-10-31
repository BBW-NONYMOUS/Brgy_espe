<?php

namespace App\Models;

use App\Enums\NutritionalStatus;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Growth monitoring (Operation Timbang). Status is picked from the OPT Plus chart.
 *
 * @property int $id
 * @property int $resident_id
 * @property CarbonImmutable $measured_on
 * @property string $weight_kg
 * @property string|null $height_cm
 * @property string|null $muac_cm
 * @property NutritionalStatus|null $nutritional_status
 * @property int $measured_by
 * @property string|null $remarks
 */
#[Fillable(['resident_id', 'measured_on', 'weight_kg', 'height_cm', 'muac_cm', 'nutritional_status', 'measured_by', 'remarks'])]
#[ObservedBy(AuditableObserver::class)]
class GrowthMeasurement extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_on' => 'date',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:1',
            'muac_cm' => 'decimal:1',
            'nutritional_status' => NutritionalStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function measuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'measured_by');
    }
}
