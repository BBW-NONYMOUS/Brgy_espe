<?php

namespace App\Services;

use App\Enums\OutbreakAlertStatus;
use App\Enums\OutbreakScope;
use App\Enums\PermissionName;
use App\Models\Disease;
use App\Models\DiseaseCase;
use App\Models\OutbreakAlert;
use App\Models\OutbreakThreshold;
use App\Models\Purok;
use App\Notifications\OutbreakDetected;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Compares recent case counts with the Admin's outbreak thresholds.
 *
 * Rule: count suspected + probable + confirmed cases (never ruled-out ones;
 * the outcome does not matter) whose onset date falls in the last
 * `days_window` days, per purok or barangay-wide. At or above `min_cases`:
 *  - no open alert (active/monitoring) for that disease + place → create one
 *    and notify everyone with outbreaks.view_summary (Officials included —
 *    the message has counts only);
 *  - an open alert exists → update its count/period; notify again only if
 *    the count rose.
 * Alerts are never closed automatically; staff close them after response.
 */
class OutbreakDetectionService
{
    public function __construct(private readonly NotificationRouter $notifications) {}

    /**
     * Check one disease (after a case is saved), optionally only one purok.
     *
     * @return Collection<int, OutbreakAlert> alerts created or escalated
     */
    public function checkDisease(Disease $disease, ?int $purokId = null, ?CarbonImmutable $today = null): Collection
    {
        $today ??= CarbonImmutable::today();

        // One detector at a time, so a burst of saved cases can't create twin alerts.
        return Cache::lock('outbreak-detection', 30)->block(10, fn () => $disease->thresholds()
            ->where('is_active', true)
            ->get()
            ->flatMap(fn (OutbreakThreshold $threshold) => $this->evaluate($disease, $threshold, $purokId, $today))
            ->values());
    }

    /**
     * Daily sweep over every active threshold.
     *
     * @return Collection<int, OutbreakAlert>
     */
    public function sweep(?CarbonImmutable $today = null): Collection
    {
        return Disease::query()
            ->where('is_active', true)
            ->whereHas('thresholds', fn ($q) => $q->where('is_active', true))
            ->get()
            ->flatMap(fn (Disease $disease) => $this->checkDisease($disease, null, $today))
            ->values();
    }

    /**
     * @return Collection<int, OutbreakAlert>
     */
    private function evaluate(Disease $disease, OutbreakThreshold $threshold, ?int $purokId, CarbonImmutable $today): Collection
    {
        $from = $today->subDays($threshold->days_window - 1)->toDateString();

        $query = DiseaseCase::query()
            ->countable()
            ->where('disease_id', $disease->id)
            ->where('onset_date', '>=', $from)
            // Exclusive next-day bound: correct for DATE columns and for SQLite's "Y-m-d 00:00:00".
            ->where('onset_date', '<', $today->addDay()->toDateString());

        if ($threshold->scope === OutbreakScope::Purok) {
            $groups = $query
                ->when($purokId, fn ($q, int $id) => $q->where('purok_id', $id))
                ->selectRaw('purok_id, COUNT(*) AS cases, MIN(onset_date) AS first_onset, MAX(onset_date) AS last_onset')
                ->groupBy('purok_id')
                ->havingRaw('COUNT(*) >= ?', [$threshold->min_cases])
                ->get();
        } else {
            $groups = $query
                ->selectRaw('COUNT(*) AS cases, MIN(onset_date) AS first_onset, MAX(onset_date) AS last_onset')
                ->havingRaw('COUNT(*) >= ?', [$threshold->min_cases])
                ->get();
        }

        return $groups
            ->map(fn (DiseaseCase $row) => $this->raise(
                $disease,
                $threshold,
                $threshold->scope === OutbreakScope::Purok ? (int) $row->getAttribute('purok_id') : null,
                (int) $row->getAttribute('cases'),
                CarbonImmutable::parse((string) $row->getAttribute('first_onset')),
                CarbonImmutable::parse((string) $row->getAttribute('last_onset')),
            ))
            ->filter()
            ->values();
    }

    private function raise(Disease $disease, OutbreakThreshold $threshold, ?int $purokId, int $cases, CarbonImmutable $first, CarbonImmutable $last): ?OutbreakAlert
    {
        $alert = OutbreakAlert::query()
            ->open()
            ->where('disease_id', $disease->id)
            ->where('purok_id', $purokId)
            ->latest('id')
            ->first();

        if ($alert === null) {
            $alert = OutbreakAlert::query()->create([
                'disease_id' => $disease->id,
                'purok_id' => $purokId,
                'threshold_min_cases' => $threshold->min_cases,
                'threshold_days_window' => $threshold->days_window,
                'case_count' => $cases,
                'period_start' => $first,
                'period_end' => $last,
                'status' => OutbreakAlertStatus::Active,
            ]);

            $this->notify($alert, $disease, $purokId, "outbreak:{$alert->id}:created", 'Possible outbreak');

            return $alert;
        }

        if ($cases <= $alert->case_count) {
            if ($last->greaterThan($alert->period_end)) {
                $alert->update(['period_end' => $last]);
            }

            return null;
        }

        $alert->update([
            'case_count' => $cases,
            'period_start' => $alert->period_start->min($first),
            'period_end' => $alert->period_end->max($last),
        ]);

        $this->notify($alert, $disease, $purokId, "outbreak:{$alert->id}:count:{$cases}", 'Outbreak cases rising');

        return $alert;
    }

    private function notify(OutbreakAlert $alert, Disease $disease, ?int $purokId, string $dedupeKey, string $title): void
    {
        $place = $purokId !== null ? (string) Purok::query()->whereKey($purokId)->value('name') : 'the barangay';

        $this->notifications->toPermission(
            PermissionName::OutbreaksViewSummary,
            new OutbreakDetected(
                title: "{$title}: {$disease->name}",
                message: "{$alert->case_count} {$disease->name} cases in {$place} within {$alert->threshold_days_window} days (alert level: {$alert->threshold_min_cases}).",
                url: route('outbreak-alerts.index', absolute: false),
                level: 'critical',
            ),
            $dedupeKey,
        );
    }
}
