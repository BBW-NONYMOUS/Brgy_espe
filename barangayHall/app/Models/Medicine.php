<?php

namespace App\Models;

use App\Enums\MedicineForm;
use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $generic_name
 * @property string|null $brand_name
 * @property MedicineForm $form
 * @property string $strength
 * @property string $unit
 * @property int $reorder_level
 * @property bool $is_active
 * @property-read string $display_name
 */
#[Fillable(['generic_name', 'brand_name', 'form', 'strength', 'unit', 'reorder_level', 'is_active'])]
#[ObservedBy(AuditableObserver::class)]
class Medicine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'form' => MedicineForm::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * "Paracetamol 500 mg tablet"
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => "{$this->generic_name} {$this->strength} {$this->form->value}");
    }

    /**
     * @return HasMany<MedicineBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class);
    }
}
