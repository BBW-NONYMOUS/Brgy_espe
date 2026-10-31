import {
    CalendarCheck2,
    CalendarClock,
    CalendarMinus2,
    CalendarX2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { StatusBadge } from '@/components/app/status-badge';
import type { BadgeTone } from '@/components/app/status-badge';
import type { AppointmentStatus, EnumValue } from '@/types';

const appointmentTone: Record<
    AppointmentStatus,
    { tone: BadgeTone; icon: ReactNode }
> = {
    scheduled: { tone: 'info', icon: <CalendarClock aria-hidden /> },
    completed: { tone: 'success', icon: <CalendarCheck2 aria-hidden /> },
    missed: { tone: 'danger', icon: <CalendarX2 aria-hidden /> },
    cancelled: { tone: 'neutral', icon: <CalendarMinus2 aria-hidden /> },
};

export function AppointmentStatusBadge({
    status,
}: {
    status: EnumValue<AppointmentStatus>;
}) {
    const style = appointmentTone[status.value];

    return (
        <StatusBadge tone={style.tone} icon={style.icon}>
            {status.label}
        </StatusBadge>
    );
}

const programTone: Record<string, BadgeTone> = {
    scheduled: 'info',
    completed: 'success',
    cancelled: 'neutral',
};

export function ProgramStatusBadge({ status }: { status: EnumValue }) {
    return (
        <StatusBadge tone={programTone[status.value] ?? 'neutral'}>
            {status.label}
        </StatusBadge>
    );
}

/** Calendar colour dot + the type name in text (colour is never the only cue). */
export function TypeLabel({
    name,
    color,
}: {
    name: string;
    color?: string | null;
}) {
    return (
        <span className="inline-flex items-center gap-2">
            <span
                aria-hidden
                className="size-3 shrink-0 rounded-full"
                style={{ background: color ?? 'var(--muted-foreground)' }}
            />
            {name}
        </span>
    );
}

/** "Tue, Nov 3 · 8:00 AM" in Philippine time. */
export function formatSlot(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'Asia/Manila',
    }).format(new Date(iso));
}
