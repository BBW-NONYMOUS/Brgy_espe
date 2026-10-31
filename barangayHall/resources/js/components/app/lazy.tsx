import { lazy, Suspense } from 'react';
import type { ComponentProps } from 'react';
import { Skeleton } from '@/components/ui/skeleton';

/*
 * Leaflet (~150 KB) and FullCalendar (~250 KB) load only on the pages that
 * use them, never in the shared bundle. Leaflet also touches `window` at
 * import time, so keeping it lazy keeps the app safe for SSR.
 */

const HouseholdMapImpl = lazy(() =>
    import('@/components/app/household-map').then((m) => ({
        default: m.HouseholdMap,
    })),
);
const GeotagPickerImpl = lazy(() =>
    import('@/components/app/geotag-picker').then((m) => ({
        default: m.GeotagPicker,
    })),
);
const CalendarViewImpl = lazy(() =>
    import('@/components/app/calendar-view').then((m) => ({
        default: m.CalendarView,
    })),
);

function Loading({
    label,
    className = 'h-[28rem]',
}: {
    label: string;
    className?: string;
}) {
    return (
        <div role="status" className="relative">
            <Skeleton className={`w-full rounded-lg ${className}`} />
            <span className="sr-only">Loading {label}…</span>
        </div>
    );
}

export function LazyHouseholdMap(
    props: ComponentProps<typeof HouseholdMapImpl>,
) {
    return (
        <Suspense fallback={<Loading label="map" />}>
            <HouseholdMapImpl {...props} />
        </Suspense>
    );
}

export function LazyGeotagPicker(
    props: ComponentProps<typeof GeotagPickerImpl>,
) {
    return (
        <Suspense fallback={<Loading label="map" className="h-96" />}>
            <GeotagPickerImpl {...props} />
        </Suspense>
    );
}

export function LazyCalendarView(
    props: ComponentProps<typeof CalendarViewImpl>,
) {
    return (
        <Suspense fallback={<Loading label="calendar" className="h-[36rem]" />}>
            <CalendarViewImpl {...props} />
        </Suspense>
    );
}
