<?php

namespace App\Models;

use App\Enums\ProgramStatus;
use App\Enums\ProgramTargetGroup;
use App\Models\Concerns\TracksAuthors;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Community health activity (immunization day, feeding program, ...).
 *
 * @property int $id
 * @property string $title
 * @property int $program_type_id
 * @property string|null $description
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable $end_at
 * @property string $venue
 * @property ProgramTargetGroup $target_group
 * @property ProgramStatus $status
 * @property-read ProgramType $type
 */
#[Fillable(['title', 'program_type_id', 'description', 'start_at', 'end_at', 'venue', 'target_group', 'status', 'created_by', 'updated_by'])]
#[ObservedBy(AuditableObserver::class)]
class HealthProgram extends Model
{
    use SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'target_group' => ProgramTargetGroup::class,
            'status' => ProgramStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ProgramType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ProgramType::class, 'program_type_id');
    }

    /**
     * @return BelongsToMany<Purok, $this>
     */
    public function puroks(): BelongsToMany
    {
        return $this->belongsToMany(Purok::class, 'program_puroks');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'program_staff');
    }

    /**
     * @return HasMany<ProgramAttendance, $this>
     */
    public function attendance(): HasMany
    {
        return $this->hasMany(ProgramAttendance::class);
    }
}
