<?php

namespace App\Enums;

enum DeliveryMode: string
{
    case Normal = 'normal';
    case Cesarean = 'cesarean';
    case Assisted = 'assisted';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal spontaneous delivery',
            self::Cesarean => 'Caesarean section',
            self::Assisted => 'Assisted (forceps / vacuum)',
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
