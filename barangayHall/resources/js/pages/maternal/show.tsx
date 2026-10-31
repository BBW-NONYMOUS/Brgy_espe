import { Head, Link } from '@inertiajs/react';
import {
    Baby,
    CalendarDays,
    CircleAlert,
    Pencil,
    Stethoscope,
} from 'lucide-react';
import { useState } from 'react';
import { ContactSchedule } from '@/components/app/contact-schedule';
import { EmptyState } from '@/components/app/empty-state';
import { HealthTimeline } from '@/components/app/health-timeline';
import {
    DeliveryForm,
    MaternalVisitSheet,
    PregnancyEditDialog,
} from '@/components/app/maternal/pregnancy-forms';
import { MedicalAlertBanner } from '@/components/app/medical-alert-banner';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import type { BadgeTone } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate, formatDateTime } from '@/lib/format';
import { show as childShow } from '@/routes/child-health';
import { index, show } from '@/routes/pregnancies';
import { show as residentShow } from '@/routes/residents';
import type { MaternalContact } from '@/types';
import type { MaternalShowPageProps } from '@/types/pages/health';

const appointmentTone: Record<string, BadgeTone> = {
    scheduled: 'info',
    completed: 'success',
    missed: 'danger',
    cancelled: 'neutral',
};

function Detail({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="font-medium">{children}</dd>
        </div>
    );
}

export default function MaternalShow({
    pregnancy,
    criticalAlerts,
    contacts,
    visits,
    appointments,
    newborns,
    options,
}: MaternalShowPageProps) {
    const [editing, setEditing] = useState(false);
    const [visitFor, setVisitFor] = useState<
        MaternalContact | null | undefined
    >(undefined);
    const ongoing = pregnancy.status.value === 'ongoing';
    const delivered = pregnancy.status.value === 'delivered';
    const resident = pregnancy.resident;
    const overdue = contacts.filter((c) => c.status === 'overdue').length;

    return (
        <>
            <Head title={`Pregnancy — ${resident?.full_name ?? ''}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <MedicalAlertBanner alerts={criticalAlerts} />

                <PageHeader
                    back={{ href: index(), label: 'Maternal tracker' }}
                    title={resident?.full_name ?? 'Pregnancy'}
                    meta={
                        <>
                            <StatusBadge
                                tone={
                                    pregnancy.risk_level.value === 'high'
                                        ? 'danger'
                                        : 'success'
                                }
                                icon={
                                    pregnancy.risk_level.value === 'high' ? (
                                        <CircleAlert aria-hidden />
                                    ) : undefined
                                }
                            >
                                {pregnancy.risk_level.label}
                            </StatusBadge>
                            <StatusBadge tone={ongoing ? 'info' : 'neutral'}>
                                {pregnancy.status.label}
                            </StatusBadge>
                            {overdue > 0 && (
                                <StatusBadge tone="danger">
                                    {overdue} overdue contact
                                    {overdue > 1 ? 's' : ''}
                                </StatusBadge>
                            )}
                        </>
                    }
                    description={
                        resident && (
                            <>
                                {resident.resident_code} · {resident.age} yrs ·{' '}
                                {resident.purok} ·{' '}
                                <Link
                                    href={residentShow(resident.id)}
                                    className="font-bold text-primary underline-offset-4 hover:underline"
                                >
                                    Resident profile
                                </Link>
                            </>
                        )
                    }
                    actions={
                        <>
                            {options && (ongoing || delivered) && (
                                <Button onClick={() => setVisitFor(null)}>
                                    <Stethoscope aria-hidden /> Record{' '}
                                    {ongoing ? 'prenatal' : 'postnatal'}{' '}
                                    check-up
                                </Button>
                            )}
                            {options && (
                                <Button
                                    variant="outline"
                                    onClick={() => setEditing(true)}
                                >
                                    <Pencil aria-hidden /> Edit
                                </Button>
                            )}
                        </>
                    }
                />

                <Card>
                    <CardContent className="pt-6">
                        <dl className="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                            <Detail label="LMP">
                                {formatDate(pregnancy.lmp) || '—'}
                            </Detail>
                            <Detail label="EDD">
                                {formatDate(pregnancy.edd)}
                            </Detail>
                            <Detail
                                label={ongoing ? 'Age of gestation' : 'Ended'}
                            >
                                {ongoing
                                    ? `${pregnancy.aog_weeks ?? '—'} weeks`
                                    : formatDate(pregnancy.outcome_date)}
                            </Detail>
                            <Detail label="Gravida / para">
                                G{pregnancy.gravida} P{pregnancy.para}
                            </Detail>
                            <div className="sm:col-span-2">
                                <dt className="text-sm text-muted-foreground">
                                    Risk factors
                                </dt>
                                <dd className="mt-1 flex flex-wrap gap-1">
                                    {pregnancy.risk_factors &&
                                    pregnancy.risk_factors.length > 0 ? (
                                        pregnancy.risk_factors.map((f) => (
                                            <StatusBadge
                                                key={f.id}
                                                tone="danger"
                                            >
                                                {f.name}
                                            </StatusBadge>
                                        ))
                                    ) : (
                                        <span className="font-medium">
                                            None recorded
                                        </span>
                                    )}
                                </dd>
                            </div>
                        </dl>
                        {pregnancy.notes && (
                            <p className="mt-4 rounded-md bg-muted p-3 text-sm">
                                <span className="font-bold">Notes: </span>
                                {pregnancy.notes}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Tabs defaultValue="schedule" className="gap-4">
                    <TabsList className="h-auto w-full flex-wrap justify-start">
                        <TabsTrigger value="schedule" className="min-h-10">
                            Schedule
                        </TabsTrigger>
                        <TabsTrigger value="visits" className="min-h-10">
                            Visits ({visits.length})
                        </TabsTrigger>
                        <TabsTrigger value="delivery" className="min-h-10">
                            Delivery
                        </TabsTrigger>
                        <TabsTrigger value="appointments" className="min-h-10">
                            Appointments ({appointments.length})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="schedule">
                        <Card>
                            <CardHeader>
                                <CardTitle>Contact schedule</CardTitle>
                                <CardDescription>
                                    From the prenatal/postnatal schedule in
                                    Settings. A visit is linked to the earliest
                                    contact it fulfils.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ContactSchedule
                                    contacts={contacts}
                                    onRecord={
                                        options
                                            ? (c) => setVisitFor(c)
                                            : undefined
                                    }
                                />
                                {!options && resident && (
                                    <p className="mt-4 text-sm text-muted-foreground">
                                        To record a check-up, open the{' '}
                                        <Link
                                            href={residentShow(resident.id)}
                                            className="font-bold text-primary underline"
                                        >
                                            resident profile
                                        </Link>{' '}
                                        and use <b>Record visit</b> (choose this
                                        pregnancy).
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="visits">
                        <HealthTimeline visits={visits} />
                    </TabsContent>

                    <TabsContent value="delivery" className="space-y-4">
                        {pregnancy.delivery ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Delivery</CardTitle>
                                    <CardDescription>
                                        {formatDate(pregnancy.outcome_date)}
                                        {pregnancy.delivery.delivery_time &&
                                            ` at ${pregnancy.delivery.delivery_time}`}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <dl className="grid gap-4 sm:grid-cols-3">
                                        <Detail label="Place">
                                            {
                                                pregnancy.delivery.place_type
                                                    .label
                                            }
                                            {pregnancy.delivery.place_name &&
                                                ` — ${pregnancy.delivery.place_name}`}
                                        </Detail>
                                        <Detail label="Attended by">
                                            {
                                                pregnancy.delivery
                                                    .attendant_type.label
                                            }
                                        </Detail>
                                        <Detail label="Type of delivery">
                                            {
                                                pregnancy.delivery.delivery_mode
                                                    .label
                                            }
                                        </Detail>
                                        <Detail label="Live births">
                                            {pregnancy.delivery.live_births}
                                        </Detail>
                                        <Detail label="Stillbirths">
                                            {pregnancy.delivery.stillbirths}
                                        </Detail>
                                        <Detail label="DOH indicators">
                                            <span className="flex flex-wrap gap-1">
                                                <StatusBadge
                                                    tone={
                                                        pregnancy.delivery
                                                            .facility_based
                                                            ? 'success'
                                                            : 'warning'
                                                    }
                                                >
                                                    {pregnancy.delivery
                                                        .facility_based
                                                        ? 'Facility-based'
                                                        : 'Not facility-based'}
                                                </StatusBadge>
                                                <StatusBadge
                                                    tone={
                                                        pregnancy.delivery
                                                            .skilled_attendant
                                                            ? 'success'
                                                            : 'warning'
                                                    }
                                                >
                                                    {pregnancy.delivery
                                                        .skilled_attendant
                                                        ? 'Skilled attendant'
                                                        : 'No skilled attendant'}
                                                </StatusBadge>
                                            </span>
                                        </Detail>
                                    </dl>
                                    {pregnancy.delivery.remarks && (
                                        <p className="rounded-md bg-muted p-3 text-sm">
                                            {pregnancy.delivery.remarks}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        ) : ongoing && options ? (
                            <DeliveryForm
                                pregnancy={pregnancy}
                                options={options}
                            />
                        ) : (
                            <EmptyState
                                icon={Baby}
                                title={
                                    ongoing
                                        ? 'Not delivered yet'
                                        : 'No delivery record'
                                }
                                description={
                                    ongoing
                                        ? 'The midwife records the delivery here after the birth.'
                                        : pregnancy.status.value ===
                                            'terminated'
                                          ? 'This pregnancy ended without a delivery.'
                                          : 'The delivery details were not recorded.'
                                }
                            />
                        )}

                        {newborns.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Newborns</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="divide-y">
                                        {newborns.map((n) => (
                                            <li
                                                key={n.id}
                                                className="flex items-center justify-between gap-3 py-2"
                                            >
                                                <span>
                                                    <span className="font-bold">
                                                        {n.full_name}
                                                    </span>{' '}
                                                    <span className="text-sm text-muted-foreground">
                                                        {n.resident_code} ·{' '}
                                                        {n.sex}
                                                    </span>
                                                </span>
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={childShow(n.id)}
                                                    >
                                                        <Baby aria-hidden />{' '}
                                                        Child health
                                                    </Link>
                                                </Button>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>

                    <TabsContent value="appointments">
                        {appointments.length === 0 ? (
                            <EmptyState
                                icon={CalendarDays}
                                title="No appointments"
                                description="Prenatal and postnatal appointments are booked automatically for contacts due in the next 60 days."
                            />
                        ) : (
                            <div className="overflow-x-auto rounded-xl border bg-card">
                                <Table>
                                    <caption className="sr-only">
                                        Appointments for this pregnancy
                                    </caption>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>When</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="hidden md:table-cell">
                                                Assigned to
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {appointments.map((a) => (
                                            <TableRow key={a.id}>
                                                <TableCell className="whitespace-nowrap">
                                                    {formatDateTime(
                                                        a.scheduled_at,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {a.type?.name}
                                                </TableCell>
                                                <TableCell>
                                                    <StatusBadge
                                                        tone={
                                                            appointmentTone[
                                                                a.status.value
                                                            ]
                                                        }
                                                    >
                                                        {a.status.label}
                                                    </StatusBadge>
                                                </TableCell>
                                                <TableCell className="hidden md:table-cell">
                                                    {a.assigned_to?.name ?? (
                                                        <span className="text-muted-foreground">
                                                            Unassigned
                                                        </span>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </TabsContent>
                </Tabs>
            </div>

            {options && editing && (
                <PregnancyEditDialog
                    pregnancy={pregnancy}
                    options={options}
                    open
                    onOpenChange={setEditing}
                />
            )}
            {options && visitFor !== undefined && (
                <MaternalVisitSheet
                    key={visitFor?.rule_id ?? 'free'}
                    pregnancy={pregnancy}
                    contact={visitFor}
                    options={options}
                    alerts={criticalAlerts}
                    open
                    onOpenChange={(open) => !open && setVisitFor(undefined)}
                />
            )}
        </>
    );
}

MaternalShow.layout = (props: MaternalShowPageProps) => ({
    breadcrumbs: [
        { title: 'Maternal tracker', href: index() },
        {
            title: props.pregnancy.resident?.full_name ?? 'Pregnancy',
            href: show(props.pregnancy.id),
        },
    ],
});
