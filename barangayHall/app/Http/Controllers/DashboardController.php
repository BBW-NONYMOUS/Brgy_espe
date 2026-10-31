<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\ProgramStatus;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\HealthProgramResource;
use App\Http\Resources\OutbreakAlertResource;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\DiseaseCase;
use App\Models\HealthProgram;
use App\Models\MedicineBatch;
use App\Models\OutbreakAlert;
use App\Services\InventoryAlertService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportService $reports, InventoryAlertService $inventoryAlerts): Response
    {
        $user = $request->user();
        $can = fn (PermissionName $p) => $user?->can($p->value) ?? false;
        $today = CarbonImmutable::today();

        return Inertia::render('dashboard', [
            // Aggregates only — safe for every role, including Barangay Officials.
            'stats' => Inertia::defer(fn () => $reports->totals(), 'stats'),
            'residentsPerPurok' => Inertia::defer(fn () => $reports->populationPerPurok(), 'charts'),
            'ageSex' => Inertia::defer(fn () => $reports->ageSexDistribution(), 'charts'),

            // Active outbreak banner: disease, place, count (no names).
            'outbreaks' => $can(PermissionName::OutbreaksViewSummary)
                ? OutbreakAlertResource::collection(OutbreakAlert::query()->open()->with(['disease', 'purok'])->latest('id')->get())
                : null,
            'diseaseTrend' => $can(PermissionName::OutbreaksViewSummary) ? Inertia::defer(fn () => $this->diseaseTrend($today), 'charts') : null,

            'serviceStats' => Inertia::defer(fn () => array_filter([
                'expiring_batches' => $can(PermissionName::InventoryView) ? MedicineBatch::query()
                    ->where('quantity_on_hand', '>', 0)
                    ->where('expiry_date', '<', $today->addDays(31)->toDateString())
                    ->count() : null,
                'low_stock_medicines' => $can(PermissionName::InventoryView) ? $inventoryAlerts->lowStock(null, $today)->count() : null,
                'appointments_today' => $can(PermissionName::AppointmentsManage) ? Appointment::query()
                    ->whereDate('scheduled_at', $today)->where('status', '!=', AppointmentStatus::Cancelled)->count() : null,
                'missed_appointments' => $can(PermissionName::AppointmentsManage) ? Appointment::query()
                    ->where('status', AppointmentStatus::Missed)->where('scheduled_at', '>=', $today->subDays(7)->startOfDay())->count() : null,
                'active_outbreaks' => $can(PermissionName::OutbreaksViewSummary) ? OutbreakAlert::query()->open()->count() : null,
            ], fn ($v) => $v !== null), 'stats'),

            'todaysAppointments' => $can(PermissionName::AppointmentsManage) ? Inertia::defer(fn () => AppointmentResource::collection(Appointment::query()
                ->with(['resident', 'type', 'assignedTo:id,name'])
                ->whereDate('scheduled_at', $today)
                ->where('status', '!=', AppointmentStatus::Cancelled)
                ->orderBy('scheduled_at')
                ->limit(20)
                ->get())->resolve(), 'activity') : null,

            'upcomingPrograms' => $can(PermissionName::ProgramsView) ? Inertia::defer(fn () => HealthProgramResource::collection(HealthProgram::query()
                ->with('type')
                ->where('status', ProgramStatus::Scheduled)
                ->where('end_at', '>=', now())
                ->orderBy('start_at')
                ->limit(5)
                ->get())->resolve(), 'activity') : null,

            // Recent record activity (no clinical details) for health workers.
            'recentActivity' => $can(PermissionName::ResidentsView) ? Inertia::defer(fn () => AuditLog::query()
                ->with('user:id,name')
                ->whereIn('action', [AuditAction::Created, AuditAction::Updated, AuditAction::Deleted, AuditAction::Restored])
                ->whereIn('table_name', ['residents', 'households', 'health_visits', 'immunizations', 'pregnancies', 'disease_cases', 'referrals'])
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action->value,
                    'description' => $log->description,
                    'table_name' => $log->table_name,
                    'record_id' => $log->record_id,
                    'user' => $log->user?->name,
                    'created_at' => $log->created_at->toIso8601String(),
                ]), 'activity') : null,
        ]);
    }

    /**
     * Countable cases per week, last 12 weeks, all diseases (aggregate).
     *
     * @return array<int, array{week: string, cases: int}>
     */
    private function diseaseTrend(CarbonImmutable $today): array
    {
        $start = $today->startOfWeek()->subWeeks(11);
        $onsets = DiseaseCase::query()->countable()->where('onset_date', '>=', $start->toDateString())->pluck('onset_date');

        return collect(range(0, 11))->map(function (int $i) use ($start, $onsets) {
            $week = $start->addWeeks($i);

            return [
                'week' => $week->toDateString(),
                'cases' => $onsets->filter(fn ($d) => $d >= $week && $d < $week->addWeek())->count(),
            ];
        })->all();
    }
}
