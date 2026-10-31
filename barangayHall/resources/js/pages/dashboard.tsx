import { Deferred, Link, usePage } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import {
    Accessibility,
    Activity,
    BarChart3,
    CalendarClock,
    CalendarDays,
    ClipboardList,
    HeartPulse,
    Home,
    Package,
    PackageX,
    Plus,
    Siren,
    Syringe,
    UserRound,
    Users,
} from 'lucide-react';
import { AgeSexPyramid, PurokChart } from '@/components/app/charts';
import { DiseaseTrendChart } from '@/components/app/dashboard/disease-trend-chart';
import {
    TodaysAppointments,
    UpcomingPrograms,
} from '@/components/app/dashboard/service-cards';
import { EmptyState } from '@/components/app/empty-state';
import { OutbreakBanner } from '@/components/app/outbreak-banner';
import { PageHeader } from '@/components/app/page-header';
import { StatCard, StatCardSkeleton } from '@/components/app/stat-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatRelative } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as appointmentsIndex } from '@/routes/appointments';
import { index as medicinesIndex } from '@/routes/medicines';
import { index as outbreakAlertsIndex } from '@/routes/outbreak-alerts';
import { index as programsIndex } from '@/routes/programs';
import { index as reportsIndex } from '@/routes/reports';
import {
    create as createResident,
    index as residentsIndex,
    show as showResident,
} from '@/routes/residents';
import type {
    AgeSexRow,
    Appointment,
    HealthProgram,
    OutbreakAlert,
    PurokPopulation,
    RecentActivity,
    ReportTotals,
} from '@/types';
import type { DiseaseTrendWeek, ServiceStats } from '@/types/pages/scheduling';

/*
 * Props that are `null` were withheld by the server for this role; the
 * matching card is not rendered. `undefined` = deferred, still loading.
 */
type Props = {
    stats?: ReportTotals;
    residentsPerPurok?: PurokPopulation[];
    ageSex?: AgeSexRow[];
    recentActivity?: RecentActivity[] | null;
    outbreaks?: OutbreakAlert[] | null;
    diseaseTrend?: DiseaseTrendWeek[] | null;
    serviceStats?: ServiceStats;
    todaysAppointments?: Appointment[] | null;
    upcomingPrograms?: HealthProgram[] | null;
};

/** "Hon. Ronaldo Catalan" → "Ronaldo", "Ma. Teresa Alonzo" → "Ma. Teresa", "Cristina Gelito, RM" → "Cristina". */
function givenName(fullName: string): string {
    const words = fullName
        .split(',')[0]
        .replace(/^(Hon|Dr|Mr|Mrs|Ms)\.\s+/i, '')
        .split(/\s+/);

    // Abbreviated first names ("Ma.", "Jo.") read better with the next word.
    return words[0].endsWith('.') && words[1]
        ? `${words[0]} ${words[1]}`
        : words[0];
}

function greeting(): string {
    const hour = new Date().getHours();

    return hour < 12
        ? 'Magandang umaga'
        : hour < 18
          ? 'Magandang hapon'
          : 'Magandang gabi';
}

export default function Dashboard({
    stats,
    residentsPerPurok,
    ageSex,
    recentActivity,
    outbreaks,
    diseaseTrend,
    serviceStats,
    todaysAppointments,
    upcomingPrograms,
}: Props) {
    const { auth, can } = usePage().props;
    const firstName = givenName(auth.user.name);
    // Officials have no access to the resident list, so their cards are not links.
    const residentsLink = (query: Record<string, string>) =>
        can.viewResidents ? residentsIndex({ query }) : undefined;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`${greeting()}, ${firstName}!`}
                    description="Here is the health center at a glance."
                    actions={
                        <>
                            {can.viewReports && (
                                <Button variant="outline" asChild>
                                    <Link href={reportsIndex()}>
                                        <BarChart3 aria-hidden /> Reports
                                    </Link>
                                </Button>
                            )}
                            {can.createResidents && (
                                <Button asChild>
                                    <Link href={createResident()}>
                                        <Plus aria-hidden /> Register resident
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                {outbreaks && <OutbreakBanner alerts={outbreaks} />}

                <section
                    aria-label="Key numbers"
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6"
                >
                    <Deferred
                        data="stats"
                        fallback={Array.from({ length: 6 }, (_, i) => (
                            <StatCardSkeleton key={i} />
                        ))}
                    >
                        {stats && (
                            <>
                                <StatCard
                                    label="Residents"
                                    value={stats.residents}
                                    icon={Users}
                                    hint={`${stats.male} male · ${stats.female} female`}
                                    href={residentsLink({})}
                                />
                                <StatCard
                                    label="Households"
                                    value={stats.households}
                                    icon={Home}
                                    tone="info"
                                />
                                <StatCard
                                    label="Senior citizens"
                                    value={stats.seniors}
                                    icon={UserRound}
                                    tone="info"
                                    hint="Aged 60 and above"
                                    href={residentsLink({
                                        age_group: 'senior',
                                    })}
                                />
                                <StatCard
                                    label="Pregnant women"
                                    value={stats.pregnant}
                                    icon={HeartPulse}
                                    tone="success"
                                    hint="Ongoing pregnancies"
                                    href={
                                        can.viewReports
                                            ? reportsIndex()
                                            : undefined
                                    }
                                />
                                <StatCard
                                    label="Children due for vaccines"
                                    value={stats.children_due}
                                    icon={Syringe}
                                    tone={
                                        stats.children_due > 0
                                            ? 'warning'
                                            : 'success'
                                    }
                                    hint="0–23 months, DOH EPI"
                                    href={
                                        can.viewReports
                                            ? reportsIndex()
                                            : undefined
                                    }
                                />
                                <StatCard
                                    label="Persons with disability"
                                    value={stats.pwd}
                                    icon={Accessibility}
                                    tone="primary"
                                    hint={`${stats.four_ps} 4Ps members`}
                                />
                            </>
                        )}
                    </Deferred>
                </section>

                {(can.viewOutbreaks ||
                    can.viewInventory ||
                    can.manageAppointments) && (
                    <section
                        aria-label="Alerts and services"
                        className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        <Deferred
                            data="serviceStats"
                            fallback={Array.from({ length: 4 }, (_, i) => (
                                <StatCardSkeleton key={i} />
                            ))}
                        >
                            {serviceStats && (
                                <ServiceStatCards stats={serviceStats} />
                            )}
                        </Deferred>
                    </section>
                )}

                {(diseaseTrend !== null ||
                    todaysAppointments !== null ||
                    upcomingPrograms !== null) && (
                    <div className="grid gap-6 xl:grid-cols-3">
                        {diseaseTrend !== null && (
                            <Card className="xl:col-span-2">
                                <CardHeader>
                                    <CardTitle>
                                        Disease cases, last 12 weeks
                                    </CardTitle>
                                    <CardDescription>
                                        All diseases, by week of onset
                                        (ruled-out cases not counted)
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Deferred
                                        data="diseaseTrend"
                                        fallback={
                                            <Skeleton className="h-64 w-full" />
                                        }
                                    >
                                        {diseaseTrend && (
                                            <DiseaseTrendChart
                                                data={diseaseTrend}
                                            />
                                        )}
                                    </Deferred>
                                </CardContent>
                            </Card>
                        )}

                        {todaysAppointments !== null && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CalendarClock
                                            aria-hidden
                                            className="size-5"
                                        />
                                        Today's appointments
                                    </CardTitle>
                                    <CardDescription>
                                        <Link
                                            href={appointmentsIndex({
                                                query: { view: 'calendar' },
                                            })}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            Open the calendar
                                        </Link>
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Deferred
                                        data="todaysAppointments"
                                        fallback={<ActivitySkeleton />}
                                    >
                                        {todaysAppointments && (
                                            <TodaysAppointments
                                                items={todaysAppointments}
                                            />
                                        )}
                                    </Deferred>
                                </CardContent>
                            </Card>
                        )}

                        {upcomingPrograms !== null && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <ClipboardList
                                            aria-hidden
                                            className="size-5"
                                        />
                                        Upcoming programs
                                    </CardTitle>
                                    <CardDescription>
                                        <Link
                                            href={programsIndex()}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            All programs
                                        </Link>
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Deferred
                                        data="upcomingPrograms"
                                        fallback={<ActivitySkeleton />}
                                    >
                                        {upcomingPrograms && (
                                            <UpcomingPrograms
                                                items={upcomingPrograms}
                                            />
                                        )}
                                    </Deferred>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                <div className="grid gap-6 xl:grid-cols-3">
                    <Card className="xl:col-span-2">
                        <CardHeader>
                            <CardTitle>Residents per purok</CardTitle>
                            <CardDescription>
                                Active residents, by sex
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Deferred
                                data="residentsPerPurok"
                                fallback={<Skeleton className="h-72 w-full" />}
                            >
                                {residentsPerPurok && (
                                    <PurokChart data={residentsPerPurok} />
                                )}
                            </Deferred>
                        </CardContent>
                    </Card>

                    {recentActivity !== null && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Recent activity</CardTitle>
                                <CardDescription>
                                    Latest changes to resident and health
                                    records
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Deferred
                                    data="recentActivity"
                                    fallback={<ActivitySkeleton />}
                                >
                                    {recentActivity && (
                                        <ActivityList items={recentActivity} />
                                    )}
                                </Deferred>
                            </CardContent>
                        </Card>
                    )}

                    <Card
                        className={
                            recentActivity === null
                                ? 'xl:col-span-1'
                                : 'xl:col-span-3'
                        }
                    >
                        <CardHeader>
                            <CardTitle>Age and sex distribution</CardTitle>
                            <CardDescription>
                                Population pyramid of active residents
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Deferred
                                data="ageSex"
                                fallback={<Skeleton className="h-96 w-full" />}
                            >
                                {ageSex && <AgeSexPyramid data={ageSex} />}
                            </Deferred>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

/**
 * Outbreaks, medicines and appointments — each card only when the role may
 * see it (the server leaves the key out otherwise).
 */
function ServiceStatCards({ stats }: { stats: ServiceStats }) {
    return (
        <>
            {stats.active_outbreaks !== undefined && (
                <StatCard
                    label="Active outbreak alerts"
                    value={stats.active_outbreaks}
                    icon={Siren}
                    tone={stats.active_outbreaks > 0 ? 'danger' : 'success'}
                    hint={
                        stats.active_outbreaks > 0
                            ? 'Response needed'
                            : 'No open alerts'
                    }
                    href={outbreakAlertsIndex()}
                />
            )}
            {stats.expiring_batches !== undefined && (
                <StatCard
                    label="Medicines expiring"
                    value={stats.expiring_batches}
                    icon={Package}
                    tone={stats.expiring_batches > 0 ? 'warning' : 'success'}
                    hint="Batches expired or within 30 days"
                    href={medicinesIndex({ query: { expiry: 'soon' } })}
                />
            )}
            {stats.low_stock_medicines !== undefined && (
                <StatCard
                    label="Low on stock"
                    value={stats.low_stock_medicines}
                    icon={PackageX}
                    tone={stats.low_stock_medicines > 0 ? 'warning' : 'success'}
                    hint="At or below the reorder level"
                    href={medicinesIndex({ query: { stock: 'low' } })}
                />
            )}
            {stats.appointments_today !== undefined && (
                <StatCard
                    label="Appointments today"
                    value={stats.appointments_today}
                    icon={CalendarDays}
                    tone={
                        (stats.missed_appointments ?? 0) > 0
                            ? 'warning'
                            : 'info'
                    }
                    hint={`${stats.missed_appointments ?? 0} missed in the last 7 days`}
                    href={appointmentsIndex({ query: { view: 'calendar' } })}
                />
            )}
        </>
    );
}

function ActivityList({ items }: { items: RecentActivity[] }) {
    if (items.length === 0) {
        return (
            <EmptyState
                icon={Activity}
                title="No activity yet"
                description="New registrations and health visits will show here."
            />
        );
    }

    return (
        <ol className="space-y-4">
            {items.map((item) => {
                const href =
                    item.table_name === 'residents' &&
                    item.record_id &&
                    item.action !== 'deleted'
                        ? showResident(item.record_id)
                        : null;

                return (
                    <li key={item.id} className="flex gap-3">
                        <span
                            className="mt-1.5 size-2.5 shrink-0 rounded-full bg-primary"
                            aria-hidden
                        />
                        <div className="min-w-0 text-sm">
                            <p>
                                <strong>{item.user ?? 'System'}</strong>{' '}
                                {href ? (
                                    <Link
                                        href={href}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {item.description?.toLowerCase()}
                                    </Link>
                                ) : (
                                    item.description?.toLowerCase()
                                )}
                            </p>
                            <p className="text-muted-foreground">
                                <time dateTime={item.created_at}>
                                    {formatRelative(item.created_at)}
                                </time>
                            </p>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

function ActivitySkeleton() {
    return (
        <div className="space-y-4" aria-hidden>
            {Array.from({ length: 5 }, (_, i) => (
                <div key={i} className="space-y-2">
                    <Skeleton className="h-4 w-4/5" />
                    <Skeleton className="h-3 w-1/3" />
                </div>
            ))}
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
