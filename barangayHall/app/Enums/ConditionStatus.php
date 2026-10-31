<?php

namespace App\Enums;

enum ConditionStatus: string
{
    case Active = 'active';
    case Controlled = 'controlled';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Controlled => 'Controlled',
            self::Resolved => 'Resolved',
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
