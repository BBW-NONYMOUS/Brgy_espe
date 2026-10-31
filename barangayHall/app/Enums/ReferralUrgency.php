<?php

namespace App\Enums;

enum ReferralUrgency: string
{
    case Routine = 'routine';
    case Urgent = 'urgent';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::Routine => 'Routine',
            self::Urgent => 'Urgent',
            self::Emergency => 'Emergency',
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
