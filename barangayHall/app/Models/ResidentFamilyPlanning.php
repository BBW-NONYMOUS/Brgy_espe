<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A resident's family planning method over a period (current = ended_on NULL).
 * Only recorded when the resident has an active family_planning consent.
 *
 * @property int $id
 * @property int $resident_id
 * @property int $family_planning_method_id
 * @property CarbonImmutable $started_on
 * @property CarbonImmutable|null $ended_on
 * @property int $recorded_by
 * @property-read FamilyPlanningMethod $method
 */
#[Fillable(['resident_id', 'family_planning_method_id', 'started_on', 'ended_on', 'recorded_by'])]
#[ObservedBy(AuditableObserver::class)]
class ResidentFamilyPlanning extends Model
{
    protected $table = 'resident_family_planning';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
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
     * @return BelongsTo<FamilyPlanningMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(FamilyPlanningMethod::class, 'family_planning_method_id');
    }
}
