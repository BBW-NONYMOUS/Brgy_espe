<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\DeliveryRequest;
use App\Models\Pregnancy;
use App\Services\PregnancyService;
use Illuminate\Http\RedirectResponse;

class DeliveryController extends Controller
{
    public function store(DeliveryRequest $request, Pregnancy $pregnancy, PregnancyService $pregnancies): RedirectResponse
    {
        $newborns = $request->validated('newborns', []);

        $pregnancies->recordDelivery(
            $pregnancy,
            $request->safe()->except('newborns'),
            $newborns,
            $request->user(),
        );

        $this->toast(count($newborns) > 0
            ? 'Delivery recorded and '.count($newborns).' newborn(s) registered. Postnatal and newborn immunization appointments were scheduled.'
            : 'Delivery recorded. Postnatal appointments were scheduled.');

        return to_route('pregnancies.show', $pregnancy);
    }
}
