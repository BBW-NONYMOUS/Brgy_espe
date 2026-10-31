<?php

namespace App\Enums;

enum ConsentType: string
{
    case HealthProfiling = 'health_profiling';
    case Geotagging = 'geotagging';
    case FamilyPlanning = 'family_planning';
    case SmsReminders = 'sms_reminders';

    public function label(): string
    {
        return match ($this) {
            self::HealthProfiling => 'Health profiling and services',
            self::Geotagging => 'Household geo-tagging',
            self::FamilyPlanning => 'Family planning information',
            self::SmsReminders => 'SMS reminders',
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
