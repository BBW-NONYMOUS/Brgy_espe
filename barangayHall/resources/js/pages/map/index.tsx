import { Deferred, Head, Link } from '@inertiajs/react';
import { MapPin, MapPinned } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { LazyHouseholdMap } from '@/components/app/lazy';
import { FLAG_LABEL, MapLegend } from '@/components/app/map-legend';
import { PageHeader } from '@/components/app/page-header';
import { SearchFilters } from '@/components/app/search-filters';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatNumber } from '@/lib/format';
import { show as householdShow } from '@/routes/households';
import { index } from '@/routes/map';
import type { HouseholdMarker, MapFlag, PurokSummary } from '@/types';
import type { MapPageProps } from '@/types/pages/records';

/** Rows shown in the list under the map before asking to narrow down. */
const LIST_LIMIT = 100;

export default function MapIndex({
    mode,
    map,
    filters,
    options,
    markers,
    notGeotagged,
    purokSummary,
    canGeotag,
}: MapPageProps) {
    const { query, update, reset } = useTableQuery(filters, {
        only: ['markers', 'notGeotagged', 'purokSummary', 'filters'],
    });
    const exact = mode === 'exact';

    return (
        <>
            <Head title="Household map" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={exact ? 'Household map' : 'Health map by purok'}
                    description={
                        exact
                            ? 'Geo-tagged households, coloured by their most important health flag. Exact locations are personal data: each view is logged.'
                            : 'Households and disease cases per purok. Individual houses are never shown.'
                    }
                />

                <SearchFilters
                    filters={[
                        {
                            key: 'purok',
                            label: 'Purok',
                            allLabel: 'All puroks',
                            options: options.puroks,
                            value: query.purok_id,
                            onChange: (v) =>
                                update({ purok_id: v ? Number(v) : null }),
                        },
                        ...(exact
                            ? []
                            : [
                                  {
                                      key: 'days',
                                      label: 'Disease cases in the last',
                                      allLabel: 'Last 30 days',
                                      options: [7, 14, 90].map((d) => ({
                                          value: d,
                                          label: `Last ${d} days`,
                                      })),
                                      value:
                                          query.days === 30 ? null : query.days,
                                      onChange: (v: string | null) =>
                                          update({ days: v ? Number(v) : 30 }),
                                  },
                              ]),
                    ]}
                    hasActiveFilters={Boolean(query.purok_id || query.flag)}
                    onReset={() => reset({ purok_id: null, flag: null })}
                >
                    {exact && (
                        <div className="flex flex-col gap-1">
                            <span id="flag-filter-label" className="sr-only">
                                Show households with
                            </span>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                aria-labelledby="flag-filter-label"
                                value={query.flag ?? 'all'}
                                onValueChange={(v) =>
                                    update({
                                        flag:
                                            !v || v === 'all'
                                                ? null
                                                : (v as MapFlag),
                                    })
                                }
                                className="flex flex-wrap justify-start"
                            >
                                <ToggleGroupItem value="all" className="h-11">
                                    All
                                </ToggleGroupItem>
                                {options.flags.map((flag) => (
                                    <ToggleGroupItem
                                        key={flag}
                                        value={flag}
                                        className="h-11"
                                    >
                                        {FLAG_LABEL[flag]}
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                        </div>
                    )}
                </SearchFilters>

                {exact ? (
                    <ExactView
                        map={map}
                        markers={markers}
                        notGeotagged={notGeotagged}
                        canGeotag={canGeotag}
                    />
                ) : (
                    <AggregateView
                        map={map}
                        puroks={purokSummary}
                        days={query.days}
                    />
                )}
            </div>
        </>
    );
}

function MapSkeleton() {
    return (
        <div role="status">
            <Skeleton className="h-[65vh] min-h-80 w-full rounded-lg" />
            <span className="sr-only">Loading map…</span>
        </div>
    );
}

function ExactView({
    map,
    markers,
    notGeotagged,
    canGeotag,
}: Pick<MapPageProps, 'map' | 'markers' | 'notGeotagged' | 'canGeotag'>) {
    return (
        <Deferred data={['markers', 'notGeotagged']} fallback={<MapSkeleton />}>
            <>
                <div className="grid gap-5 xl:grid-cols-[1fr_20rem]">
                    <div className="flex flex-col gap-3">
                        <LazyHouseholdMap
                            config={map}
                            markers={markers ?? []}
                            className="h-[65vh] min-h-80"
                        />
                        <MapLegend />
                    </div>
                    <NotGeotaggedCard
                        households={notGeotagged ?? []}
                        canGeotag={canGeotag}
                    />
                </div>
                <HouseholdList markers={markers ?? []} />
            </>
        </Deferred>
    );
}

function NotGeotaggedCard({
    households,
    canGeotag,
}: {
    households: NonNullable<MapPageProps['notGeotagged']>;
    canGeotag: boolean;
}) {
    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle>Not yet geo-tagged ({households.length})</CardTitle>
                <CardDescription>
                    Visit these households to save their location.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {households.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Every household in this view is on the map.
                    </p>
                ) : (
                    <ul className="flex max-h-[50vh] flex-col divide-y overflow-y-auto">
                        {households.map((h) => (
                            <li
                                key={h.id}
                                className="flex items-center justify-between gap-2 py-2"
                            >
                                <div className="min-w-0">
                                    <p className="font-bold">
                                        {h.household_no}
                                    </p>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {h.purok} · {h.address}
                                    </p>
                                </div>
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="h-11 shrink-0"
                                >
                                    <Link href={householdShow(h.id)}>
                                        <MapPin aria-hidden />
                                        {canGeotag ? 'Geo-tag' : 'Open'}
                                        <span className="sr-only">
                                            {' '}
                                            {h.household_no}
                                        </span>
                                    </Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

/** The same households as a table: the map alternative for keyboard and screen-reader users. */
function HouseholdList({ markers }: { markers: HouseholdMarker[] }) {
    const [showAll, setShowAll] = useState(false);
    const rows = showAll ? markers : markers.slice(0, LIST_LIMIT);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Households on the map</CardTitle>
                <CardDescription>
                    {formatNumber(markers.length)} household
                    {markers.length === 1 ? '' : 's'} in this view.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {markers.length === 0 ? (
                    <EmptyState
                        icon={MapPinned}
                        title="No households match"
                        description="Choose another purok or flag, or geo-tag households first."
                    />
                ) : (
                    <>
                        <Table>
                            <caption className="sr-only">
                                Geo-tagged households
                            </caption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Household</TableHead>
                                    <TableHead>Head</TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Purok
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Members
                                    </TableHead>
                                    <TableHead>Flags</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell>
                                            <Link
                                                href={householdShow(m.id)}
                                                className="font-bold underline-offset-4 hover:underline"
                                            >
                                                {m.household_no}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{m.head ?? '—'}</TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            {m.purok}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {m.members}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {m.flags.length === 0 ? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                ) : (
                                                    m.flags.map((f) => (
                                                        <StatusBadge
                                                            key={f}
                                                            tone={
                                                                f ===
                                                                'active_case'
                                                                    ? 'danger'
                                                                    : 'neutral'
                                                            }
                                                        >
                                                            {FLAG_LABEL[f]}
                                                        </StatusBadge>
                                                    ))
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {markers.length > LIST_LIMIT && !showAll && (
                            <Button
                                variant="outline"
                                className="mt-3"
                                onClick={() => setShowAll(true)}
                            >
                                Show all {formatNumber(markers.length)}
                            </Button>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}

function AggregateView({
    map,
    puroks,
    days,
}: {
    map: MapPageProps['map'];
    puroks?: PurokSummary[];
    days: number;
}) {
    return (
        <Deferred data="purokSummary" fallback={<MapSkeleton />}>
            <div className="grid gap-5 xl:grid-cols-[1fr_30rem]">
                <div className="flex flex-col gap-3">
                    <LazyHouseholdMap
                        config={map}
                        puroks={puroks ?? []}
                        className="h-[65vh] min-h-80"
                        label="Disease cases per purok"
                    />
                    <MapLegend mode="puroks" />
                </div>
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Per purok</CardTitle>
                        <CardDescription>
                            Disease cases with onset in the last {days} days.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <caption className="sr-only">
                                Households, residents and disease cases per
                                purok
                            </caption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Purok</TableHead>
                                    <TableHead className="text-right">
                                        Households
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Residents
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Cases
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {(puroks ?? []).map((p) => (
                                    <TableRow key={p.id}>
                                        <TableCell>
                                            <div className="font-bold">
                                                {p.name}
                                            </div>
                                            {Object.entries(
                                                p.cases_by_disease,
                                            ).map(([disease, n]) => (
                                                <div
                                                    key={disease}
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    {disease}: {n}
                                                </div>
                                            ))}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatNumber(p.households)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatNumber(p.residents)}
                                        </TableCell>
                                        <TableCell
                                            className={
                                                p.cases > 0
                                                    ? 'text-right font-bold text-destructive'
                                                    : 'text-right'
                                            }
                                        >
                                            {p.cases}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </Deferred>
    );
}

MapIndex.layout = {
    breadcrumbs: [{ title: 'Map', href: index() }],
};
