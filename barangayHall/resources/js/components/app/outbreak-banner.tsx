import { Link } from '@inertiajs/react';
import { Siren } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as outbreakAlerts } from '@/routes/outbreak-alerts';
import type { OutbreakAlert } from '@/types';

/**
 * Dashboard banner while an outbreak alert is open. Aggregate data only
 * (disease, place, count), so it is the same for Barangay Officials.
 */
export function OutbreakBanner({
    alerts,
    className,
}: {
    alerts: OutbreakAlert[];
    className?: string;
}) {
    if (alerts.length === 0) {
        return null;
    }

    return (
        <section
            aria-label="Active outbreak alerts"
            className={cn(
                'flex flex-col gap-3 rounded-lg border-2 border-destructive bg-danger-soft px-4 py-3 text-danger-soft-foreground sm:flex-row sm:items-center',
                className,
            )}
        >
            <Siren aria-hidden className="size-7 shrink-0 text-destructive" />
            <div className="min-w-0 flex-1">
                <p className="font-bold">
                    {alerts.length === 1
                        ? 'Possible outbreak'
                        : `${alerts.length} possible outbreaks`}
                </p>
                <ul className="mt-0.5 space-y-0.5 text-sm">
                    {alerts.map((alert) => (
                        <li key={alert.id}>
                            <span className="font-bold">
                                {alert.case_count} {alert.disease?.name}
                            </span>{' '}
                            cases in {alert.purok?.name ?? 'the whole barangay'}{' '}
                            ({formatDate(alert.period_start)} –{' '}
                            {formatDate(alert.period_end)}) ·{' '}
                            {alert.status.label}
                        </li>
                    ))}
                </ul>
            </div>
            <Button asChild variant="destructive" className="shrink-0">
                <Link href={outbreakAlerts()}>View alerts</Link>
            </Button>
        </section>
    );
}
