import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { BarChart3, Lock } from 'lucide-react';
import { StatCard, StatCardSkeleton } from '@/components/app/stat-card';
import { StatusBadge } from '@/components/app/status-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatNumber } from '@/lib/format';

/** ModuleReportService::build() — every module report has this shape. */
export type ModuleReport = {
    key: string;
    title: string;
    period: { from: string; to: string };
    purok: string | null;
    scope_note?: string | null;
    totals: { label: string; value: number | string; hint?: string | null }[];
    tables: ReportTable[];
};

type ReportTable = {
    key: string;
    title: string;
    description: string | null;
    columns: { key: string; label: string; numeric: boolean }[];
    rows: Record<string, string | number | null>[];
    /** Lists residents by name (only sent to roles allowed to see it). */
    named: boolean;
    chart: { label: string; values: string[] } | null;
    empty: string;
};

/**
 * Renders any module report: headline numbers, then each table (with a bar
 * chart when the server marks one). Tables with names carry a "Contains
 * names" badge as a reminder before printing or sharing.
 */
export function ModuleReportView({ report }: { report: ModuleReport }) {
    const spans = fullWidthTables(report.tables);

    return (
        <div className="flex flex-col gap-6">
            {report.scope_note && (
                <p className="text-sm text-muted-foreground">
                    {report.scope_note}
                </p>
            )}

            <div
                className={`grid gap-4 sm:grid-cols-2 ${TOTALS_COLUMNS[report.totals.length] ?? 'xl:grid-cols-5'}`}
            >
                {report.totals.map((total) => (
                    <StatCard
                        key={total.label}
                        label={total.label}
                        value={
                            typeof total.value === 'number' ? total.value : null
                        }
                        hint={
                            typeof total.value === 'number'
                                ? (total.hint ?? undefined)
                                : (total.hint ?? 'Not enough data yet')
                        }
                        icon={BarChart3}
                    />
                ))}
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                {report.tables.map((table, i) => (
                    <Card
                        key={table.key}
                        className={
                            spans[i] ? 'min-w-0 xl:col-span-2' : 'min-w-0'
                        }
                    >
                        <CardHeader>
                            <CardTitle className="flex flex-wrap items-center gap-2">
                                {table.title}
                                {table.named && (
                                    <StatusBadge
                                        tone="warning"
                                        icon={<Lock aria-hidden />}
                                    >
                                        Contains names
                                    </StatusBadge>
                                )}
                            </CardTitle>
                            {table.description && (
                                <CardDescription>
                                    {table.description}
                                </CardDescription>
                            )}
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {table.chart && table.rows.length > 0 && (
                                <TableChart table={table} />
                            )}
                            <ReportDataTable table={table} />
                        </CardContent>
                    </Card>
                ))}
            </div>
        </div>
    );
}

const TOTALS_COLUMNS: Record<number, string> = {
    3: 'xl:grid-cols-3',
    4: 'xl:grid-cols-4',
};

/**
 * Long or wide tables take the full row; shorter ones sit in pairs. A short
 * table with no partner also takes the full row instead of leaving a gap.
 */
function fullWidthTables(tables: ReportTable[]): boolean[] {
    const wide = tables.map((t) => t.rows.length > 8 || t.columns.length > 4);
    const spans = [...wide];
    let open: number | null = null;

    wide.forEach((isWide, i) => {
        if (isWide) {
            if (open !== null) {
                spans[open] = true;
            }

            open = null;
        } else {
            open = open === null ? i : null;
        }
    });

    if (open !== null) {
        spans[open] = true;
    }

    return spans;
}

function ReportDataTable({ table }: { table: ReportTable }) {
    if (table.rows.length === 0) {
        return <p className="text-muted-foreground">{table.empty}</p>;
    }

    return (
        <Table>
            <caption className="sr-only">{table.title}</caption>
            <TableHeader>
                <TableRow>
                    {table.columns.map((c) => (
                        <TableHead
                            key={c.key}
                            className={c.numeric ? 'text-right' : undefined}
                        >
                            {c.label}
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {table.rows.map((row, i) => (
                    <TableRow key={i}>
                        {table.columns.map((c) => {
                            const value = row[c.key];

                            return (
                                <TableCell
                                    key={c.key}
                                    className={
                                        c.numeric
                                            ? 'text-right tabular-nums'
                                            : 'whitespace-normal'
                                    }
                                >
                                    {value === null ||
                                    value === undefined ||
                                    value === ''
                                        ? '—'
                                        : typeof value === 'number'
                                          ? formatNumber(value)
                                          : value}
                                </TableCell>
                            );
                        })}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

/**
 * Horizontal bars (labels stay readable on tablets). The data table right
 * below holds the same numbers, so the chart is hidden from screen readers.
 */
function TableChart({ table }: { table: ReportTable }) {
    const chart = table.chart!;
    const config = Object.fromEntries(
        chart.values.map((key, i) => [
            key,
            {
                label: table.columns.find((c) => c.key === key)?.label ?? key,
                color: `var(--chart-${(i % 5) + 1})`,
            },
        ]),
    ) satisfies ChartConfig;
    const data = table.rows.map((row) => ({
        ...row,
        [chart.label]: String(row[chart.label] ?? ''),
    }));
    // Long labels (medicine names) wrap onto 2–3 lines and need taller rows.
    const longest = Math.max(
        ...data.map((row) => String(row[chart.label]).length),
    );
    const rowHeight = longest > 40 ? 52 : longest > 20 ? 42 : 34;

    return (
        <ChartContainer
            config={config}
            className="aspect-auto w-full"
            style={{ height: Math.max(160, data.length * rowHeight + 40) }}
            aria-hidden
        >
            <BarChart
                data={data}
                layout="vertical"
                margin={{ left: 8, right: 16 }}
            >
                <CartesianGrid horizontal={false} />
                <XAxis
                    type="number"
                    allowDecimals={false}
                    tickLine={false}
                    axisLine={false}
                />
                <YAxis
                    type="category"
                    dataKey={chart.label}
                    width={150}
                    tickLine={false}
                    axisLine={false}
                    interval={0}
                />
                <ChartTooltip content={<ChartTooltipContent />} />
                {chart.values.map((key) => (
                    <Bar
                        key={key}
                        dataKey={key}
                        fill={`var(--color-${key})`}
                        radius={4}
                        isAnimationActive={false}
                    />
                ))}
            </BarChart>
        </ChartContainer>
    );
}

export function ModuleReportSkeleton() {
    return (
        <div className="flex flex-col gap-6" role="status">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                {[0, 1, 2, 3, 4].map((i) => (
                    <StatCardSkeleton key={i} />
                ))}
            </div>
            <Skeleton className="h-72 w-full" />
            <span className="sr-only">Loading report…</span>
        </div>
    );
}
