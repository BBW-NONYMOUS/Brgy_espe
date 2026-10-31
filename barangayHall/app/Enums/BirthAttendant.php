<?php

namespace App\Enums;

enum BirthAttendant: string
{
    case Doctor = 'doctor';
    case Nurse = 'nurse';
    case Midwife = 'midwife';
    case Hilot = 'hilot';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Doctor => 'Doctor',
            self::Nurse => 'Nurse',
            self::Midwife => 'Midwife',
            self::Hilot => 'Hilot / traditional birth attendant',
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
