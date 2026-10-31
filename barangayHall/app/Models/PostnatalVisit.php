<?php

namespace App\Models;

use App\Enums\BreastfeedingStatus;
use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pregnancy_id
 * @property int $health_visit_id
 * @property int|null $maternal_visit_rule_id
 * @property BreastfeedingStatus|null $breastfeeding
 * @property bool $family_planning_counseled
 * @property string|null $remarks
 * @property-read Pregnancy $pregnancy
 * @property-read HealthVisit $healthVisit
 */
#[Fillable(['pregnancy_id', 'health_visit_id', 'maternal_visit_rule_id', 'breastfeeding', 'family_planning_counseled', 'remarks'])]
#[ObservedBy(AuditableObserver::class)]
class PostnatalVisit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'breastfeeding' => BreastfeedingStatus::class,
            'family_planning_counseled' => 'boolean',
            'remarks' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Pregnancy, $this>
     */
    public function pregnancy(): BelongsTo
    {
        return $this->belongsTo(Pregnancy::class);
    }

    /**
     * @return BelongsTo<HealthVisit, $this>
     */
    public function healthVisit(): BelongsTo
    {
        return $this->belongsTo(HealthVisit::class);
    }

    /**
     * @return BelongsTo<MaternalVisitRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(MaternalVisitRule::class, 'maternal_visit_rule_id');
    }
}
