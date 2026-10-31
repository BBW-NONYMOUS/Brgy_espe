<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\ResidentConditionRequest;
use App\Models\Resident;
use App\Models\ResidentCondition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ResidentConditionController extends Controller
{
    public function store(ResidentConditionRequest $request, Resident $resident): RedirectResponse
    {
        $resident->conditions()->create([
            ...$request->validated(),
            'recorded_by' => $request->user()?->id,
        ]);

        $this->toast('Condition recorded.');

        return to_route('residents.show', $resident);
    }

    public function update(ResidentConditionRequest $request, ResidentCondition $condition): RedirectResponse
    {
        $condition->update($request->validated());

        $this->toast('Condition updated.');

        return to_route('residents.show', $condition->resident_id);
    }

    public function destroy(ResidentCondition $condition): RedirectResponse
    {
        Gate::authorize('delete', $condition);

        $condition->delete();

        $this->toast('Condition removed.');

        return to_route('residents.show', $condition->resident_id);
    }
}
