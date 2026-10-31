<?php

namespace App\Enums;

enum CaseClassification: string
{
    case Suspected = 'suspected';
    case Probable = 'probable';
    case Confirmed = 'confirmed';
    case RuledOut = 'ruled_out';

    public function label(): string
    {
        return match ($this) {
            self::Suspected => 'Suspected',
            self::Probable => 'Probable',
            self::Confirmed => 'Confirmed',
            self::RuledOut => 'Ruled out',
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
