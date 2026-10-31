<?php

namespace Database\Seeders;

use App\Enums\ReferralStatus;
use App\Enums\ReferralUrgency;
use App\Models\Facility;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\FindsSeededResidents;
use Illuminate\Database\Seeder;

/**
 * One referral in each stage of pending → sent → received → completed.
 */
class ReferralSeeder extends Seeder
{
    use FindsSeededResidents;

    /**
     * [last|first, facility, urgency, days ago, reason, status changes after "pending" as [status, days ago], feedback]
     *
     * @var list<array{0: string, 1: string, 2: ReferralUrgency, 3: int, 4: string, 5: list<array{0: ReferralStatus, 1: int}>, 6: string|null}>
     */
    private const REFERRALS = [
        ['Villanueva|Lourdes', 'Isulan Rural Health Unit', ReferralUrgency::Urgent, 40,
            'Fasting blood sugar 11.2 mmol/L despite metformin. For physician evaluation and medication adjustment.',
            [[ReferralStatus::Sent, 40], [ReferralStatus::Received, 39], [ReferralStatus::Completed, 35]],
            'Seen by the Municipal Health Officer. Insulin started; monthly blood sugar monitoring at the barangay health station.'],
        ['Ocampo|Rhealyn', 'Sultan Kudarat Provincial Hospital', ReferralUrgency::Routine, 10,
            'High-risk pregnancy (previous caesarean section), 28 weeks AOG. For OB evaluation and delivery planning.',
            [[ReferralStatus::Sent, 9]], null],
        ['Lumabao|Gabriel', 'Sultan Kudarat Provincial Hospital', ReferralUrgency::Emergency, 5,
            'Dengue with warning signs: abdominal pain and persistent vomiting on day 4 of fever.',
            [[ReferralStatus::Sent, 5], [ReferralStatus::Received, 5]], null],
        ['Lumabao|Benjamin', 'Isulan Rural Health Unit', ReferralUrgency::Routine, 0,
            'COPD with worsening cough for 2 weeks. For chest X-ray and physician review.',
            [], null],
    ];

    public function run(): void
    {
        $midwife = $this->user('mw.cristina');
        $facilities = Facility::query()->pluck('id', 'name');
        $today = CarbonImmutable::today();
        $year = $today->year;

        foreach (self::REFERRALS as $index => [$key, $facility, $urgency, $daysAgo, $reason, $changes, $feedback]) {
            $resident = $this->resident(...explode('|', $key));
            $time = fn (int $days) => $today->subDays($days)->setTime(9 + $index, 30);
            $trail = [[ReferralStatus::Pending, $daysAgo], ...$changes];
            [$finalStatus, $finalDaysAgo] = $trail[array_key_last($trail)];

            $referral = $resident->referrals()->create([
                'referral_no' => sprintf('REF-%d-%05d', $year, $index + 1),
                'facility_id' => $facilities[$facility],
                'health_visit_id' => $resident->healthVisits()->whereDate('visit_date', $time($daysAgo)->toDateString())->value('id'),
                'referred_by' => $midwife->id,
                'referred_at' => $time($daysAgo),
                'reason' => $reason,
                'urgency' => $urgency,
                'status' => $finalStatus,
                'feedback_note' => $feedback,
                'completed_at' => $finalStatus === ReferralStatus::Completed ? $time($finalDaysAgo) : null,
            ]);

            $from = null;

            foreach ($trail as [$to, $changedDaysAgo]) {
                $referral->statusChanges()->create([
                    'from_status' => $from,
                    'to_status' => $to,
                    'changed_by' => $midwife->id,
                    'note' => $to === ReferralStatus::Completed ? 'Return slip received.' : null,
                    'created_at' => $time($changedDaysAgo),
                ]);
                $from = $to;
            }
        }
    }
}
