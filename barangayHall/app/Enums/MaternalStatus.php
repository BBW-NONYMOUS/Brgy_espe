<?php

namespace App\Enums;

enum MaternalStatus: string
{
    case Ongoing = 'ongoing';
    case Delivered = 'delivered';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Ongoing => 'Ongoing pregnancy',
            self::Delivered => 'Delivered',
            self::Terminated => 'Terminated / Loss',
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
