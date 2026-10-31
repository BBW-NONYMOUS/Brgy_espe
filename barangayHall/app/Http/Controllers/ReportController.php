<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Exports\ModuleReportExport;
use App\Exports\SummaryReportExport;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Services\AuditLogger;
use App\Services\LookupOptions;
use App\Services\ModuleReportService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly LookupOptions $lookups,
        private readonly ModuleReportService $modules,
    ) {}

    public function index(ReportFilterRequest $request): Response
    {
        $filters = $request->filters();
        $canSeeNames = $this->canSeeNames($request);
        $report = $request->report();
        $isSummary = $report === 'summary';

        return Inertia::render('reports/index', [
            'filters' => [
                'report' => $report,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'purok_id' => isset($filters['purok_id']) ? (int) $filters['purok_id'] : null,
            ],
            'options' => [
                'puroks' => $this->lookups->puroks(),
                'reports' => [
                    ['value' => 'summary', 'label' => 'Population & health summary'],
                    ...array_map(fn (string $key, string $label) => ['value' => $key, 'label' => $label], array_keys(ModuleReportService::REPORTS), ModuleReportService::REPORTS),
                ],
            ],
            'summary' => $isSummary ? Inertia::defer(fn () => $this->reports->summary($filters), 'summary') : null,
            // Named lists: health workers only. Barangay Officials never receive this prop.
            'lists' => $isSummary && $canSeeNames ? Inertia::defer(fn () => $this->reports->lists($filters), 'lists') : null,
            // Module reports: name tables are only built for roles allowed to see them.
            'moduleReport' => $isSummary ? null : Inertia::defer(fn () => $this->modules->build($report, $filters, $request->user()), 'summary'),
            'canExport' => $request->user()?->can(PermissionName::ReportsExport->value) ?? false,
        ]);
    }

    /**
     * PDF / Excel / CSV download — a normal link, not an Inertia visit.
     */
    public function export(ReportFilterRequest $request, AuditLogger $audit): HttpResponse
    {
        Gate::authorize(PermissionName::ReportsExport->value);

        $format = $request->validated('format') ?? 'pdf';
        $filters = $request->filters();

        if ($request->report() !== 'summary') {
            return $this->exportModule($request, $audit, $format, $filters);
        }

        $summary = $this->reports->summary($filters);
        $lists = $this->canSeeNames($request) ? $this->reports->lists($filters) : null;
        $fileName = sprintf('health-summary-%s_%s', $summary['period']['from'], $summary['period']['to']);

        $audit->log(
            AuditAction::Exported,
            description: sprintf(
                'Exported summary report (%s) %s to %s%s%s',
                strtoupper($format),
                $summary['period']['from'],
                $summary['period']['to'],
                $summary['purok'] ? ', '.$summary['purok'] : '',
                $lists ? ', with name lists' : ', aggregates only',
            ),
        );

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.summary', [
                'summary' => $summary,
                'lists' => $lists,
                'generatedBy' => $request->user()?->name,
                'healthCenter' => config('barangay'),
            ])->setPaper('a4');

            return $request->boolean('inline')
                ? $pdf->stream("{$fileName}.pdf")
                : $pdf->download("{$fileName}.pdf");
        }

        return Excel::download(
            new SummaryReportExport($summary, $lists),
            "{$fileName}.{$format}",
            $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX,
        );
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, purok_id?: int|string|null}  $filters
     */
    private function exportModule(ReportFilterRequest $request, AuditLogger $audit, string $format, array $filters): HttpResponse
    {
        $report = $this->modules->build($request->report(), $filters, $request->user());
        $named = in_array(true, array_column($report['tables'], 'named'), true);
        $fileName = sprintf('%s-%s_%s', str_replace('_', '-', $report['key']), $report['period']['from'], $report['period']['to']);

        $audit->log(
            AuditAction::Exported,
            description: sprintf(
                'Exported %s report (%s) %s to %s%s%s',
                strtolower($report['title']),
                strtoupper($format),
                $report['period']['from'],
                $report['period']['to'],
                $report['purok'] ? ', '.$report['purok'] : '',
                $named ? ', with name lists' : ', aggregates only',
            ),
        );

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.module', [
                'report' => $report,
                'generatedBy' => $request->user()?->name,
                'healthCenter' => config('barangay'),
            ])->setPaper('a4');

            return $request->boolean('inline') ? $pdf->stream("{$fileName}.pdf") : $pdf->download("{$fileName}.pdf");
        }

        return Excel::download(
            new ModuleReportExport($report, multipleSheets: $format === 'xlsx'),
            "{$fileName}.{$format}",
            $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX,
        );
    }

    private function canSeeNames(Request $request): bool
    {
        return $request->user()?->can(PermissionName::ResidentsView->value) ?? false;
    }
}
