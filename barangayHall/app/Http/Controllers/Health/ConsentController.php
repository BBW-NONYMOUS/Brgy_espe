<?php

namespace App\Http\Controllers\Health;

use App\Enums\ConsentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Health\ConsentRequest;
use App\Models\Consent;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ConsentController extends Controller
{
    public function store(ConsentRequest $request, Resident $resident): RedirectResponse
    {
        $resident->consents()->create([
            ...$request->validated(),
            'obtained_by' => $request->user()?->id,
        ]);

        $this->toast('Consent recorded.');

        return to_route('residents.show', $resident);
    }

    /**
     * Withdrawal of consent (RA 10173 §16). The record stays, dated.
     */
    public function revoke(Consent $consent): RedirectResponse
    {
        Gate::authorize('recordConsent', $consent->resident);

        $consent->update(['revoked_at' => today()]);

        // Without any geo-tagging consent left in the household, its location must go.
        if ($consent->consent_type === ConsentType::Geotagging) {
            $household = $consent->resident->household;
            $stillConsented = Consent::query()
                ->activeOfType(ConsentType::Geotagging)
                ->whereIn('resident_id', $household->members()->select('id'))
                ->exists();

            if (! $stillConsented && $household->isGeotagged()) {
                $household->update(['latitude' => null, 'longitude' => null, 'geotag_accuracy_m' => null, 'geotagged_at' => null, 'geotagged_by' => null]);
                $this->toast('Consent withdrawn. The household location was removed from the map.', 'warning');

                return to_route('residents.show', $consent->resident_id);
            }
        }

        $this->toast('Consent marked as withdrawn.', 'warning');

        return to_route('residents.show', $consent->resident_id);
    }
}
