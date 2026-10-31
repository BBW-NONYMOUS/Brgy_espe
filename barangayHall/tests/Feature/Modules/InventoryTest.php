<?php

use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStock;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\User;
use App\Notifications\LowStockAlert;
use App\Notifications\MedicineExpiryDigest;
use App\Services\InventoryAlertService;
use App\Services\InventoryService;

beforeEach(function () {
    seedReferenceData();
    $this->midwife = User::factory()->midwife()->create();
    $this->medicine = Medicine::query()->create([
        'generic_name' => 'Paracetamol', 'form' => 'tablet', 'strength' => '500 mg', 'unit' => 'tablet', 'reorder_level' => 50,
    ]);
    $this->inventory = app(InventoryService::class);
});

function receiveBatch(Medicine $medicine, User $user, string $batchNo, int $expiresInDays, int $quantity): MedicineBatch
{
    return app(InventoryService::class)->receive($medicine, [
        'batch_no' => $batchNo,
        'expiry_date' => today()->addDays($expiresInDays)->toDateString(),
        'quantity' => $quantity,
        'received_at' => today()->subDays(400)->toDateString(),
    ], $user);
}

test('dispensing takes from the batch that expires first and never from expired stock (FEFO)', function () {
    $expired = receiveBatch($this->medicine, $this->midwife, 'OLD', -5, 100);
    $later = receiveBatch($this->medicine, $this->midwife, 'LATER', 400, 100);
    $sooner = receiveBatch($this->medicine, $this->midwife, 'SOONER', 40, 30);

    $movements = $this->inventory->dispense($this->medicine, 50, residentInPurok(), $this->midwife);

    expect($movements->map(fn ($m) => [$m->batch->batch_no, $m->quantity])->all())->toBe([['SOONER', -30], ['LATER', -20]])
        ->and($sooner->fresh()->quantity_on_hand)->toBe(0)
        ->and($later->fresh()->quantity_on_hand)->toBe(80)
        ->and($expired->fresh()->quantity_on_hand)->toBe(100);
});

test('asking for more than the usable stock changes nothing', function () {
    $batch = receiveBatch($this->medicine, $this->midwife, 'A', 100, 10);
    receiveBatch($this->medicine, $this->midwife, 'EXPIRED', -1, 500);

    actingAsRole(RoleName::Bhw);
    $this->post(route('medicines.dispense', $this->medicine), ['resident_id' => residentInPurok()->id, 'quantity' => 11])
        ->assertSessionHasErrors(['quantity' => 'Only 10 left in usable (non-expired) stock; 11 requested.']);

    expect($batch->fresh()->quantity_on_hand)->toBe(10)
        ->and($batch->movements()->where('type', StockMovementType::Dispensed)->count())->toBe(0);
});

test('the ledger always reconciles with stock on hand', function () {
    $batch = receiveBatch($this->medicine, $this->midwife, 'A', 10, 100);
    $expired = receiveBatch($this->medicine, $this->midwife, 'B', -3, 40);

    $this->inventory->dispense($this->medicine, 25, residentInPurok(), $this->midwife);
    $this->inventory->adjust($batch, 70, 'Physical count', $this->midwife);
    $this->inventory->transferOut($batch, 5, 'Returned to RHU', $this->midwife);
    $this->inventory->writeOffExpired($expired, $this->midwife);

    foreach ([$batch, $expired] as $b) {
        expect((int) $b->movements()->sum('quantity'))->toBe($b->fresh()->quantity_on_hand);
    }
    expect($batch->fresh()->quantity_on_hand)->toBe(65)
        ->and($expired->fresh()->quantity_on_hand)->toBe(0);

    expect(fn () => $this->inventory->writeOffExpired($batch, $this->midwife))->toThrow(InvalidArgumentException::class);
});

test('no stock operation can take a batch below zero', function () {
    $batch = receiveBatch($this->medicine, $this->midwife, 'A', 100, 10);
    $resident = residentInPurok();

    $this->inventory->dispense($this->medicine, 10, $resident, $this->midwife);

    expect(fn () => $this->inventory->dispense($this->medicine, 1, $resident, $this->midwife))->toThrow(InsufficientStock::class)
        ->and(fn () => $this->inventory->transferOut($batch->fresh(), 1, 'Returned to RHU', $this->midwife))->toThrow(InsufficientStock::class)
        ->and(fn () => $this->inventory->adjust($batch->fresh(), -1, 'Count', $this->midwife))->toThrow(InvalidArgumentException::class)
        ->and($batch->fresh()->quantity_on_hand)->toBe(0)
        ->and((int) $batch->movements()->sum('quantity'))->toBe(0);
});

test('BHWs may dispense but only midwives and admins receive or adjust stock', function () {
    $batch = receiveBatch($this->medicine, $this->midwife, 'A', 100, 100);
    $resident = residentInPurok();

    actingAsRole(RoleName::Bhw);
    $this->post(route('medicines.dispense', $this->medicine), ['resident_id' => $resident->id, 'quantity' => 5])->assertSessionHasNoErrors();
    $this->post(route('medicines.receive', $this->medicine), ['batch_no' => 'X', 'expiry_date' => today()->addYear()->toDateString(), 'quantity' => 10])->assertForbidden();
    $this->post(route('batches.adjust', $batch), ['quantity' => 0, 'reason' => 'x'])->assertForbidden();

    actingAsRole(RoleName::Official);
    $this->get(route('medicines.index'))->assertForbidden();

    actingAsRole(RoleName::Midwife);
    $this->post(route('medicines.receive', $this->medicine), ['batch_no' => 'a', 'expiry_date' => today()->addYear()->toDateString(), 'quantity' => 10])
        ->assertSessionHasErrors('batch_no'); // "A" already received (batch numbers are upper-cased)
});

test('expiry alerts fire once per band per batch', function () {
    $ninety = receiveBatch($this->medicine, $this->midwife, 'N', 80, 10);
    receiveBatch($this->medicine, $this->midwife, 'T', 20, 10);
    receiveBatch($this->medicine, $this->midwife, 'X', -2, 10);
    receiveBatch($this->medicine, $this->midwife, 'FAR', 300, 10);
    $alerts = app(InventoryAlertService::class);

    expect($alerts->expiryDigest())->toBe([0 => 1, 30 => 1, 90 => 1])
        ->and($this->midwife->notifications()->where('type', MedicineExpiryDigest::class)->count())->toBe(1);

    // Same day again: nothing new.
    expect($alerts->expiryDigest())->toBe([]);

    // 25 days later batch N has moved from the 90-day band to the 60-day band.
    expect($alerts->expiryDigest(today()->addDays(25)))->toBe([0 => 1, 60 => 1])
        ->and($ninety->fresh()->expiry_alert_band)->toBe(60);
});

test('low stock is notified once a day to inventory managers only', function () {
    receiveBatch($this->medicine, $this->midwife, 'A', 100, 60);
    $bhw = User::factory()->bhw()->create();

    $this->inventory->dispense($this->medicine, 15, residentInPurok(), $this->midwife);
    $this->inventory->dispense($this->medicine, 1, residentInPurok(), $this->midwife);

    expect($this->midwife->notifications()->where('type', LowStockAlert::class)->count())->toBe(1)
        ->and($bhw->notifications()->count())->toBe(0)
        ->and($this->midwife->notifications()->first()->data['title'])->toBe('Low stock: Paracetamol 500 mg tablet');
});
