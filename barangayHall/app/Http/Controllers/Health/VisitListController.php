<?php

namespace App\Http\Controllers\Health;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\HealthVisit;
use App\Models\Resident;
use App\Services\LookupOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Health visits across all residents ("Visits" in the sidebar). The list
 * shows who was seen, when and for what type of visit — never the chief
 * complaint, diagnosis or notes; those stay on the resident's timeline.
 */
class VisitListController extends Controller
{
    private const SORTS = ['visit_date', 'resident'];

    public function __invoke(Request $request, LookupOptions $lookups): Response
    {
        Gate::authorize(PermissionName::HealthViewSensitive->value);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'visit_type_id' => ['nullable', 'integer'],
            'purok_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'mine' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $sort = $filters['sort'] ?? 'visit_date';
        $direction = $filters['direction'] ?? 'desc';

        $visits = HealthVisit::query()
            ->with(['resident.household.purok', 'visitType', 'attendedBy:id,name'])
            ->withExists(['vitalSign', 'prenatalVisit', 'postnatalVisit'])
            ->whereHas('resident')
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->whereHas('resident', fn (Builder $r) => $r->where(fn (Builder $w) => $w
                ->where('last_name', 'like', "{$s}%")
                ->orWhere('first_name', 'like', "{$s}%")
                ->orWhere('resident_code', 'like', "{$s}%"))))
            ->when($filters['visit_type_id'] ?? null, fn (Builder $q, $id) => $q->where('visit_type_id', $id))
            ->when($filters['purok_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('resident.household', fn (Builder $h) => $h->where('purok_id', $id)))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $d) => $q->whereDate('visit_date', '>=', $d))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $d) => $q->whereDate('visit_date', '<=', $d))
            ->when($filters['mine'] ?? false, fn (Builder $q) => $q->where('attended_by', $request->user()?->id))
            ->when(
                $sort === 'resident',
                fn (Builder $q) => $q->orderBy(
                    Resident::query()->select('last_name')->whereColumn('residents.id', 'health_visits.resident_id'),
                    $direction,
                ),
                fn (Builder $q) => $q->orderBy('visit_date', $direction)->orderBy('id', $direction),
            )
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString()
            ->through(fn (HealthVisit $v) => [
                'id' => $v->id,
                'visit_date' => $v->visit_date->toDateString(),
                'visit_type' => $v->visitType->name,
                'resident' => [
                    'id' => $v->resident->id,
                    'resident_code' => $v->resident->resident_code,
                    'full_name' => $v->resident->full_name,
                    'age' => $v->resident->age,
                ],
                'purok' => $v->resident->household->purok->name,
                'attended_by' => $v->attendedBy->name,
                'has_vitals' => (bool) $v->getAttribute('vital_sign_exists'),
                'maternal' => match (true) {
                    (bool) $v->getAttribute('prenatal_visit_exists') => ['value' => 'prenatal', 'label' => 'Prenatal'],
                    (bool) $v->getAttribute('postnatal_visit_exists') => ['value' => 'postnatal', 'label' => 'Postnatal'],
                    default => null,
                },
            ]);

        return Inertia::render('health/visits', [
            'visits' => JsonResource::collection($visits),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'visit_type_id' => isset($filters['visit_type_id']) ? (int) $filters['visit_type_id'] : null,
                'purok_id' => isset($filters['purok_id']) ? (int) $filters['purok_id'] : null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'mine' => (bool) ($filters['mine'] ?? false),
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => (int) ($filters['per_page'] ?? 15),
            ],
            'options' => [
                'visitTypes' => $lookups->visitTypes(),
                'puroks' => $lookups->puroks(),
            ],
        ]);
    }
}
