import { Link } from '@inertiajs/react';
import { CalendarClock, ClipboardList } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import {
    AppointmentStatusBadge,
    formatSlot,
    TypeLabel,
} from '@/components/app/scheduling/status';
import { show as programShow } from '@/routes/programs';
import { show as residentShow } from '@/routes/residents';
import type { Appointment, HealthProgram } from '@/types';

const timeOnly = (iso: string | null) =>
    iso
        ? new Intl.DateTimeFormat('en-PH', {
              hour: 'numeric',
              minute: '2-digit',
              timeZone: 'Asia/Manila',
          }).format(new Date(iso))
        : '—';

export function TodaysAppointments({ items }: { items: Appointment[] }) {
    if (items.length === 0) {
        return (
            <EmptyState icon={CalendarClock} title="No appointments today" />
        );
    }

    return (
        <ul className="divide-y">
            {items.map((a) => (
                <li key={a.id} className="flex flex-col gap-1 py-2.5">
                    {/* Time and status on top, the person below: fits the narrow side column. */}
                    <span className="flex items-center justify-between gap-2">
                        <span className="font-bold">
                            {timeOnly(a.scheduled_at)}
                        </span>
                        <AppointmentStatusBadge status={a.status} />
                    </span>
                    {a.resident ? (
                        <Link
                            href={residentShow(a.resident.id)}
                            className="font-medium underline-offset-4 hover:underline"
                        >
                            {a.resident.full_name}
                        </Link>
                    ) : (
                        <span>—</span>
                    )}
                    {a.type && (
                        <span className="text-sm text-muted-foreground">
                            <TypeLabel
                                name={a.type.name}
                                color={a.type.color}
                            />
                            {a.assigned_to ? ` · ${a.assigned_to.name}` : ''}
                        </span>
                    )}
                </li>
            ))}
        </ul>
    );
}

export function UpcomingPrograms({ items }: { items: HealthProgram[] }) {
    if (items.length === 0) {
        return (
            <EmptyState icon={ClipboardList} title="No programs scheduled" />
        );
    }

    return (
        <ul className="divide-y">
            {items.map((p) => (
                <li key={p.id} className="py-2.5">
                    <Link
                        href={programShow(p.id)}
                        className="font-medium underline-offset-4 hover:underline"
                    >
                        {p.title}
                    </Link>
                    <span className="block text-sm text-muted-foreground">
                        {formatSlot(p.start_at)} · {p.venue}
                    </span>
                    {p.type && (
                        <span className="block text-sm">
                            <TypeLabel
                                name={p.type.name}
                                color={p.type.color}
                            />
                        </span>
                    )}
                </li>
            ))}
        </ul>
    );
}
