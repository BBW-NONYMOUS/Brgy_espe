<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lookup: household toilet / sanitation facility.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_sanitary
 * @property int $sort_order
 */
#[Fillable(['name', 'is_sanitary', 'sort_order'])]
#[ObservedBy(AuditableObserver::class)]
class ToiletType extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_sanitary' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Household, $this>
     */
    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }
}
