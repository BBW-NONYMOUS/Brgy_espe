<?php

namespace App\Enums;

enum MedicineForm: string
{
    case Tablet = 'tablet';
    case Capsule = 'capsule';
    case Syrup = 'syrup';
    case Suspension = 'suspension';
    case Drops = 'drops';
    case Injection = 'injection';
    case Sachet = 'sachet';
    case Ointment = 'ointment';
    case Inhaler = 'inhaler';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Tablet => 'Tablet',
            self::Capsule => 'Capsule',
            self::Syrup => 'Syrup',
            self::Suspension => 'Suspension',
            self::Drops => 'Drops',
            self::Injection => 'Injection',
            self::Sachet => 'Sachet',
            self::Ointment => 'Ointment',
            self::Inhaler => 'Inhaler / nebule',
            self::Other => 'Other',
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
