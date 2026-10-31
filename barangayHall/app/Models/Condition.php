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
 * @property string|null $icd10_code
 */
#[Fillable(['name', 'icd10_code'])]
#[ObservedBy(AuditableObserver::class)]
class Condition extends Model
{
    /**
     * @return HasMany<ResidentCondition, $this>
     */
    public function residentConditions(): HasMany
    {
        return $this->hasMany(ResidentCondition::class);
    }
}
