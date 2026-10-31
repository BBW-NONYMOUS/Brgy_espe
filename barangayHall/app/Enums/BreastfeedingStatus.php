<?php

namespace App\Enums;

enum BreastfeedingStatus: string
{
    case Exclusive = 'exclusive';
    case Mixed = 'mixed';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Exclusive => 'Exclusive breastfeeding',
            self::Mixed => 'Mixed feeding',
            self::None => 'Not breastfeeding',
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
