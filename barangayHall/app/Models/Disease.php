<?php

namespace App\Models;

use App\Enums\PidsrCategory;
use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Notifiable disease list (DOH PIDSR categories), editable by the Admin.
 *
 * @property int $id
 * @property string $name
 * @property string|null $icd10_code
 * @property PidsrCategory $pidsr_category
 * @property bool $is_notifiable
 * @property bool $is_active
 */
#[Fillable(['name', 'icd10_code', 'pidsr_category', 'is_notifiable', 'is_active'])]
#[ObservedBy(AuditableObserver::class)]
class Disease extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pidsr_category' => PidsrCategory::class,
            'is_notifiable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<DiseaseCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(DiseaseCase::class);
    }

    /**
     * @return HasMany<OutbreakThreshold, $this>
     */
    public function thresholds(): HasMany
    {
        return $this->hasMany(OutbreakThreshold::class);
    }

    /**
     * @return HasMany<OutbreakAlert, $this>
     */
    public function outbreakAlerts(): HasMany
    {
        return $this->hasMany(OutbreakAlert::class);
    }
}
