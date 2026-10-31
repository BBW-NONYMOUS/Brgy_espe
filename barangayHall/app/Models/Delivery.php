<?php

namespace App\Models;

use App\Enums\BirthAttendant;
use App\Enums\DeliveryMode;
use App\Enums\DeliveryPlace;
use App\Models\Concerns\TracksAuthors;
use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Delivery details for a pregnancy (1:1). The delivery date is the
 * pregnancy's outcome_date; live-born babies are residents whose mother_id
 * is the mother and whose birthdate is that date.
 *
 * @property int $id
 * @property int $pregnancy_id
 * @property string|null $delivery_time
 * @property DeliveryPlace $place_type
 * @property string|null $place_name
 * @property BirthAttendant $attendant_type
 * @property DeliveryMode $delivery_mode
 * @property int $live_births
 * @property int $stillbirths
 * @property string|null $remarks
 * @property-read Pregnancy $pregnancy
 */
#[Fillable([
    'pregnancy_id', 'delivery_time', 'place_type', 'place_name', 'attendant_type', 'delivery_mode',
    'live_births', 'stillbirths', 'remarks', 'created_by', 'updated_by',
])]
#[ObservedBy(AuditableObserver::class)]
class Delivery extends Model
{
    use TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'place_type' => DeliveryPlace::class,
            'attendant_type' => BirthAttendant::class,
            'delivery_mode' => DeliveryMode::class,
            'remarks' => 'encrypted',
        ];
    }

    /**
     * DOH indicator: delivered in a health facility or birthing home.
     */
    public function isFacilityBased(): bool
    {
        return in_array($this->place_type, [DeliveryPlace::HealthFacility, DeliveryPlace::BirthingHome], true);
    }

    /**
     * DOH indicator: attended by a skilled health professional.
     */
    public function isSkilledAttendant(): bool
    {
        return in_array($this->attendant_type, [BirthAttendant::Doctor, BirthAttendant::Nurse, BirthAttendant::Midwife], true);
    }

    /**
     * @return BelongsTo<Pregnancy, $this>
     */
    public function pregnancy(): BelongsTo
    {
        return $this->belongsTo(Pregnancy::class);
    }
}
