import { Deferred, Head, Link, router } from '@inertiajs/react';
import {
    Accessibility,
    Download,
    FileSpreadsheet,
    FileText,
    HeartPulse,
    Home,
    Printer,
    Syringe,
    UserRound,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import ReportController from '@/actions/App/Http/Controllers/ReportController';
import { AgeSexPyramid, PurokChart } from '@/components/app/charts';
import { DatePicker } from '@/components/app/date-picker';
import { EmptyState } from '@/components/app/empty-state';
import {
    ModuleReportSkeleton,
    ModuleReportView,
} from '@/components/app/reports/module-report-view';
import type { ModuleReport } from '@/components/app/reports/module-report-view';
import { PageHeader } from '@/components/app/page-header';
import { StatCard, StatCardSkeleton } from '@/components/app/stat-card';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate, formatNumber } from '@/lib/format';
import { index } from '@/routes/reports';
import { show as showResident } from '@/routes/residents';
import type { Option, ReportLists, ReportSummary } from '@/types';

type Filters = {
    /** 'summary' or a module report key (diseases, referrals, …). */
    report: string;
    date_from: string | null;
    date_to: string | null;
    purok_id: number | null;
};

type Props = {
    filters: Filters;
    options: { puroks: Option[]; reports: Option<string>[] };
    /** null when a module report is selected. */
    summary?: ReportSummary | null;
    /** null when the population & health summary is selected. */
    moduleReport?: ModuleReport | null;
    /** null for Barangay Officials (aggregates only). */
    lists?: ReportLists | null;
    canExport: boolean;
};

const ALL = '__all';

export default function ReportsIndex({
    filters,
    options,
    summary,
    moduleReport,
    lists,
    canExport,
}: Props) {
    const period = summary?.period ?? moduleReport?.period;
    const purokName = summary ? summary.purok : moduleReport?.purok;
    const [draft, setDraft] = useState<Filters>(filters);

    const apply = (next: Filters) => {
        setDraft(next);
        router.get(index.url(), clean(next), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const exportUrl = (format: 'pdf' | 'xlsx' | 'csv', inline = false) =>
        ReportController.export.url({
            query: {
                ...clean(filters),
                format,
                ...(inline ? { inline: 1 } : {}),
            },
        });

    return (
        <>
            <Head title="Reports" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Reports"
                    description={
                        period
                            ? `${moduleReport ? `${moduleReport.title} · ` : ''}${formatDate(period.from)} – ${formatDate(period.to)} · ${purokName ?? 'All puroks'}`
                            : 'Summary of the health center’s residents and services.'
                    }
                    actions={
                        canExport && (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() => window.print()}
                                >
                                    <Printer aria-hidden /> Print
                                </Button>
                                {/* Downloads are normal links, not Inertia visits. */}
                                <Button variant="outline" asChild>
                                    <a href={exportUrl('pdf')}>
                                        <FileText aria-hidden /> PDF
                                    </a>
                                </Button>
                                <Button variant="outline" asChild>
                                    <a href={exportUrl('xlsx')}>
                                        <FileSpreadsheet aria-hidden /> Excel
                                    </a>
                                </Button>
                                <Button variant="outline" asChild>
                                    <a href={exportUrl('csv')}>
                                        <Download aria-hidden /> CSV
                                    </a>
                                </Button>
                            </>
                        )
                    }
                />

                <form
                    className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr_auto] lg:items-end"
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply(draft);
                    }}
                    data-print="hide"
                    aria-label="Report filters"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="report">Report</Label>
                        <Select
                            value={draft.report}
                            onValueChange={(v) =>
                                apply({ ...draft, report: v })
                            }
                        >
                            <SelectTrigger
                                id="report"
                                className="w-full data-[size=default]:h-11"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.reports.map((r) => (
                                    <SelectItem key={r.value} value={r.value}>
                                        {r.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="date_from">From</Label>
                        <DatePicker
                            id="date_from"
                            value={draft.date_from}
                            onChange={(v) =>
                                setDraft({ ...draft, date_from: v })
                            }
                            disableFuture
                            fromYear={2015}
                            placeholder="Start of this year"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="date_to">To</Label>
                        <DatePicker
                            id="date_to"
                            value={draft.date_to}
                            onChange={(v) => setDraft({ ...draft, date_to: v })}
                            disableFuture
                            fromYear={2015}
                            placeholder="Today"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="purok">Purok</Label>
                        <Select
                            value={
                                draft.purok_id ? String(draft.purok_id) : ALL
                            }
                            onValueChange={(v) =>
                                setDraft({
                                    ...draft,
                                    purok_id: v === ALL ? null : Number(v),
                                })
                            }
                        >
                            <SelectTrigger
                                id="purok"
                                className="w-full data-[size=default]:h-11"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All puroks</SelectItem>
                                {options.puroks.map((p) => (
                                    <SelectItem
                                        key={p.value}
                                        value={String(p.value)}
                                    >
                                        {p.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" className="h-11">
                            Apply
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-11"
                            onClick={() =>
                                apply({
                                    report: draft.report,
                                    date_from: null,
                                    date_to: null,
                                    purok_id: null,
                                })
                            }
                        >
                            Reset
                        </Button>
                    </div>
                </form>

                {filters.report === 'summary' ? (
                    <Deferred data="summary" fallback={<SummarySkeleton />}>
                        {summary && <Summary summary={summary} />}
                    </Deferred>
                ) : (
                    <Deferred
                        data="moduleReport"
                        fallback={<ModuleReportSkeleton />}
                    >
                        {moduleReport && (
                            <ModuleReportView report={moduleReport} />
                        )}
                    </Deferred>
                )}

                {filters.report === 'summary' && lists !== null && (
                    <Deferred
                        data="lists"
                        fallback={<Skeleton className="h-64 w-full" />}
                    >
                        {lists && <Lists lists={lists} />}
                    </Deferred>
                )}
            </div>
        </>
    );
}

function clean(filters: Filters): Record<string, string | number> {
    return Object.fromEntries(
        Object.entries(filters).filter(
            // The summary is the default report; keep it out of the URL.
            ([k, v]) =>
                v !== null && v !== '' && !(k === 'report' && v === 'summary'),
        ),
    ) as Record<string, string | number>;
}

function Summary({ summary }: { summary: ReportSummary }) {
    const t = summary.totals;

    return (
        <>
            <section
                aria-label="Totals"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <StatCard
                    label="Residents"
                    value={t.residents}
                    icon={Users}
                    hint={`${formatNumber(t.male)} male · ${formatNumber(t.female)} female`}
                />
                <StatCard
                    label="Households"
                    value={t.households}
                    icon={Home}
                    tone="info"
                />
                <StatCard
                    label="Senior citizens (60+)"
                    value={t.seniors}
                    icon={UserRound}
                    tone="info"
                />
                <StatCard
                    label="Persons with disability"
                    value={t.pwd}
                    icon={Accessibility}
                    hint={`${formatNumber(t.four_ps)} 4Ps members`}
                />
                <StatCard
                    label="Pregnant women"
                    value={t.pregnant}
                    icon={HeartPulse}
                    tone="success"
                />
                <StatCard
                    label="Infants (under 1)"
                    value={t.infants}
                    icon={Users}
                />
                <StatCard
                    label="Children due for vaccines"
                    value={t.children_due}
                    icon={Syringe}
                    tone={t.children_due > 0 ? 'warning' : 'success'}
                    hint="0–23 months"
                />
            </section>

            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Population per purok</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <PurokChart data={summary.population} />
                        <SimpleTable
                            caption="Population per purok"
                            headers={[
                                'Purok',
                                'Households',
                                'Residents',
                                'Male',
                                'Female',
                            ]}
                            rows={summary.population.map((r) => [
                                r.purok,
                                r.households,
                                r.residents,
                                r.male,
                                r.female,
                            ])}
                        />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Age and sex distribution</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <AgeSexPyramid data={summary.age_sex} />
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Common conditions</CardTitle>
                        <CardDescription>Active or controlled</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <SimpleTable
                            caption="Common conditions"
                            headers={['Condition', 'Residents']}
                            rows={summary.conditions.map((r) => [
                                r.icd10_code
                                    ? `${r.condition} (${r.icd10_code})`
                                    : r.condition,
                                r.residents,
                            ])}
                            empty="No conditions recorded."
                        />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Health visits</CardTitle>
                        <CardDescription>
                            In the selected period
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <SimpleTable
                            caption="Visits by type"
                            headers={['Visit type', 'Visits']}
                            rows={summary.visits.map((r) => [
                                r.visit_type,
                                r.visits,
                            ])}
                            empty="No visits in this period."
                        />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Immunizations given</CardTitle>
                        <CardDescription>
                            In the selected period
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <SimpleTable
                            caption="Doses given by vaccine"
                            headers={['Vaccine', 'Doses']}
                            rows={summary.immunizations.map((r) => [
                                r.vaccine,
                                r.doses,
                            ])}
                            empty="No doses given in this period."
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Lists({ lists }: { lists: ReportLists }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Follow-up lists</CardTitle>
                <CardDescription>
                    Named lists for health workers. Not shown to barangay
                    officials.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Tabs defaultValue="children">
                    <TabsList data-print="hide">
                        <TabsTrigger value="children">
                            Children due ({lists.children_due.length})
                        </TabsTrigger>
                        <TabsTrigger value="pregnant">
                            Pregnant women ({lists.pregnant.length})
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="children" className="pt-4">
                        {lists.children_due.length === 0 ? (
                            <EmptyState
                                icon={Syringe}
                                title="No children are due for immunization"
                            />
                        ) : (
                            <Table>
                                <caption className="sr-only">
                                    Children due for immunization
                                </caption>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Child</TableHead>
                                        <TableHead>Age</TableHead>
                                        <TableHead>Purok</TableHead>
                                        <TableHead>Doses due</TableHead>
                                        <TableHead>Since</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {lists.children_due.map((c) => (
                                        <TableRow key={c.resident_id}>
                                            <TableCell>
                                                <Link
                                                    href={showResident(
                                                        c.resident_id,
                                                    )}
                                                    className="font-bold underline-offset-4 hover:underline"
                                                >
                                                    {c.name}
                                                </Link>
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {c.resident_code}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {c.age_months} mo
                                            </TableCell>
                                            <TableCell>
                                                {c.purok} · {c.household_no}
                                            </TableCell>
                                            <TableCell className="whitespace-normal">
                                                {c.pending.join(', ')}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {formatDate(c.earliest_due)}{' '}
                                                {c.overdue && (
                                                    <StatusBadge tone="danger">
                                                        Overdue
                                                    </StatusBadge>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </TabsContent>
                    <TabsContent value="pregnant" className="pt-4">
                        {lists.pregnant.length === 0 ? (
                            <EmptyState
                                icon={HeartPulse}
                                title="No ongoing pregnancies recorded"
                            />
                        ) : (
                            <Table>
                                <caption className="sr-only">
                                    Pregnant women
                                </caption>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Age</TableHead>
                                        <TableHead>Purok</TableHead>
                                        <TableHead>Expected delivery</TableHead>
                                        <TableHead>AOG</TableHead>
                                        <TableHead>G/P</TableHead>
                                        <TableHead>Risk</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {lists.pregnant.map((p) => (
                                        <TableRow key={p.resident_id}>
                                            <TableCell>
                                                <Link
                                                    href={showResident(
                                                        p.resident_id,
                                                    )}
                                                    className="font-bold underline-offset-4 hover:underline"
                                                >
                                                    {p.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell>{p.age}</TableCell>
                                            <TableCell>{p.purok}</TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {formatDate(p.edd)}
                                            </TableCell>
                                            <TableCell>
                                                {p.aog_weeks !== null
                                                    ? `${p.aog_weeks} wks`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                G{p.gravida}P{p.para}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    tone={
                                                        p.risk_level.startsWith(
                                                            'High',
                                                        )
                                                            ? 'danger'
                                                            : 'success'
                                                    }
                                                >
                                                    {p.risk_level}
                                                </StatusBadge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </TabsContent>
                </Tabs>
            </CardContent>
        </Card>
    );
}

function SimpleTable({
    caption,
    headers,
    rows,
    empty,
}: {
    caption: string;
    headers: string[];
    rows: ReactNode[][];
    empty?: string;
}) {
    if (rows.length === 0) {
        return <p className="text-muted-foreground">{empty}</p>;
    }

    return (
        <Table>
            <caption className="sr-only">{caption}</caption>
            <TableHeader>
                <TableRow>
                    {headers.map((h, i) => (
                        <TableHead
                            key={h}
                            className={i > 0 ? 'text-right' : undefined}
                        >
                            {h}
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row, r) => (
                    <TableRow key={r}>
                        {row.map((cell, i) => (
                            <TableCell
                                key={i}
                                className={
                                    i > 0
                                        ? 'text-right tabular-nums'
                                        : 'whitespace-normal'
                                }
                            >
                                {typeof cell === 'number'
                                    ? formatNumber(cell)
                                    : cell}
                            </TableCell>
                        ))}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function SummarySkeleton() {
    return (
        <div className="space-y-6" aria-hidden>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {Array.from({ length: 4 }, (_, i) => (
                    <StatCardSkeleton key={i} />
                ))}
            </div>
            <Skeleton className="h-80 w-full" />
        </div>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
};
