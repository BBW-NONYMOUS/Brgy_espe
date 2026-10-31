import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { formatNumber } from '@/lib/format';
import type { AgeSexRow, PurokPopulation } from '@/types';

const purokConfig = {
    male: { label: 'Male', color: 'var(--chart-2)' },
    female: { label: 'Female', color: 'var(--chart-4)' },
} satisfies ChartConfig;

/**
 * Residents per purok, stacked by sex. A data table with the same numbers
 * is rendered for screen readers (charts alone are not accessible).
 */
export function PurokChart({ data }: { data: PurokPopulation[] }) {
    return (
        <figure>
            <ChartContainer
                config={purokConfig}
                className="aspect-auto h-72 w-full"
                aria-hidden
            >
                <BarChart data={data} margin={{ left: -12, right: 8 }}>
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="purok"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        // Every purok gets a label; "Purok 3" → "P3" so they fit on tablets.
                        interval={0}
                        tickFormatter={(v: string) =>
                            v.replace(/^Purok\s+/i, 'P')
                        }
                    />
                    <YAxis
                        tickLine={false}
                        axisLine={false}
                        allowDecimals={false}
                        width={40}
                    />
                    <ChartTooltip content={<ChartTooltipContent />} />
                    <ChartLegend content={<ChartLegendContent />} />
                    <Bar
                        isAnimationActive={false}
                        dataKey="male"
                        stackId="sex"
                        fill="var(--color-male)"
                    />
                    <Bar
                        isAnimationActive={false}
                        dataKey="female"
                        stackId="sex"
                        fill="var(--color-female)"
                        radius={[6, 6, 0, 0]}
                    />
                </BarChart>
            </ChartContainer>
            <SrTable
                caption="Residents per purok"
                headers={['Purok', 'Male', 'Female', 'Total']}
                rows={data.map((r) => [r.purok, r.male, r.female, r.residents])}
            />
        </figure>
    );
}

const pyramidConfig = {
    male: { label: 'Male', color: 'var(--chart-2)' },
    female: { label: 'Female', color: 'var(--chart-4)' },
} satisfies ChartConfig;

/**
 * Population pyramid: males to the left, females to the right, oldest
 * age group on top.
 */
export function AgeSexPyramid({ data }: { data: AgeSexRow[] }) {
    const rows = [...data].reverse().map((r) => ({ ...r, male: -r.male }));
    const max = Math.max(1, ...data.map((r) => Math.max(r.male, r.female)));
    // Symmetric, evenly spaced whole-number ticks: -top … 0 … top.
    const step = Math.max(1, Math.ceil(max / 4));
    const top = step * Math.ceil(max / step);
    const ticks = Array.from(
        { length: (2 * top) / step + 1 },
        (_, i) => -top + i * step,
    );

    return (
        <figure>
            <ChartContainer
                config={pyramidConfig}
                className="aspect-auto h-96 w-full"
                aria-hidden
            >
                <BarChart
                    data={rows}
                    layout="vertical"
                    stackOffset="sign"
                    margin={{ left: 8, right: 8 }}
                >
                    <CartesianGrid horizontal={false} />
                    <XAxis
                        type="number"
                        domain={[-top, top]}
                        ticks={ticks}
                        tickFormatter={(v: number) => formatNumber(Math.abs(v))}
                        tickLine={false}
                        axisLine={false}
                        allowDecimals={false}
                    />
                    <YAxis
                        type="category"
                        dataKey="age_group"
                        tickLine={false}
                        axisLine={false}
                        width={84}
                    />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                formatter={(value, name) => (
                                    <span className="flex w-full justify-between gap-4">
                                        <span>
                                            {name === 'male'
                                                ? 'Male'
                                                : 'Female'}
                                        </span>
                                        <span className="font-bold tabular-nums">
                                            {formatNumber(
                                                Math.abs(Number(value)),
                                            )}
                                        </span>
                                    </span>
                                )}
                            />
                        }
                    />
                    <ChartLegend content={<ChartLegendContent />} />
                    <Bar
                        isAnimationActive={false}
                        dataKey="male"
                        stackId="pyramid"
                        fill="var(--color-male)"
                        radius={[6, 0, 0, 6]}
                    />
                    <Bar
                        isAnimationActive={false}
                        dataKey="female"
                        stackId="pyramid"
                        fill="var(--color-female)"
                        radius={[0, 6, 6, 0]}
                    />
                </BarChart>
            </ChartContainer>
            <SrTable
                caption="Residents by age group and sex"
                headers={['Age group', 'Male', 'Female', 'Total']}
                rows={data.map((r) => [r.age_group, r.male, r.female, r.total])}
            />
        </figure>
    );
}

function SrTable({
    caption,
    headers,
    rows,
}: {
    caption: string;
    headers: string[];
    rows: (string | number)[][];
}) {
    return (
        <table className="sr-only">
            <caption>{caption}</caption>
            <thead>
                <tr>
                    {headers.map((h) => (
                        <th key={h} scope="col">
                            {h}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={String(row[0])}>
                        {row.map((cell, i) =>
                            i === 0 ? (
                                <th key={i} scope="row">
                                    {cell}
                                </th>
                            ) : (
                                <td key={i}>{cell}</td>
                            ),
                        )}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
