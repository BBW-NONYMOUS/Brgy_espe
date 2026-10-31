<?php

namespace App\Enums;

enum NutritionalStatus: string
{
    case Normal = 'normal';
    case Underweight = 'underweight';
    case SeverelyUnderweight = 'severely_underweight';
    case Stunted = 'stunted';
    case SeverelyStunted = 'severely_stunted';
    case Wasted = 'wasted';
    case SeverelyWasted = 'severely_wasted';
    case Overweight = 'overweight';
    case Obese = 'obese';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Underweight => 'Underweight',
            self::SeverelyUnderweight => 'Severely underweight',
            self::Stunted => 'Stunted',
            self::SeverelyStunted => 'Severely stunted',
            self::Wasted => 'Wasted',
            self::SeverelyWasted => 'Severely wasted',
            self::Overweight => 'Overweight',
            self::Obese => 'Obese',
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
