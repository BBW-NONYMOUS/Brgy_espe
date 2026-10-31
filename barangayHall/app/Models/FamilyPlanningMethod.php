<?php

namespace App\Models;

use App\Enums\FamilyPlanningCategory;
use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Lookup: DOH Family Planning Program method.
 *
 * @property int $id
 * @property string $name
 * @property FamilyPlanningCategory $category
 * @property bool $is_active
 */
#[Fillable(['name', 'category', 'is_active'])]
#[ObservedBy(AuditableObserver::class)]
class FamilyPlanningMethod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FamilyPlanningCategory::class,
            'is_active' => 'boolean',
        ];
    }
}
