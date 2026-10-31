<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only stock ledger. Signed quantity (+ in, - out).
 *
 * @property int $id
 * @property int $medicine_batch_id
 * @property StockMovementType $type
 * @property int $quantity
 * @property int|null $resident_id
 * @property int|null $health_visit_id
 * @property int $user_id
 * @property string|null $reason
 * @property CarbonImmutable $created_at
 * @property-read MedicineBatch $batch
 */
#[Fillable(['medicine_batch_id', 'type', 'quantity', 'resident_id', 'health_visit_id', 'user_id', 'reason'])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('StockMovement rows are append-only.'));
        static::deleting(fn () => throw new LogicException('StockMovement rows are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
        ];
    }

    /**
     * @return BelongsTo<MedicineBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
