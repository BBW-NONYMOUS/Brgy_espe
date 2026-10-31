<?php

namespace App\Enums;

enum ResidentStatus: string
{
    case Active = 'active';
    case Moved = 'moved';
    case Deceased = 'deceased';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Moved => 'Moved out',
            self::Deceased => 'Deceased',
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
