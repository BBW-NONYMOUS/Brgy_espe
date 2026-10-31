<?php

namespace App\Enums;

enum BackupStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Pruned = 'pruned';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Pruned => 'Removed (rotation)',
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
