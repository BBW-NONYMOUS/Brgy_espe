<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\EmergencyContactRequest;
use App\Models\EmergencyContact;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EmergencyContactController extends Controller
{
    public function store(EmergencyContactRequest $request, Resident $resident): RedirectResponse
    {
        $this->save($resident, new EmergencyContact, $request->validated());

        $this->toast('Emergency contact added.');

        return back();
    }

    public function update(EmergencyContactRequest $request, EmergencyContact $contact): RedirectResponse
    {
        $this->save($contact->resident, $contact, $request->validated());

        $this->toast('Emergency contact updated.');

        return back();
    }

    public function destroy(EmergencyContact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        $contact->delete();

        $this->toast('Emergency contact removed.');

        return back();
    }

    /**
     * At most one primary contact per resident: making one primary demotes
     * the others in the same transaction. The first contact is always primary.
     *
     * @param  array<string, mixed>  $data
     */
    private function save(Resident $resident, EmergencyContact $contact, array $data): void
    {
        DB::transaction(function () use ($resident, $contact, $data) {
            $others = $resident->emergencyContacts()->whereKeyNot($contact->id ?? 0);
            $data['is_primary'] = $data['is_primary'] || ! $others->clone()->where('is_primary', true)->exists();

            if ($data['is_primary']) {
                $others->clone()->where('is_primary', true)->get()->each->update(['is_primary' => false]);
            }

            $contact->fill([...$data, 'resident_id' => $resident->id])->save();
        });
    }
}
