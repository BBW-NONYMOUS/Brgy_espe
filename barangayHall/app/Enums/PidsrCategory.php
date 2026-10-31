<?php

namespace App\Enums;

enum PidsrCategory: string
{
    case Category1 = 'category_1';
    case Category2 = 'category_2';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Category1 => 'Category I (immediately notifiable)',
            self::Category2 => 'Category II (weekly)',
            self::Other => 'Not notifiable',
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
