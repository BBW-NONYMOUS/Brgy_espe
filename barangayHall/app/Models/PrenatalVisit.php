<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prenatal findings for one health visit. Date, staff, BP and weight are on
 * the linked HealthVisit / VitalSign.
 *
 * @property int $id
 * @property int $pregnancy_id
 * @property int $health_visit_id
 * @property int|null $maternal_visit_rule_id
 * @property string|null $fundal_height_cm
 * @property int|null $fetal_heart_rate
 * @property string|null $remarks
 * @property-read Pregnancy $pregnancy
 * @property-read HealthVisit $healthVisit
 */
#[Fillable(['pregnancy_id', 'health_visit_id', 'maternal_visit_rule_id', 'fundal_height_cm', 'fetal_heart_rate', 'remarks'])]
#[ObservedBy(AuditableObserver::class)]
class PrenatalVisit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fundal_height_cm' => 'decimal:1',
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
