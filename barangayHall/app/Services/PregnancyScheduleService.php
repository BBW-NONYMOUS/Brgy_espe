<?php

namespace App\Services;

use App\Enums\MaternalStatus;
use App\Enums\MaternalVisitKind;
use App\Models\MaternalVisitRule;
use App\Models\PostnatalVisit;
use App\Models\Pregnancy;
use App\Models\PrenatalVisit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Prenatal and postnatal contacts of a pregnancy, computed from the
 * configurable maternal_visit_rules (never stored).
 *
 *  - Prenatal contact due = (EDD − 280 days) + offset_days (age of gestation).
 *  - Postnatal contact due = delivery date + offset_days.
 *  - done: a visit is linked to the rule; overdue: due date + grace passed;
 *    due: within the due-soon window or grace; upcoming: later;
 *    missed: an unattended prenatal contact of a pregnancy that has ended;
 *    not_applicable: prenatal contacts due after the pregnancy ended.
 */
class PregnancyScheduleService
{
    /** @var Collection<int, MaternalVisitRule>|null */
    private ?Collection $rules = null;

    /**
     * @return Collection<int, array{rule_id: int, kind: string, sequence: int, label: string, due_date: string, status: string, visit_date: string|null, health_visit_id: int|null}>
     */
    public function contacts(Pregnancy $pregnancy, ?CarbonImmutable $today = null): Collection
    {
        $today ??= CarbonImmutable::today();
        $pregnancy->loadMissing(['prenatalVisits.healthVisit', 'postnatalVisits.healthVisit']);

        return $this->rules()
            ->map(function (MaternalVisitRule $rule) use ($pregnancy, $today) {
                $due = $this->dueDate($pregnancy, $rule);

                if ($due === null) {
                    return null;
                }

                /** @var PrenatalVisit|PostnatalVisit|null $visit */
                $visit = ($rule->kind === MaternalVisitKind::Prenatal ? $pregnancy->prenatalVisits : $pregnancy->postnatalVisits)
                    ->firstWhere('maternal_visit_rule_id', $rule->id);

                $endedBeforeDue = $rule->kind === MaternalVisitKind::Prenatal
                    && $pregnancy->outcome_date !== null
                    && $due->greaterThanOrEqualTo($pregnancy->outcome_date);

                return [
                    'rule_id' => $rule->id,
                    'kind' => $rule->kind->value,
                    'sequence' => $rule->sequence,
                    'label' => $rule->label,
                    'due_date' => $due->toDateString(),
                    'status' => match (true) {
                        $visit !== null => 'done',
                        $endedBeforeDue || $pregnancy->status === MaternalStatus::Terminated => 'not_applicable',
                        // Ended pregnancy: past prenatal contacts are history, not to-dos.
                        $rule->kind === MaternalVisitKind::Prenatal && $pregnancy->status !== MaternalStatus::Ongoing => 'missed',
                        $due->addDays($this->graceDays())->lessThan($today) => 'overdue',
                        $due->subDays($this->dueSoonDays())->lessThanOrEqualTo($today) => 'due',
                        default => 'upcoming',
                    },
                    'visit_date' => $visit?->healthVisit->visit_date->toDateString(),
                    'health_visit_id' => $visit?->health_visit_id,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * The next contact still to be attended (overdue first), if any.
     *
     * @return array{rule_id: int, kind: string, sequence: int, label: string, due_date: string, status: string, visit_date: string|null, health_visit_id: int|null}|null
     */
    public function nextContact(Pregnancy $pregnancy, ?CarbonImmutable $today = null): ?array
    {
        return $this->contacts($pregnancy, $today)->first(fn (array $c) => in_array($c['status'], ['overdue', 'due', 'upcoming'], true));
    }

    public function overdueCount(Pregnancy $pregnancy, ?CarbonImmutable $today = null): int
    {
        return $this->contacts($pregnancy, $today)->where('status', 'overdue')->count();
    }

    /**
     * Which scheduled contact a newly recorded visit fulfils: the earliest
     * unattended contact of that kind that is due by the visit date (plus
     * the due-soon window, for early attendance). Null = extra visit.
     */
    public function ruleForVisit(Pregnancy $pregnancy, MaternalVisitKind $kind, CarbonImmutable $visitDate, ?int $ignoreHealthVisitId = null): ?MaternalVisitRule
    {
        $pregnancy->loadMissing(['prenatalVisits', 'postnatalVisits']);
        $visits = $kind === MaternalVisitKind::Prenatal ? $pregnancy->prenatalVisits : $pregnancy->postnatalVisits;
        $taken = $visits->where('health_visit_id', '!=', $ignoreHealthVisitId)->pluck('maternal_visit_rule_id')->filter()->all();

        return $this->rules()
            ->where('kind', $kind)
            ->reject(fn (MaternalVisitRule $rule) => in_array($rule->id, $taken, true))
            ->first(function (MaternalVisitRule $rule) use ($pregnancy, $visitDate) {
                $due = $this->dueDate($pregnancy, $rule);

                return $due !== null && $due->subDays($this->dueSoonDays())->lessThanOrEqualTo($visitDate);
            });
    }

    public function dueDate(Pregnancy $pregnancy, MaternalVisitRule $rule): ?CarbonImmutable
    {
        if ($rule->kind === MaternalVisitKind::Prenatal) {
            return $pregnancy->edd->subDays(Pregnancy::GESTATION_DAYS)->addDays($rule->offset_days);
        }

        return $pregnancy->status === MaternalStatus::Delivered && $pregnancy->outcome_date !== null
            ? $pregnancy->outcome_date->addDays($rule->offset_days)
            : null;
    }

    /**
     * Active rules, prenatal then postnatal, in sequence order.
     *
     * @return Collection<int, MaternalVisitRule>
     */
    public function rules(): Collection
    {
        // Sorted in PHP: ENUM ordering differs between MySQL (by index) and SQLite (alphabetical).
        return $this->rules ??= MaternalVisitRule::query()
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (MaternalVisitRule $rule) => [$rule->kind === MaternalVisitKind::Prenatal ? 0 : 1, $rule->sequence])
            ->values();
    }

    /**
     * Forget cached rules (after an admin edits the schedule).
     */
    public function refresh(): void
    {
        $this->rules = null;
    }

    private function graceDays(): int
    {
        return (int) config('barangay.alerts.maternal_grace_days');
    }

    private function dueSoonDays(): int
    {
        return (int) config('barangay.alerts.due_soon_days');
    }
}
