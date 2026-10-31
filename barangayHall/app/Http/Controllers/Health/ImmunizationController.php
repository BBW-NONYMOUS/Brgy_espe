<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\ImmunizationRequest;
use App\Models\Immunization;
use App\Models\Resident;
use App\Services\AppointmentGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ImmunizationController extends Controller
{
    public function store(ImmunizationRequest $request, Resident $resident, AppointmentGenerator $appointments): RedirectResponse
    {
        $resident->immunizations()->create([
            ...$request->validated(),
            'given_by' => $request->user()?->id,
        ]);

        // Completes this dose's appointment and books the next dose.
        $appointments->syncChild($resident->unsetRelation('immunizations'));

        $this->toast('Immunization recorded.');

        return to_route('residents.show', $resident);
    }

    public function destroy(Immunization $immunization): RedirectResponse
    {
        Gate::authorize('delete', $immunization);

        $immunization->delete();

        $this->toast('Immunization entry removed.');

        return to_route('residents.show', $immunization->resident_id);
    }
}
