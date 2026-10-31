<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock of one medicine with one batch number and expiry date.
 *
 * quantity_on_hand is a stored total kept equal to SUM(stock_movements.quantity)
 * by InventoryService (same transaction, batch row locked FOR UPDATE).
 *
 * @property int $id
 * @property int $medicine_id
 * @property string $batch_no
 * @property CarbonImmutable $expiry_date
 * @property int $quantity_received
 * @property int $quantity_on_hand
 * @property CarbonImmutable $received_at
 * @property string|null $source
 * @property int|null $expiry_alert_band
 * @property int $received_by
 * @property-read Medicine $medicine
 */
#[Fillable(['medicine_id', 'batch_no', 'expiry_date', 'quantity_received', 'quantity_on_hand', 'received_at', 'source', 'expiry_alert_band', 'received_by'])]
#[ObservedBy(AuditableObserver::class)]
class MedicineBatch extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_at' => 'date',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        // Not expired and not empty: the only batches FEFO may pick from.
        $query->where('expiry_date', '>=', today()->toDateString())->where('quantity_on_hand', '>', 0);
    }

    /**
     * The ledger already records every quantity change; logging each dispense
     * again as a batch update would flood the audit log.
     *
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        return ['quantity_on_hand', 'expiry_alert_band'];
    }

    /**
     * @return BelongsTo<Medicine, $this>
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
