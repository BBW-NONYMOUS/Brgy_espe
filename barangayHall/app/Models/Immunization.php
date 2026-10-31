<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $resident_id
 * @property int $vaccine_id
 * @property int $dose_no
 * @property CarbonImmutable $date_given
 * @property int $given_by
 * @property string|null $lot_no
 * @property string|null $remarks
 * @property-read Vaccine $vaccine
 */
#[Fillable(['resident_id', 'vaccine_id', 'dose_no', 'date_given', 'given_by', 'lot_no', 'remarks'])]
#[ObservedBy(AuditableObserver::class)]
class Immunization extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_given' => 'date',
            'dose_no' => 'integer',
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
     * @return BelongsTo<Vaccine, $this>
     */
    public function vaccine(): BelongsTo
    {
        return $this->belongsTo(Vaccine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function givenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'given_by');
    }
}
