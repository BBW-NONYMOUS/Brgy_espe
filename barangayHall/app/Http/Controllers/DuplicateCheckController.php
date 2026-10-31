<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\Resident;
use App\Services\DuplicateDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The one JSON endpoint: live duplicate warning while a BHW types a
 * new resident's name and birthdate. Returns only identifying fields.
 */
class DuplicateCheckController extends Controller
{
    public function __invoke(Request $request, DuplicateDetector $duplicates): JsonResponse
    {
        abort_unless(
            $request->user()?->can(PermissionName::ResidentsCreate->value) || $request->user()?->can(PermissionName::ResidentsUpdate->value),
            403,
        );

        $data = $request->validate([
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'birthdate' => ['required', 'date'],
            'ignore_id' => ['nullable', 'integer'],
        ]);

        $matches = $duplicates->find($data['last_name'], $data['first_name'], $data['birthdate'], $data['ignore_id'] ?? null);

        return response()->json([
            'matches' => $matches->map(fn (Resident $r) => [
                'id' => $r->id,
                'resident_code' => $r->resident_code,
                'full_name' => $r->full_name,
                'birthdate' => $r->birthdate->toDateString(),
                'household_no' => $r->household->household_no,
                'purok' => $r->household->purok->name,
                'archived' => $r->trashed(),
            ])->values(),
        ]);
    }
}
