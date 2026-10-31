<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $doses_required
 */
#[Fillable(['code', 'name', 'doses_required'])]
#[ObservedBy(AuditableObserver::class)]
class Vaccine extends Model
{
    /**
     * @return HasMany<VaccineScheduleRule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(VaccineScheduleRule::class)->orderBy('dose_no');
    }

    /**
     * @return HasMany<Immunization, $this>
     */
    public function immunizations(): HasMany
    {
        return $this->hasMany(Immunization::class);
    }
}
