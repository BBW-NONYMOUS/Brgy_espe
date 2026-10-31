<?php

namespace App\Enums;

enum OutbreakAlertStatus: string
{
    case Active = 'active';
    case Monitoring = 'monitoring';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Monitoring => 'Monitoring',
            self::Closed => 'Closed',
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
