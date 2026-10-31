<?php

namespace App\Services;

use App\Enums\ConsentType;
use App\Enums\MaternalStatus;
use App\Enums\NutritionalStatus;
use App\Enums\ResidentStatus;
use App\Enums\RiskLevel;
use App\Models\Delivery;
use App\Models\Pregnancy;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pregnancy registration and delivery, keeping appointments in step.
 */
class PregnancyService
{
    public function __construct(
        private readonly AppointmentGenerator $appointments,
        private readonly ResidentCodeGenerator $codes,
    ) {}

    /**
     * Any flagged risk factor makes the pregnancy high-risk (the midwife may
     * also mark it high-risk without one).
     *
     * @param  array<string, mixed>  $data  validated PregnancyRequest data
     * @param  list<int>  $riskFactorIds
     */
    public function register(Resident $resident, array $data, array $riskFactorIds = []): Pregnancy
    {
        $pregnancy = DB::transaction(function () use ($resident, $data, $riskFactorIds) {
            $pregnancy = $resident->pregnancies()->create([
                ...$data,
                'risk_level' => $riskFactorIds !== [] ? RiskLevel::High : $data['risk_level'],
                'status' => MaternalStatus::Ongoing,
            ]);

            $pregnancy->riskFactors()->sync($riskFactorIds);

            return $pregnancy;
        });

        $this->appointments->syncPregnancy($pregnancy);

        return $pregnancy;
    }

    /**
     * @param  array<string, mixed>  $data  validated PregnancyRequest data (update)
     * @param  list<int>|null  $riskFactorIds  null = leave unchanged
     */
    public function update(Pregnancy $pregnancy, array $data, ?array $riskFactorIds = null): Pregnancy
    {
        DB::transaction(function () use ($pregnancy, $data, $riskFactorIds) {
            if ($riskFactorIds !== null) {
                $pregnancy->riskFactors()->sync($riskFactorIds);
                $data['risk_level'] = $riskFactorIds !== [] ? RiskLevel::High->value : $data['risk_level'];
            }

            $pregnancy->update($data);
        });

        $this->appointments->syncPregnancy($pregnancy->refresh());

        return $pregnancy;
    }

    /**
     * Ends the pregnancy as delivered, stores the delivery details and
     * optionally registers the live-born babies as residents of the mother's
     * household (mother_id set, birth weight as their first growth record,
     * consent given by the mother). Then: ANC appointments cancelled, PNC and
     * newborn EPI appointments generated.
     *
     * @param  array<string, mixed>  $delivery  validated DeliveryRequest data (without newborns)
     * @param  list<array{first_name: string, last_name: string, middle_name?: string|null, sex: string, relationship_to_head: string, birth_weight_kg?: float|string|null}>  $newborns
     */
    public function recordDelivery(Pregnancy $pregnancy, array $delivery, array $newborns, User $user): Delivery
    {
        $mother = $pregnancy->resident;
        $date = $delivery['delivery_date'];

        $result = DB::transaction(function () use ($pregnancy, $delivery, $newborns, $user, $mother, $date) {
            $record = $pregnancy->delivery()->create(collect($delivery)->except('delivery_date')->all());
            $pregnancy->update(['status' => MaternalStatus::Delivered, 'outcome_date' => $date]);

            foreach ($newborns as $baby) {
                $child = Resident::query()->create([
                    'resident_code' => $this->codes->next(),
                    'household_id' => $mother->household_id,
                    'mother_id' => $mother->id,
                    'last_name' => $baby['last_name'],
                    'first_name' => $baby['first_name'],
                    'middle_name' => $baby['middle_name'] ?? null,
                    'sex' => $baby['sex'],
                    'birthdate' => $date,
                    'birthplace' => $delivery['place_name'] ?? null,
                    'civil_status' => 'single',
                    'relationship_to_head' => $baby['relationship_to_head'],
                    'status' => ResidentStatus::Active,
                ]);

                $child->consents()->create([
                    'consent_type' => ConsentType::HealthProfiling,
                    'consent_date' => today(),
                    'obtained_by' => $user->id,
                    'purpose' => 'Barangay health profiling and newborn care — given by the mother',
                ]);

                if (filled($baby['birth_weight_kg'] ?? null)) {
                    $child->growthMeasurements()->create([
                        'measured_on' => $date,
                        'weight_kg' => $baby['birth_weight_kg'],
                        'nutritional_status' => (float) $baby['birth_weight_kg'] < 2.5 ? NutritionalStatus::Underweight : NutritionalStatus::Normal,
                        'measured_by' => $user->id,
                        'remarks' => (float) $baby['birth_weight_kg'] < 2.5 ? 'Low birth weight (below 2,500 g).' : 'Birth weight.',
                    ]);
                }
            }

            return $record;
        });

        $this->appointments->syncPregnancy($pregnancy->refresh());

        $mother->children()->whereDate('birthdate', $date)->get()
            ->each(fn (Resident $child) => $this->appointments->syncChild($child));

        return $result;
    }
}
