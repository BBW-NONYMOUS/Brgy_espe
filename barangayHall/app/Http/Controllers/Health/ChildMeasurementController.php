<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\GrowthMeasurementRequest;
use App\Http\Requests\Health\MicronutrientRequest;
use App\Models\GrowthMeasurement;
use App\Models\MicronutrientRecord;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Weighing (growth monitoring) and micronutrient supplementation entries.
 */
class ChildMeasurementController extends Controller
{
    public function storeGrowth(GrowthMeasurementRequest $request, Resident $resident): RedirectResponse
    {
        $resident->growthMeasurements()->create([
            ...$request->validated(),
            'measured_by' => $request->user()?->id,
        ]);

        $this->toast('Measurement recorded.');

        return back();
    }

    public function destroyGrowth(GrowthMeasurement $measurement): RedirectResponse
    {
        Gate::authorize('delete', $measurement);

        $measurement->delete();

        $this->toast('Measurement removed.');

        return back();
    }

    public function storeMicronutrient(MicronutrientRequest $request, Resident $resident): RedirectResponse
    {
        $record = $resident->micronutrientRecords()->create([
            ...$request->validated(),
            'given_by' => $request->user()?->id,
        ]);

        $this->toast("{$record->type->label()} recorded.");

        return back();
    }

    public function destroyMicronutrient(MicronutrientRecord $record): RedirectResponse
    {
        Gate::authorize('delete', $record);

        $record->delete();

        $this->toast('Entry removed.');

        return back();
    }
}
