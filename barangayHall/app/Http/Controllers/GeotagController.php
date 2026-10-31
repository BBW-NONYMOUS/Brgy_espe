<?php

namespace App\Http\Controllers;

use App\Http\Requests\Residents\GeotagRequest;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Save or clear a household's GPS location (personal data under RA 10173).
 */
class GeotagController extends Controller
{
    public function update(GeotagRequest $request, Household $household): RedirectResponse
    {
        $household->update([
            'latitude' => round((float) $request->validated('latitude'), 7),
            'longitude' => round((float) $request->validated('longitude'), 7),
            'geotag_accuracy_m' => $request->validated('accuracy_m'),
            'geotagged_at' => now(),
            'geotagged_by' => $request->user()?->id,
        ]);

        $accuracy = $request->validated('accuracy_m');
        $maxAccuracy = (int) config('barangay.map.max_accuracy_m');

        $accuracy !== null && $accuracy > $maxAccuracy
            ? $this->toast("Location saved, but the GPS was only accurate to about {$accuracy} m. Re-tag outdoors if you can.", 'warning')
            : $this->toast("Location of {$household->household_no} saved.");

        return back();
    }

    public function destroy(Household $household): RedirectResponse
    {
        Gate::authorize('geotag', $household);

        $household->update([
            'latitude' => null,
            'longitude' => null,
            'geotag_accuracy_m' => null,
            'geotagged_at' => null,
            'geotagged_by' => null,
        ]);

        $this->toast("Location of {$household->household_no} removed.", 'warning');

        return back();
    }
}
