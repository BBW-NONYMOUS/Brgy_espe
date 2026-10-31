<?php

namespace App\Http\Controllers\Scheduling;

use App\Enums\ProgramStatus;
use App\Enums\ProgramTargetGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\HealthProgramRequest;
use App\Http\Resources\HealthProgramResource;
use App\Models\HealthProgram;
use App\Models\ProgramAttendance;
use App\Models\Resident;
use App\Services\LookupOptions;
use App\Services\ProgramService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HealthProgramController extends Controller
{
    public function __construct(
        private readonly LookupOptions $lookups,
        private readonly ProgramService $programs,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', HealthProgram::class);

        $filters = $request->validate([
            'when' => ['nullable', Rule::in(['upcoming', 'past'])],
            'program_type_id' => ['nullable', 'integer'],
        ]);

        $past = ($filters['when'] ?? 'upcoming') === 'past';

        $programs = HealthProgram::query()
            ->with(['type', 'puroks'])
            ->withCount('attendance')
            ->when($past, fn (Builder $q) => $q->where('end_at', '<', now()), fn (Builder $q) => $q->where('end_at', '>=', now()))
            ->when($filters['program_type_id'] ?? null, fn (Builder $q, $id) => $q->where('program_type_id', $id))
            ->orderBy('start_at', $past ? 'desc' : 'asc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('programs/index', [
            'programs' => HealthProgramResource::collection($programs),
            'filters' => ['when' => $past ? 'past' : 'upcoming', 'program_type_id' => isset($filters['program_type_id']) ? (int) $filters['program_type_id'] : null],
            'options' => $this->formOptions(),
            'canManage' => Gate::allows('create', HealthProgram::class),
        ]);
    }

    public function show(Request $request, HealthProgram $program): Response
    {
        Gate::authorize('view', $program);

        $program->load(['type', 'puroks', 'staff:id,name'])->loadCount('attendance');
        $canManage = Gate::allows('recordAttendance', $program);
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('programs/show', [
            'program' => HealthProgramResource::make($program),
            'eligibleCount' => $this->programs->eligible($program)->count(),
            // Attendee names are personal data: only for program managers.
            'attendance' => $canManage ? $program->attendance()
                ->with('resident:id,resident_code,last_name,first_name,middle_name,suffix,household_id', 'resident.household.purok:id,name')
                ->latest('attended_at')
                ->get()
                ->map(fn (ProgramAttendance $a) => [
                    'id' => $a->id,
                    'resident_id' => $a->resident_id,
                    'resident_code' => $a->resident->resident_code,
                    'full_name' => $a->resident->full_name,
                    'purok' => $a->resident->household->purok->name,
                    'attended_at' => $a->attended_at->toIso8601String(),
                ]) : null,
            'attendanceByPurok' => $program->attendance()
                ->join('residents', 'residents.id', '=', 'program_attendance.resident_id')
                ->join('households', 'households.id', '=', 'residents.household_id')
                ->join('puroks', 'puroks.id', '=', 'households.purok_id')
                ->groupBy('puroks.name')
                ->orderBy('puroks.name')
                ->selectRaw('puroks.name AS purok, COUNT(*) AS total')
                ->pluck('total', 'purok'),
            // Attendance checklist search among eligible residents (partial reload).
            'eligible' => $canManage ? Inertia::optional(fn () => $this->programs->eligible($program)
                ->with('household.purok')
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('last_name', 'like', "{$search}%")->orWhere('first_name', 'like', "{$search}%")->orWhere('resident_code', 'like', "{$search}%")))
                ->whereNotIn('id', $program->attendance()->select('resident_id'))
                ->orderBy('last_name')
                ->limit(50)
                ->get()
                ->map(fn (Resident $r) => ['id' => $r->id, 'resident_code' => $r->resident_code, 'full_name' => $r->full_name, 'age' => $r->age, 'purok' => $r->household->purok->name])) : null,
            'options' => $canManage ? $this->formOptions() : null,
            'canManage' => $canManage,
        ]);
    }

    public function store(HealthProgramRequest $request): RedirectResponse
    {
        $program = DB::transaction(function () use ($request) {
            $program = HealthProgram::query()->create([
                ...$request->safe()->except(['purok_ids', 'staff_ids']),
                'status' => ProgramStatus::Scheduled,
            ]);
            $program->puroks()->sync($request->validated('purok_ids', []));
            $program->staff()->sync($request->validated('staff_ids', []));

            return $program;
        });

        $this->toast("Program \"{$program->title}\" scheduled.");

        return to_route('programs.show', $program);
    }

    public function update(HealthProgramRequest $request, HealthProgram $program): RedirectResponse
    {
        DB::transaction(function () use ($request, $program) {
            $program->update($request->safe()->except(['purok_ids', 'staff_ids']));
            $program->puroks()->sync($request->validated('purok_ids', []));
            $program->staff()->sync($request->validated('staff_ids', []));
        });

        $this->toast('Program updated.');

        return back();
    }

    public function destroy(HealthProgram $program): RedirectResponse
    {
        Gate::authorize('delete', $program);

        $program->delete();

        $this->toast('Program removed.');

        return to_route('programs.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'types' => $this->lookups->programTypes(),
            'targetGroups' => ProgramTargetGroup::options(),
            'statuses' => ProgramStatus::options(),
            'puroks' => $this->lookups->puroks(),
            'staff' => $this->lookups->staff(),
        ];
    }
}
