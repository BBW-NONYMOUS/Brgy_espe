<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $health_visit_id
 * @property int|null $bp_systolic
 * @property int|null $bp_diastolic
 * @property string|null $weight_kg
 * @property string|null $height_cm
 * @property string|null $temperature_c
 * @property int|null $pulse_rate
 * @property int|null $respiratory_rate
 */
#[Fillable([
    'health_visit_id', 'bp_systolic', 'bp_diastolic', 'weight_kg', 'height_cm',
    'temperature_c', 'pulse_rate', 'respiratory_rate',
])]
#[ObservedBy(AuditableObserver::class)]
class VitalSign extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:1',
            'temperature_c' => 'decimal:1',
        ];
    }

    /**
     * @return BelongsTo<HealthVisit, $this>
     */
    public function healthVisit(): BelongsTo
    {
        return $this->belongsTo(HealthVisit::class);
    }

    /**
     * BMI is derived, so it is computed on read and never stored.
     */
    public function bmi(): ?float
    {
        if (! $this->weight_kg || ! $this->height_cm) {
            return null;
        }

        $meters = (float) $this->height_cm / 100;

        return round((float) $this->weight_kg / ($meters * $meters), 1);
    }
}
