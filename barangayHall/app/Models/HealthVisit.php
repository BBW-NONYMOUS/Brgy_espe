<?php

namespace App\Models;

use App\Models\Concerns\TracksAuthors;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Database\Factories\HealthVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $resident_id
 * @property int $visit_type_id
 * @property CarbonImmutable $visit_date
 * @property int $attended_by
 * @property string|null $chief_complaint
 * @property string|null $diagnosis
 * @property string|null $notes
 * @property-read Resident $resident
 * @property-read VisitType $visitType
 * @property-read User $attendedBy
 * @property-read VitalSign|null $vitalSign
 * @property-read PrenatalVisit|null $prenatalVisit
 * @property-read PostnatalVisit|null $postnatalVisit
 */
#[Fillable([
    'resident_id', 'visit_type_id', 'visit_date', 'attended_by',
    'chief_complaint', 'diagnosis', 'notes', 'created_by', 'updated_by',
])]
#[ObservedBy(AuditableObserver::class)]
class HealthVisit extends Model
{
    /** @use HasFactory<HealthVisitFactory> */
    use HasFactory, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'chief_complaint' => 'encrypted',
            'diagnosis' => 'encrypted',
            'notes' => 'encrypted',
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
     * @return BelongsTo<VisitType, $this>
     */
    public function visitType(): BelongsTo
    {
        return $this->belongsTo(VisitType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function attendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by');
    }

    /**
     * @return HasOne<VitalSign, $this>
     */
    public function vitalSign(): HasOne
    {
        return $this->hasOne(VitalSign::class);
    }

    /**
     * @return HasOne<PrenatalVisit, $this>
     */
    public function prenatalVisit(): HasOne
    {
        return $this->hasOne(PrenatalVisit::class);
    }

    /**
     * @return HasOne<PostnatalVisit, $this>
     */
    public function postnatalVisit(): HasOne
    {
        return $this->hasOne(PostnatalVisit::class);
    }
}
