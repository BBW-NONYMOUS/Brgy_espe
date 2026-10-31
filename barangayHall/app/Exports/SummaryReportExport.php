<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One sheet with every report section stacked vertically, so the same
 * export works for XLSX and CSV (CSV has no multiple sheets).
 */
class SummaryReportExport implements FromArray, ShouldAutoSize, WithTitle
{
    /**
     * @param  array<string, mixed>  $summary  ReportService::summary()
     * @param  array<string, mixed>|null  $lists  ReportService::lists(), null for aggregate-only users
     */
    public function __construct(
        private readonly array $summary,
        private readonly ?array $lists = null,
    ) {}

    public function title(): string
    {
        return 'Health Summary';
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $s = $this->summary;
        $rows = [
            ['Barangay Health Resident Profiling System — Summary Report'],
            ['Barangay', config('barangay.barangay').', '.config('barangay.municipality').', '.config('barangay.province')],
            ['Period', $s['period']['from'].' to '.$s['period']['to']],
            ['Purok', $s['purok'] ?? 'All puroks'],
            ['Generated', now()->format('Y-m-d H:i')],
            [],
            ['TOTALS'],
        ];

        $labels = [
            'residents' => 'Residents', 'households' => 'Households', 'male' => 'Male', 'female' => 'Female',
            'seniors' => 'Senior citizens (60+)', 'pwd' => 'Persons with disability', 'four_ps' => '4Ps members',
            'infants' => 'Infants (under 1)', 'pregnant' => 'Pregnant women', 'children_due' => 'Children due for immunization',
        ];

        foreach ($labels as $key => $label) {
            $rows[] = [$label, $s['totals'][$key] ?? 0];
        }

        $rows = [...$rows, [], ['POPULATION PER PUROK'], ['Purok', 'Households', 'Residents', 'Male', 'Female']];
        foreach ($s['population'] as $r) {
            $rows[] = [$r['purok'], $r['households'], $r['residents'], $r['male'], $r['female']];
        }

        $rows = [...$rows, [], ['AGE AND SEX DISTRIBUTION'], ['Age group', 'Male', 'Female', 'Total']];
        foreach ($s['age_sex'] as $r) {
            $rows[] = [$r['age_group'], $r['male'], $r['female'], $r['total']];
        }

        $rows = [...$rows, [], ['COMMON CONDITIONS (active / controlled)'], ['Condition', 'ICD-10', 'Residents']];
        foreach ($s['conditions'] as $r) {
            $rows[] = [$r['condition'], $r['icd10_code'], $r['residents']];
        }

        $rows = [...$rows, [], ['HEALTH VISITS IN PERIOD'], ['Visit type', 'Visits']];
        foreach ($s['visits'] as $r) {
            $rows[] = [$r['visit_type'], $r['visits']];
        }

        $rows = [...$rows, [], ['IMMUNIZATIONS GIVEN IN PERIOD'], ['Vaccine', 'Doses']];
        foreach ($s['immunizations'] as $r) {
            $rows[] = [$r['vaccine'], $r['doses']];
        }

        if ($this->lists !== null) {
            $rows = [...$rows, [], ['CHILDREN DUE FOR IMMUNIZATION'], ['Resident code', 'Name', 'Age (months)', 'Purok', 'Household', 'Doses due', 'Earliest due', 'Overdue']];
            foreach ($this->lists['children_due'] as $r) {
                $rows[] = [$r['resident_code'], $r['name'], $r['age_months'], $r['purok'], $r['household_no'], implode(', ', $r['pending']), $r['earliest_due'], $r['overdue'] ? 'Yes' : 'No'];
            }

            $rows = [...$rows, [], ['PREGNANT WOMEN'], ['Resident code', 'Name', 'Age', 'Purok', 'EDD', 'AOG (weeks)', 'G', 'P', 'Risk']];
            foreach ($this->lists['pregnant'] as $r) {
                $rows[] = [$r['resident_code'], $r['name'], $r['age'], $r['purok'], $r['edd'], $r['aog_weeks'], $r['gravida'], $r['para'], $r['risk_level']];
            }
        }

        $rows[] = [];
        $rows[] = ['CONFIDENTIAL — contains health information protected under RA 10173 (Data Privacy Act of 2012).'];

        return $rows;
    }
}
