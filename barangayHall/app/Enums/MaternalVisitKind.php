<?php

namespace App\Enums;

enum MaternalVisitKind: string
{
    case Prenatal = 'prenatal';
    case Postnatal = 'postnatal';

    public function label(): string
    {
        return match ($this) {
            self::Prenatal => 'Prenatal',
            self::Postnatal => 'Postnatal',
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
