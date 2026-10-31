<?php

namespace App\Http\Requests\Inventory;

use App\Models\MedicineBatch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Count correction, transfer out or expired write-off of one batch. The
 * route decides which; every change needs a reason for the ledger.
 */
class StockChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('batch');

        return $batch instanceof MedicineBatch && ($this->user()?->can('update', $batch->medicine) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => [$this->routeIs('batches.write-off') ? 'prohibited' : 'required', 'integer', 'min:0', 'max:1000000'],
            'reason' => [$this->routeIs('batches.write-off') ? 'nullable' : 'required', 'string', 'max:255'],
        ];
    }
}
