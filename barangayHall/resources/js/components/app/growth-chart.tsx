import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { formatDate, parseDateOnly } from '@/lib/format';
import type { GrowthMeasurement } from '@/types';

const config = {
    weight_kg: { label: 'Weight (kg)', color: 'var(--chart-1)' },
    height_cm: { label: 'Length/height (cm)', color: 'var(--chart-2)' },
} satisfies ChartConfig;

function monthsBetween(from: string, to: string): number {
    const a = parseDateOnly(from);
    const b = parseDateOnly(to);

    return Math.max(
        0,
        Math.round(((b.getTime() - a.getTime()) / 86_400_000 / 30.4375) * 10) /
            10,
    );
}

/**
 * Weight and length/height against age in months (two y-axes). The status
 * from the OPT Plus chart is listed in the table below — this chart shows
 * the trend, not a WHO z-score. A data table repeats every point for
 * screen readers.
 */
export function GrowthChart({
    measurements,
    birthdate,
}: {
    measurements: GrowthMeasurement[];
    birthdate: string;
}) {
    const data = measurements.map((m) => ({
        age: monthsBetween(birthdate, m.measured_on),
        weight_kg: m.weight_kg,
        height_cm: m.height_cm,
        date: m.measured_on,
    }));

    return (
        <figure>
            <ChartContainer
                config={config}
                className="aspect-auto h-72 w-full"
                aria-hidden
            >
                <LineChart data={data} margin={{ left: 0, right: 0, top: 8 }}>
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="age"
                        type="number"
                        domain={['dataMin', 'dataMax']}
                        tickLine={false}
                        axisLine={false}
                        tickFormatter={(v: number) => `${Math.round(v)} mo`}
                    />
                    <YAxis
                        yAxisId="weight"
                        tickLine={false}
                        axisLine={false}
                        width={36}
                    />
                    <YAxis
                        yAxisId="height"
                        orientation="right"
                        tickLine={false}
                        axisLine={false}
                        width={40}
                    />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                labelFormatter={(_, payload) => {
                                    const point = payload?.[0]?.payload as
                                        | { date?: string; age?: number }
                                        | undefined;

                                    return point?.date
                                        ? `${formatDate(point.date)} · ${point.age} months`
                                        : '';
                                }}
                            />
                        }
                    />
                    <Line
                        yAxisId="weight"
                        dataKey="weight_kg"
                        stroke="var(--color-weight_kg)"
                        strokeWidth={2.5}
                        dot={{ r: 4 }}
                        isAnimationActive={false}
                    />
                    <Line
                        yAxisId="height"
                        dataKey="height_cm"
                        stroke="var(--color-height_cm)"
                        strokeWidth={2.5}
                        strokeDasharray="6 3"
                        dot={{ r: 4 }}
                        connectNulls
                        isAnimationActive={false}
                    />
                </LineChart>
            </ChartContainer>
            <figcaption className="mt-2 flex flex-wrap gap-4 text-sm text-muted-foreground">
                <span className="flex items-center gap-2">
                    <span aria-hidden className="h-0.5 w-6 bg-chart-1" /> Weight
                    (kg, left axis)
                </span>
                <span className="flex items-center gap-2">
                    <span
                        aria-hidden
                        className="h-0.5 w-6 border-t-2 border-dashed border-chart-2"
                    />{' '}
                    Length/height (cm, right axis)
                </span>
            </figcaption>
            <table className="sr-only">
                <caption>Growth measurements</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Age (months)</th>
                        <th scope="col">Weight (kg)</th>
                        <th scope="col">Length/height (cm)</th>
                        <th scope="col">Nutritional status</th>
                    </tr>
                </thead>
                <tbody>
                    {measurements.map((m, i) => (
                        <tr key={m.id}>
                            <td>{formatDate(m.measured_on)}</td>
                            <td>{data[i].age}</td>
                            <td>{m.weight_kg}</td>
                            <td>{m.height_cm ?? '—'}</td>
                            <td>{m.nutritional_status?.label ?? '—'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}
