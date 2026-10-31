<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\InsufficientStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\DispenseRequest;
use App\Http\Requests\Inventory\ReceiveStockRequest;
use App\Http\Requests\Inventory\StockChangeRequest;
use App\Models\HealthVisit;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Every stock change goes through InventoryService (transaction + row lock
 * + ledger row).
 */
class StockController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function receive(ReceiveStockRequest $request, Medicine $medicine): RedirectResponse
    {
        $batch = $this->inventory->receive($medicine, $request->validated(), $request->user());

        $this->toast("Received {$batch->quantity_received} {$medicine->unit}(s), batch {$batch->batch_no}.");

        return back();
    }

    public function dispense(DispenseRequest $request, Medicine $medicine): RedirectResponse
    {
        $visitId = $request->validated('health_visit_id');

        try {
            $movements = $this->inventory->dispense(
                $medicine,
                (int) $request->validated('quantity'),
                $request->resident(),
                $request->user(),
                $visitId ? HealthVisit::query()->whereKey($visitId)->first() : null,
                $request->validated('reason'),
            );
        } catch (InsufficientStock $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }

        $from = $movements->map(fn ($m) => "{$m->batch->batch_no} (".abs($m->quantity).')')->implode(', ');
        $this->toast("Dispensed {$request->validated('quantity')} {$medicine->unit}(s) from batch {$from}.");

        return back();
    }

    /**
     * Physical count correction.
     */
    public function adjust(StockChangeRequest $request, MedicineBatch $batch): RedirectResponse
    {
        $movement = $this->inventory->adjust($batch, (int) $request->validated('quantity'), (string) $request->validated('reason'), $request->user());

        $this->toast($movement ? "Batch {$batch->batch_no} adjusted to {$request->validated('quantity')}." : 'No change: the count matches the stock on hand.', $movement ? 'success' : 'info');

        return back();
    }

    public function transferOut(StockChangeRequest $request, MedicineBatch $batch): RedirectResponse
    {
        try {
            $this->inventory->transferOut($batch, (int) $request->validated('quantity'), (string) $request->validated('reason'), $request->user());
        } catch (InsufficientStock $e) {
            throw ValidationException::withMessages(['quantity' => "Batch {$batch->batch_no} has only {$e->available} left."]);
        }

        $this->toast("Transferred {$request->validated('quantity')} out of batch {$batch->batch_no}.");

        return back();
    }

    public function writeOff(StockChangeRequest $request, MedicineBatch $batch): RedirectResponse
    {
        try {
            $movement = $this->inventory->writeOffExpired($batch, $request->user(), $request->validated('reason'));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['batch' => $e->getMessage()]);
        }

        $this->toast($movement ? "Batch {$batch->batch_no} written off ({$movement->quantity})." : 'Nothing left to write off.', $movement ? 'success' : 'info');

        return back();
    }
}
