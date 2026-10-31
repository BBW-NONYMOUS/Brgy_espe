import type {
    DateSelectArg,
    EventClickArg,
    EventContentArg,
} from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { events as eventsRoute } from '@/routes/calendar';
import type { CalendarEvent } from '@/types';

type Props = {
    /** Open the appointment sheet / program page. */
    onEventClick?: (event: CalendarEvent) => void;
    /** Book an appointment on an empty slot (omit for read-only calendars, e.g. Officials). */
    onSelectSlot?: (start: Date) => void;
    /** Change to refetch (e.g. after saving an appointment). */
    refreshKey?: number;
};

/**
 * Shared calendar of appointments and community programs (FullCalendar,
 * MIT plugins only). Events come from GET /calendar/events for the visible
 * range only; the server leaves out appointments for roles that may not see
 * them. Themed by the `.fc` variables in app.css. Phones start in the list
 * view, which is easier to read and tap than a month grid.
 */
export function CalendarView({
    onEventClick,
    onSelectSlot,
    refreshKey = 0,
}: Props) {
    const calendar = useRef<FullCalendar>(null);
    const isMobile = useIsMobile();
    const [loading, setLoading] = useState(false);
    // Stable source: a new object on every render makes FullCalendar drop and
    // re-add the source, which refetches, re-renders and loops.
    const eventSource = useMemo(() => ({ url: eventsRoute().url }), []);

    useEffect(() => {
        if (refreshKey > 0) {
            calendar.current?.getApi().refetchEvents();
        }
    }, [refreshKey]);

    return (
        <div className="relative" aria-busy={loading}>
            <FullCalendar
                ref={calendar}
                plugins={[
                    dayGridPlugin,
                    timeGridPlugin,
                    listPlugin,
                    interactionPlugin,
                ]}
                initialView={isMobile ? 'listWeek' : 'dayGridMonth'}
                headerToolbar={{
                    left: 'prev,next today',
                    center: 'title',
                    right: isMobile
                        ? 'listWeek,timeGridDay'
                        : 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
                }}
                buttonText={{
                    today: 'Today',
                    month: 'Month',
                    week: 'Week',
                    day: 'Day',
                    list: 'List',
                }}
                events={eventSource}
                loading={setLoading}
                height="auto"
                firstDay={0}
                slotMinTime="07:00:00"
                slotMaxTime="18:00:00"
                businessHours={{
                    daysOfWeek: [1, 2, 3, 4, 5],
                    startTime: '08:00',
                    endTime: '17:00',
                }}
                nowIndicator
                dayMaxEvents={3}
                eventTimeFormat={{
                    hour: 'numeric',
                    minute: '2-digit',
                    meridiem: 'short',
                }}
                selectable={Boolean(onSelectSlot)}
                selectMirror
                select={(arg: DateSelectArg) => onSelectSlot?.(arg.start)}
                eventClick={(arg: EventClickArg) => {
                    // Programs carry a URL; let the page decide (Inertia visit) instead of a full reload.
                    arg.jsEvent.preventDefault();
                    onEventClick?.({
                        id: arg.event.id,
                        title: arg.event.title,
                        start: arg.event.startStr,
                        end: arg.event.endStr,
                        color: arg.event.backgroundColor,
                        url: arg.event.url || undefined,
                        extendedProps: arg.event
                            .extendedProps as CalendarEvent['extendedProps'],
                    });
                }}
                eventClassNames={(arg) => {
                    const status = (
                        arg.event.extendedProps as { status?: string }
                    ).status;

                    return status === 'completed'
                        ? ['opacity-60']
                        : status === 'missed'
                          ? ['line-through']
                          : [];
                }}
                eventContent={(arg: EventContentArg) => {
                    const status = (
                        arg.event.extendedProps as { status?: string }
                    ).status;

                    return (
                        <span className="flex min-w-0 items-center gap-1 overflow-hidden px-1 text-xs">
                            {arg.timeText && (
                                <span className="font-bold">
                                    {arg.timeText}
                                </span>
                            )}
                            <span className="truncate">{arg.event.title}</span>
                            {status === 'missed' && (
                                <span className="sr-only">(missed)</span>
                            )}
                            {status === 'completed' && (
                                <span className="sr-only">(completed)</span>
                            )}
                        </span>
                    );
                }}
            />
        </div>
    );
}
