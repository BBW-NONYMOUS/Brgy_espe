<?php

namespace App\Models;

use App\Enums\OutbreakScope;
use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "N or more cases of a disease in the same purok (or barangay) within D days."
 *
 * @property int $id
 * @property int $disease_id
 * @property OutbreakScope $scope
 * @property int $min_cases
 * @property int $days_window
 * @property bool $is_active
 * @property-read Disease $disease
 */
#[Fillable(['disease_id', 'scope', 'min_cases', 'days_window', 'is_active', 'updated_by'])]
#[ObservedBy(AuditableObserver::class)]
class OutbreakThreshold extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => OutbreakScope::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Disease, $this>
     */
    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }
}
