<?php

namespace App\Enums;

enum RiskLevel: string
{
    case Low = 'low';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low risk',
            self::High => 'High risk',
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
