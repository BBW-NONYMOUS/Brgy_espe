<?php

namespace App\Http\Requests\Residents;

use App\Enums\ConsentType;
use App\Models\Consent;
use App\Models\Household;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Save a household's location from "Use my location" (GPS) or a map click.
 * Household coordinates are personal data (RA 10173): a current member must
 * have given a geo-tagging consent, and points outside Isulan are refused
 * as mis-clicks or bad GPS fixes.
 */
class GeotagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $household = $this->route('household');

        return $household instanceof Household && ($this->user()?->can('geotag', $household) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        [[$minLat, $minLng], [$maxLat, $maxLng]] = config('barangay.map.bounds');

        return [
            'latitude' => ['required', 'numeric', "between:{$minLat},{$maxLat}"],
            'longitude' => ['required', 'numeric', "between:{$minLng},{$maxLng}"],
            // Reported by the Geolocation API; empty when placed by clicking the map.
            'accuracy_m' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.between' => 'This point is outside Isulan. Check the GPS signal or place the marker on the map.',
            'longitude.between' => 'This point is outside Isulan. Check the GPS signal or place the marker on the map.',
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Household $household */
                $household = $this->route('household');

                $consented = Consent::query()
                    ->activeOfType(ConsentType::Geotagging)
                    ->whereIn('resident_id', $household->members()->select('id'))
                    ->exists();

                if (! $consented) {
                    $validator->errors()->add('consent', 'Record a geo-tagging consent from a household member before saving the location.');
                }
            },
        ];
    }
}
