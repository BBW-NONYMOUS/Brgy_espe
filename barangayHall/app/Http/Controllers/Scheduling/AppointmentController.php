<?php

namespace App\Http\Controllers\Scheduling;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\AppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\LookupOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * List view and booking. The calendar view loads its events from
 * CalendarEventsController (only the visible date range).
 */
class AppointmentController extends Controller
{
    public function __construct(private readonly LookupOptions $lookups) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Appointment::class);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            'appointment_type_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', Rule::in(['me', 'unassigned'])],
            'resident' => ['nullable', 'string', 'max:100'],
            'appointment' => ['nullable', 'integer'],
            'view' => ['nullable', Rule::in(['calendar', 'list'])],
        ]);

        $today = CarbonImmutable::today();
        $missedView = ($filters['status'] ?? null) === AppointmentStatus::Missed->value;
        $from = $filters['date_from'] ?? ($missedView ? $today->subDays(30)->toDateString() : $today->toDateString());
        $to = $filters['date_to'] ?? ($missedView ? $today->toDateString() : $today->addDays(7)->toDateString());

        $appointments = Appointment::query()
            ->with(['resident', 'type', 'assignedTo:id,name'])
            ->whereBetween('scheduled_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->when($filters['appointment_type_id'] ?? null, fn (Builder $q, $id) => $q->where('appointment_type_id', $id))
            ->when(($filters['assigned_to'] ?? null) === 'me', fn (Builder $q) => $q->where('assigned_to', $request->user()?->id))
            ->when(($filters['assigned_to'] ?? null) === 'unassigned', fn (Builder $q) => $q->whereNull('assigned_to'))
            ->orderBy('scheduled_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('appointments/index', [
            'appointments' => AppointmentResource::collection($appointments),
            'filters' => [
                'date_from' => $from,
                'date_to' => $to,
                'status' => $filters['status'] ?? null,
                'appointment_type_id' => isset($filters['appointment_type_id']) ? (int) $filters['appointment_type_id'] : null,
                'assigned_to' => $filters['assigned_to'] ?? null,
            ],
            'counts' => [
                'today' => Appointment::query()->whereDate('scheduled_at', $today)->where('status', '!=', AppointmentStatus::Cancelled)->count(),
                'missed_last_7_days' => Appointment::query()->where('status', AppointmentStatus::Missed)
                    ->where('scheduled_at', '>=', $today->subDays(7)->startOfDay())->count(),
            ],
            'options' => [
                'types' => $this->lookups->appointmentTypes(),
                'statuses' => AppointmentStatus::options(),
                'staff' => $this->lookups->staff(),
            ],
            'residentOptions' => Inertia::optional(fn () => $this->lookups->residents($filters['resident'] ?? null)),
            // Calendar click → edit sheet (partial reload with ?appointment=ID).
            'selectedAppointment' => Inertia::optional(fn () => isset($filters['appointment'])
                ? AppointmentResource::make(Appointment::query()
                    ->with(['resident', 'type', 'assignedTo:id,name'])
                    ->whereKey($filters['appointment'])
                    ->firstOrFail())
                : null),
        ]);
    }

    public function store(AppointmentRequest $request): RedirectResponse
    {
        Appointment::query()->create([
            ...$request->validated(),
            'status' => AppointmentStatus::Scheduled,
        ]);

        $this->toast('Appointment booked.');

        return back();
    }

    /**
     * Rescheduling or reassigning a generated appointment marks it as edited
     * so the generator leaves it alone from now on.
     */
    public function update(AppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $appointment->fill($request->validated());

        if ($appointment->source_type !== null && $appointment->isDirty(['scheduled_at', 'assigned_to'])) {
            $appointment->edited_manually = true;
        }

        $appointment->save();

        $this->toast("Appointment {$appointment->status->label()}.");

        return back();
    }
}
