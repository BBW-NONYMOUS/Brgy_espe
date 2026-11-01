import 'leaflet/dist/leaflet.css';
import { Link } from '@inertiajs/react';
import { WifiOff } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    CircleMarker,
    MapContainer,
    Popup,
    TileLayer,
    Tooltip,
    useMap,
} from 'react-leaflet';
import { FLAG_LABEL, MARKER_FLAGS } from '@/components/app/map-legend';
import { show as householdShow } from '@/routes/households';
import { cn } from '@/lib/utils';
import type {
    HouseholdMarker,
    MapConfig,
    MapFlag,
    PurokSummary,
} from '@/types';
import { osmTiles } from '@/lib/map-tiles';

/** Canvas needs real colour strings, so read the theme tokens once. */
function useMarkerColors(): Record<string, string> {
    return useMemo(() => {
        const style = getComputedStyle(document.documentElement);

        return Object.fromEntries(
            MARKER_FLAGS.map((m) => [
                m.flag,
                style.getPropertyValue(m.cssVar).trim() || '#0f766e',
            ]),
        );
    }, []);
}

function FitToPoints({ points }: { points: [number, number][] }) {
    const map = useMap();

    useEffect(() => {
        if (points.length > 1) {
            map.fitBounds(points, { padding: [32, 32], maxZoom: 17 });
        }
        // Only on the first set of points; later filter changes keep the user's view.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [points.length > 1]);

    return null;
}

type Props = {
    config: MapConfig;
    /** Health staff: exact households. */
    markers?: HouseholdMarker[];
    /** Barangay Officials / disease map: one circle per purok. */
    puroks?: PurokSummary[];
    /** What the purok circles count (default: disease cases). */
    purokMetric?: 'cases' | 'households';
    className?: string;
    /** Accessible name of the map region. */
    label?: string;
};

/**
 * Leaflet + OpenStreetMap. Rendered on a canvas (fast with thousands of
 * points on a modest PC). Tiles need internet; when they fail the markers
 * still show over a plain background with a notice. Every marker's meaning
 * is also in its popup and the legend, never colour alone; pages render a
 * list of the same households for keyboard and screen-reader users.
 */
export function HouseholdMap({
    config,
    markers,
    puroks,
    purokMetric = 'cases',
    className,
    label = 'Household map',
}: Props) {
    const colors = useMarkerColors();
    const [tilesFailed, setTilesFailed] = useState(false);
    const points = useMemo<[number, number][]>(
        () =>
            markers
                ? markers.map((m) => [m.lat, m.lng])
                : (puroks ?? [])
                      .filter((p) => p.lat !== null && p.lng !== null)
                      .map((p) => [p.lat as number, p.lng as number]),
        [markers, puroks],
    );
    const maxMetric = Math.max(1, ...(puroks ?? []).map((p) => p[purokMetric]));

    return (
        <div
            className={cn(
                'relative isolate overflow-hidden rounded-lg border',
                className,
            )}
            role="region"
            aria-label={label}
        >
            <MapContainer
                center={config.center}
                zoom={config.zoom}
                preferCanvas
                scrollWheelZoom={false}
                className="h-full min-h-80 w-full"
            >
                <TileLayer
                    {...osmTiles}
                    eventHandlers={{
                        tileerror: () => setTilesFailed(true),
                        tileload: () => setTilesFailed(false),
                    }}
                />
                <FitToPoints points={points} />

                {markers?.map((m) => {
                    const primary =
                        MARKER_FLAGS.find(
                            (f) =>
                                f.flag !== 'household' &&
                                m.flags.includes(f.flag as MapFlag),
                        )?.flag ?? 'household';

                    return (
                        <CircleMarker
                            key={m.id}
                            center={[m.lat, m.lng]}
                            radius={m.flags.length > 0 ? 8 : 6}
                            pathOptions={{
                                color: '#ffffff',
                                weight: 2,
                                fillColor: colors[primary],
                                fillOpacity: 1,
                            }}
                        >
                            <Popup>
                                <div className="flex min-w-48 flex-col gap-1 text-sm">
                                    <p className="text-base font-bold">
                                        {m.household_no}
                                    </p>
                                    <p>{m.head ?? 'No household head set'}</p>
                                    <p className="text-muted-foreground">
                                        {m.purok} · {m.members} member
                                        {m.members === 1 ? '' : 's'}
                                    </p>
                                    {m.flags.length > 0 && (
                                        <p>
                                            {m.flags
                                                .map((f) => FLAG_LABEL[f])
                                                .join(' · ')}
                                        </p>
                                    )}
                                    <Link
                                        href={householdShow(m.id)}
                                        className="mt-1 font-bold text-primary underline"
                                    >
                                        Open family health profile
                                    </Link>
                                </div>
                            </Popup>
                        </CircleMarker>
                    );
                })}

                {puroks
                    ?.filter((p) => p.lat !== null && p.lng !== null)
                    .map((p) => (
                        <CircleMarker
                            key={p.id}
                            center={[p.lat as number, p.lng as number]}
                            radius={12 + 22 * (p[purokMetric] / maxMetric)}
                            pathOptions={{
                                color: '#ffffff',
                                weight: 2,
                                fillColor:
                                    purokMetric === 'cases' && p.cases > 0
                                        ? colors.active_case
                                        : colors.household,
                                fillOpacity: 0.75,
                            }}
                        >
                            <Tooltip
                                permanent
                                direction="center"
                                className="!border-0 !bg-transparent !shadow-none"
                            >
                                <span className="font-bold text-white [text-shadow:0_0_3px_#000]">
                                    {p.name.replace(/^Purok\s+/i, 'P')}:{' '}
                                    {p[purokMetric]}
                                </span>
                            </Tooltip>
                            <Popup>
                                <div className="flex min-w-44 flex-col gap-1 text-sm">
                                    <p className="text-base font-bold">
                                        {p.name}
                                    </p>
                                    <p>
                                        {p.households} households ·{' '}
                                        {p.residents} residents
                                    </p>
                                    <p className="font-bold">
                                        {p.cases} disease case
                                        {p.cases === 1 ? '' : 's'}
                                    </p>
                                    {Object.entries(p.cases_by_disease).map(
                                        ([disease, n]) => (
                                            <p
                                                key={disease}
                                                className="text-muted-foreground"
                                            >
                                                {disease}: {n}
                                            </p>
                                        ),
                                    )}
                                </div>
                            </Popup>
                        </CircleMarker>
                    ))}
            </MapContainer>

            {tilesFailed && (
                <p
                    className="absolute top-2 left-1/2 z-[1000] flex -translate-x-1/2 items-center gap-2 rounded-md border bg-card px-3 py-1.5 text-sm shadow"
                    role="status"
                >
                    <WifiOff aria-hidden className="size-4" /> Map background
                    needs internet — markers are still accurate.
                </p>
            )}
        </div>
    );
}
