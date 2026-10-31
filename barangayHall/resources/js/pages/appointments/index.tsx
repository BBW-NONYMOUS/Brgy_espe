import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    CalendarCheck2,
    CalendarClock,
    CalendarDays,
    CalendarX2,
    List,
    MoreHorizontal,
    Pencil,
    Plus,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import AppointmentController from '@/actions/App/Http/Controllers/Scheduling/AppointmentController';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { DateField } from '@/components/app/fields';
import { LazyCalendarView } from '@/components/app/lazy';
import { PageHeader } from '@/components/app/page-header';
import { AppointmentSheet } from '@/components/app/scheduling/appointment-sheet';
import {
    AppointmentStatusBadge,
    formatSlot,
    TypeLabel,
} from '@/components/app/scheduling/status';
import { SearchFilters } from '@/components/app/search-filters';
import { StatCard } from '@/components/app/stat-card';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTableQuery } from '@/hooks/use-table-query';
import { toDateOnly } from '@/lib/format';
import { index } from '@/routes/appointments';
import { show as programShow } from '@/routes/programs';
import { show as residentShow } from '@/routes/residents';
import type { Appointment, AppointmentStatus, CalendarEvent } from '@/types';
import type {
    AppointmentFilters,
    AppointmentsPageProps,
} from '@/types/pages/scheduling';

type View = 'calendar' | 'list';

function viewFromUrl(url: string): View {
    return new URLSearchParams(url.split('?')[1] ?? '').get('view') ===
        'calendar'
        ? 'calendar'
        : 'list';
}

export default function AppointmentsIndex({
    appointments,
    filters,
    counts,
    options,
    residentOptions,
    selectedAppointment,
}: AppointmentsPageProps) {
    const page = usePage();
    const { query, update, setPage, reset, processing } = useTableQuery<
        AppointmentFilters & { view: View }
    >(
        { ...filters, view: viewFromUrl(page.url) },
        { only: ['appointments', 'filters', 'counts'] },
    );
    const [booking, setBooking] = useState<{
        date: string | null;
        time: string | null;
    } | null>(null);
    const [editing, setEditing] = useState<Appointment | null>(null);
    const [refreshKey, setRefreshKey] = useState(0);
    const refresh = () => setRefreshKey((k) => k + 1);

    /** Quick status change; sends only the fields it changes (notes and staff stay as they are). */
    const setStatus = (appointment: Appointment, status: AppointmentStatus) =>
        router.put(
            AppointmentController.update.url(appointment.id),
            {
                appointment_type_id: appointment.type?.id ?? null,
                scheduled_at: appointment.scheduled_at,
                status,
            },
            {
                preserveScroll: true,
                only: ['appointments', 'counts', 'flash'],
                onSuccess: refresh,
            },
        );

    const openFromCalendar = (event: CalendarEvent) => {
        if (event.extendedProps.kind === 'program') {
            router.visit(programShow(event.extendedProps.program_id));

            return;
        }

        router.reload({
            only: ['selectedAppointment'],
            data: { appointment: event.extendedProps.appointment_id },
            onSuccess: (next) => {
                const appointment = next.props.selectedAppointment as
                    | Appointment
                    | null
                    | undefined;

                if (appointment) {
                    setEditing(appointment);
                }
            },
        });
    };

    const columns = useMemo<ColumnDef<Appointment, unknown>[]>(
        () => [
            {
                id: 'when',
                header: 'When',
                cell: ({ row }) => (
                    <span className="font-bold whitespace-nowrap">
                        {formatSlot(row.original.scheduled_at)}
                    </span>
                ),
            },
            {
                id: 'resident',
                header: 'Resident',
                cell: ({ row }) =>
                    row.original.resident ? (
                        <div>
                            <Link
                                href={residentShow(row.original.resident.id)}
                                className="font-bold underline-offset-4 hover:underline"
                            >
                                {row.original.resident.full_name}
                            </Link>
                            <div className="font-mono text-sm text-muted-foreground">
                                {row.original.resident.resident_code}
                            </div>
                        </div>
                    ) : (
                        '—'
                    ),
            },
            {
                id: 'type',
                header: 'Type',
                cell: ({ row }) =>
                    row.original.type ? (
                        <TypeLabel
                            name={row.original.type.name}
                            color={row.original.type.color}
                        />
                    ) : (
                        '—'
                    ),
            },
            {
                id: 'assigned',
                header: 'Assigned to',
                meta: { hideOnMobile: true },
                cell: ({ row }) =>
                    row.original.assigned_to?.name ?? (
                        <span className="text-muted-foreground">
                            Not assigned
                        </span>
                    ),
            },
            {
                id: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <AppointmentStatusBadge status={row.original.status} />
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                meta: { cellClassName: 'w-12 text-right' },
                cell: ({ row }) => {
                    const a = row.original;
                    const name = a.resident?.full_name ?? 'appointment';

                    return (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-11"
                                    aria-label={`Actions for ${name}`}
                                >
                                    <MoreHorizontal aria-hidden />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {a.status.value !== 'completed' && (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            setStatus(a, 'completed')
                                        }
                                    >
                                        <CalendarCheck2 aria-hidden /> Mark
                                        completed
                                    </DropdownMenuItem>
                                )}
                                {a.status.value === 'scheduled' && (
                                    <DropdownMenuItem
                                        onSelect={() => setStatus(a, 'missed')}
                                    >
                                        <CalendarX2 aria-hidden /> Mark missed
                                    </DropdownMenuItem>
                                )}
                                {a.status.value === 'scheduled' && (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            setStatus(a, 'cancelled')
                                        }
                                    >
                                        <XCircle aria-hidden /> Cancel
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() => setEditing(a)}
                                >
                                    <Pencil aria-hidden /> Edit or reschedule
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    );
                },
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    return (
        <>
            <Head title="Appointments" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Appointments"
                    description="Check-ups, prenatal and immunization visits. Appointments from the schedules are created automatically."
                    actions={
                        <Button
                            onClick={() =>
                                setBooking({ date: null, time: null })
                            }
                        >
                            <Plus aria-hidden /> Book appointment
                        </Button>
                    }
                />

                <section
                    aria-label="Appointment counts"
                    className="grid gap-4 sm:grid-cols-2"
                >
                    <StatCard
                        label="Today"
                        value={counts.today}
                        icon={CalendarClock}
                        hint="Not counting cancelled"
                    />
                    <StatCard
                        label="Missed in the last 7 days"
                        value={counts.missed_last_7_days}
                        icon={CalendarX2}
                        tone={
                            counts.missed_last_7_days > 0 ? 'danger' : 'success'
                        }
                        hint="Follow these residents up"
                        href={index({ query: { status: 'missed' } })}
                    />
                </section>

                <Tabs
                    value={query.view}
                    onValueChange={(v) => update({ view: v as View })}
                    className="gap-4"
                >
                    <TabsList className="w-full justify-start sm:w-auto">
                        <TabsTrigger value="calendar" className="min-h-10">
                            <CalendarDays aria-hidden /> Calendar
                        </TabsTrigger>
                        <TabsTrigger value="list" className="min-h-10">
                            <List aria-hidden /> List
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="calendar">
                        <p className="mb-3 text-sm text-muted-foreground">
                            Click an appointment to edit it, a program to open
                            it, or an empty time to book.
                        </p>
                        <LazyCalendarView
                            refreshKey={refreshKey}
                            onEventClick={openFromCalendar}
                            onSelectSlot={(start) => {
                                const midnight =
                                    start.getHours() === 0 &&
                                    start.getMinutes() === 0;
                                setBooking({
                                    date: toDateOnly(start),
                                    time: midnight
                                        ? '09:00'
                                        : `${String(start.getHours()).padStart(2, '0')}:${String(start.getMinutes()).padStart(2, '0')}`,
                                });
                            }}
                        />
                    </TabsContent>

                    <TabsContent value="list" className="flex flex-col gap-4">
                        <SearchFilters
                            filters={[
                                {
                                    key: 'status',
                                    label: 'Status',
                                    allLabel: 'Any status',
                                    options: options.statuses,
                                    value: query.status,
                                    onChange: (v) =>
                                        update({
                                            status: v as AppointmentStatus | null,
                                        }),
                                },
                                {
                                    key: 'type',
                                    label: 'Type',
                                    allLabel: 'All types',
                                    options: options.types,
                                    value: query.appointment_type_id,
                                    onChange: (v) =>
                                        update({
                                            appointment_type_id: v
                                                ? Number(v)
                                                : null,
                                        }),
                                },
                                {
                                    key: 'assigned',
                                    label: 'Assigned to',
                                    allLabel: 'Everyone',
                                    options: [
                                        {
                                            value: 'me',
                                            label: 'Assigned to me',
                                        },
                                        {
                                            value: 'unassigned',
                                            label: 'Not assigned',
                                        },
                                    ],
                                    value: query.assigned_to,
                                    onChange: (v) =>
                                        update({
                                            assigned_to:
                                                v as AppointmentFilters['assigned_to'],
                                        }),
                                },
                            ]}
                            hasActiveFilters={Boolean(
                                query.status ||
                                query.appointment_type_id ||
                                query.assigned_to,
                            )}
                            onReset={() =>
                                reset({
                                    status: null,
                                    appointment_type_id: null,
                                    assigned_to: null,
                                })
                            }
                        >
                            <div className="grid grid-cols-2 gap-3">
                                <DateField
                                    id="date_from"
                                    label="From"
                                    disableFuture={false}
                                    value={query.date_from}
                                    onChange={(v) =>
                                        v && update({ date_from: v })
                                    }
                                />
                                <DateField
                                    id="date_to"
                                    label="To"
                                    disableFuture={false}
                                    value={query.date_to}
                                    onChange={(v) =>
                                        v && update({ date_to: v })
                                    }
                                />
                            </div>
                        </SearchFilters>

                        <DataTable
                            columns={columns}
                            data={appointments}
                            onPageChange={setPage}
                            itemLabel="appointments"
                            caption="Appointments in the selected dates"
                            processing={processing}
                            empty={
                                <EmptyState
                                    icon={CalendarClock}
                                    title="No appointments in these dates"
                                    description="Change the dates or filters, or book an appointment."
                                />
                            }
                        />
                    </TabsContent>
                </Tabs>
            </div>

            {booking && (
                <AppointmentSheet
                    open
                    onOpenChange={(o) => !o && setBooking(null)}
                    initialDate={booking.date}
                    initialTime={booking.time}
                    options={options}
                    residentOptions={residentOptions}
                    onSaved={refresh}
                />
            )}

            {editing && (
                <AppointmentSheet
                    key={editing.id}
                    open
                    onOpenChange={(o) => !o && setEditing(null)}
                    appointment={
                        selectedAppointment?.id === editing.id
                            ? selectedAppointment
                            : editing
                    }
                    options={options}
                    residentOptions={residentOptions}
                    onSaved={refresh}
                />
            )}
        </>
    );
}

AppointmentsIndex.layout = {
    breadcrumbs: [{ title: 'Appointments', href: index() }],
};
