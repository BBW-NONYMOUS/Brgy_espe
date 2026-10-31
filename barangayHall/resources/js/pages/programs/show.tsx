import { Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    CheckSquare,
    MapPin,
    Pencil,
    Search,
    Trash2,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import HealthProgramController from '@/actions/App/Http/Controllers/Scheduling/HealthProgramController';
import ProgramAttendanceController from '@/actions/App/Http/Controllers/Scheduling/ProgramAttendanceController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { ProgramSheet } from '@/components/app/scheduling/program-sheet';
import {
    formatSlot,
    ProgramStatusBadge,
    TypeLabel,
} from '@/components/app/scheduling/status';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { index, show } from '@/routes/programs';
import type { ProgramShowProps } from '@/types/pages/scheduling';

export default function ProgramShow({
    program,
    eligibleCount,
    attendance,
    attendanceByPurok,
    eligible,
    options,
    canManage,
}: ProgramShowProps) {
    const [editing, setEditing] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const started =
        program.start_at !== null && new Date(program.start_at) <= new Date();

    return (
        <>
            <Head title={program.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    back={{ href: index(), label: 'Programs' }}
                    title={program.title}
                    meta={<ProgramStatusBadge status={program.status} />}
                    actions={
                        canManage && (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() => setEditing(true)}
                                >
                                    <Pencil aria-hidden /> Edit
                                </Button>
                                <Button
                                    variant="outline"
                                    className="text-destructive"
                                    onClick={() => setDeleting(true)}
                                >
                                    <Trash2 aria-hidden /> Remove
                                </Button>
                            </>
                        )
                    }
                />

                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2 lg:grid-cols-4">
                        <Detail icon={CalendarDays} label="When">
                            {formatSlot(program.start_at)} –{' '}
                            {formatSlot(program.end_at)}
                        </Detail>
                        <Detail icon={MapPin} label="Venue">
                            {program.venue}
                        </Detail>
                        <Detail icon={Users} label="For whom">
                            {program.target_group.label} ·{' '}
                            {program.puroks && program.puroks.length > 0
                                ? program.puroks.map((p) => p.name).join(', ')
                                : 'All puroks'}
                        </Detail>
                        <Detail icon={UserCheck} label="Staff">
                            {program.staff && program.staff.length > 0
                                ? program.staff.map((s) => s.name).join(', ')
                                : 'Not assigned'}
                        </Detail>
                        {program.type && (
                            <Detail label="Type">
                                <TypeLabel
                                    name={program.type.name}
                                    color={program.type.color}
                                />
                            </Detail>
                        )}
                        {program.description && (
                            <Detail
                                label="Details"
                                className="sm:col-span-2 lg:col-span-3"
                            >
                                {program.description}
                            </Detail>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Attendance</CardTitle>
                        <CardDescription>
                            {program.attendance_count ?? 0} attended ·{' '}
                            {eligibleCount} eligible residents (
                            {program.target_group.label.toLowerCase()}).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-6">
                        {canManage && attendance ? (
                            <>
                                {started ? (
                                    <AttendanceChecklist
                                        programId={program.id}
                                        eligible={eligible}
                                    />
                                ) : (
                                    <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                        Attendance can be taken once the program
                                        has started.
                                    </p>
                                )}
                                <Attendees attendees={attendance} />
                            </>
                        ) : (
                            <PurokCounts counts={attendanceByPurok} />
                        )}
                    </CardContent>
                </Card>
            </div>

            {editing && options && (
                <ProgramSheet
                    open
                    onOpenChange={setEditing}
                    program={program}
                    options={options}
                />
            )}

            <ConfirmDialog
                open={deleting}
                onOpenChange={setDeleting}
                title={`Remove "${program.title}"?`}
                description={
                    <p>
                        The program disappears from the calendar. Its attendance
                        record is kept in the audit log.
                    </p>
                }
                confirmLabel="Remove program"
                destructive
                onConfirm={() =>
                    router.delete(
                        HealthProgramController.destroy.url(program.id),
                    )
                }
            />
        </>
    );
}

function Detail({
    icon: Icon,
    label,
    children,
    className,
}: {
    icon?: typeof CalendarDays;
    label: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={className}>
            <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                {Icon && <Icon aria-hidden className="size-4" />}
                {label}
            </p>
            <div className="font-medium">{children}</div>
        </div>
    );
}

/**
 * Search the eligible residents (partial reload of the optional `eligible`
 * prop with ?search=) and mark the ticked ones present.
 */
function AttendanceChecklist({
    programId,
    eligible,
}: {
    programId: number;
    eligible: ProgramShowProps['eligible'];
}) {
    const [term, setTerm] = useState('');
    const [selected, setSelected] = useState<number[]>([]);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const load = (search: string) => {
        setLoading(true);
        router.reload({
            only: ['eligible'],
            data: { search },
            onFinish: () => setLoading(false),
        });
    };

    useEffect(() => {
        load('');

        return () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        };
    }, []);

    const markPresent = () =>
        router.post(
            ProgramAttendanceController.store.url(programId),
            { resident_ids: selected },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: () => {
                    setSelected([]);
                    load(term);
                },
            },
        );

    return (
        <section
            aria-labelledby="checklist-title"
            className="flex flex-col gap-3"
        >
            <h3 id="checklist-title" className="font-bold">
                Mark residents present
            </h3>
            <Label htmlFor="attendee-search" className="sr-only">
                Search eligible residents
            </Label>
            <InputGroup className="h-11">
                <InputGroupAddon>
                    <Search aria-hidden />
                </InputGroupAddon>
                <InputGroupInput
                    id="attendee-search"
                    placeholder="Search eligible residents by name or ID"
                    value={term}
                    onChange={(e) => {
                        const next = e.target.value;
                        setTerm(next);

                        if (timer.current) {
                            clearTimeout(timer.current);
                        }

                        timer.current = setTimeout(() => load(next), 300);
                    }}
                />
            </InputGroup>

            <div
                aria-busy={loading}
                className="max-h-96 overflow-y-auto rounded-lg border"
            >
                {!eligible ? (
                    <div className="flex flex-col gap-2 p-3">
                        {[0, 1, 2].map((i) => (
                            <Skeleton key={i} className="h-11 w-full" />
                        ))}
                    </div>
                ) : eligible.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        No eligible residents left to mark
                        {term ? ' for this search' : ''}.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {eligible.map((r) => (
                            <li key={r.id}>
                                <label className="flex min-h-11 cursor-pointer items-center gap-3 px-3 py-2 hover:bg-accent">
                                    <Checkbox
                                        checked={selected.includes(r.id)}
                                        onCheckedChange={(on) =>
                                            setSelected((s) =>
                                                on === true
                                                    ? [...s, r.id]
                                                    : s.filter(
                                                          (x) => x !== r.id,
                                                      ),
                                            )
                                        }
                                        aria-label={`${r.full_name}, ${r.age} years, ${r.purok}`}
                                    />
                                    <span className="flex-1 font-medium">
                                        {r.full_name}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {r.age} yrs · {r.purok}
                                    </span>
                                </label>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <div>
                <Button
                    onClick={markPresent}
                    disabled={selected.length === 0 || saving}
                >
                    {saving ? <Spinner /> : <CheckSquare aria-hidden />}
                    Mark {selected.length > 0 ? selected.length : ''} present
                </Button>
            </div>
        </section>
    );
}

function Attendees({
    attendees,
}: {
    attendees: NonNullable<ProgramShowProps['attendance']>;
}) {
    if (attendees.length === 0) {
        return (
            <EmptyState icon={UserCheck} title="No one marked present yet" />
        );
    }

    return (
        <Table>
            <TableCaption className="sr-only">
                Residents who attended
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Resident</TableHead>
                    <TableHead>Purok</TableHead>
                    <TableHead className="hidden sm:table-cell">
                        Recorded
                    </TableHead>
                    <TableHead className="w-12 text-right">
                        <span className="sr-only">Remove</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {attendees.map((a) => (
                    <TableRow key={a.id}>
                        <TableCell>
                            <div className="font-medium">{a.full_name}</div>
                            <div className="font-mono text-sm text-muted-foreground">
                                {a.resident_code}
                            </div>
                        </TableCell>
                        <TableCell>{a.purok}</TableCell>
                        <TableCell className="hidden sm:table-cell">
                            {formatDateTime(a.attended_at)}
                        </TableCell>
                        <TableCell className="text-right">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-11"
                                aria-label={`Remove ${a.full_name} from the attendance list`}
                                onClick={() =>
                                    router.delete(
                                        ProgramAttendanceController.destroy.url(
                                            a.id,
                                        ),
                                        {
                                            preserveScroll: true,
                                            only: [
                                                'attendance',
                                                'program',
                                                'attendanceByPurok',
                                                'flash',
                                            ],
                                        },
                                    )
                                }
                            >
                                <X aria-hidden />
                            </Button>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

/** Officials: counts only, never names. */
function PurokCounts({ counts }: { counts: Record<string, number> }) {
    const rows = Object.entries(counts);

    if (rows.length === 0) {
        return <EmptyState icon={Users} title="No attendance recorded yet" />;
    }

    return (
        <Table>
            <TableCaption className="sr-only">
                Attendance per purok
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Purok</TableHead>
                    <TableHead className="text-right">Attended</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map(([purok, total]) => (
                    <TableRow key={purok}>
                        <TableCell>{purok}</TableCell>
                        <TableCell className="text-right font-bold">
                            {total}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

ProgramShow.layout = (props: ProgramShowProps) => ({
    breadcrumbs: [
        { title: 'Programs', href: index() },
        { title: props.program.title, href: show(props.program.id) },
    ],
});
