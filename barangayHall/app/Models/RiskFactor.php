<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Lookup: high-risk pregnancy factor.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'is_active'])]
#[ObservedBy(AuditableObserver::class)]
class RiskFactor extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Pregnancy, $this>
     */
    public function pregnancies(): BelongsToMany
    {
        return $this->belongsToMany(Pregnancy::class);
    }
}
