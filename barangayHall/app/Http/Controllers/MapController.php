<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Services\AuditLogger;
use App\Services\HouseholdMapService;
use App\Services\LookupOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Household map. Health staff (map.view_exact) get exact markers;
 * Barangay Officials (map.view_aggregate only) get one circle per purok,
 * drawn at the Admin-set purok centre, with counts — never a house.
 */
class MapController extends Controller
{
    public function index(Request $request, HouseholdMapService $map, LookupOptions $lookups, AuditLogger $audit): Response
    {
        $user = $request->user();
        $exact = $user?->can(PermissionName::MapViewExact->value) ?? false;

        abort_unless($exact || ($user?->can(PermissionName::MapViewAggregate->value) ?? false), 403);

        $filters = $request->validate([
            'purok_id' => ['nullable', 'integer'],
            'flag' => ['nullable', Rule::in(HouseholdMapService::FLAGS)],
            'days' => ['nullable', 'integer', Rule::in([7, 14, 30, 90])],
        ]);

        $purokId = isset($filters['purok_id']) ? (int) $filters['purok_id'] : null;
        $days = (int) ($filters['days'] ?? 30);

        return Inertia::render('map/index', [
            'mode' => $exact ? 'exact' : 'aggregate',
            'map' => [
                'center' => config('barangay.map.center'),
                'zoom' => config('barangay.map.zoom'),
                'max_accuracy_m' => config('barangay.map.max_accuracy_m'),
            ],
            'filters' => ['purok_id' => $purokId, 'flag' => $filters['flag'] ?? null, 'days' => $days],
            'options' => ['puroks' => $lookups->puroks(), 'flags' => HouseholdMapService::FLAGS],
            // Exact points: deferred, and every load is a logged view of personal data.
            'markers' => $exact ? Inertia::defer(function () use ($map, $purokId, $filters, $audit) {
                $audit->log(AuditAction::ViewedSensitive, null, 'Viewed the household map'.($purokId ? " (purok #{$purokId})" : ''));

                return $map->markers($purokId, $filters['flag'] ?? null);
            }, 'markers') : null,
            'notGeotagged' => $exact ? Inertia::defer(fn () => $map->notGeotagged($purokId), 'markers') : null,
            'purokSummary' => Inertia::defer(fn () => $map->purokSummary($days), 'summary'),
            'canGeotag' => Gate::allows(PermissionName::HouseholdsGeotag->value),
        ]);
    }
}
