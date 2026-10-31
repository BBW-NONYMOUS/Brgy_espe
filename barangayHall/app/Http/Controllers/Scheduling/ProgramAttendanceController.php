<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\HealthProgram;
use App\Models\ProgramAttendance;
use App\Services\ProgramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramAttendanceController extends Controller
{
    public function store(Request $request, HealthProgram $program, ProgramService $programs): RedirectResponse
    {
        Gate::authorize('recordAttendance', $program);

        $data = $request->validate([
            'resident_ids' => ['required', 'array', 'min:1', 'max:500'],
            'resident_ids.*' => ['integer', Rule::exists('residents', 'id')->whereNull('deleted_at')],
        ]);

        if ($program->start_at->isFuture()) {
            throw ValidationException::withMessages(['resident_ids' => 'Attendance can be recorded once the program has started.']);
        }

        $added = $programs->recordAttendance($program, array_values(array_map('intval', $data['resident_ids'])), $request->user());

        $this->toast($added > 0 ? "{$added} attendee(s) recorded." : 'They were already on the list.', $added > 0 ? 'success' : 'info');

        return back();
    }

    public function destroy(ProgramAttendance $attendance): RedirectResponse
    {
        Gate::authorize('recordAttendance', $attendance->program);

        $attendance->delete();

        $this->toast('Removed from the attendance list.');

        return back();
    }
}
