<?php

namespace App\Http\Requests\Inventory;

use App\Models\Medicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReceiveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $medicine = $this->route('medicine');

        return $medicine instanceof Medicine && ($this->user()?->can('update', $medicine) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'batch_no' => Str::upper(Str::squish((string) $this->input('batch_no'))),
            'received_at' => $this->input('received_at') ?: today()->toDateString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Medicine $medicine */
        $medicine = $this->route('medicine');

        return [
            'batch_no' => ['required', 'string', 'max:40', Rule::unique('medicine_batches')->where('medicine_id', $medicine->id)],
            'expiry_date' => ['required', 'date', 'after:received_at'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'source' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['batch_no.unique' => 'This batch number was already received for this medicine.'];
    }
}
