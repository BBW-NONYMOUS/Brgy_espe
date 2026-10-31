<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStock;
use App\Models\HealthVisit;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Resident;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only code allowed to change medicine_batches.quantity_on_hand.
 *
 * Every change runs in one transaction that (1) locks the batch rows with
 * SELECT … FOR UPDATE, (2) writes the stock_movements ledger row and
 * (3) updates the stored total — so SUM(movements) always equals on-hand
 * and two people dispensing at once can never take the same last tablets.
 */
class InventoryService
{
    public function __construct(private readonly InventoryAlertService $alerts) {}

    /**
     * @param  array<string, mixed>  $data  validated ReceiveStockRequest data (batch_no, expiry_date, quantity, received_at?, source?)
     */
    public function receive(Medicine $medicine, array $data, User $user): MedicineBatch
    {
        return DB::transaction(function () use ($medicine, $data, $user) {
            $batch = $medicine->batches()->create([
                'batch_no' => $data['batch_no'],
                'expiry_date' => $data['expiry_date'],
                'quantity_received' => $data['quantity'],
                'quantity_on_hand' => $data['quantity'],
                'received_at' => $data['received_at'] ?? today()->toDateString(),
                'source' => $data['source'] ?? null,
                'received_by' => $user->id,
            ]);

            $source = $data['source'] ?? null;
            $this->ledger($batch, StockMovementType::In, (int) $data['quantity'], $user, reason: is_string($source) && $source !== '' ? "Received from {$source}" : 'Stock received');

            return $batch;
        });
    }

    /**
     * FEFO (first-expire, first-out): take from the usable batch that expires
     * soonest, then the next, until the quantity is filled. Expired batches
     * are never dispensed.
     *
     * @return Collection<int, StockMovement> one movement per batch used
     *
     * @throws InsufficientStock (nothing is changed)
     */
    public function dispense(Medicine $medicine, int $quantity, Resident $resident, User $user, ?HealthVisit $visit = null, ?string $reason = null): Collection
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        $movements = DB::transaction(function () use ($medicine, $quantity, $resident, $user, $visit, $reason) {
            $batches = MedicineBatch::query()
                ->where('medicine_id', $medicine->id)
                ->usable()
                ->orderBy('expiry_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = (int) $batches->sum('quantity_on_hand');

            if ($available < $quantity) {
                throw new InsufficientStock($quantity, $available);
            }

            $remaining = $quantity;
            $movements = collect();

            foreach ($batches as $batch) {
                if ($remaining === 0) {
                    break;
                }

                $take = min($remaining, $batch->quantity_on_hand);
                $batch->update(['quantity_on_hand' => $batch->quantity_on_hand - $take]);
                $movements->push($this->ledger($batch, StockMovementType::Dispensed, -$take, $user, $resident, $visit, $reason));
                $remaining -= $take;
            }

            return $movements;
        });

        $this->alerts->checkLowStock($medicine);

        return $movements;
    }

    /**
     * Physical count correction: set the batch to what is actually on the shelf.
     */
    public function adjust(MedicineBatch $batch, int $countedQuantity, string $reason, User $user): ?StockMovement
    {
        if ($countedQuantity < 0) {
            throw new InvalidArgumentException('Counted quantity cannot be negative.');
        }

        return DB::transaction(function () use ($batch, $countedQuantity, $reason, $user) {
            $locked = MedicineBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $delta = $countedQuantity - $locked->quantity_on_hand;

            if ($delta === 0) {
                return null;
            }

            $locked->update(['quantity_on_hand' => $countedQuantity]);

            return $this->ledger($locked, StockMovementType::Adjustment, $delta, $user, reason: $reason);
        });
    }

    /**
     * Remove what is left of an expired batch from stock (for disposal).
     */
    public function writeOffExpired(MedicineBatch $batch, User $user, ?string $reason = null, ?CarbonImmutable $today = null): ?StockMovement
    {
        $today ??= CarbonImmutable::today();

        if ($batch->expiry_date->greaterThanOrEqualTo($today)) {
            throw new InvalidArgumentException('Only expired batches can be written off.');
        }

        return DB::transaction(function () use ($batch, $user, $reason) {
            $locked = MedicineBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($locked->quantity_on_hand === 0) {
                return null;
            }

            $quantity = $locked->quantity_on_hand;
            $locked->update(['quantity_on_hand' => 0]);

            return $this->ledger($locked, StockMovementType::Expired, -$quantity, $user, reason: $reason ?? 'Expired stock written off for disposal');
        });
    }

    /**
     * Send stock out of the station (e.g. returned to the RHU).
     */
    public function transferOut(MedicineBatch $batch, int $quantity, string $reason, User $user): StockMovement
    {
        return DB::transaction(function () use ($batch, $quantity, $reason, $user) {
            $locked = MedicineBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($quantity < 1 || $quantity > $locked->quantity_on_hand) {
                throw new InsufficientStock($quantity, $locked->quantity_on_hand);
            }

            $locked->update(['quantity_on_hand' => $locked->quantity_on_hand - $quantity]);

            return $this->ledger($locked, StockMovementType::Out, -$quantity, $user, reason: $reason);
        });
    }

    /**
     * Usable (non-expired) stock of one medicine.
     */
    public function usableStock(Medicine $medicine): int
    {
        return (int) $medicine->batches()->usable()->sum('quantity_on_hand');
    }

    private function ledger(
        MedicineBatch $batch,
        StockMovementType $type,
        int $quantity,
        User $user,
        ?Resident $resident = null,
        ?HealthVisit $visit = null,
        ?string $reason = null,
    ): StockMovement {
        return $batch->movements()->create([
            'type' => $type,
            'quantity' => $quantity,
            'resident_id' => $resident?->id,
            'health_visit_id' => $visit?->id,
            'user_id' => $user->id,
            'reason' => $reason,
        ]);
    }
}
