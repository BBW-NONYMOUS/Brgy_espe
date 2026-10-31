<?php

namespace App\Enums;

enum MicronutrientType: string
{
    case VitaminA = 'vitamin_a';
    case Deworming = 'deworming';
    case Iron = 'iron';
    case MicronutrientPowder = 'micronutrient_powder';

    public function label(): string
    {
        return match ($this) {
            self::VitaminA => 'Vitamin A',
            self::Deworming => 'Deworming',
            self::Iron => 'Iron supplementation',
            self::MicronutrientPowder => 'Micronutrient powder',
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
