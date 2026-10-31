import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { formatDate, parseDateOnly } from '@/lib/format';
import type { DiseaseTrendWeek } from '@/types/pages/scheduling';

const config = {
    cases: { label: 'Cases', color: 'var(--chart-4)' },
} satisfies ChartConfig;

const shortWeek = (week: string) =>
    new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric' }).format(
        parseDateOnly(week),
    );

/**
 * Disease cases (suspected, probable, confirmed — not ruled out) per week,
 * last 12 weeks, all diseases. Aggregate only. A data table repeats the
 * numbers for screen readers.
 */
export function DiseaseTrendChart({ data }: { data: DiseaseTrendWeek[] }) {
    return (
        <figure>
            <ChartContainer
                config={config}
                className="aspect-auto h-64 w-full"
                aria-hidden
            >
                <BarChart data={data} margin={{ left: -16, right: 8 }}>
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="week"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        tickFormatter={shortWeek}
                        interval="preserveStartEnd"
                    />
                    <YAxis
                        allowDecimals={false}
                        tickLine={false}
                        axisLine={false}
                        width={40}
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
                    <Bar
                        dataKey="cases"
                        fill="var(--color-cases)"
                        radius={4}
                        isAnimationActive={false}
                    />
                </BarChart>
            </ChartContainer>
            <table className="sr-only">
                <caption>Disease cases per week, last 12 weeks</caption>
                <thead>
                    <tr>
                        <th scope="col">Week starting</th>
                        <th scope="col">Cases</th>
                    </tr>
                </thead>
                <tbody>
                    {data.map((row) => (
                        <tr key={row.week}>
                            <td>{formatDate(row.week)}</td>
                            <td>{row.cases}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}
