<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lookup: community health program type (calendar colour).
 *
 * @property int $id
 * @property string $name
 * @property string $color
 */
#[Fillable(['name', 'color'])]
#[ObservedBy(AuditableObserver::class)]
class ProgramType extends Model
{
    /**
     * @return HasMany<HealthProgram, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(HealthProgram::class);
    }
}
