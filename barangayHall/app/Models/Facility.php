<?php

namespace App\Models;

use App\Enums\FacilityType;
use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Referral destination (RHU, hospital, other).
 *
 * @property int $id
 * @property string $name
 * @property FacilityType $type
 * @property string|null $address
 * @property string|null $contact_no
 * @property bool $is_active
 */
#[Fillable(['name', 'type', 'address', 'contact_no', 'is_active'])]
#[ObservedBy(AuditableObserver::class)]
class Facility extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FacilityType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Referral, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }
}
