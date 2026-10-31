<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\MedicalAlertRequest;
use App\Models\MedicalAlert;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MedicalAlertController extends Controller
{
    public function store(MedicalAlertRequest $request, Resident $resident): RedirectResponse
    {
        $resident->medicalAlerts()->create([
            ...$request->validated(),
            'recorded_by' => $request->user()?->id,
        ]);

        $this->toast('Medical alert added.');

        return back();
    }

    public function update(MedicalAlertRequest $request, MedicalAlert $alert): RedirectResponse
    {
        $alert->update($request->validated());

        $this->toast('Medical alert updated.');

        return back();
    }

    /**
     * Soft delete — an alert entered by mistake. Resolved alerts should be
     * set inactive instead so the history stays visible.
     */
    public function destroy(MedicalAlert $alert): RedirectResponse
    {
        Gate::authorize('delete', $alert);

        $alert->delete();

        $this->toast('Medical alert removed.');

        return back();
    }
}
