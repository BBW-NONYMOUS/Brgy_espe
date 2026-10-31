import {
    CalendarClock,
    CalendarX2,
    PackageCheck,
    PackageX,
    TriangleAlert,
} from 'lucide-react';
import { StatusBadge } from '@/components/app/status-badge';
import type { BadgeTone } from '@/components/app/status-badge';
import { formatDate } from '@/lib/format';
import type { ExpiryBand } from '@/types';

const bandStyle: Record<
    Exclude<ExpiryBand, null>,
    { tone: BadgeTone; label: (days: number) => string }
> = {
    0: {
        tone: 'danger',
        label: (d) =>
            `Expired ${Math.abs(d)} day${Math.abs(d) === 1 ? '' : 's'} ago`,
    },
    30: {
        tone: 'caution',
        label: (d) =>
            d === 0
                ? 'Expires today'
                : `Expires in ${d} day${d === 1 ? '' : 's'}`,
    },
    60: { tone: 'warning', label: (d) => `Expires in ${d} days` },
    90: { tone: 'warning', label: (d) => `Expires in ${d} days` },
};

/**
 * Medicine batch expiry: red = expired, orange = within 30 days, yellow =
 * within 90 days, neutral = later. The text always states the days, so the
 * colour is never the only cue. Band and days come from the server
 * (MedicineBatchResource) so every screen agrees with the alerts.
 */
export function ExpiryBadge({
    expiryDate,
    band,
    days,
}: {
    expiryDate: string;
    band: ExpiryBand;
    days: number;
}) {
    if (band === null) {
        return (
            <StatusBadge
                tone="neutral"
                icon={<CalendarClock aria-hidden />}
                title={`Expiry date ${formatDate(expiryDate)}`}
            >
                Exp. {formatDate(expiryDate)}
            </StatusBadge>
        );
    }

    const style = bandStyle[band];

    return (
        <StatusBadge
            tone={style.tone}
            icon={
                band === 0 ? (
                    <CalendarX2 aria-hidden />
                ) : (
                    <TriangleAlert aria-hidden />
                )
            }
            title={`Expiry date ${formatDate(expiryDate)}`}
        >
            {style.label(days)}
        </StatusBadge>
    );
}

/**
 * Usable (non-expired) stock against the reorder level.
 */
export function StockLevelBadge({
    onHand,
    reorderLevel,
    unit,
}: {
    onHand: number;
    reorderLevel: number;
    unit: string;
}) {
    if (onHand === 0) {
        return (
            <StatusBadge tone="danger" icon={<PackageX aria-hidden />}>
                Out of stock
            </StatusBadge>
        );
    }

    if (onHand <= reorderLevel) {
        return (
            <StatusBadge
                tone="caution"
                icon={<TriangleAlert aria-hidden />}
                title={`Reorder level: ${reorderLevel} ${unit}(s)`}
            >
                Low: {onHand.toLocaleString()} left
            </StatusBadge>
        );
    }

    return (
        <StatusBadge tone="success" icon={<PackageCheck aria-hidden />}>
            {onHand.toLocaleString()} in stock
        </StatusBadge>
    );
}
