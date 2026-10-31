<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Dispensed = 'dispensed';
    case Expired = 'expired';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Stock received',
            self::Out => 'Transferred out',
            self::Dispensed => 'Dispensed',
            self::Expired => 'Expired write-off',
            self::Adjustment => 'Adjustment',
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
