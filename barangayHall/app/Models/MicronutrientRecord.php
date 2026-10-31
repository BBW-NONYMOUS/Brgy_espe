<?php

namespace App\Models;

use App\Enums\MicronutrientType;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vitamin A, deworming, iron and micronutrient powder given to a resident.
 *
 * @property int $id
 * @property int $resident_id
 * @property MicronutrientType $type
 * @property string|null $dose
 * @property CarbonImmutable $date_given
 * @property int $given_by
 */
#[Fillable(['resident_id', 'type', 'dose', 'date_given', 'given_by'])]
#[ObservedBy(AuditableObserver::class)]
class MicronutrientRecord extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MicronutrientType::class,
            'date_given' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function givenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'given_by');
    }
}
