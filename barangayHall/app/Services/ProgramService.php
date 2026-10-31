<?php

namespace App\Services;

use App\Enums\ProgramTargetGroup;
use App\Enums\RelationshipToHead;
use App\Enums\ResidentStatus;
use App\Enums\Sex;
use App\Models\HealthProgram;
use App\Models\Resident;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who a community program is for, and its attendance list.
 */
class ProgramService
{
    /**
     * Active residents in the program's target puroks (all puroks when none
     * are set) who belong to its target group, ages as of the program date.
     *
     * @return Builder<Resident>
     */
    public function eligible(HealthProgram $program): Builder
    {
        $on = $program->start_at->startOfDay();
        $purokIds = $program->puroks()->pluck('puroks.id');

        return Resident::query()
            ->where('status', ResidentStatus::Active)
            ->when($purokIds->isNotEmpty(), fn (Builder $q) => $q->whereHas('household', fn (Builder $h) => $h->whereIn('purok_id', $purokIds)))
            ->tap(fn (Builder $q) => $this->applyTargetGroup($q, $program->target_group, $on));
    }

    /**
     * Mark residents present; already-listed residents are skipped.
     *
     * @param  list<int>  $residentIds
     * @return int residents added
     */
    public function recordAttendance(HealthProgram $program, array $residentIds, User $user, ?CarbonImmutable $at = null): int
    {
        return DB::transaction(function () use ($program, $residentIds, $user, $at) {
            $existing = $program->attendance()->pluck('resident_id')->all();
            $new = array_values(array_diff(array_unique($residentIds), $existing));

            foreach ($new as $residentId) {
                $program->attendance()->create([
                    'resident_id' => $residentId,
                    'attended_at' => $at ?? now(),
                    'recorded_by' => $user->id,
                ]);
            }

            return count($new);
        });
    }

    /**
     * @param  Builder<Resident>  $query
     */
    private function applyTargetGroup(Builder $query, ProgramTargetGroup $group, CarbonImmutable $on): void
    {
        $born = fn (int $minYears, ?int $maxYears) => $query
            ->where('birthdate', '<=', $on->subYears($minYears)->toDateString())
            ->when($maxYears !== null, fn (Builder $q) => $q->where('birthdate', '>', $on->subYears($maxYears + 1)->toDateString()));

        match ($group) {
            ProgramTargetGroup::Everyone => null,
            ProgramTargetGroup::Infants => $query->where('birthdate', '>', $on->subMonths(12)->toDateString()),
            ProgramTargetGroup::UnderFive => $query->where('birthdate', '>', $on->subMonths(60)->toDateString()),
            ProgramTargetGroup::SchoolAge => $born(5, 12),
            ProgramTargetGroup::Adolescents => $born(10, 19),
            ProgramTargetGroup::ReproductiveAgeWomen => $born(15, 49)->where('sex', Sex::Female),
            ProgramTargetGroup::Pregnant => $query->pregnant(),
            ProgramTargetGroup::Seniors => $born(Resident::SENIOR_AGE, null),
            ProgramTargetGroup::Pwd => $query->where('is_pwd', true),
            ProgramTargetGroup::Households => $query->where('relationship_to_head', RelationshipToHead::Head),
        };
    }
}
