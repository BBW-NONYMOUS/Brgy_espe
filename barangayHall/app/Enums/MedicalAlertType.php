<?php

namespace App\Enums;

enum MedicalAlertType: string
{
    case Allergy = 'allergy';
    case Chronic = 'chronic';
    case Disability = 'disability';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Allergy => 'Allergy',
            self::Chronic => 'Chronic condition',
            self::Disability => 'Disability',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
