<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MedicineForm;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\MedicineRequest;
use App\Http\Resources\MedicalAlertResource;
use App\Http\Resources\MedicineBatchResource;
use App\Http\Resources\MedicineResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Medicine;
use App\Models\Resident;
use App\Models\StockMovement;
use App\Services\LookupOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MedicineController extends Controller
{
    public function __construct(private readonly LookupOptions $lookups) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Medicine::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            // soon = a batch with stock expires within 90 days (or already has); low = at/below reorder level
            'expiry' => ['nullable', Rule::in(['soon'])],
            'stock' => ['nullable', Rule::in(['low'])],
            'resident' => ['nullable', 'string', 'max:100'],
            'resident_id' => ['nullable', 'integer'],
        ]);

        $today = CarbonImmutable::today()->toDateString();
        $soon = CarbonImmutable::today()->addDays(91)->toDateString(); // exclusive bound: within 90 days

        $medicines = Medicine::query()
            ->withSum(['batches as usable_on_hand' => fn (Builder $q) => $q->where('expiry_date', '>=', $today)], 'quantity_on_hand')
            ->withSum(['batches as expired_on_hand' => fn (Builder $q) => $q->where('expiry_date', '<', $today)], 'quantity_on_hand')
            ->withMin(['batches as nearest_expiry' => fn (Builder $q) => $q->where('quantity_on_hand', '>', 0)], 'expiry_date')
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w
                ->where('generic_name', 'like', "%{$s}%")->orWhere('brand_name', 'like', "%{$s}%")))
            ->when($filters['expiry'] ?? null, fn (Builder $q) => $q->whereHas('batches', fn (Builder $b) => $b
                ->where('quantity_on_hand', '>', 0)->where('expiry_date', '<', $soon)))
            ->orderBy('generic_name')
            ->orderBy('strength')
            ->get()
            ->when($filters['stock'] ?? null, fn ($c) => $c->filter(fn (Medicine $m) => (int) $m->getAttribute('usable_on_hand') <= $m->reorder_level))
            ->values();

        $residentId = isset($filters['resident_id']) ? (int) $filters['resident_id'] : null;

        return Inertia::render('inventory/index', [
            'medicines' => MedicineResource::collection($medicines),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'expiry' => $filters['expiry'] ?? null,
                'stock' => $filters['stock'] ?? null,
            ],
            'options' => ['forms' => MedicineForm::options()],
            // Dispense dialog: resident search and that resident's critical alerts (partial reloads).
            'residentOptions' => Inertia::optional(fn () => $this->lookups->residents($filters['resident'] ?? null)),
            'residentAlerts' => Inertia::optional(fn () => $residentId && Gate::allows('viewSensitive', $resident = Resident::query()->findOrFail($residentId))
                ? MedicalAlertResource::collection($resident->criticalAlerts)->resolve()
                : []),
        ]);
    }

    public function show(Request $request, Medicine $medicine): Response
    {
        Gate::authorize('view', $medicine);

        $filters = $request->validate(['type' => ['nullable', Rule::enum(StockMovementType::class)]]);

        $medicine->load(['batches' => fn ($q) => $q->orderBy('expiry_date')->orderBy('id')]);

        $movements = StockMovement::query()
            ->with(['batch:id,batch_no', 'resident:id,last_name,first_name,middle_name,suffix', 'user:id,name'])
            ->whereIn('medicine_batch_id', $medicine->batches->pluck('id'))
            ->when($filters['type'] ?? null, fn (Builder $q, $t) => $q->where('type', $t))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('inventory/show', [
            'medicine' => MedicineResource::make($medicine),
            'batches' => MedicineBatchResource::collection($medicine->batches),
            'movements' => StockMovementResource::collection($movements),
            'filters' => ['type' => $filters['type'] ?? null],
            'options' => ['movementTypes' => StockMovementType::options(), 'forms' => MedicineForm::options()],
        ]);
    }

    public function store(MedicineRequest $request): RedirectResponse
    {
        $medicine = Medicine::query()->create($request->validated());

        $this->toast("{$medicine->display_name} added. Receive its first batch to put it in stock.");

        return to_route('medicines.show', $medicine);
    }

    public function update(MedicineRequest $request, Medicine $medicine): RedirectResponse
    {
        $medicine->update($request->validated());

        $this->toast('Medicine updated.');

        return back();
    }
}
