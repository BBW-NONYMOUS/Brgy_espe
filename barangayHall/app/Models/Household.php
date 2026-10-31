<?php

namespace App\Models;

use App\Models\Concerns\TracksAuthors;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $household_no
 * @property int $purok_id
 * @property string $address
 * @property int|null $head_resident_id
 * @property int|null $water_source_id
 * @property int|null $toilet_type_id
 * @property bool $is_4ps
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $geotag_accuracy_m
 * @property CarbonImmutable|null $geotagged_at
 * @property int|null $geotagged_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Purok $purok
 * @property-read Resident|null $head
 * @property-read WaterSource|null $waterSource
 * @property-read ToiletType|null $toiletType
 */
#[Fillable([
    'household_no', 'purok_id', 'address', 'head_resident_id', 'water_source_id', 'toilet_type_id', 'is_4ps',
    'latitude', 'longitude', 'geotag_accuracy_m', 'geotagged_at', 'geotagged_by', 'created_by', 'updated_by',
])]
#[ObservedBy(AuditableObserver::class)]
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_4ps' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geotagged_at' => 'datetime',
        ];
    }

    public function isGeotagged(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeGeotagged(Builder $query): void
    {
        $query->whereNotNull('latitude');
    }

    /**
     * @return BelongsTo<Purok, $this>
     */
    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'head_resident_id');
    }

    /**
     * @return HasMany<Resident, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    /**
     * @return BelongsTo<WaterSource, $this>
     */
    public function waterSource(): BelongsTo
    {
        return $this->belongsTo(WaterSource::class);
    }

    /**
     * @return BelongsTo<ToiletType, $this>
     */
    public function toiletType(): BelongsTo
    {
        return $this->belongsTo(ToiletType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function geotaggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geotagged_by');
    }
}
