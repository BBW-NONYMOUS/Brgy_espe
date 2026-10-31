import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ClipboardCheck,
    MoreHorizontal,
    Plus,
    Siren,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import DiseaseCaseController from '@/actions/App/Http/Controllers/Surveillance/DiseaseCaseController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table';
import {
    ClassifyCaseDialog,
    ReportCaseSheet,
} from '@/components/app/diseases/case-forms';
import { DiseaseTrendChart } from '@/components/app/diseases/disease-trend-chart';
import { EmptyState } from '@/components/app/empty-state';
import { LazyHouseholdMap } from '@/components/app/lazy';
import { MapLegend } from '@/components/app/map-legend';
import { OutbreakBanner } from '@/components/app/outbreak-banner';
import { PageHeader } from '@/components/app/page-header';
import { SearchFilters } from '@/components/app/search-filters';
import { StatusBadge } from '@/components/app/status-badge';
import type { BadgeTone } from '@/components/app/status-badge';
import { DatePicker } from '@/components/app/date-picker';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatDate } from '@/lib/format';
import { index } from '@/routes/disease-cases';
import { index as outbreakAlerts } from '@/routes/outbreak-alerts';
import { show as residentShow } from '@/routes/residents';
import type { DiseaseCase, PurokSummary } from '@/types';
import type { DiseaseCasesPageProps } from '@/types/pages/health';

const DEFAULTS: Partial<DiseaseCasesPageProps['filters']> = {
    search: '',
    disease_id: null,
    purok_id: null,
    classification: null,
    outcome: null,
    date_from: null,
    date_to: null,
};

const classificationTone: Record<string, BadgeTone> = {
    suspected: 'warning',
    probable: 'caution',
    confirmed: 'danger',
    ruled_out: 'neutral',
};

const outcomeTone: Record<string, BadgeTone> = {
    ongoing: 'info',
    recovered: 'success',
    died: 'neutral',
};

export default function DiseasesIndex({
    cases,
    filters,
    openAlerts,
    options,
    residentOptions = [],
}: DiseaseCasesPageProps) {
    const { can } = usePage().props;
    const { query, update, setPage, reset, processing } = useTableQuery(
        filters,
        { only: ['cases', 'filters'] },
    );
    const [reporting, setReporting] = useState(false);
    const [classifying, setClassifying] = useState<DiseaseCase | null>(null);
    const [removing, setRemoving] = useState<DiseaseCase | null>(null);
    const [removeProcessing, setRemoveProcessing] = useState(false);

    const hasActiveFilters = Boolean(
        query.search ||
        query.disease_id ||
        query.purok_id ||
        query.classification ||
        query.outcome ||
        query.date_from ||
        query.date_to,
    );

    const columns = useMemo<ColumnDef<DiseaseCase, unknown>[]>(
        () => [
            {
                id: 'onset',
                header: 'Onset',
                meta: { cellClassName: 'whitespace-nowrap' },
                cell: ({ row }) => (
                    <div>
                        <div>{formatDate(row.original.onset_date)}</div>
                        <div className="text-xs text-muted-foreground">
                            Reported {formatDate(row.original.reported_date)}
                        </div>
                    </div>
                ),
            },
            {
                id: 'resident',
                header: 'Resident',
                cell: ({ row }) =>
                    row.original.resident && (
                        <div>
                            <Link
                                href={residentShow(row.original.resident.id)}
                                className="font-bold underline-offset-4 hover:underline"
                            >
                                {row.original.resident.full_name}
                            </Link>
                            <div className="text-xs text-muted-foreground">
                                {row.original.resident.age} yrs ·{' '}
                                {row.original.purok?.name}
                            </div>
                        </div>
                    ),
            },
            {
                id: 'disease',
                header: 'Disease',
                cell: ({ row }) => (
                    <div>
                        <div className="font-medium">
                            {row.original.disease?.name}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {row.original.disease?.pidsr_category.label}
                        </div>
                    </div>
                ),
            },
            {
                id: 'classification',
                header: 'Classification',
                cell: ({ row }) => (
                    <StatusBadge
                        tone={
                            classificationTone[
                                row.original.classification.value
                            ]
                        }
                    >
                        {row.original.classification.label}
                    </StatusBadge>
                ),
            },
            {
                id: 'outcome',
                header: 'Outcome',
                meta: { hideOnMobile: true },
                cell: ({ row }) => (
                    <div className="space-y-0.5">
                        <StatusBadge
                            tone={outcomeTone[row.original.outcome.value]}
                        >
                            {row.original.outcome.label}
                        </StatusBadge>
                        {row.original.outcome_date && (
                            <div className="text-xs text-muted-foreground">
                                {formatDate(row.original.outcome_date)}
                            </div>
                        )}
                    </div>
                ),
            },
            {
                id: 'reported_by',
                header: 'Reported by',
                meta: { hideOnMobile: true },
                cell: ({ row }) => row.original.reported_by,
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                meta: { cellClassName: 'w-12 text-right' },
                cell: ({ row }) =>
                    can.classifyDiseases && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Actions for this ${row.original.disease?.name} case`}
                                >
                                    <MoreHorizontal aria-hidden />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    onSelect={() =>
                                        setClassifying(row.original)
                                    }
                                >
                                    <ClipboardCheck aria-hidden /> Classify /
                                    outcome
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setRemoving(row.original)}
                                >
                                    <Trash2 aria-hidden /> Remove (entered in
                                    error)
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ),
            },
        ],
        [can.classifyDiseases],
    );

    return (
        <>
            <Head title="Disease surveillance" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Disease surveillance"
                    description="Reported cases of notifiable diseases (DOH PIDSR). Outbreak thresholds are checked every time a case is saved."
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={outbreakAlerts()}>
                                    <Siren aria-hidden /> Outbreak alerts
                                </Link>
                            </Button>
                            {can.recordDiseases && (
                                <Button onClick={() => setReporting(true)}>
                                    <Plus aria-hidden /> Report a case
                                </Button>
                            )}
                        </>
                    }
                />

                <OutbreakBanner alerts={openAlerts} />

                <div className="grid gap-5 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Cases per week</CardTitle>
                            <CardDescription>
                                Suspected, probable and confirmed cases; top 5
                                diseases of the last 26 weeks.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Deferred
                                data="trend"
                                fallback={<Skeleton className="h-64 w-full" />}
                            >
                                <TrendCard />
                            </Deferred>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Cases per purok</CardTitle>
                            <CardDescription>
                                Last 30 days. Circles are drawn at the purok
                                centre, never at a house.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Deferred
                                data="perPurok"
                                fallback={<Skeleton className="h-64 w-full" />}
                            >
                                <PurokCard />
                            </Deferred>
                        </CardContent>
                    </Card>
                </div>

                <SearchFilters
                    search={{
                        value: query.search,
                        onChange: (value) =>
                            update({ search: value }, { debounced: true }),
                        placeholder: 'Resident name or ID',
                        label: 'Search cases by resident',
                    }}
                    filters={[
                        {
                            key: 'disease',
                            label: 'Disease',
                            allLabel: 'All diseases',
                            options: options.diseases,
                            value: query.disease_id,
                            onChange: (v) =>
                                update({ disease_id: v ? Number(v) : null }),
                        },
                        {
                            key: 'purok',
                            label: 'Purok',
                            allLabel: 'All puroks',
                            options: options.puroks,
                            value: query.purok_id,
                            onChange: (v) =>
                                update({ purok_id: v ? Number(v) : null }),
                        },
                        {
                            key: 'classification',
                            label: 'Classification',
                            allLabel: 'Any classification',
                            options: options.classifications,
                            value: query.classification,
                            onChange: (v) => update({ classification: v }),
                        },
                        {
                            key: 'outcome',
                            label: 'Outcome',
                            allLabel: 'Any outcome',
                            options: options.outcomes,
                            value: query.outcome,
                            onChange: (v) => update({ outcome: v }),
                        },
                    ]}
                    hasActiveFilters={hasActiveFilters}
                    onReset={() => reset(DEFAULTS)}
                >
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="date_from">Onset from</Label>
                            <DatePicker
                                id="date_from"
                                value={query.date_from}
                                onChange={(v) => update({ date_from: v })}
                                fromYear={2000}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="date_to">Onset to</Label>
                            <DatePicker
                                id="date_to"
                                value={query.date_to}
                                onChange={(v) => update({ date_to: v })}
                                fromYear={2000}
                            />
                        </div>
                    </div>
                </SearchFilters>

                <DataTable
                    columns={columns}
                    data={cases}
                    onPageChange={setPage}
                    itemLabel="cases"
                    caption="Disease cases"
                    processing={processing}
                    empty={
                        <EmptyState
                            icon={Siren}
                            title={
                                hasActiveFilters
                                    ? 'No cases match these filters'
                                    : 'No disease cases reported'
                            }
                            description={
                                hasActiveFilters
                                    ? 'Try a wider date range or clear the filters.'
                                    : 'Cases reported by BHWs and the midwife will appear here.'
                            }
                            action={
                                hasActiveFilters ? (
                                    <Button
                                        variant="outline"
                                        onClick={() => reset(DEFAULTS)}
                                    >
                                        Clear filters
                                    </Button>
                                ) : (
                                    can.recordDiseases && (
                                        <Button
                                            onClick={() => setReporting(true)}
                                        >
                                            <Plus aria-hidden /> Report a case
                                        </Button>
                                    )
                                )
                            }
                        />
                    }
                />
            </div>

            {can.recordDiseases && (
                <ReportCaseSheet
                    open={reporting}
                    onOpenChange={setReporting}
                    diseases={options.diseases}
                    classifications={options.classifications}
                    residentOptions={residentOptions}
                />
            )}

            {classifying && (
                <ClassifyCaseDialog
                    key={classifying.id}
                    open
                    onOpenChange={(open) => !open && setClassifying(null)}
                    diseaseCase={classifying}
                    classifications={options.classifications}
                    outcomes={options.outcomes}
                />
            )}

            {removing && (
                <ConfirmDialog
                    open
                    onOpenChange={(open) => !open && setRemoving(null)}
                    title="Remove this case?"
                    description="Only for a case entered by mistake. It no longer counts in reports or outbreak checks; the audit log keeps a record."
                    confirmLabel="Remove case"
                    destructive
                    processing={removeProcessing}
                    onConfirm={() =>
                        router.delete(
                            DiseaseCaseController.destroy.url(removing.id),
                            {
                                preserveScroll: true,
                                onStart: () => setRemoveProcessing(true),
                                onFinish: () => {
                                    setRemoveProcessing(false);
                                    setRemoving(null);
                                },
                            },
                        )
                    }
                />
            )}
        </>
    );
}

function TrendCard() {
    const { trend } = usePage<DiseaseCasesPageProps>().props;

    if (!trend || trend.series.length === 0) {
        return (
            <EmptyState
                icon={Siren}
                title="No cases in the last 26 weeks"
                className="border-0"
            />
        );
    }

    return <DiseaseTrendChart trend={trend} />;
}

function PurokCard() {
    const { perPurok, map } = usePage<DiseaseCasesPageProps>().props;
    const puroks: PurokSummary[] = perPurok ?? [];

    return (
        <div className="flex flex-col gap-3">
            <LazyHouseholdMap
                config={map}
                puroks={puroks}
                className="h-64"
                label="Disease cases per purok, last 30 days"
            />
            <MapLegend mode="puroks" />
            <Table>
                <caption className="sr-only">
                    Disease cases per purok, last 30 days
                </caption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Purok</TableHead>
                        <TableHead className="text-right">Cases</TableHead>
                        <TableHead>Diseases</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {puroks.map((p) => (
                        <TableRow key={p.id}>
                            <TableCell className="font-medium">
                                {p.name}
                            </TableCell>
                            <TableCell className="text-right font-bold">
                                {p.cases}
                            </TableCell>
                            <TableCell className="text-sm text-muted-foreground">
                                {Object.entries(p.cases_by_disease)
                                    .map(([d, n]) => `${d} ${n}`)
                                    .join(' · ') || '—'}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

DiseasesIndex.layout = {
    breadcrumbs: [{ title: 'Diseases', href: index() }],
};
