<?php

namespace App\Models;

use App\Enums\MaternalVisitKind;
use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Configurable prenatal/postnatal contact. Prenatal offset = age of gestation
 * in days; postnatal offset = days after delivery.
 *
 * @property int $id
 * @property MaternalVisitKind $kind
 * @property int $sequence
 * @property string $label
 * @property int $offset_days
 * @property bool $is_active
 */
#[Fillable(['kind', 'sequence', 'label', 'offset_days', 'is_active'])]
#[ObservedBy(AuditableObserver::class)]
class MaternalVisitRule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MaternalVisitKind::class,
            'is_active' => 'boolean',
        ];
    }
}
