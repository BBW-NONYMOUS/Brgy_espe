<?php

namespace App\Http\Requests\Health;

use App\Enums\BirthAttendant;
use App\Enums\DeliveryMode;
use App\Enums\DeliveryPlace;
use App\Enums\MaternalStatus;
use App\Enums\RelationshipToHead;
use App\Enums\Sex;
use App\Models\Pregnancy;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Delivery record (ends the pregnancy) with optional newborn registration.
 */
class DeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pregnancy = $this->route('pregnancy');

        return $pregnancy instanceof Pregnancy && ($this->user()?->can('update', $pregnancy) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Pregnancy $pregnancy */
        $pregnancy = $this->route('pregnancy');
        $earliest = ($pregnancy->lmp ?? $pregnancy->edd->subDays(Pregnancy::GESTATION_DAYS))->toDateString();

        return [
            'delivery_date' => ['required', 'date', 'before_or_equal:today', 'after:'.$earliest],
            'delivery_time' => ['nullable', 'date_format:H:i'],
            'place_type' => ['required', Rule::enum(DeliveryPlace::class)],
            'place_name' => ['nullable', 'string', 'max:150'],
            'attendant_type' => ['required', Rule::enum(BirthAttendant::class)],
            'delivery_mode' => ['required', Rule::enum(DeliveryMode::class)],
            'live_births' => ['required', 'integer', 'between:0,5'],
            'stillbirths' => ['required', 'integer', 'between:0,5'],
            'remarks' => ['nullable', 'string', 'max:1000'],

            'newborns' => ['array', 'max:5'],
            'newborns.*.first_name' => ['required', 'string', 'max:100'],
            'newborns.*.middle_name' => ['nullable', 'string', 'max:100'],
            'newborns.*.last_name' => ['required', 'string', 'max:100'],
            'newborns.*.sex' => ['required', Rule::enum(Sex::class)],
            'newborns.*.relationship_to_head' => ['required', Rule::enum(RelationshipToHead::class)->except([RelationshipToHead::Head, RelationshipToHead::Spouse])],
            'newborns.*.birth_weight_kg' => ['nullable', 'numeric', 'between:0.5,7'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'newborns.*.first_name' => 'baby\'s first name',
            'newborns.*.last_name' => 'baby\'s last name',
            'newborns.*.sex' => 'baby\'s sex',
            'newborns.*.relationship_to_head' => 'relationship to household head',
            'newborns.*.birth_weight_kg' => 'birth weight',
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Pregnancy $pregnancy */
                $pregnancy = $this->route('pregnancy');

                if ($pregnancy->status !== MaternalStatus::Ongoing) {
                    $validator->errors()->add('pregnancy', 'This pregnancy has already ended.');
                }

                $live = (int) $this->input('live_births');

                if ($live + (int) $this->input('stillbirths') < 1) {
                    $validator->errors()->add('live_births', 'Record at least one live birth or stillbirth.');
                }

                if (count((array) $this->input('newborns', [])) > $live) {
                    $validator->errors()->add('newborns', 'You can register at most as many babies as live births.');
                }
            },
        ];
    }
}
