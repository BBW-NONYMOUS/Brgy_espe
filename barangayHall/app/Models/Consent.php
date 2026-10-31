<?php

namespace App\Models;

use App\Enums\ConsentType;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $resident_id
 * @property ConsentType $consent_type
 * @property CarbonImmutable $consent_date
 * @property int $obtained_by
 * @property string $purpose
 * @property CarbonImmutable|null $revoked_at
 */
#[Fillable(['resident_id', 'consent_type', 'consent_date', 'obtained_by', 'purpose', 'revoked_at'])]
#[ObservedBy(AuditableObserver::class)]
class Consent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consent_type' => ConsentType::class,
            'consent_date' => 'date',
            'revoked_at' => 'date',
        ];
    }

    /**
     * Consents of a type that have not been revoked.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActiveOfType(Builder $query, ConsentType $type): void
    {
        $query->where('consent_type', $type)->whereNull('revoked_at');
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
    public function obtainedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'obtained_by');
    }
}
