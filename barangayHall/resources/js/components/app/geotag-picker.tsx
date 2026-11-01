import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { Crosshair, LocateFixed, MapPinOff } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    Circle,
    MapContainer,
    Marker,
    TileLayer,
    useMap,
    useMapEvents,
} from 'react-leaflet';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { osmTiles } from '@/lib/map-tiles';
import type { GeotagValue, MapConfig } from '@/types';

/** Pure-CSS pin (no image files: nothing to break in the bundle, works offline). */
const pinIcon = L.divIcon({
    className: '',
    html: '<span style="display:block;width:28px;height:28px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:var(--marker-household);border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.45)"></span>',
    iconSize: [28, 28],
    iconAnchor: [14, 28],
});

function ClickToPlace({
    onPick,
}: {
    onPick: (lat: number, lng: number) => void;
}) {
    useMapEvents({ click: (e) => onPick(e.latlng.lat, e.latlng.lng) });

    return null;
}

function FlyTo({ position }: { position: [number, number] | null }) {
    const map = useMap();

    useEffect(() => {
        if (position) {
            map.flyTo(position, Math.max(map.getZoom(), 18), { duration: 0.6 });
        }
    }, [map, position]);

    return null;
}

type Props = {
    config: MapConfig;
    value: GeotagValue | null;
    onChange: (value: GeotagValue) => void;
};

const round7 = (n: number) => Math.round(n * 1e7) / 1e7;

/**
 * Geo-tag a household: "Use my location" (tablet GPS, needs HTTPS) or
 * tap/click the map, then drag the pin to adjust. Shows the GPS accuracy
 * circle and warns when it is worse than the configured limit.
 */
export function GeotagPicker({ config, value, onChange }: Props) {
    const [locating, setLocating] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [flyTarget, setFlyTarget] = useState<[number, number] | null>(null);
    const secure =
        typeof window !== 'undefined' &&
        window.isSecureContext &&
        'geolocation' in navigator;
    const maxAccuracy = config.max_accuracy_m ?? 100;
    const position = useMemo<[number, number] | null>(
        () => (value ? [value.latitude, value.longitude] : null),
        [value],
    );
    // SVG attributes can't use CSS variables, so resolve the theme colour once.
    const accuracyColor = useMemo(
        () =>
            getComputedStyle(document.documentElement)
                .getPropertyValue('--marker-senior')
                .trim() || '#1d4ed8',
        [],
    );

    const useMyLocation = () => {
        setError(null);
        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setLocating(false);
                const next = {
                    latitude: round7(pos.coords.latitude),
                    longitude: round7(pos.coords.longitude),
                    accuracy_m: Math.round(pos.coords.accuracy),
                };
                onChange(next);
                setFlyTarget([next.latitude, next.longitude]);
            },
            (err) => {
                setLocating(false);
                setError(
                    err.code === err.PERMISSION_DENIED
                        ? 'Location permission was refused. Allow location for this site in the browser settings, or tap the map instead.'
                        : err.code === err.TIMEOUT
                          ? 'The GPS took too long. Go outdoors or near a window and try again, or tap the map.'
                          : 'The location could not be found. Tap the house on the map instead.',
                );
            },
            { enableHighAccuracy: true, timeout: 20_000, maximumAge: 0 },
        );
    };

    const pick = (lat: number, lng: number) =>
        onChange({
            latitude: round7(lat),
            longitude: round7(lng),
            accuracy_m: null,
        });

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    onClick={useMyLocation}
                    disabled={!secure || locating}
                >
                    {locating ? <Spinner /> : <LocateFixed aria-hidden />}
                    {locating ? 'Finding your location…' : 'Use my location'}
                </Button>
                <span className="flex items-center gap-1 text-sm text-muted-foreground">
                    <Crosshair aria-hidden className="size-4" /> or tap the
                    house on the map, then drag the pin to adjust.
                </span>
            </div>

            {!secure && (
                <Alert>
                    <MapPinOff aria-hidden />
                    <AlertTitle>
                        "Use my location" needs a secure (https://) address
                    </AlertTitle>
                    <AlertDescription>
                        Browsers only share GPS with secure sites. Ask the admin
                        to open the system through its https:// address; until
                        then, tap the map.
                    </AlertDescription>
                </Alert>
            )}
            {error && (
                <Alert variant="destructive" role="alert">
                    <MapPinOff aria-hidden />
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            <div className="relative isolate overflow-hidden rounded-lg border">
                <MapContainer
                    center={position ?? config.center}
                    zoom={position ? 18 : config.zoom}
                    className="h-80 w-full"
                    aria-label="Choose the household location"
                >
                    <TileLayer {...osmTiles} />
                    <ClickToPlace onPick={pick} />
                    <FlyTo position={flyTarget} />
                    {position && value?.accuracy_m && (
                        <Circle
                            center={position}
                            radius={value.accuracy_m}
                            pathOptions={{
                                color: accuracyColor,
                                weight: 1,
                                fillOpacity: 0.12,
                            }}
                        />
                    )}
                    {position && (
                        <Marker
                            position={position}
                            icon={pinIcon}
                            draggable
                            keyboard
                            eventHandlers={{
                                dragend: (e) => {
                                    const ll = (
                                        e.target as L.Marker
                                    ).getLatLng();
                                    pick(ll.lat, ll.lng);
                                },
                            }}
                        />
                    )}
                </MapContainer>
            </div>

            <p className="text-sm" aria-live="polite">
                {value ? (
                    <>
                        Selected:{' '}
                        <span className="font-mono">
                            {value.latitude.toFixed(5)},{' '}
                            {value.longitude.toFixed(5)}
                        </span>
                        {value.accuracy_m !== null && (
                            <span
                                className={
                                    value.accuracy_m > maxAccuracy
                                        ? 'font-bold text-destructive'
                                        : 'text-muted-foreground'
                                }
                            >
                                {' '}
                                · GPS accuracy about {value.accuracy_m} m
                                {value.accuracy_m > maxAccuracy &&
                                    ' — too rough; move outdoors and try again, or adjust the pin'}
                            </span>
                        )}
                    </>
                ) : (
                    <span className="text-muted-foreground">
                        No location chosen yet.
                    </span>
                )}
            </p>
        </div>
    );
}
