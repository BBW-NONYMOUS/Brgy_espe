<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $health_program_id
 * @property int $resident_id
 * @property CarbonImmutable $attended_at
 * @property int $recorded_by
 */
#[Fillable(['health_program_id', 'resident_id', 'attended_at', 'recorded_by'])]
#[ObservedBy(AuditableObserver::class)]
class ProgramAttendance extends Model
{
    protected $table = 'program_attendance';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<HealthProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(HealthProgram::class, 'health_program_id');
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
