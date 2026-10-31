<?php

namespace App\Enums;

enum DeliveryPlace: string
{
    case HealthFacility = 'health_facility';
    case BirthingHome = 'birthing_home';
    case Home = 'home';
    case InTransit = 'in_transit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::HealthFacility => 'Health facility',
            self::BirthingHome => 'Birthing home / lying-in',
            self::Home => 'Home',
            self::InTransit => 'On the way to a facility',
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
