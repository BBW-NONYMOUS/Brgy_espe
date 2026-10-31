<?php

namespace App\Exports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class ModuleReportSheet implements FromArray, ShouldAutoSize, WithTitle
{
    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function __construct(
        private readonly string $title,
        private readonly array $rows,
    ) {}

    /**
     * Excel sheet names: max 31 characters, no []:*?/\.
     */
    public function title(): string
    {
        return Str::limit(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $this->title), 31, '');
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }
}
