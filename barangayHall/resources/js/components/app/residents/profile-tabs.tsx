import { Link } from '@inertiajs/react';
import {
    Baby,
    CalendarDays,
    HeartPulse,
    MoreHorizontal,
    Pencil,
    Phone,
    Pill,
    Plus,
    Ruler,
    Send,
    ShieldAlert,
    Star,
    Trash2,
} from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import { GrowthChart } from '@/components/app/growth-chart';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ageInMonths, formatDate, formatDateTime } from '@/lib/format';
import { index as appointmentsIndex } from '@/routes/appointments';
import { show as childHealthShow } from '@/routes/child-health';
import { show as pregnancyShow } from '@/routes/pregnancies';
import { show as referralShow } from '@/routes/referrals';
import type {
    Appointment,
    EmergencyContact,
    GrowthMeasurement,
    MedicalAlert,
    MicronutrientRecord,
    Pregnancy,
    Referral,
    Resident,
} from '@/types';

const severityTone: Record<string, BadgeTone> = {
    critical: 'danger',
    warning: 'warning',
    info: 'info',
};

const referralTone: Record<string, BadgeTone> = {
    pending: 'neutral',
    sent: 'info',
    received: 'primary',
    completed: 'success',
    cancelled: 'danger',
};

const urgencyTone: Record<string, BadgeTone> = {
    routine: 'neutral',
    urgent: 'warning',
    emergency: 'danger',
};

const appointmentTone: Record<string, BadgeTone> = {
    scheduled: 'info',
    completed: 'success',
    missed: 'danger',
    cancelled: 'neutral',
};

function ItemMenu({
    label,
    onEdit,
    onDelete,
}: {
    label: string;
    onEdit: () => void;
    onDelete: () => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-11 shrink-0"
                    aria-label={`Actions for ${label}`}
                >
                    <MoreHorizontal aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onSelect={onEdit}>
                    <Pencil aria-hidden /> Edit
                </DropdownMenuItem>
                <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                    <Trash2 aria-hidden /> Remove
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** Emergency contacts and medical alerts. */
export function SafetyTab({
    contacts,
    alerts,
    canRecord,
    onContact,
    onDeleteContact,
    onAlert,
    onDeleteAlert,
}: {
    contacts: EmergencyContact[];
    alerts: MedicalAlert[];
    canRecord: boolean;
    onContact: (contact: EmergencyContact | null) => void;
    onDeleteContact: (contact: EmergencyContact) => void;
    onAlert: (alert: MedicalAlert | null) => void;
    onDeleteAlert: (alert: MedicalAlert) => void;
}) {
    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader className="flex flex-row items-start justify-between gap-4">
                    <div className="space-y-1.5">
                        <CardTitle>Emergency contacts</CardTitle>
                        <CardDescription>
                            Who to call first is marked primary.
                        </CardDescription>
                    </div>
                    {canRecord && (
                        <Button
                            variant="outline"
                            onClick={() => onContact(null)}
                        >
                            <Plus aria-hidden /> Add contact
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    {contacts.length === 0 ? (
                        <EmptyState
                            icon={Phone}
                            title="No emergency contacts"
                            description="Add a family member or neighbour to call in an emergency."
                        />
                    ) : (
                        <ul className="divide-y rounded-xl border">
                            {contacts.map((c) => (
                                <li
                                    key={c.id}
                                    className="flex items-center justify-between gap-3 p-3"
                                >
                                    <div className="min-w-0 space-y-0.5">
                                        <p className="flex flex-wrap items-center gap-2 font-bold">
                                            {c.name}
                                            {c.is_primary && (
                                                <StatusBadge
                                                    tone="primary"
                                                    icon={<Star aria-hidden />}
                                                >
                                                    Primary
                                                </StatusBadge>
                                            )}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {c.relationship} ·{' '}
                                            <a
                                                href={`tel:${c.phone}`}
                                                className="font-mono text-foreground underline-offset-4 hover:underline"
                                            >
                                                {c.phone}
                                            </a>
                                        </p>
                                    </div>
                                    {canRecord && (
                                        <ItemMenu
                                            label={c.name}
                                            onEdit={() => onContact(c)}
                                            onDelete={() => onDeleteContact(c)}
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-start justify-between gap-4">
                    <div className="space-y-1.5">
                        <CardTitle>Medical alerts</CardTitle>
                        <CardDescription>
                            Allergies, chronic conditions and disabilities that
                            change how this person is treated.
                        </CardDescription>
                    </div>
                    {canRecord && (
                        <Button variant="outline" onClick={() => onAlert(null)}>
                            <Plus aria-hidden /> Add alert
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    {alerts.length === 0 ? (
                        <EmptyState
                            icon={ShieldAlert}
                            title="No medical alerts"
                            description="Record allergies and conditions staff must know before treatment."
                        />
                    ) : (
                        <ul className="divide-y rounded-xl border">
                            {alerts.map((a) => (
                                <li
                                    key={a.id}
                                    className="flex items-start justify-between gap-3 p-3"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <p className="flex flex-wrap items-center gap-2">
                                            <span className="font-bold">
                                                {a.alert_type.label}
                                            </span>
                                            <StatusBadge
                                                tone={
                                                    a.is_active
                                                        ? severityTone[
                                                              a.severity.value
                                                          ]
                                                        : 'neutral'
                                                }
                                            >
                                                {a.severity.label}
                                            </StatusBadge>
                                            {!a.is_active && (
                                                <StatusBadge tone="neutral">
                                                    No longer applies
                                                </StatusBadge>
                                            )}
                                        </p>
                                        <p
                                            className={
                                                a.is_active
                                                    ? 'text-sm'
                                                    : 'text-sm text-muted-foreground line-through'
                                            }
                                        >
                                            {a.description}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatDateTime(a.created_at)}
                                            {a.recorded_by &&
                                                ` · ${a.recorded_by}`}
                                        </p>
                                    </div>
                                    {canRecord && (
                                        <ItemMenu
                                            label={`${a.alert_type.label} alert`}
                                            onEdit={() => onAlert(a)}
                                            onDelete={() => onDeleteAlert(a)}
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

/** Women: pregnancy summary + tracker link. Children under 5: growth and supplements. */
export function GrowthTab({
    resident,
    pregnancies,
    growth,
    micronutrients,
    canRecord,
    canManageMaternal,
    onAddPregnancy,
    onEditPregnancy,
    onAddGrowth,
    onAddMicronutrient,
}: {
    resident: Resident;
    pregnancies: Pregnancy[];
    growth: GrowthMeasurement[];
    micronutrients: MicronutrientRecord[];
    canRecord: boolean;
    canManageMaternal: boolean;
    onAddPregnancy: () => void;
    onEditPregnancy: (pregnancy: Pregnancy) => void;
    onAddGrowth: () => void;
    onAddMicronutrient: () => void;
}) {
    const isChild = ageInMonths(resident.birthdate) < 60;
    const current = pregnancies.find((p) => p.status.value === 'ongoing');
    const latest = growth.at(-1);

    return (
        <div className="space-y-6">
            {!isChild && (
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div className="space-y-1.5">
                            <CardTitle>
                                {current ? 'Current pregnancy' : 'Pregnancies'}
                            </CardTitle>
                            <CardDescription>
                                Prenatal contacts, delivery and postnatal care
                                are managed in the maternal tracker.
                            </CardDescription>
                        </div>
                        {canManageMaternal && !current && (
                            <Button variant="outline" onClick={onAddPregnancy}>
                                <HeartPulse aria-hidden /> Register pregnancy
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {pregnancies.length === 0 ? (
                            <EmptyState
                                icon={HeartPulse}
                                title="No pregnancy records"
                            />
                        ) : (
                            <ul className="divide-y rounded-xl border">
                                {pregnancies.map((p) => (
                                    <li
                                        key={p.id}
                                        className="flex flex-wrap items-center justify-between gap-3 p-3"
                                    >
                                        <div className="space-y-1">
                                            <p className="flex flex-wrap items-center gap-2 font-bold">
                                                {p.status.label}
                                                <StatusBadge
                                                    tone={
                                                        p.risk_level.value ===
                                                        'high'
                                                            ? 'danger'
                                                            : 'success'
                                                    }
                                                >
                                                    {p.risk_level.label}
                                                </StatusBadge>
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                EDD {formatDate(p.edd)}
                                                {p.aog_weeks !== null &&
                                                    ` · ${p.aog_weeks} weeks`}{' '}
                                                · G{p.gravida} P{p.para}
                                                {p.outcome_date &&
                                                    ` · ended ${formatDate(p.outcome_date)}`}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {canManageMaternal && (
                                                <Button
                                                    variant="ghost"
                                                    onClick={() =>
                                                        onEditPregnancy(p)
                                                    }
                                                >
                                                    <Pencil aria-hidden />{' '}
                                                    Update
                                                </Button>
                                            )}
                                            <Button asChild variant="outline">
                                                <Link
                                                    href={pregnancyShow(p.id)}
                                                >
                                                    Open in maternal tracker
                                                </Link>
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            )}

            {(isChild || growth.length > 0) && (
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div className="space-y-1.5">
                            <CardTitle>Growth</CardTitle>
                            <CardDescription>
                                {latest
                                    ? `Last weighed ${formatDate(latest.measured_on)}${latest.nutritional_status ? ` · ${latest.nutritional_status.label}` : ''}`
                                    : 'No measurements yet.'}
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap justify-end gap-2">
                            {canRecord && (
                                <Button variant="outline" onClick={onAddGrowth}>
                                    <Ruler aria-hidden /> Add measurement
                                </Button>
                            )}
                            {isChild && (
                                <Button asChild variant="ghost">
                                    <Link href={childHealthShow(resident.id)}>
                                        <Baby aria-hidden /> Child health record
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {growth.length === 0 ? (
                            <EmptyState
                                icon={Ruler}
                                title="No measurements"
                                description="Weighing results from Operation Timbang and check-ups appear here."
                            />
                        ) : (
                            <GrowthChart
                                measurements={growth}
                                birthdate={resident.birthdate}
                            />
                        )}
                    </CardContent>
                </Card>
            )}

            {(isChild || micronutrients.length > 0) && (
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div className="space-y-1.5">
                            <CardTitle>Supplements</CardTitle>
                            <CardDescription>
                                Vitamin A, deworming and iron given.
                            </CardDescription>
                        </div>
                        {canRecord && (
                            <Button
                                variant="outline"
                                onClick={onAddMicronutrient}
                            >
                                <Pill aria-hidden /> Record
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {micronutrients.length === 0 ? (
                            <p className="text-muted-foreground">
                                Nothing recorded yet.
                            </p>
                        ) : (
                            <Table>
                                <caption className="sr-only">
                                    Supplements given
                                </caption>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Given</TableHead>
                                        <TableHead>Dose</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {micronutrients.map((m) => (
                                        <TableRow key={m.id}>
                                            <TableCell className="whitespace-nowrap">
                                                {formatDate(m.date_given)}
                                            </TableCell>
                                            <TableCell>
                                                {m.type.label}
                                            </TableCell>
                                            <TableCell>
                                                {m.dose ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

export function ReferralsTab({ referrals }: { referrals: Referral[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Referrals</CardTitle>
                <CardDescription>
                    Referrals to the RHU and hospitals. Open one to see the
                    reason, status history and return slip.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {referrals.length === 0 ? (
                    <EmptyState icon={Send} title="No referrals" />
                ) : (
                    <Table>
                        <caption className="sr-only">Referrals</caption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Referral</TableHead>
                                <TableHead>Facility</TableHead>
                                <TableHead>Urgency</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {referrals.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell>
                                        <Link
                                            href={referralShow(r.id)}
                                            className="font-mono font-bold underline-offset-4 hover:underline"
                                        >
                                            {r.referral_no}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {formatDateTime(r.referred_at)}
                                        </div>
                                    </TableCell>
                                    <TableCell>{r.facility?.name}</TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            tone={urgencyTone[r.urgency.value]}
                                        >
                                            {r.urgency.label}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            tone={referralTone[r.status.value]}
                                        >
                                            {r.status.label}
                                        </StatusBadge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}

export function AppointmentsTab({
    appointments,
    canManage,
}: {
    appointments: Appointment[];
    canManage: boolean;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle>Appointments</CardTitle>
                    <CardDescription>
                        The last 6 months and everything upcoming. Prenatal and
                        immunization visits are booked automatically.
                    </CardDescription>
                </div>
                {canManage && (
                    <Button asChild variant="outline">
                        <Link href={appointmentsIndex()}>
                            <CalendarDays aria-hidden /> All appointments
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {appointments.length === 0 ? (
                    <EmptyState icon={CalendarDays} title="No appointments" />
                ) : (
                    <Table>
                        <caption className="sr-only">Appointments</caption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>When</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead className="hidden sm:table-cell">
                                    Assigned to
                                </TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {appointments.map((a) => (
                                <TableRow key={a.id}>
                                    <TableCell className="whitespace-nowrap">
                                        {formatDateTime(a.scheduled_at)}
                                    </TableCell>
                                    <TableCell>
                                        {a.type?.name}
                                        {a.is_generated && (
                                            <span className="block text-xs text-muted-foreground">
                                                From schedule
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="hidden sm:table-cell">
                                        {a.assigned_to?.name ?? 'Unassigned'}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            tone={
                                                appointmentTone[a.status.value]
                                            }
                                        >
                                            {a.status.label}
                                        </StatusBadge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}
