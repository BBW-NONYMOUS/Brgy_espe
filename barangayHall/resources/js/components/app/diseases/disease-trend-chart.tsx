import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { formatDate } from '@/lib/format';
import type { DiseaseTrend } from '@/types';

const DASHES = ['', '6 3', '2 3', '10 4', '4 2 1 2'];

/**
 * Countable cases per week for the most frequent diseases. Each series also
 * has its own dash pattern (not colour alone), and an sr-only table repeats
 * every number.
 */
export function DiseaseTrendChart({ trend }: { trend: DiseaseTrend }) {
    // Series keys must be CSS-safe (they become --color-s0 …).
    const config = Object.fromEntries(
        trend.series.map((s, i) => [
            `s${i}`,
            { label: s.disease, color: `var(--chart-${(i % 5) + 1})` },
        ]),
    ) satisfies ChartConfig;

    const data = trend.weeks.map((week, w) => ({
        week,
        ...Object.fromEntries(
            trend.series.map((s, i) => [`s${i}`, s.counts[w] ?? 0]),
        ),
    }));

    return (
        <figure>
            <ChartContainer
                config={config}
                className="aspect-auto h-64 w-full"
                aria-hidden
            >
                <LineChart data={data} margin={{ left: -16, right: 8, top: 8 }}>
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="week"
                        tickLine={false}
                        axisLine={false}
                        minTickGap={24}
                        tickFormatter={(v: string) =>
                            formatDate(v).replace(/,\s*\d{4}$/, '')
                        }
                    />
                    <YAxis
                        allowDecimals={false}
                        tickLine={false}
                        axisLine={false}
                        width={36}
                    />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                labelFormatter={(v) =>
                                    `Week of ${formatDate(String(v))}`
                                }
                            />
                        }
                    />
                    <ChartLegend content={<ChartLegendContent />} />
                    {trend.series.map((_, i) => (
                        <Line
                            key={i}
                            dataKey={`s${i}`}
                            stroke={`var(--color-s${i})`}
                            strokeWidth={2.5}
                            strokeDasharray={DASHES[i % DASHES.length]}
                            dot={false}
                            isAnimationActive={false}
                        />
                    ))}
                </LineChart>
            </ChartContainer>
            <table className="sr-only">
                <caption>Disease cases per week</caption>
                <thead>
                    <tr>
                        <th scope="col">Week of</th>
                        {trend.series.map((s) => (
                            <th key={s.disease} scope="col">
                                {s.disease}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {trend.weeks.map((week, w) => (
                        <tr key={week}>
                            <td>{formatDate(week)}</td>
                            {trend.series.map((s) => (
                                <td key={s.disease}>{s.counts[w] ?? 0}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}
