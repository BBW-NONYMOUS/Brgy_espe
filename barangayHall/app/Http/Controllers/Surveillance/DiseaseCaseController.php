<?php

namespace App\Http\Controllers\Surveillance;

use App\Enums\CaseClassification;
use App\Enums\CaseOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Health\ClassifyDiseaseCaseRequest;
use App\Http\Requests\Health\DiseaseCaseRequest;
use App\Http\Resources\DiseaseCaseResource;
use App\Http\Resources\OutbreakAlertResource;
use App\Models\DiseaseCase;
use App\Models\OutbreakAlert;
use App\Services\DiseaseCaseService;
use App\Services\HouseholdMapService;
use App\Services\LookupOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DiseaseCaseController extends Controller
{
    public function __construct(
        private readonly LookupOptions $lookups,
        private readonly DiseaseCaseService $cases,
    ) {}

    public function index(Request $request, HouseholdMapService $map): Response
    {
        Gate::authorize('viewAny', DiseaseCase::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'disease_id' => ['nullable', 'integer'],
            'purok_id' => ['nullable', 'integer'],
            'classification' => ['nullable', Rule::enum(CaseClassification::class)],
            'outcome' => ['nullable', Rule::enum(CaseOutcome::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'resident' => ['nullable', 'string', 'max:100'],
        ]);

        $cases = DiseaseCase::query()
            ->with(['resident', 'disease', 'purok', 'reportedBy:id,name'])
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->whereHas('resident', fn (Builder $r) => $r
                ->where('last_name', 'like', "{$s}%")->orWhere('first_name', 'like', "{$s}%")->orWhere('resident_code', 'like', "{$s}%")))
            ->when($filters['disease_id'] ?? null, fn (Builder $q, $id) => $q->where('disease_id', $id))
            ->when($filters['purok_id'] ?? null, fn (Builder $q, $id) => $q->where('purok_id', $id))
            ->when($filters['classification'] ?? null, fn (Builder $q, $c) => $q->where('classification', $c))
            ->when($filters['outcome'] ?? null, fn (Builder $q, $o) => $q->where('outcome', $o))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $d) => $q->where('onset_date', '>=', $d))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $d) => $q->whereDate('onset_date', '<=', $d))
            ->latest('onset_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('diseases/index', [
            'cases' => DiseaseCaseResource::collection($cases),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'disease_id' => isset($filters['disease_id']) ? (int) $filters['disease_id'] : null,
                'purok_id' => isset($filters['purok_id']) ? (int) $filters['purok_id'] : null,
                'classification' => $filters['classification'] ?? null,
                'outcome' => $filters['outcome'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
            'map' => ['center' => config('barangay.map.center'), 'zoom' => config('barangay.map.zoom')],
            'openAlerts' => OutbreakAlertResource::collection(OutbreakAlert::query()->open()->with(['disease', 'purok'])->latest('id')->get()),
            'trend' => Inertia::defer(fn () => $this->weeklyTrend(), 'charts'),
            'perPurok' => Inertia::defer(fn () => $map->purokSummary(30), 'charts'),
            'options' => [
                'diseases' => $this->lookups->diseases(false),
                'puroks' => $this->lookups->puroks(),
                'classifications' => CaseClassification::options(),
                'outcomes' => CaseOutcome::options(),
            ],
            // Resident Combobox for the case form (partial reload).
            'residentOptions' => Inertia::optional(fn () => $this->lookups->residents($filters['resident'] ?? null)),
        ]);
    }

    public function store(DiseaseCaseRequest $request): RedirectResponse
    {
        $case = $this->cases->record($request->resident(), $request->safe()->except('resident_id'), $request->user());

        $this->toast("{$case->disease->name} case recorded. Outbreak thresholds are being checked.");

        return back();
    }

    public function update(ClassifyDiseaseCaseRequest $request, DiseaseCase $case): RedirectResponse
    {
        $this->cases->classify($case, $request->validated(), $request->user());

        $this->toast('Case updated.');

        return back();
    }

    /**
     * Soft delete (entered in error). Only those who may classify cases.
     */
    public function destroy(DiseaseCase $case): RedirectResponse
    {
        Gate::authorize('delete', $case);

        $case->delete();

        $this->toast('Case removed.');

        return back();
    }

    /**
     * Countable cases per ISO week for the 5 most frequent diseases of the
     * last 26 weeks — aggregate, for the trend chart.
     *
     * @return array{weeks: array<int, string>, series: array<int, array{disease: string, counts: array<int, int>}>}
     */
    private function weeklyTrend(): array
    {
        $start = CarbonImmutable::today()->startOfWeek()->subWeeks(25);
        $weeks = collect(range(0, 25))->map(fn (int $i) => $start->addWeeks($i)->toDateString());

        $rows = DiseaseCase::query()
            ->countable()
            ->with('disease:id,name')
            ->where('onset_date', '>=', $start->toDateString())
            ->get(['id', 'disease_id', 'onset_date']);

        $top = $rows->countBy('disease_id')->sortDesc()->take(5)->keys();

        return [
            'weeks' => $weeks->all(),
            'series' => $top->map(fn ($diseaseId) => [
                'disease' => $rows->firstWhere('disease_id', $diseaseId)?->disease->name ?? '',
                'counts' => $weeks->map(fn (string $week) => $rows
                    ->where('disease_id', $diseaseId)
                    ->filter(fn (DiseaseCase $c) => $c->onset_date->startOfWeek()->toDateString() === $week)
                    ->count())->all(),
            ])->values()->all(),
        ];
    }
}
