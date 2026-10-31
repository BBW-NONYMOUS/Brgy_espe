<?php

namespace App\Http\Requests\Reports;

use App\Enums\PermissionName;
use App\Services\ModuleReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    public const FORMATS = ['pdf', 'xlsx', 'csv'];

    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ReportsView->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from', 'before_or_equal:today'],
            'purok_id' => ['nullable', 'integer', Rule::exists('puroks', 'id')],
            'format' => ['nullable', Rule::in(self::FORMATS)],
            // "summary" = the population/health summary; the others are module reports.
            'report' => ['nullable', Rule::in(['summary', ...array_keys(ModuleReportService::REPORTS)])],
        ];
    }

    public function report(): string
    {
        return $this->validated('report') ?? 'summary';
    }

    /**
     * @return array{date_from?: string|null, date_to?: string|null, purok_id?: int|string|null}
     */
    public function filters(): array
    {
        return [
            'date_from' => $this->validated('date_from'),
            'date_to' => $this->validated('date_to'),
            'purok_id' => $this->validated('purok_id'),
        ];
    }
}
