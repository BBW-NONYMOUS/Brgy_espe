<?php

namespace App\Http\Resources\Concerns;

use BackedEnum;
use Carbon\CarbonInterface;

trait FormatsValues
{
    /**
     * @return array{value: string|int, label: string}|null
     */
    protected function enum(?BackedEnum $enum): ?array
    {
        if ($enum === null) {
            return null;
        }

        return [
            'value' => $enum->value,
            'label' => method_exists($enum, 'label') ? $enum->label() : (string) $enum->value,
        ];
    }

    protected function date(?CarbonInterface $date): ?string
    {
        return $date?->toDateString();
    }

    protected function dateTime(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }

    /**
     * "02-051234567-1" → "••-•••••••67-1"
     */
    protected function maskPhilHealth(?string $number): ?string
    {
        if ($number === null || $number === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $number) ?? '';

        return '••-•••••••'.substr($digits, -3, 2).'-'.substr($digits, -1);
    }
}
