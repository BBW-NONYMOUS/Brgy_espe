<?php

namespace App\Enums;

enum CaseOutcome: string
{
    case Ongoing = 'ongoing';
    case Recovered = 'recovered';
    case Died = 'died';

    public function label(): string
    {
        return match ($this) {
            self::Ongoing => 'Ongoing',
            self::Recovered => 'Recovered',
            self::Died => 'Died',
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
