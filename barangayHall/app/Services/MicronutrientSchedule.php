<?php

namespace App\Services;

use App\Enums\MicronutrientType;
use App\Models\MicronutrientRecord;
use App\Models\Resident;
use Carbon\CarbonImmutable;

/**
 * DOH Garantisadong Pambata rules for children 6–59 months:
 * vitamin A every 6 months from 6 months, deworming every 6 months from
 * 12 months. Expects `micronutrientRecords` to be loaded (or loads it).
 */
class MicronutrientSchedule
{
    public const INTERVAL_MONTHS = 6;

    /**
     * @return array{vitamin_a: array{last: string|null, due: bool, due_date: string|null}, deworming: array{last: string|null, due: bool, due_date: string|null}}
     */
    public function forChild(Resident $child, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        return [
            'vitamin_a' => $this->status($child, MicronutrientType::VitaminA, 6, $today),
            'deworming' => $this->status($child, MicronutrientType::Deworming, 12, $today),
        ];
    }

    public function vitaminADue(Resident $child, ?CarbonImmutable $today = null): bool
    {
        return $this->forChild($child, $today)['vitamin_a']['due'];
    }

    /**
     * @return array{last: string|null, due: bool, due_date: string|null}
     */
    private function status(Resident $child, MicronutrientType $type, int $fromMonths, CarbonImmutable $today): array
    {
        $last = $child->micronutrientRecords
            ->filter(fn (MicronutrientRecord $r) => $r->type === $type)
            ->max(fn (MicronutrientRecord $r) => $r->date_given);

        $start = $child->birthdate->addMonths($fromMonths);
        $end = $child->birthdate->addMonths(60);

        if ($today->lessThan($start) || $today->greaterThanOrEqualTo($end)) {
            return ['last' => $last?->toDateString(), 'due' => false, 'due_date' => $today->lessThan($start) ? $start->toDateString() : null];
        }

        $dueDate = $last !== null ? $last->addMonths(self::INTERVAL_MONTHS)->max($start) : $start;

        return [
            'last' => $last?->toDateString(),
            'due' => $dueDate->lessThanOrEqualTo($today),
            'due_date' => $dueDate->toDateString(),
        ];
    }
}
