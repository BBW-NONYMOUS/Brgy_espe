<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Notifications\LowStockAlert;
use App\Notifications\MedicineExpiryDigest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Expiry bands (90 / 60 / 30 days, expired) and low-stock alerts for the
 * people who manage inventory. Each batch is reported once per band
 * (medicine_batches.expiry_alert_band remembers the last one sent).
 */
class InventoryAlertService
{
    public function __construct(private readonly NotificationRouter $notifications) {}

    /**
     * Band a batch is in today: 0 = expired, else the smallest configured
     * band (30/60/90) it falls within; null = more than 90 days left.
     */
    public static function bandFor(CarbonImmutable $expiry, ?CarbonImmutable $today = null): ?int
    {
        $today ??= CarbonImmutable::today();
        $days = (int) $today->diffInDays($expiry, false);

        if ($days < 0) {
            return 0;
        }

        $bands = collect((array) config('barangay.alerts.expiry_bands'))->map(fn ($b) => (int) $b)->sort()->values();

        return $bands->first(fn (int $band) => $days <= $band);
    }

    /**
     * Batches with stock that entered a new band since the last run → one
     * digest notification.
     *
     * @return array<int, int> band => number of batches newly in it
     */
    public function expiryDigest(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $widest = (int) collect((array) config('barangay.alerts.expiry_bands'))->max();

        $entered = MedicineBatch::query()
            ->with('medicine')
            ->where('quantity_on_hand', '>', 0)
            ->where('expiry_date', '<', $today->addDays($widest + 1)->toDateString())
            ->get()
            ->filter(function (MedicineBatch $batch) use ($today) {
                $band = self::bandFor($batch->expiry_date, $today);

                return $band !== null && ($batch->expiry_alert_band === null || $band < $batch->expiry_alert_band);
            });

        if ($entered->isEmpty()) {
            return [];
        }

        // Every batch in $entered has a band (filtered above); -1 never occurs.
        $counts = $entered->map(fn (MedicineBatch $b) => self::bandFor($b->expiry_date, $today) ?? -1)->countBy()->sortKeys()->all();

        $this->notifications->toPermission(
            PermissionName::InventoryManage,
            new MedicineExpiryDigest(
                title: 'Medicines expiring',
                message: $this->describe($counts, $entered),
                url: route('medicines.index', ['expiry' => 'soon'], absolute: false),
                level: isset($counts[0]) || isset($counts[30]) ? 'critical' : 'warning',
            ),
            'expiry:'.$today->toDateString(),
        );

        $entered->each(fn (MedicineBatch $b) => $b->update(['expiry_alert_band' => self::bandFor($b->expiry_date, $today)]));

        return $counts;
    }

    /**
     * Medicines whose usable stock is at or below the reorder level.
     * Notified at most once a day per medicine.
     *
     * @return Collection<int, array{medicine: Medicine, on_hand: int}>
     */
    public function checkLowStock(?Medicine $only = null, ?CarbonImmutable $today = null): Collection
    {
        $today ??= CarbonImmutable::today();
        $low = $this->lowStock($only, $today);

        foreach ($low as ['medicine' => $medicine, 'on_hand' => $onHand]) {
            $this->notifications->toPermission(
                PermissionName::InventoryManage,
                new LowStockAlert(
                    title: $onHand === 0 ? "Out of stock: {$medicine->display_name}" : "Low stock: {$medicine->display_name}",
                    message: "{$onHand} {$medicine->unit}(s) left; reorder level is {$medicine->reorder_level}.",
                    url: route('medicines.show', $medicine, absolute: false),
                    level: $onHand === 0 ? 'critical' : 'warning',
                ),
                "low-stock:{$medicine->id}:{$today->toDateString()}",
            );
        }

        return $low;
    }

    /**
     * Medicines at or below their reorder level (no notifications).
     *
     * @return Collection<int, array{medicine: Medicine, on_hand: int}>
     */
    public function lowStock(?Medicine $only = null, ?CarbonImmutable $today = null): Collection
    {
        $today ??= CarbonImmutable::today();

        return Medicine::query()
            ->where('is_active', true)
            ->where('reorder_level', '>', 0)
            ->when($only, fn ($q, Medicine $m) => $q->whereKey($m->id))
            ->withSum(['batches as usable_on_hand' => fn ($q) => $q->where('expiry_date', '>=', $today->toDateString())], 'quantity_on_hand')
            ->get()
            ->map(fn (Medicine $m) => ['medicine' => $m, 'on_hand' => (int) $m->getAttribute('usable_on_hand')])
            ->filter(fn (array $row) => $row['on_hand'] <= $row['medicine']->reorder_level)
            ->values();
    }

    /**
     * @param  array<int, int>  $counts
     * @param  Collection<int, MedicineBatch>  $batches
     */
    private function describe(array $counts, Collection $batches): string
    {
        $parts = collect($counts)->map(fn (int $n, int $band) => $band === 0
            ? "{$n} batch(es) expired"
            : "{$n} batch(es) expiring within {$band} days");

        $names = $batches->map(fn (MedicineBatch $b) => $b->medicine->display_name)->unique()->take(3)->implode(', ');

        return $parts->implode('; ').". Includes {$names}.";
    }
}
