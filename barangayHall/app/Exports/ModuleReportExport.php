<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Any ModuleReportService report. XLSX: an "Overview" sheet (title, period,
 * totals) plus one sheet per table. CSV can only hold one sheet, so it gets
 * everything stacked on the first.
 */
class ModuleReportExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $report  ModuleReportService::build()
     */
    public function __construct(
        private readonly array $report,
        private readonly bool $multipleSheets = true,
    ) {}

    /**
     * @return list<ModuleReportSheet>
     */
    public function sheets(): array
    {
        if (! $this->multipleSheets) {
            $rows = $this->header();

            foreach ($this->report['tables'] as $table) {
                $rows = [...$rows, [], [mb_strtoupper($table['title'])], ...$this->tableRows($table)];
            }

            return [new ModuleReportSheet($this->report['title'], [...$rows, [], [self::FOOTER]])];
        }

        return [
            new ModuleReportSheet('Overview', [...$this->header(), [], [self::FOOTER]]),
            ...array_values(array_map(fn (array $table) => new ModuleReportSheet($table['title'], $this->tableRows($table)), $this->report['tables'])),
        ];
    }

    private const FOOTER = 'CONFIDENTIAL — contains health information protected under RA 10173 (Data Privacy Act of 2012).';

    /**
     * @return array<int, array<int, mixed>>
     */
    private function header(): array
    {
        $r = $this->report;
        $rows = [
            ["{$r['title']} report"],
            ['Barangay', config('barangay.barangay').', '.config('barangay.municipality').', '.config('barangay.province')],
            ['Period', $r['period']['from'].' to '.$r['period']['to']],
            ['Purok', $r['purok'] ?? 'All puroks'],
            ['Generated', now()->format('Y-m-d H:i')],
        ];

        if (! empty($r['scope_note'])) {
            $rows[] = ['Note', $r['scope_note']];
        }

        $rows = [...$rows, [], ['TOTALS']];

        foreach ($r['totals'] as $total) {
            $rows[] = [$total['label'], $total['value'], $total['hint'] ?? null];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $table
     * @return array<int, array<int, mixed>>
     */
    private function tableRows(array $table): array
    {
        $rows = [array_column($table['columns'], 'label')];

        foreach ($table['rows'] as $row) {
            $rows[] = array_map(fn (array $column) => $row[$column['key']] ?? null, $table['columns']);
        }

        if ($table['rows'] === []) {
            $rows[] = [$table['empty']];
        }

        return $rows;
    }
}
