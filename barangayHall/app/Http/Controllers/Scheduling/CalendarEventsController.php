<?php

namespace App\Http\Controllers\Scheduling;

use App\Enums\AppointmentStatus;
use App\Enums\PermissionName;
use App\Enums\ProgramStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\HealthProgram;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON feed for the shared calendar (FullCalendar asks for the visible
 * range only). Appointments name residents, so they are sent only to
 * appointment managers; programs go to everyone with programs.view —
 * Barangay Officials see programs only.
 */
class CalendarEventsController extends Controller
{
    /** Longest range one request may ask for (month view + margins). */
    private const MAX_DAYS = 62;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $canAppointments = $user?->can(PermissionName::AppointmentsManage->value) ?? false;
        $canPrograms = $user?->can(PermissionName::ProgramsView->value) ?? false;

        abort_unless($canAppointments || $canPrograms, 403);

        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        $start = CarbonImmutable::parse($data['start']);
        $end = CarbonImmutable::parse($data['end'])->min($start->addDays(self::MAX_DAYS));

        $events = collect();

        if ($canAppointments) {
            $events = $events->merge(Appointment::query()
                ->with(['resident:id,last_name,first_name,middle_name,suffix', 'type'])
                ->whereBetween('scheduled_at', [$start, $end])
                ->where('status', '!=', AppointmentStatus::Cancelled)
                ->get()
                ->map(fn (Appointment $a) => [
                    'id' => "appointment-{$a->id}",
                    'title' => "{$a->type->name}: {$a->resident->full_name}",
                    'start' => $a->scheduled_at->toIso8601String(),
                    'end' => $a->scheduled_at->addMinutes($a->type->default_duration_minutes)->toIso8601String(),
                    'color' => $a->type->color,
                    'extendedProps' => ['kind' => 'appointment', 'appointment_id' => $a->id, 'resident_id' => $a->resident_id, 'status' => $a->status->value],
                ]));
        }

        if ($canPrograms) {
            $events = $events->merge(HealthProgram::query()
                ->with('type')
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->where('status', '!=', ProgramStatus::Cancelled)
                ->get()
                ->map(fn (HealthProgram $p) => [
                    'id' => "program-{$p->id}",
                    'title' => $p->title,
                    'start' => $p->start_at->toIso8601String(),
                    'end' => $p->end_at->toIso8601String(),
                    'color' => $p->type->color,
                    'url' => route('programs.show', $p, absolute: false),
                    'extendedProps' => ['kind' => 'program', 'program_id' => $p->id, 'venue' => $p->venue, 'status' => $p->status->value],
                ]));
        }

        return response()->json($events->values());
    }
}
