<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 */
#[Fillable(['name'])]
#[ObservedBy(AuditableObserver::class)]
class VisitType extends Model
{
    /**
     * @return HasMany<HealthVisit, $this>
     */
    public function healthVisits(): HasMany
    {
        return $this->hasMany(HealthVisit::class);
    }
}
