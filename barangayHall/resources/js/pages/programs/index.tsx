import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, ClipboardList, MapPin, Plus, Users } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { LazyCalendarView } from '@/components/app/lazy';
import { PageHeader } from '@/components/app/page-header';
import { ProgramSheet } from '@/components/app/scheduling/program-sheet';
import {
    formatSlot,
    ProgramStatusBadge,
    TypeLabel,
} from '@/components/app/scheduling/status';
import { SearchFilters } from '@/components/app/search-filters';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTableQuery } from '@/hooks/use-table-query';
import { index, show } from '@/routes/programs';
import type { CalendarEvent, HealthProgram } from '@/types';
import type { ProgramsPageProps } from '@/types/pages/scheduling';

export default function ProgramsIndex({
    programs,
    filters,
    options,
    canManage,
}: ProgramsPageProps) {
    const page = usePage();
    const calendarView =
        new URLSearchParams(page.url.split('?')[1] ?? '').get('view') ===
        'calendar';
    const { query, update, setPage, processing } = useTableQuery(
        { ...filters, view: calendarView ? 'calendar' : null },
        { only: ['programs', 'filters'] },
    );
    const [creating, setCreating] = useState(false);

    const openEvent = (event: CalendarEvent) => {
        if (event.extendedProps.kind === 'program') {
            router.visit(show(event.extendedProps.program_id));
        }
    };

    return (
        <>
            <Head title="Community health programs" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Community health programs"
                    description="Immunization days, feeding programs, medical missions, seminars and clean-up drives."
                    actions={
                        <>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    update({
                                        view: query.view ? null : 'calendar',
                                    })
                                }
                                aria-pressed={Boolean(query.view)}
                            >
                                <CalendarDays aria-hidden />{' '}
                                {query.view ? 'Hide calendar' : 'Show calendar'}
                            </Button>
                            {canManage && (
                                <Button onClick={() => setCreating(true)}>
                                    <Plus aria-hidden /> Schedule program
                                </Button>
                            )}
                        </>
                    }
                />

                {query.view === 'calendar' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Program calendar</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {/* Read-only here: programs are scheduled with the button above. */}
                            <LazyCalendarView onEventClick={openEvent} />
                        </CardContent>
                    </Card>
                )}

                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <Tabs
                        value={query.when}
                        onValueChange={(v) =>
                            update({ when: v as 'upcoming' | 'past' })
                        }
                    >
                        <TabsList>
                            <TabsTrigger value="upcoming" className="min-h-10">
                                Upcoming
                            </TabsTrigger>
                            <TabsTrigger value="past" className="min-h-10">
                                Past
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                    <SearchFilters
                        filters={[
                            {
                                key: 'type',
                                label: 'Type',
                                allLabel: 'All types',
                                options: options.types,
                                value: query.program_type_id,
                                onChange: (v) =>
                                    update({
                                        program_type_id: v ? Number(v) : null,
                                    }),
                            },
                        ]}
                    />
                </div>

                <section
                    aria-label={
                        query.when === 'past'
                            ? 'Past programs'
                            : 'Upcoming programs'
                    }
                    aria-busy={processing}
                >
                    {programs.data.length === 0 ? (
                        <EmptyState
                            icon={ClipboardList}
                            title={
                                query.when === 'past'
                                    ? 'No past programs'
                                    : 'No upcoming programs'
                            }
                            description={
                                canManage
                                    ? 'Schedule one with the button above.'
                                    : undefined
                            }
                        />
                    ) : (
                        <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {programs.data.map((program) => (
                                <ProgramCard
                                    key={program.id}
                                    program={program}
                                />
                            ))}
                        </ul>
                    )}
                </section>

                {programs.meta.last_page > 1 && (
                    <nav
                        aria-label="Pages"
                        className="flex items-center justify-between gap-2"
                    >
                        <Button
                            variant="outline"
                            disabled={programs.meta.current_page <= 1}
                            onClick={() =>
                                setPage(programs.meta.current_page - 1)
                            }
                        >
                            Previous
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            Page {programs.meta.current_page} of{' '}
                            {programs.meta.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={
                                programs.meta.current_page >=
                                programs.meta.last_page
                            }
                            onClick={() =>
                                setPage(programs.meta.current_page + 1)
                            }
                        >
                            Next
                        </Button>
                    </nav>
                )}
            </div>

            {creating && (
                <ProgramSheet
                    open
                    onOpenChange={setCreating}
                    options={options}
                />
            )}
        </>
    );
}

function ProgramCard({ program }: { program: HealthProgram }) {
    return (
        <li>
            <Link
                href={show(program.id)}
                className="flex h-full flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs hover:bg-accent focus-visible:bg-accent"
            >
                <div className="flex items-start justify-between gap-2">
                    <span className="text-lg font-bold">{program.title}</span>
                    <ProgramStatusBadge status={program.status} />
                </div>
                {program.type && (
                    <TypeLabel
                        name={program.type.name}
                        color={program.type.color}
                    />
                )}
                <dl className="grid gap-1 text-sm">
                    <div className="flex gap-2">
                        <dt className="sr-only">When</dt>
                        <CalendarDays
                            aria-hidden
                            className="size-4 shrink-0 text-muted-foreground"
                        />
                        <dd>{formatSlot(program.start_at)}</dd>
                    </div>
                    <div className="flex gap-2">
                        <dt className="sr-only">Venue</dt>
                        <MapPin
                            aria-hidden
                            className="size-4 shrink-0 text-muted-foreground"
                        />
                        <dd>{program.venue}</dd>
                    </div>
                    <div className="flex gap-2">
                        <dt className="sr-only">For whom</dt>
                        <Users
                            aria-hidden
                            className="size-4 shrink-0 text-muted-foreground"
                        />
                        <dd>
                            {program.target_group.label}
                            {' · '}
                            {program.puroks && program.puroks.length > 0
                                ? program.puroks.map((p) => p.name).join(', ')
                                : 'All puroks'}
                        </dd>
                    </div>
                </dl>
                <p className="mt-auto text-sm text-muted-foreground">
                    {program.attendance_count ?? 0} attended
                </p>
            </Link>
        </li>
    );
}

ProgramsIndex.layout = {
    breadcrumbs: [{ title: 'Programs', href: index() }],
};
