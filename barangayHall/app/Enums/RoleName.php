<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Bhw = 'bhw';
    case Midwife = 'midwife';
    case Official = 'official';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Bhw => 'Barangay Health Worker',
            self::Midwife => 'Midwife / Health Staff',
            self::Official => 'Barangay Official',
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
