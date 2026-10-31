import { OctagonAlert } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { MedicalAlert } from '@/types';

type Props = {
    /** Active critical alerts (the server sends only these). */
    alerts: MedicalAlert[];
    /** Compact one-line form for Sheets/Dialogs (visit, dispense, referral forms). */
    compact?: boolean;
    className?: string;
};

/**
 * Red banner for critical medical alerts (allergies, conditions that change
 * treatment). Shown at the top of the resident profile and on every health
 * form. A labelled region, not role="alert": it is read once with the page,
 * not announced again on every re-render.
 */
export function MedicalAlertBanner({
    alerts,
    compact = false,
    className,
}: Props) {
    if (alerts.length === 0) {
        return null;
    }

    return (
        <section
            aria-label="Critical medical alerts"
            className={cn(
                'flex gap-3 rounded-lg border-2 border-destructive bg-danger-soft text-danger-soft-foreground',
                compact ? 'items-center px-3 py-2 text-sm' : 'px-4 py-3',
                className,
            )}
        >
            <OctagonAlert
                aria-hidden
                className={cn(
                    'shrink-0 text-destructive',
                    compact ? 'size-5' : 'mt-0.5 size-6',
                )}
            />
            <div className="min-w-0">
                {!compact && (
                    <p className="font-bold">
                        Critical alert{alerts.length > 1 ? 's' : ''} — check
                        before giving any treatment
                    </p>
                )}
                <ul
                    className={cn(
                        compact ? 'inline' : 'mt-1 list-disc space-y-0.5 pl-5',
                    )}
                >
                    {alerts.map((alert, i) => (
                        <li key={alert.id} className={cn(compact && 'inline')}>
                            <span className="font-bold">
                                {alert.alert_type.label}:
                            </span>{' '}
                            {alert.description}
                            {compact && i < alerts.length - 1 && ' · '}
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
