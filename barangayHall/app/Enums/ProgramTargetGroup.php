<?php

namespace App\Enums;

enum ProgramTargetGroup: string
{
    case Everyone = 'everyone';
    case Infants = 'infants_0_11m';
    case UnderFive = 'children_0_59m';
    case SchoolAge = 'school_age';
    case Adolescents = 'adolescents';
    case ReproductiveAgeWomen = 'women_15_49';
    case Pregnant = 'pregnant';
    case Seniors = 'seniors';
    case Pwd = 'pwd';
    case Households = 'households';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'Everyone',
            self::Infants => 'Infants 0-11 months',
            self::UnderFive => 'Children 0-59 months',
            self::SchoolAge => 'School-age children (5-12)',
            self::Adolescents => 'Adolescents (10-19)',
            self::ReproductiveAgeWomen => 'Women 15-49',
            self::Pregnant => 'Pregnant women',
            self::Seniors => 'Senior citizens',
            self::Pwd => 'Persons with disability',
            self::Households => 'Household heads',
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
