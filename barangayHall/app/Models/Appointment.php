<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\TracksAuthors;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Manual or auto-generated appointment. Auto-generated ones carry a source
 * (Pregnancy or Resident) and a source_key naming the rule, e.g. "anc:3".
 *
 * @property int $id
 * @property int $resident_id
 * @property int $appointment_type_id
 * @property CarbonImmutable $scheduled_at
 * @property int|null $assigned_to
 * @property AppointmentStatus $status
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $source_key
 * @property bool $edited_manually
 * @property int|null $health_visit_id
 * @property string|null $notes
 * @property CarbonImmutable|null $reminder_sent_at
 * @property-read Resident $resident
 * @property-read AppointmentType $type
 */
#[Fillable(['resident_id', 'appointment_type_id', 'scheduled_at', 'assigned_to', 'status', 'source_type', 'source_id', 'source_key', 'edited_manually', 'health_visit_id', 'notes', 'reminder_sent_at', 'created_by', 'updated_by'])]
#[ObservedBy(AuditableObserver::class)]
class Appointment extends Model
{
    use TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'edited_manually' => 'boolean',
            'notes' => 'encrypted',
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return BelongsTo<AppointmentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<HealthVisit, $this>
     */
    public function healthVisit(): BelongsTo
    {
        return $this->belongsTo(HealthVisit::class);
    }
}
