<?php

namespace Database\Seeders;

use App\Enums\MedicineForm;
use App\Enums\StockMovementType;
use App\Models\Medicine;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\FindsSeededResidents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Health-station medicines with batches in every expiry band:
 * expired (Amoxicillin suspension), ≤ 30 days (ORS), ≤ 60 days (Paracetamol
 * syrup), ≤ 90 days (Paracetamol tablets batch A, Albendazole), plus low stock
 * (Losartan) and out of stock (Cotrimoxazole). Every batch's quantity_on_hand
 * equals the sum of its stock_movements.
 */
class InventorySeeder extends Seeder
{
    use FindsSeededResidents;

    /**
     * generic, brand|null, form, strength, unit, reorder level, batches:
     * list of [batch no, expiry days from today, received, dispensed, received days ago, source]
     *
     * @var list<array{0: string, 1: string|null, 2: MedicineForm, 3: string, 4: string, 5: int, 6: list<array{0: string, 1: int, 2: int, 3: int, 4: int, 5: string}>}>
     */
    private const MEDICINES = [
        ['Paracetamol', null, MedicineForm::Tablet, '500 mg', 'tablet', 200, [['PCM-2405A', 75, 1000, 920, 300, 'DOH'], ['PCM-2602B', 730, 500, 0, 20, 'DOH']]],
        ['Paracetamol', null, MedicineForm::Syrup, '250 mg/5 mL', 'bottle', 20, [['PCS-2511', 50, 40, 14, 120, 'LGU Isulan']]],
        ['Amoxicillin', null, MedicineForm::Capsule, '500 mg', 'capsule', 150, [['AMX-2603', 400, 500, 120, 60, 'DOH']]],
        ['Amoxicillin', null, MedicineForm::Suspension, '250 mg/5 mL', 'bottle', 15, [['AMS-2410', -10, 30, 18, 400, 'DOH']]],
        ['Oral rehydration salts', 'ORS 75', MedicineForm::Sachet, '20.5 g', 'sachet', 50, [['ORS-2412', 20, 100, 36, 330, 'DOH']]],
        ['Zinc sulfate', null, MedicineForm::Tablet, '20 mg', 'tablet', 60, [['ZNC-2601', 300, 200, 50, 90, 'DOH']]],
        ['Ferrous sulfate + folic acid', null, MedicineForm::Tablet, '60 mg + 400 mcg', 'tablet', 300, [['FFA-2602', 500, 1000, 300, 75, 'DOH']]],
        ['Retinol (vitamin A)', null, MedicineForm::Capsule, '200,000 IU', 'capsule', 100, [['VTA-2604', 200, 200, 40, 150, 'DOH']]],
        ['Retinol (vitamin A)', null, MedicineForm::Capsule, '100,000 IU', 'capsule', 50, [['VTB-2604', 200, 100, 10, 150, 'DOH']]],
        ['Albendazole', null, MedicineForm::Tablet, '400 mg', 'tablet', 100, [['ALB-2501', 85, 200, 60, 280, 'DOH']]],
        ['Losartan', null, MedicineForm::Tablet, '50 mg', 'tablet', 200, [['LOS-2509', 365, 300, 180, 100, 'LGU Isulan']]],
        ['Amlodipine', null, MedicineForm::Tablet, '5 mg', 'tablet', 200, [['AML-2604', 540, 300, 60, 45, 'LGU Isulan']]],
        ['Metformin', null, MedicineForm::Tablet, '500 mg', 'tablet', 200, [['MET-2603', 450, 500, 120, 70, 'LGU Isulan']]],
        ['Salbutamol', null, MedicineForm::Tablet, '2 mg', 'tablet', 100, [['SAL-2605', 600, 200, 20, 40, 'DOH']]],
        ['Cotrimoxazole', null, MedicineForm::Tablet, '400 mg/80 mg', 'tablet', 100, [['CTX-2506', 250, 200, 200, 200, 'DOH']]],
        ['Mefenamic acid', null, MedicineForm::Capsule, '500 mg', 'capsule', 100, [['MEF-2603', 380, 200, 30, 65, 'Donation']]],
    ];

    /** Maintenance medicines go to residents with the matching condition. */
    private const MAINTENANCE = [
        'Losartan' => 'Essential hypertension',
        'Amlodipine' => 'Essential hypertension',
        'Metformin' => 'Type 2 diabetes mellitus',
    ];

    public function run(): void
    {
        $midwife = $this->user('mw.cristina');
        $bhw = $this->user('bhw.analiza');
        $today = CarbonImmutable::today();
        $everyone = Resident::query()->orderBy('id')->pluck('id');

        foreach (self::MEDICINES as [$generic, $brand, $form, $strength, $unit, $reorder, $batches]) {
            $medicine = Medicine::query()->create([
                'generic_name' => $generic,
                'brand_name' => $brand,
                'form' => $form,
                'strength' => $strength,
                'unit' => $unit,
                'reorder_level' => $reorder,
            ]);

            $patients = isset(self::MAINTENANCE[$generic]) ? $this->residentsWith(self::MAINTENANCE[$generic]) : $everyone;

            foreach ($batches as [$batchNo, $expiresIn, $received, $dispensed, $receivedDaysAgo, $source]) {
                $receivedAt = $today->subDays($receivedDaysAgo);

                $batch = $medicine->batches()->create([
                    'batch_no' => $batchNo,
                    'expiry_date' => $today->addDays($expiresIn),
                    'quantity_received' => $received,
                    'quantity_on_hand' => $received - $dispensed,
                    'received_at' => $receivedAt,
                    'source' => $source,
                    'received_by' => $midwife->id,
                ]);

                $batch->movements()->create([
                    'type' => StockMovementType::In,
                    'quantity' => $received,
                    'user_id' => $midwife->id,
                    'reason' => "Delivery from {$source}",
                    'created_at' => $receivedAt->setTime(9, 0),
                ]);

                // Spread what was dispensed over a few residents and dates.
                foreach ($this->split($dispensed) as $i => $quantity) {
                    $batch->movements()->create([
                        'type' => StockMovementType::Dispensed,
                        'quantity' => -$quantity,
                        'resident_id' => $patients[($i * 7 + strlen($batchNo)) % $patients->count()],
                        'user_id' => $i % 2 === 0 ? $bhw->id : $midwife->id,
                        'created_at' => $receivedAt->addDays(intdiv(($i + 1) * $receivedDaysAgo, 8))->setTime(10 + $i % 6, 15),
                    ]);
                }
            }
        }
    }

    /**
     * @return Collection<int, int>
     */
    private function residentsWith(string $condition): Collection
    {
        return Resident::query()
            ->whereHas('conditions', fn ($q) => $q->whereHas('condition', fn ($c) => $c->where('name', $condition)))
            ->orderBy('id')
            ->pluck('id');
    }

    /**
     * Break a total into 1–6 dispensing events (sum preserved).
     *
     * @return list<int>
     */
    private function split(int $total): array
    {
        if ($total === 0) {
            return [];
        }

        $parts = min(6, max(1, intdiv($total, 10)));
        $base = intdiv($total, $parts);
        $chunks = array_fill(0, $parts, $base);
        $chunks[0] += $total - $base * $parts;

        return $chunks;
    }
}
