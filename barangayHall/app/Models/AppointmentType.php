<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lookup: appointment type. `code` is used by the appointment generator.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $color
 * @property int $default_duration_minutes
 */
#[Fillable(['code', 'name', 'color', 'default_duration_minutes'])]
#[ObservedBy(AuditableObserver::class)]
class AppointmentType extends Model
{
    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
