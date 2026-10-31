<?php

namespace App\Enums;

enum FacilityType: string
{
    case Rhu = 'rhu';
    case Hospital = 'hospital';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rhu => 'Rural Health Unit',
            self::Hospital => 'Hospital',
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
