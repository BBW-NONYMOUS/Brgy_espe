<?php

namespace App\Models;

use App\Enums\ReferralStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only status trail of a referral.
 *
 * @property int $id
 * @property int $referral_id
 * @property ReferralStatus|null $from_status
 * @property ReferralStatus $to_status
 * @property int $changed_by
 * @property string|null $note
 * @property CarbonImmutable $created_at
 */
#[Fillable(['referral_id', 'from_status', 'to_status', 'changed_by', 'note'])]
class ReferralStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('ReferralStatusChange rows are append-only.'));
        static::deleting(fn () => throw new LogicException('ReferralStatusChange rows are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => ReferralStatus::class,
            'to_status' => ReferralStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Referral, $this>
     */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
