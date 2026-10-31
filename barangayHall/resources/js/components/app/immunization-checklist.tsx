import { Check, CircleAlert, CircleDashed, Clock } from 'lucide-react';
import type { ReactNode } from 'react';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ScheduledDose } from '@/types';

const doseStyle: Record<
    ScheduledDose['status'],
    { label: string; className: string; icon: ReactNode }
> = {
    given: {
        label: 'Given',
        className:
            'border-success/30 bg-success-soft text-success-soft-foreground',
        icon: <Check aria-hidden />,
    },
    due: {
        label: 'Due',
        className:
            'border-warning/50 bg-warning-soft text-warning-soft-foreground',
        icon: <Clock aria-hidden />,
    },
    overdue: {
        label: 'Overdue',
        className:
            'border-destructive/40 bg-danger-soft text-danger-soft-foreground',
        icon: <CircleAlert aria-hidden />,
    },
    upcoming: {
        label: 'Upcoming',
        className: 'border-border bg-muted text-muted-foreground',
        icon: <CircleDashed aria-hidden />,
    },
};

/**
 * EPI checklist: one row per vaccine, one tile per dose with status text,
 * date given or due date. Clicking a due/overdue dose records it.
 */
export function ImmunizationChecklist({
    doses,
    onRecord,
}: {
    doses: ScheduledDose[];
    onRecord?: (dose: ScheduledDose) => void;
}) {
    const vaccines = [...new Map(doses.map((d) => [d.vaccine_id, d])).values()];
    const counts = {
        given: doses.filter((d) => d.status === 'given').length,
        overdue: doses.filter((d) => d.status === 'overdue').length,
    };

    return (
        <div className="flex flex-col gap-3">
            <p className="text-sm text-muted-foreground">
                {counts.given} of {doses.length} doses given
                {counts.overdue > 0 && (
                    <span className="font-bold text-destructive">
                        {' '}
                        · {counts.overdue} overdue
                    </span>
                )}
            </p>
            <ul className="divide-y rounded-lg border">
                {vaccines.map((vaccine) => (
                    <li
                        key={vaccine.vaccine_id}
                        className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center"
                    >
                        <div className="min-w-0 sm:w-56">
                            <p className="font-bold">{vaccine.vaccine_code}</p>
                            <p className="truncate text-xs text-muted-foreground">
                                {vaccine.vaccine_name}
                            </p>
                        </div>
                        <ul
                            className="flex flex-wrap gap-2"
                            aria-label={`${vaccine.vaccine_code} doses`}
                        >
                            {doses
                                .filter(
                                    (d) => d.vaccine_id === vaccine.vaccine_id,
                                )
                                .map((dose) => {
                                    const style = doseStyle[dose.status];
                                    const actionable =
                                        onRecord &&
                                        (dose.status === 'due' ||
                                            dose.status === 'overdue');
                                    const content = (
                                        <>
                                            <span className="flex items-center gap-1 text-xs font-bold [&_svg]:size-3.5">
                                                {style.icon} Dose {dose.dose_no}{' '}
                                                · {style.label}
                                            </span>
                                            <span className="text-xs">
                                                {dose.date_given
                                                    ? formatDate(
                                                          dose.date_given,
                                                      )
                                                    : `Due ${formatDate(dose.due_date)}`}
                                            </span>
                                        </>
                                    );

                                    return (
                                        <li key={dose.dose_no}>
                                            {actionable ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        onRecord(dose)
                                                    }
                                                    className={cn(
                                                        'flex min-h-11 flex-col items-start rounded-md border px-2.5 py-1 text-left hover:brightness-95',
                                                        style.className,
                                                    )}
                                                    aria-label={`Record ${vaccine.vaccine_code} dose ${dose.dose_no}, ${style.label.toLowerCase()} since ${formatDate(dose.due_date)}`}
                                                >
                                                    {content}
                                                </button>
                                            ) : (
                                                <div
                                                    className={cn(
                                                        'flex flex-col rounded-md border px-2.5 py-1',
                                                        style.className,
                                                    )}
                                                >
                                                    {content}
                                                </div>
                                            )}
                                        </li>
                                    );
                                })}
                        </ul>
                    </li>
                ))}
            </ul>
        </div>
    );
}
