import {
    CheckCircle2,
    CircleAlert,
    CircleDashed,
    CircleMinus,
    Clock,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { StatusBadge } from '@/components/app/status-badge';
import type { BadgeTone } from '@/components/app/status-badge';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ContactStatus, MaternalContact } from '@/types';

export const contactStatus: Record<
    ContactStatus,
    { label: string; tone: BadgeTone; icon: ReactNode }
> = {
    done: {
        label: 'Done',
        tone: 'success',
        icon: <CheckCircle2 aria-hidden />,
    },
    due: { label: 'Due now', tone: 'warning', icon: <Clock aria-hidden /> },
    overdue: {
        label: 'Overdue',
        tone: 'danger',
        icon: <CircleAlert aria-hidden />,
    },
    upcoming: {
        label: 'Upcoming',
        tone: 'neutral',
        icon: <CircleDashed aria-hidden />,
    },
    missed: {
        label: 'Missed',
        tone: 'danger',
        icon: <CircleMinus aria-hidden />,
    },
    not_applicable: {
        label: 'Not needed',
        tone: 'neutral',
        icon: <CircleMinus aria-hidden />,
    },
};

/**
 * Prenatal / postnatal contact schedule of one pregnancy (from the
 * configurable rules): one row per contact with its due date, status and
 * the date it was attended. Rows link to the visit when there is one.
 */
export function ContactSchedule({
    contacts,
    onRecord,
}: {
    contacts: MaternalContact[];
    /** "Record visit" for the next actionable contact. */
    onRecord?: (contact: MaternalContact) => void;
}) {
    const groups = (['prenatal', 'postnatal'] as const)
        .map((kind) => ({
            kind,
            items: contacts.filter((c) => c.kind === kind),
        }))
        .filter((g) => g.items.length > 0);

    return (
        <div className="flex flex-col gap-6">
            {groups.map(({ kind, items }) => (
                <section key={kind} aria-labelledby={`contacts-${kind}`}>
                    <h3 id={`contacts-${kind}`} className="mb-2 font-bold">
                        {kind === 'prenatal'
                            ? 'Prenatal contacts'
                            : 'Postnatal contacts'}
                    </h3>
                    <ol className="relative flex flex-col gap-1 border-l-2 border-border pl-5">
                        {items.map((contact) => {
                            const status = contactStatus[contact.status];
                            const actionable = ['due', 'overdue'].includes(
                                contact.status,
                            );

                            return (
                                <li
                                    key={contact.rule_id}
                                    className={cn(
                                        'relative flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md py-2 pr-2',
                                        contact.status === 'overdue' &&
                                            'bg-danger-soft/50',
                                    )}
                                >
                                    <span
                                        aria-hidden
                                        className={cn(
                                            'absolute top-1/2 -left-[1.6rem] size-3 -translate-y-1/2 rounded-full border-2 border-card',
                                            contact.status === 'done'
                                                ? 'bg-success'
                                                : contact.status ===
                                                        'overdue' ||
                                                    contact.status === 'missed'
                                                  ? 'bg-destructive'
                                                  : 'bg-border',
                                        )}
                                    />
                                    <span className="min-w-48 flex-1 font-medium">
                                        {contact.label}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {contact.visit_date
                                            ? `Attended ${formatDate(contact.visit_date)}`
                                            : `Due ${formatDate(contact.due_date)}`}
                                    </span>
                                    <StatusBadge
                                        tone={status.tone}
                                        icon={status.icon}
                                    >
                                        {status.label}
                                    </StatusBadge>
                                    {actionable && onRecord && (
                                        <button
                                            type="button"
                                            onClick={() => onRecord(contact)}
                                            className="min-h-11 rounded-md px-2 text-sm font-bold text-primary underline-offset-4 hover:underline"
                                        >
                                            Record visit
                                        </button>
                                    )}
                                </li>
                            );
                        })}
                    </ol>
                </section>
            ))}
        </div>
    );
}
