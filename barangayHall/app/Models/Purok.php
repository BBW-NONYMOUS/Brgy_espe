<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property string|null $center_latitude
 * @property string|null $center_longitude
 */
#[Fillable(['name', 'sort_order', 'center_latitude', 'center_longitude'])]
#[ObservedBy(AuditableObserver::class)]
class Purok extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'center_latitude' => 'decimal:7',
            'center_longitude' => 'decimal:7',
        ];
    }

    /**
     * @return HasMany<Household, $this>
     */
    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }

    /**
     * @return HasManyThrough<Resident, Household, $this>
     */
    public function residents(): HasManyThrough
    {
        return $this->hasManyThrough(Resident::class, Household::class);
    }
}
