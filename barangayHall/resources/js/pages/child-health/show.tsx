import { Head, Link, router } from '@inertiajs/react';
import { Pill, Plus, Ruler, Syringe, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ChildMeasurementController from '@/actions/App/Http/Controllers/Health/ChildMeasurementController';
import {
    DoseDialog,
    GrowthDialog,
    MicronutrientDialog,
} from '@/components/app/child-health/child-forms';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { EmptyState } from '@/components/app/empty-state';
import { GrowthChart } from '@/components/app/growth-chart';
import { ImmunizationChecklist } from '@/components/app/immunization-checklist';
import { MedicalAlertBanner } from '@/components/app/medical-alert-banner';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
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
import { formatDate } from '@/lib/format';
import { index, show } from '@/routes/child-health';
import { show as householdShow } from '@/routes/households';
import { show as residentShow } from '@/routes/residents';
import type {
    GrowthMeasurement,
    MicronutrientRecord,
    ScheduledDose,
} from '@/types';
import type { ChildHealthShowPageProps } from '@/types/pages/health';

type Removing =
    | { kind: 'growth'; item: GrowthMeasurement }
    | { kind: 'micronutrient'; item: MicronutrientRecord };

function ageLabel(months: number): string {
    return months < 24
        ? `${months} months old`
        : `${Math.floor(months / 12)} yr ${months % 12} mo old`;
}

export default function ChildHealthShow({
    child,
    criticalAlerts,
    immunizationSchedule,
    growth,
    micronutrients,
    micronutrientStatus,
    options,
}: ChildHealthShowPageProps) {
    const [dose, setDose] = useState<ScheduledDose | null | undefined>(
        undefined,
    );
    const [addingGrowth, setAddingGrowth] = useState(false);
    const [supplement, setSupplement] = useState<string | undefined>(undefined);
    const [removing, setRemoving] = useState<Removing | null>(null);
    const [removeProcessing, setRemoveProcessing] = useState(false);
    const latest = growth.length > 0 ? growth[growth.length - 1] : null;

    const remove = () => {
        if (!removing) {
            return;
        }

        router.delete(
            removing.kind === 'growth'
                ? ChildMeasurementController.destroyGrowth.url(removing.item.id)
                : ChildMeasurementController.destroyMicronutrient.url(
                      removing.item.id,
                  ),
            {
                preserveScroll: true,
                onStart: () => setRemoveProcessing(true),
                onFinish: () => {
                    setRemoveProcessing(false);
                    setRemoving(null);
                },
            },
        );
    };

    return (
        <>
            <Head title={`Child health — ${child.full_name}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <MedicalAlertBanner alerts={criticalAlerts} />

                <PageHeader
                    back={{ href: index(), label: 'Child health' }}
                    title={child.full_name}
                    meta={
                        latest?.nutritional_status && (
                            <StatusBadge
                                tone={
                                    latest.nutritional_status.value === 'normal'
                                        ? 'success'
                                        : 'caution'
                                }
                            >
                                {latest.nutritional_status.label}
                            </StatusBadge>
                        )
                    }
                    description={
                        <>
                            {child.resident_code} · {child.sex.label} ·{' '}
                            {ageLabel(child.age_months)} (born{' '}
                            {formatDate(child.birthdate)}) · {child.purok}
                            {child.mother && (
                                <>
                                    {' '}
                                    · Mother:{' '}
                                    <Link
                                        href={residentShow(child.mother.id)}
                                        className="font-bold text-primary underline-offset-4 hover:underline"
                                    >
                                        {child.mother.full_name}
                                    </Link>
                                </>
                            )}
                        </>
                    }
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link href={residentShow(child.id)}>
                                    Resident profile
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={householdShow(child.household_id)}>
                                    Household
                                </Link>
                            </Button>
                        </>
                    }
                />

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-3">
                        <div>
                            <CardTitle>Immunization (DOH EPI)</CardTitle>
                            <CardDescription>
                                Tap a due or overdue dose to record it.
                            </CardDescription>
                        </div>
                        {options && (
                            <Button
                                variant="outline"
                                onClick={() => setDose(null)}
                            >
                                <Syringe aria-hidden /> Record dose
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {immunizationSchedule.length > 0 ? (
                            <ImmunizationChecklist
                                doses={immunizationSchedule}
                                onRecord={
                                    options ? (d) => setDose(d) : undefined
                                }
                            />
                        ) : (
                            <EmptyState
                                icon={Syringe}
                                title="No scheduled vaccines"
                                description="The immunization schedule is set in Settings."
                            />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-3">
                        <div>
                            <CardTitle>Growth</CardTitle>
                            <CardDescription>
                                Weighing sessions and check-ups. Status read
                                from the OPT Plus chart.
                            </CardDescription>
                        </div>
                        {options && (
                            <Button
                                variant="outline"
                                onClick={() => setAddingGrowth(true)}
                            >
                                <Plus aria-hidden /> Add measurement
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {growth.length === 0 ? (
                            <EmptyState
                                icon={Ruler}
                                title="No measurements yet"
                                description="Weigh the child at the next Operation Timbang or check-up."
                            />
                        ) : (
                            <>
                                {growth.length > 1 && (
                                    <GrowthChart
                                        measurements={growth}
                                        birthdate={child.birthdate}
                                    />
                                )}
                                <div className="overflow-x-auto rounded-lg border">
                                    <Table>
                                        <caption className="sr-only">
                                            Growth measurements
                                        </caption>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Date</TableHead>
                                                <TableHead className="text-right">
                                                    Weight
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Length/height
                                                </TableHead>
                                                <TableHead className="hidden text-right sm:table-cell">
                                                    MUAC
                                                </TableHead>
                                                <TableHead>Status</TableHead>
                                                {options && (
                                                    <TableHead>
                                                        <span className="sr-only">
                                                            Actions
                                                        </span>
                                                    </TableHead>
                                                )}
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {[...growth].reverse().map((m) => (
                                                <TableRow key={m.id}>
                                                    <TableCell className="whitespace-nowrap">
                                                        {formatDate(
                                                            m.measured_on,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {m.weight_kg} kg
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {m.height_cm !== null
                                                            ? `${m.height_cm} cm`
                                                            : '—'}
                                                    </TableCell>
                                                    <TableCell className="hidden text-right sm:table-cell">
                                                        {m.muac_cm !== null
                                                            ? `${m.muac_cm} cm`
                                                            : '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {m.nutritional_status ? (
                                                            <StatusBadge
                                                                tone={
                                                                    m
                                                                        .nutritional_status
                                                                        .value ===
                                                                    'normal'
                                                                        ? 'success'
                                                                        : 'caution'
                                                                }
                                                            >
                                                                {
                                                                    m
                                                                        .nutritional_status
                                                                        .label
                                                                }
                                                            </StatusBadge>
                                                        ) : (
                                                            '—'
                                                        )}
                                                        {m.remarks && (
                                                            <div className="mt-1 text-xs text-muted-foreground">
                                                                {m.remarks}
                                                            </div>
                                                        )}
                                                    </TableCell>
                                                    {options && (
                                                        <TableCell className="w-12 text-right">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Remove measurement of ${formatDate(m.measured_on)}`}
                                                                onClick={() =>
                                                                    setRemoving(
                                                                        {
                                                                            kind: 'growth',
                                                                            item: m,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <Trash2
                                                                    aria-hidden
                                                                />
                                                            </Button>
                                                        </TableCell>
                                                    )}
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-3">
                        <div>
                            <CardTitle>Vitamin A and deworming</CardTitle>
                            <CardDescription>
                                Every 6 months: vitamin A from 6 months,
                                deworming from 12 months (until 59 months).
                            </CardDescription>
                        </div>
                        {options && (
                            <Button
                                variant="outline"
                                onClick={() => setSupplement('vitamin_a')}
                            >
                                <Pill aria-hidden /> Record
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <ul className="grid gap-3 sm:grid-cols-2">
                            {(
                                [
                                    ['vitamin_a', 'Vitamin A'],
                                    ['deworming', 'Deworming'],
                                ] as const
                            ).map(([key, label]) => {
                                const status = micronutrientStatus[key];

                                return (
                                    <li
                                        key={key}
                                        className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
                                    >
                                        <div>
                                            <p className="font-bold">{label}</p>
                                            <p className="text-sm text-muted-foreground">
                                                Last given:{' '}
                                                {status.last
                                                    ? formatDate(status.last)
                                                    : 'never'}
                                                {status.due_date &&
                                                    ` · next due ${formatDate(status.due_date)}`}
                                            </p>
                                        </div>
                                        {status.due ? (
                                            options ? (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        setSupplement(key)
                                                    }
                                                >
                                                    Due — record
                                                </Button>
                                            ) : (
                                                <StatusBadge tone="warning">
                                                    Due
                                                </StatusBadge>
                                            )
                                        ) : (
                                            <StatusBadge tone="success">
                                                Up to date
                                            </StatusBadge>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                        {micronutrients.length > 0 && (
                            <div className="overflow-x-auto rounded-lg border">
                                <Table>
                                    <caption className="sr-only">
                                        Supplements given
                                    </caption>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Date</TableHead>
                                            <TableHead>Given</TableHead>
                                            <TableHead>Dose</TableHead>
                                            <TableHead className="hidden sm:table-cell">
                                                By
                                            </TableHead>
                                            {options && (
                                                <TableHead>
                                                    <span className="sr-only">
                                                        Actions
                                                    </span>
                                                </TableHead>
                                            )}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {micronutrients.map((r) => (
                                            <TableRow key={r.id}>
                                                <TableCell className="whitespace-nowrap">
                                                    {formatDate(r.date_given)}
                                                </TableCell>
                                                <TableCell>
                                                    {r.type.label}
                                                </TableCell>
                                                <TableCell>
                                                    {r.dose ?? '—'}
                                                </TableCell>
                                                <TableCell className="hidden sm:table-cell">
                                                    {r.given_by}
                                                </TableCell>
                                                {options && (
                                                    <TableCell className="w-12 text-right">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Remove ${r.type.label} of ${formatDate(r.date_given)}`}
                                                            onClick={() =>
                                                                setRemoving({
                                                                    kind: 'micronutrient',
                                                                    item: r,
                                                                })
                                                            }
                                                        >
                                                            <Trash2
                                                                aria-hidden
                                                            />
                                                        </Button>
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {options && dose !== undefined && (
                <DoseDialog
                    key={dose ? `${dose.vaccine_id}-${dose.dose_no}` : 'free'}
                    childId={child.id}
                    options={options}
                    preset={dose}
                    open
                    onOpenChange={(open) => !open && setDose(undefined)}
                />
            )}
            {options && addingGrowth && (
                <GrowthDialog
                    childId={child.id}
                    options={options}
                    open
                    onOpenChange={setAddingGrowth}
                />
            )}
            {options && supplement !== undefined && (
                <MicronutrientDialog
                    key={supplement}
                    childId={child.id}
                    ageMonths={child.age_months}
                    options={options}
                    preset={supplement}
                    open
                    onOpenChange={(open) => !open && setSupplement(undefined)}
                />
            )}
            {removing && (
                <ConfirmDialog
                    open
                    onOpenChange={(open) => !open && setRemoving(null)}
                    title={
                        removing.kind === 'growth'
                            ? 'Remove this measurement?'
                            : 'Remove this entry?'
                    }
                    description="Only for an entry made by mistake. The audit log keeps a record."
                    confirmLabel="Remove"
                    destructive
                    processing={removeProcessing}
                    onConfirm={remove}
                />
            )}
        </>
    );
}

ChildHealthShow.layout = (props: ChildHealthShowPageProps) => ({
    breadcrumbs: [
        { title: 'Child health', href: index() },
        { title: props.child.full_name, href: show(props.child.id) },
    ],
});
