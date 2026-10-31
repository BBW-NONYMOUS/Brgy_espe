<?php

namespace App\Enums;

enum OutbreakScope: string
{
    case Purok = 'purok';
    case Barangay = 'barangay';

    public function label(): string
    {
        return match ($this) {
            self::Purok => 'Same purok',
            self::Barangay => 'Whole barangay',
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
