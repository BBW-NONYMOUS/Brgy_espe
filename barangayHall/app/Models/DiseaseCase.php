<?php

namespace App\Models;

use App\Enums\CaseClassification;
use App\Enums\CaseOutcome;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One reported case. purok_id is where the case occurred at reporting time
 * (a snapshot, not the resident's current purok).
 *
 * @property int $id
 * @property int $resident_id
 * @property int $disease_id
 * @property int $purok_id
 * @property CarbonImmutable $onset_date
 * @property CarbonImmutable $reported_date
 * @property CaseClassification $classification
 * @property CaseOutcome $outcome
 * @property CarbonImmutable|null $outcome_date
 * @property string|null $notes
 * @property int $reported_by
 * @property int|null $classified_by
 * @property int|null $health_visit_id
 * @property-read Disease $disease
 * @property-read Resident $resident
 * @property-read Purok $purok
 */
#[Fillable(['resident_id', 'disease_id', 'purok_id', 'onset_date', 'reported_date', 'classification', 'outcome', 'outcome_date', 'notes', 'reported_by', 'classified_by', 'health_visit_id'])]
#[ObservedBy(AuditableObserver::class)]
class DiseaseCase extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'onset_date' => 'date',
            'reported_date' => 'date',
            'classification' => CaseClassification::class,
            'outcome' => CaseOutcome::class,
            'outcome_date' => 'date',
            'notes' => 'encrypted',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeCountable(Builder $query): void
    {
        // Cases that count toward outbreak thresholds (ruled-out cases do not).
        $query->whereIn('classification', [CaseClassification::Suspected, CaseClassification::Probable, CaseClassification::Confirmed]);
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return BelongsTo<Disease, $this>
     */
    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }

    /**
     * @return BelongsTo<Purok, $this>
     */
    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function classifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classified_by');
    }

    /**
     * @return BelongsTo<HealthVisit, $this>
     */
    public function healthVisit(): BelongsTo
    {
        return $this->belongsTo(HealthVisit::class);
    }
}
