<?php

namespace App\Enums;

enum FamilyPlanningCategory: string
{
    case ModernArtificial = 'modern_artificial';
    case ModernNatural = 'modern_natural';
    case Traditional = 'traditional';

    public function label(): string
    {
        return match ($this) {
            self::ModernArtificial => 'Modern (artificial)',
            self::ModernNatural => 'Modern natural (NFP)',
            self::Traditional => 'Traditional',
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
