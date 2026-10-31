<?php

namespace App\Enums;

enum BloodType: string
{
    case APositive = 'A+';
    case ANegative = 'A-';
    case BPositive = 'B+';
    case BNegative = 'B-';
    case AbPositive = 'AB+';
    case AbNegative = 'AB-';
    case OPositive = 'O+';
    case ONegative = 'O-';

    public function label(): string
    {
        return match ($this) {
            self::APositive => 'A+',
            self::ANegative => 'A-',
            self::BPositive => 'B+',
            self::BNegative => 'B-',
            self::AbPositive => 'AB+',
            self::AbNegative => 'AB-',
            self::OPositive => 'O+',
            self::ONegative => 'O-',
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
