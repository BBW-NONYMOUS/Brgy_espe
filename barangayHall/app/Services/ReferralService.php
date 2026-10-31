<?php

namespace App\Services;

use App\Enums\ReferralStatus;
use App\Models\Referral;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\ReferralStatusUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Referrals from the barangay health center to the RHU / hospitals.
 * Status moves forward only: pending → sent → received → completed;
 * pending or sent may be cancelled.
 */
class ReferralService
{
    /** @var array<string, list<ReferralStatus>> */
    public const TRANSITIONS = [
        'pending' => [ReferralStatus::Sent, ReferralStatus::Cancelled],
        'sent' => [ReferralStatus::Received, ReferralStatus::Cancelled],
        'received' => [ReferralStatus::Completed],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(private readonly NotificationRouter $notifications) {}

    /**
     * @param  array<string, mixed>  $data  validated ReferralRequest data (facility_id, reason, urgency, referred_at?, health_visit_id?)
     */
    public function create(Resident $resident, array $data, User $user): Referral
    {
        return DB::transaction(function () use ($resident, $data, $user) {
            $referral = $resident->referrals()->create([
                'referral_no' => $this->nextNumber(),
                'facility_id' => $data['facility_id'],
                'health_visit_id' => $data['health_visit_id'] ?? null,
                'referred_by' => $user->id,
                'referred_at' => $data['referred_at'] ?? now(),
                'reason' => $data['reason'],
                'urgency' => $data['urgency'],
                'status' => ReferralStatus::Pending,
            ]);

            $referral->statusChanges()->create([
                'from_status' => null,
                'to_status' => ReferralStatus::Pending,
                'changed_by' => $user->id,
            ]);

            return $referral;
        });
    }

    /**
     * @return list<ReferralStatus>
     */
    public function allowedNext(Referral $referral): array
    {
        return self::TRANSITIONS[$referral->status->value];
    }

    public function transition(Referral $referral, ReferralStatus $to, User $user, ?string $note = null, ?string $feedback = null): Referral
    {
        if (! in_array($to, $this->allowedNext($referral), true)) {
            throw ValidationException::withMessages([
                'status' => "A {$referral->status->label()} referral cannot be marked {$to->label()}.",
            ]);
        }

        DB::transaction(function () use ($referral, $to, $user, $note, $feedback) {
            $from = $referral->status;

            $referral->update([
                'status' => $to,
                'completed_at' => $to === ReferralStatus::Completed ? now() : $referral->completed_at,
                'feedback_note' => $feedback ?? $referral->feedback_note,
            ]);

            $referral->statusChanges()->create([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $user->id,
                'note' => $note,
            ]);
        });

        // Tell the person who made the referral (unless they made this change).
        $referrer = $referral->referredBy;

        if ($referrer->id !== $user->id) {
            $this->notifications->toUsers($referrer, new ReferralStatusUpdated(
                title: "Referral {$referral->referral_no}: {$to->label()}",
                message: "Your referral to {$referral->facility->name} is now {$to->label()}.",
                url: route('referrals.show', $referral, absolute: false),
                level: $to === ReferralStatus::Cancelled ? 'warning' : 'info',
            ), "referral:{$referral->id}:{$to->value}");
        }

        return $referral;
    }

    /**
     * REF-2026-00001. Call inside a transaction (row lock on the last number).
     */
    private function nextNumber(): string
    {
        $prefix = 'REF-'.now()->year.'-';
        $last = Referral::withTrashed()
            ->where('referral_no', 'like', $prefix.'%')
            ->orderByDesc('referral_no')
            ->lockForUpdate()
            ->value('referral_no');

        $sequence = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
