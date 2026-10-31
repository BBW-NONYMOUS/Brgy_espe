import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    ClipboardPlus,
    FileHeart,
    HeartPulse,
    History,
    MoreHorizontal,
    Pencil,
    Printer,
    ShieldCheck,
    Stethoscope,
    Syringe,
    Trash2,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import ConsentController from '@/actions/App/Http/Controllers/Health/ConsentController';
import EmergencyContactController from '@/actions/App/Http/Controllers/Health/EmergencyContactController';
import HealthVisitController from '@/actions/App/Http/Controllers/Health/HealthVisitController';
import ImmunizationController from '@/actions/App/Http/Controllers/Health/ImmunizationController';
import MedicalAlertController from '@/actions/App/Http/Controllers/Health/MedicalAlertController';
import ResidentConditionController from '@/actions/App/Http/Controllers/Health/ResidentConditionController';
import ResidentController from '@/actions/App/Http/Controllers/ResidentController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { EmptyState } from '@/components/app/empty-state';
import { HealthTimeline } from '@/components/app/health-timeline';
import { MedicalAlertBanner } from '@/components/app/medical-alert-banner';
import { PageHeader } from '@/components/app/page-header';
import { ResidentBadges } from '@/components/app/resident-badges';
import { ArchiveResidentDialog } from '@/components/app/residents/archive-resident-dialog';
import {
    ConditionDialog,
    ConsentDialog,
    ImmunizationDialog,
    MaternalDialog,
} from '@/components/app/residents/health-dialogs';
import {
    EmergencyContactDialog,
    GrowthDialog,
    MedicalAlertDialog,
    MicronutrientDialog,
} from '@/components/app/residents/profile-dialogs';
import {
    AppointmentsTab,
    GrowthTab,
    ReferralsTab,
    SafetyTab,
} from '@/components/app/residents/profile-tabs';
import { VisitSheet } from '@/components/app/residents/visit-sheet';
import { SensitiveValue } from '@/components/app/sensitive-value';
import { StatusBadge } from '@/components/app/status-badge';
import type { Tone } from '@/components/app/stat-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    formatAge,
    formatAuditValue,
    formatDate,
    formatDateTime,
} from '@/lib/format';
import { show as showHousehold } from '@/routes/households';
import { edit, index, show } from '@/routes/residents';
import type {
    Consent,
    EmergencyContact,
    HealthVisit,
    HouseholdMember,
    MedicalAlert,
    Pregnancy,
    Option,
    RecordHistoryEntry,
    Resident,
    ResidentCondition,
    ResidentHealth,
    ScheduledDose,
} from '@/types';
import type { ProfileHealthOptions } from '@/types/pages/records';

type Props = {
    resident: Resident;
    householdMembers: HouseholdMember[];
    consents: Consent[];
    /** Active critical alerts (red banner); empty for roles without health access. */
    criticalAlerts: MedicalAlert[];
    /** Deferred; null when the viewer's role may not see health records. */
    health?: ResidentHealth | null;
    history?: RecordHistoryEntry[] | null;
    healthOptions: ProfileHealthOptions | null;
    archiveStatuses: Option<string>[];
};

type Pending =
    | { kind: 'visit'; visit: HealthVisit | null }
    | { kind: 'immunization'; preset: ScheduledDose | null }
    | { kind: 'condition'; condition: ResidentCondition | null }
    | { kind: 'maternal'; record: Pregnancy | null }
    | { kind: 'consent' }
    | { kind: 'contact'; contact: EmergencyContact | null }
    | { kind: 'alert'; alert: MedicalAlert | null }
    | { kind: 'growth' }
    | { kind: 'micronutrient' }
    | { kind: 'archive' }
    | { kind: 'delete'; title: string; url: string; label: string };

export default function ResidentsShow({
    resident,
    householdMembers,
    consents,
    criticalAlerts,
    health,
    history,
    healthOptions,
}: Props) {
    const { can } = usePage().props;
    const [pending, setPending] = useState<Pending | null>(null);
    const [deleting, setDeleting] = useState(false);
    const archived = resident.archived_at !== null;
    const canRecord = can.recordHealth && !archived && healthOptions !== null;
    const canManageMaternal =
        can.manageMaternal && !archived && healthOptions !== null;
    const showHealth = health !== null;
    const maternalEligible =
        resident.sex.value === 'female' &&
        resident.age >= 10 &&
        resident.age <= 55;
    const close = () => setPending(null);

    const remove = (title: string, url: string, label: string) =>
        setPending({ kind: 'delete', title, url, label });

    return (
        <>
            <Head title={resident.full_name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <MedicalAlertBanner alerts={criticalAlerts} />
                <PageHeader
                    back={{ href: index(), label: 'Residents' }}
                    title={resident.full_name}
                    meta={
                        <ResidentBadges resident={resident} showActiveStatus />
                    }
                    description={
                        <span className="flex flex-wrap gap-x-3 gap-y-1">
                            <span className="font-mono text-foreground">
                                {resident.resident_code}
                            </span>
                            <span>
                                {formatAge(resident.age, resident.birthdate)}
                            </span>
                            <span>{resident.sex.label}</span>
                            {resident.household && (
                                <span>
                                    {resident.household.household_no} ·{' '}
                                    {resident.household.purok?.name}
                                </span>
                            )}
                        </span>
                    }
                    actions={
                        <>
                            {canRecord && (
                                <Button
                                    onClick={() =>
                                        setPending({
                                            kind: 'visit',
                                            visit: null,
                                        })
                                    }
                                >
                                    <Stethoscope aria-hidden /> Record visit
                                </Button>
                            )}
                            {can.updateResidents && !archived && (
                                <Button variant="outline" asChild>
                                    <Link href={edit(resident.id)}>
                                        <Pencil aria-hidden /> Edit
                                    </Link>
                                </Button>
                            )}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        aria-label="More actions"
                                    >
                                        <MoreHorizontal aria-hidden />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {canRecord && (
                                        <>
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    setPending({
                                                        kind: 'immunization',
                                                        preset: null,
                                                    })
                                                }
                                            >
                                                <Syringe aria-hidden /> Record
                                                immunization
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    setPending({
                                                        kind: 'condition',
                                                        condition: null,
                                                    })
                                                }
                                            >
                                                <ClipboardPlus aria-hidden />{' '}
                                                Add condition
                                            </DropdownMenuItem>
                                            {maternalEligible &&
                                                canManageMaternal && (
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setPending({
                                                                kind: 'maternal',
                                                                record: null,
                                                            })
                                                        }
                                                    >
                                                        <HeartPulse
                                                            aria-hidden
                                                        />{' '}
                                                        Add pregnancy record
                                                    </DropdownMenuItem>
                                                )}
                                        </>
                                    )}
                                    {can.recordConsents && !archived && (
                                        <DropdownMenuItem
                                            onSelect={() =>
                                                setPending({ kind: 'consent' })
                                            }
                                        >
                                            <ShieldCheck aria-hidden /> Record
                                            consent
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuItem
                                        onSelect={() => window.print()}
                                    >
                                        <Printer aria-hidden /> Print profile
                                    </DropdownMenuItem>
                                    {can.archiveResidents && (
                                        <>
                                            <DropdownMenuSeparator />
                                            {archived ? (
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        router.post(
                                                            ResidentController.restore.url(
                                                                resident.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <ArchiveRestore
                                                        aria-hidden
                                                    />{' '}
                                                    Restore
                                                </DropdownMenuItem>
                                            ) : (
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onSelect={() =>
                                                        setPending({
                                                            kind: 'archive',
                                                        })
                                                    }
                                                >
                                                    <Archive aria-hidden />{' '}
                                                    Archive
                                                </DropdownMenuItem>
                                            )}
                                        </>
                                    )}
                                    {archived && can.forceDeleteResidents && (
                                        <DropdownMenuItem
                                            variant="destructive"
                                            onSelect={() =>
                                                remove(
                                                    `Permanently delete ${resident.full_name}?`,
                                                    ResidentController.forceDestroy.url(
                                                        resident.id,
                                                    ),
                                                    'Delete permanently',
                                                )
                                            }
                                        >
                                            <Trash2 aria-hidden /> Delete
                                            permanently
                                        </DropdownMenuItem>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </>
                    }
                />

                {archived && (
                    <Alert>
                        <Archive aria-hidden />
                        <AlertTitle>
                            Archived{' '}
                            {formatDate(resident.archived_at?.slice(0, 10))} ·{' '}
                            {resident.status.label}
                        </AlertTitle>
                        <AlertDescription>
                            {resident.archive_reason}
                        </AlertDescription>
                    </Alert>
                )}

                <Tabs defaultValue="personal" className="gap-4">
                    <TabsList
                        className="h-auto w-full justify-start overflow-x-auto [&>button]:flex-none"
                        data-print="hide"
                    >
                        <TabsTrigger value="personal">
                            Personal info
                        </TabsTrigger>
                        {showHealth && (
                            <TabsTrigger value="safety">
                                Emergency contacts &amp; alerts
                            </TabsTrigger>
                        )}
                        <TabsTrigger value="household">Household</TabsTrigger>
                        {showHealth && (
                            <TabsTrigger value="timeline">
                                Health timeline
                            </TabsTrigger>
                        )}
                        {showHealth && (
                            <TabsTrigger value="immunizations">
                                Immunizations &amp; conditions
                            </TabsTrigger>
                        )}
                        {showHealth && (
                            <TabsTrigger value="growth">
                                Maternal / child growth
                            </TabsTrigger>
                        )}
                        {showHealth && (
                            <TabsTrigger value="referrals">
                                Referrals
                            </TabsTrigger>
                        )}
                        {showHealth && (
                            <TabsTrigger value="appointments">
                                Appointments
                            </TabsTrigger>
                        )}
                        {history !== null && (
                            <TabsTrigger value="history">History</TabsTrigger>
                        )}
                    </TabsList>

                    <TabsContent value="personal" className="space-y-6">
                        <PersonalInfo resident={resident} />
                        <ConsentsCard
                            consents={consents}
                            canRevoke={can.recordConsents && !archived}
                            onRevoke={(consent) =>
                                router.patch(
                                    ConsentController.revoke.url(consent.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        />
                    </TabsContent>

                    <TabsContent value="household">
                        <HouseholdTab
                            resident={resident}
                            members={householdMembers}
                        />
                    </TabsContent>

                    {showHealth && (
                        <>
                            <TabsContent value="timeline">
                                <Deferred
                                    data="health"
                                    fallback={<TabSkeleton />}
                                >
                                    {health && (
                                        <HealthTimeline
                                            visits={health.timeline}
                                            emptyAction={
                                                canRecord && (
                                                    <Button
                                                        onClick={() =>
                                                            setPending({
                                                                kind: 'visit',
                                                                visit: null,
                                                            })
                                                        }
                                                    >
                                                        <Stethoscope
                                                            aria-hidden
                                                        />{' '}
                                                        Record visit
                                                    </Button>
                                                )
                                            }
                                            renderActions={
                                                canRecord
                                                    ? (visit) => (
                                                          <RowActions
                                                              label={`visit on ${formatDate(visit.visit_date)}`}
                                                              onEdit={() =>
                                                                  setPending({
                                                                      kind: 'visit',
                                                                      visit,
                                                                  })
                                                              }
                                                              onDelete={() =>
                                                                  remove(
                                                                      `Remove the visit on ${formatDate(visit.visit_date)}?`,
                                                                      HealthVisitController.destroy.url(
                                                                          visit.id,
                                                                      ),
                                                                      'Remove visit',
                                                                  )
                                                              }
                                                          />
                                                      )
                                                    : undefined
                                            }
                                        />
                                    )}
                                </Deferred>
                            </TabsContent>

                            <TabsContent
                                value="immunizations"
                                className="space-y-6"
                            >
                                <Deferred
                                    data="health"
                                    fallback={<TabSkeleton />}
                                >
                                    {health && (
                                        <>
                                            <ImmunizationsCard
                                                health={health}
                                                canRecord={canRecord}
                                                onRecord={(preset) =>
                                                    setPending({
                                                        kind: 'immunization',
                                                        preset,
                                                    })
                                                }
                                                onDelete={(id, label) =>
                                                    remove(
                                                        `Remove ${label}?`,
                                                        ImmunizationController.destroy.url(
                                                            id,
                                                        ),
                                                        'Remove dose',
                                                    )
                                                }
                                            />
                                            <ConditionsCard
                                                conditions={health.conditions}
                                                canRecord={canRecord}
                                                onAdd={() =>
                                                    setPending({
                                                        kind: 'condition',
                                                        condition: null,
                                                    })
                                                }
                                                onEdit={(condition) =>
                                                    setPending({
                                                        kind: 'condition',
                                                        condition,
                                                    })
                                                }
                                                onDelete={(c) =>
                                                    remove(
                                                        `Remove ${c.condition?.name ?? 'this condition'}?`,
                                                        ResidentConditionController.destroy.url(
                                                            c.id,
                                                        ),
                                                        'Remove condition',
                                                    )
                                                }
                                            />
                                        </>
                                    )}
                                </Deferred>
                            </TabsContent>

                            <TabsContent value="safety">
                                <Deferred
                                    data="health"
                                    fallback={<TabSkeleton />}
                                >
                                    {health && (
                                        <SafetyTab
                                            contacts={health.emergencyContacts}
                                            alerts={health.medicalAlerts}
                                            canRecord={canRecord}
                                            onContact={(contact) =>
                                                setPending({
                                                    kind: 'contact',
                                                    contact,
                                                })
                                            }
                                            onDeleteContact={(c) =>
                                                remove(
                                                    `Remove ${c.name} as an emergency contact?`,
                                                    EmergencyContactController.destroy.url(
                                                        c.id,
                                                    ),
                                                    'Remove contact',
                                                )
                                            }
                                            onAlert={(alert) =>
                                                setPending({
                                                    kind: 'alert',
                                                    alert,
                                                })
                                            }
                                            onDeleteAlert={(a) =>
                                                remove(
                                                    `Remove this ${a.alert_type.label.toLowerCase()} alert?`,
                                                    MedicalAlertController.destroy.url(
                                                        a.id,
                                                    ),
                                                    'Remove alert',
                                                )
                                            }
                                        />
                                    )}
                                </Deferred>
                            </TabsContent>

                            <TabsContent value="growth">
                                <Deferred
                                    data="health"
                                    fallback={<TabSkeleton />}
                                >
                                    {health && (
                                        <GrowthTab
                                            resident={resident}
                                            pregnancies={health.pregnancies}
                                            growth={health.growth}
                                            micronutrients={
                                                health.micronutrients
                                            }
                                            canRecord={canRecord}
                                            canManageMaternal={
                                                canManageMaternal &&
                                                maternalEligible
                                            }
                                            onAddPregnancy={() =>
                                                setPending({
                                                    kind: 'maternal',
                                                    record: null,
                                                })
                                            }
                                            onEditPregnancy={(record) =>
                                                setPending({
                                                    kind: 'maternal',
                                                    record,
                                                })
                                            }
                                            onAddGrowth={() =>
                                                setPending({ kind: 'growth' })
                                            }
                                            onAddMicronutrient={() =>
                                                setPending({
                                                    kind: 'micronutrient',
                                                })
                                            }
                                        />
                                    )}
                                </Deferred>
                            </TabsContent>

                            <TabsContent value="referrals">
                                <Deferred
                                    data="health"
                                    fallback={<TabSkeleton />}
                                >
                                    {health && (
                                        <ReferralsTab
                                            referrals={health.referrals}
                                        />
                                    )}
                                </Deferred>
                            </TabsContent>

                            <TabsContent value="appointments">
                                <Deferred
                                    data="health"
                                    fallback={<TabSkeleton />}
                                >
                                    {health && (
                                        <AppointmentsTab
                                            appointments={health.appointments}
                                            canManage={can.manageAppointments}
                                        />
                                    )}
                                </Deferred>
                            </TabsContent>
                        </>
                    )}

                    {history !== null && (
                        <TabsContent value="history">
                            <Deferred data="history" fallback={<TabSkeleton />}>
                                {history && <HistoryTab entries={history} />}
                            </Deferred>
                        </TabsContent>
                    )}
                </Tabs>
            </div>

            {healthOptions && pending?.kind === 'visit' && (
                <VisitSheet
                    resident={resident}
                    options={healthOptions}
                    pregnancies={health?.pregnancies ?? []}
                    visit={pending.visit}
                    criticalAlerts={criticalAlerts}
                    open
                    onOpenChange={(o) => !o && close()}
                />
            )}
            {healthOptions && pending?.kind === 'immunization' && (
                <ImmunizationDialog
                    residentId={resident.id}
                    options={healthOptions}
                    preset={pending.preset}
                    open
                    onOpenChange={(o) => !o && close()}
                />
            )}
            {healthOptions && pending?.kind === 'condition' && (
                <ConditionDialog
                    residentId={resident.id}
                    options={healthOptions}
                    condition={pending.condition}
                    open
                    onOpenChange={(o) => !o && close()}
                />
            )}
            {healthOptions && pending?.kind === 'maternal' && (
                <MaternalDialog
                    residentId={resident.id}
                    options={healthOptions}
                    record={pending.record}
                    open
                    onOpenChange={(o) => !o && close()}
                />
            )}
            {pending?.kind === 'consent' && (
                <ConsentDialog
                    residentId={resident.id}
                    consentTypes={healthOptions?.consentTypes}
                    open
                    onOpenChange={(o) => !o && close()}
                />
            )}
            {pending?.kind === 'contact' && (
                <EmergencyContactDialog
                    residentId={resident.id}
                    contact={pending.contact}
                    open
                    onOpenChange={(o) => !o && close()}
                />
            )}
            {healthOptions && pending?.kind === 'alert' && (
                <MedicalAlertDialog
                    residentId={resident.id}
                    alert={pending.alert}
                    options={healthOptions}
                    open
                    onOpenChange={(o) => !o && close()}
                />
            )}
            {healthOptions && pending?.kind === 'growth' && (
                <GrowthDialog
                    residentId={resident.id}
                    options={healthOptions}
                    open
                    onOpenChange={(o) => !o && close()}
                />
            )}
            {healthOptions && pending?.kind === 'micronutrient' && (
                <MicronutrientDialog
                    residentId={resident.id}
                    options={healthOptions}
                    open
                    onOpenChange={(o) => !o && close()}
                />
            )}
            {pending?.kind === 'archive' && (
                <ArchiveResidentDialog
                    resident={resident}
                    open
                    onOpenChange={(o) => !o && close()}
                />
            )}
            {pending?.kind === 'delete' && (
                <ConfirmDialog
                    open
                    onOpenChange={(o) => !o && close()}
                    title={pending.title}
                    description={
                        <p>
                            This entry is removed from the record. The change is
                            kept in the audit log.
                        </p>
                    }
                    confirmLabel={pending.label}
                    destructive
                    processing={deleting}
                    onConfirm={() =>
                        router.delete(pending.url, {
                            preserveScroll: true,
                            onStart: () => setDeleting(true),
                            onFinish: () => {
                                setDeleting(false);
                                close();
                            },
                        })
                    }
                />
            )}
        </>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-1">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="font-bold">
                {children || (
                    <span className="font-normal text-muted-foreground">—</span>
                )}
            </dd>
        </div>
    );
}

function PersonalInfo({ resident }: { resident: Resident }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Personal information</CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <Field label="Resident ID">
                        <span className="font-mono">
                            {resident.resident_code}
                        </span>
                    </Field>
                    <Field label="Sex">{resident.sex.label}</Field>
                    <Field label="Birthdate">
                        {formatDate(resident.birthdate, 'long')} (
                        {formatAge(resident.age, resident.birthdate)})
                    </Field>
                    <Field label="Birthplace">{resident.birthplace}</Field>
                    <Field label="Civil status">
                        {resident.civil_status.label}
                    </Field>
                    <Field label="Mobile number">{resident.contact_no}</Field>
                    <Field label="PhilHealth number">
                        <SensitiveValue
                            value={resident.philhealth_no}
                            masked={resident.philhealth_no_masked}
                            label="PhilHealth number"
                        />
                    </Field>
                    <Field label="Relationship to head">
                        {resident.relationship_to_head.label}
                    </Field>
                    <Field label="Programs">
                        {[
                            resident.is_pwd && 'PWD',
                            resident.is_4ps && '4Ps',
                            resident.is_senior && 'Senior citizen',
                        ]
                            .filter(Boolean)
                            .join(', ')}
                    </Field>
                    <Field label="Registered">
                        <span className="font-normal">
                            {formatDateTime(resident.created_at)}
                            {resident.created_by &&
                                ` by ${resident.created_by}`}
                        </span>
                    </Field>
                    <Field label="Last updated">
                        <span className="font-normal">
                            {formatDateTime(resident.updated_at)}
                            {resident.updated_by &&
                                ` by ${resident.updated_by}`}
                        </span>
                    </Field>
                </dl>
            </CardContent>
        </Card>
    );
}

function ConsentsCard({
    consents,
    canRevoke,
    onRevoke,
}: {
    consents: Consent[];
    canRevoke: boolean;
    onRevoke: (c: Consent) => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Consent (RA 10173)</CardTitle>
                <CardDescription>
                    When and why the resident agreed to have their information
                    recorded.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {consents.length === 0 ? (
                    <p className="text-muted-foreground">
                        No consent recorded.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Purpose</TableHead>
                                <TableHead>Obtained by</TableHead>
                                <TableHead>Status</TableHead>
                                {canRevoke && (
                                    <TableHead className="sr-only">
                                        Actions
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {consents.map((consent) => (
                                <TableRow key={consent.id}>
                                    <TableCell className="whitespace-nowrap">
                                        {formatDate(consent.consent_date)}
                                    </TableCell>
                                    <TableCell className="min-w-64 whitespace-normal">
                                        {consent.purpose}
                                    </TableCell>
                                    <TableCell>{consent.obtained_by}</TableCell>
                                    <TableCell>
                                        {consent.revoked_at ? (
                                            <StatusBadge tone="danger">
                                                Withdrawn{' '}
                                                {formatDate(consent.revoked_at)}
                                            </StatusBadge>
                                        ) : (
                                            <StatusBadge tone="success">
                                                Active
                                            </StatusBadge>
                                        )}
                                    </TableCell>
                                    {canRevoke && (
                                        <TableCell className="text-right">
                                            {!consent.revoked_at && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        onRevoke(consent)
                                                    }
                                                >
                                                    Mark withdrawn
                                                </Button>
                                            )}
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}

function HouseholdTab({
    resident,
    members,
}: {
    resident: Resident;
    members: HouseholdMember[];
}) {
    const household = resident.household;

    return (
        <div className="grid gap-6 lg:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardTitle>{household?.household_no}</CardTitle>
                    <CardDescription>{household?.purok?.name}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <dl className="space-y-4">
                        <Field label="Address">{household?.address}</Field>
                        <Field label="Household head">
                            {household?.head?.full_name}
                        </Field>
                    </dl>
                    {household && (
                        <Button variant="outline" asChild>
                            <Link href={showHousehold(household.id)}>
                                Open household
                            </Link>
                        </Button>
                    )}
                </CardContent>
            </Card>
            <Card className="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Other members</CardTitle>
                </CardHeader>
                <CardContent>
                    {members.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="No other members"
                            description="This resident is the only registered member of the household."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Relationship</TableHead>
                                    <TableHead>Age</TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Sex
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {members.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell>
                                            <Link
                                                href={show(m.id)}
                                                className="font-bold underline-offset-4 hover:underline"
                                            >
                                                {m.full_name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {m.relationship_to_head}
                                        </TableCell>
                                        <TableCell>{m.age} yrs</TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            {m.sex}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

const doseTone: Record<ScheduledDose['status'], Tone | 'neutral'> = {
    given: 'success',
    due: 'warning',
    overdue: 'danger',
    upcoming: 'neutral',
};
const doseLabel: Record<ScheduledDose['status'], string> = {
    given: 'Given',
    due: 'Due now',
    overdue: 'Overdue',
    upcoming: 'Upcoming',
};

function ImmunizationsCard({
    health,
    canRecord,
    onRecord,
    onDelete,
}: {
    health: ResidentHealth;
    canRecord: boolean;
    onRecord: (preset: ScheduledDose | null) => void;
    onDelete: (id: number, label: string) => void;
}) {
    const schedule = health.immunizationSchedule;
    const scheduledKeys = new Set(
        schedule.map((d) => `${d.vaccine_id}-${d.dose_no}`),
    );
    const others = health.immunizations.filter(
        (i) => !scheduledKeys.has(`${i.vaccine?.id}-${i.dose_no}`),
    );
    const findGiven = (dose: ScheduledDose) =>
        health.immunizations.find(
            (i) =>
                i.vaccine?.id === dose.vaccine_id && i.dose_no === dose.dose_no,
        );

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle>Immunizations</CardTitle>
                    <CardDescription>
                        {schedule.length > 0
                            ? 'DOH Expanded Program on Immunization schedule'
                            : 'Vaccines given'}
                    </CardDescription>
                </div>
                {canRecord && (
                    <Button variant="outline" onClick={() => onRecord(null)}>
                        <Syringe aria-hidden /> Record dose
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-6">
                {schedule.length > 0 && (
                    <Table>
                        <caption className="sr-only">EPI schedule</caption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Vaccine</TableHead>
                                <TableHead>Dose</TableHead>
                                <TableHead>Due date</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Given</TableHead>
                                {canRecord && (
                                    <TableHead className="sr-only">
                                        Actions
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {schedule.map((dose) => {
                                const given = findGiven(dose);

                                return (
                                    <TableRow
                                        key={`${dose.vaccine_id}-${dose.dose_no}`}
                                    >
                                        <TableCell>
                                            <span className="font-bold">
                                                {dose.vaccine_code}
                                            </span>
                                            <span className="hidden text-muted-foreground md:inline">
                                                {' '}
                                                · {dose.vaccine_name}
                                            </span>
                                        </TableCell>
                                        <TableCell>{dose.dose_no}</TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {formatDate(dose.due_date)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                tone={doseTone[dose.status]}
                                            >
                                                {doseLabel[dose.status]}
                                            </StatusBadge>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {dose.date_given
                                                ? formatDate(dose.date_given)
                                                : '—'}
                                        </TableCell>
                                        {canRecord && (
                                            <TableCell className="text-right">
                                                {given ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Remove ${dose.vaccine_code} dose ${dose.dose_no}`}
                                                        onClick={() =>
                                                            onDelete(
                                                                given.id,
                                                                `${dose.vaccine_code} dose ${dose.dose_no}`,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 aria-hidden />
                                                    </Button>
                                                ) : (
                                                    dose.status !==
                                                        'upcoming' && (
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                onRecord(dose)
                                                            }
                                                        >
                                                            Record
                                                        </Button>
                                                    )
                                                )}
                                            </TableCell>
                                        )}
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}

                {others.length > 0 && (
                    <div className="space-y-2">
                        {schedule.length > 0 && (
                            <h3 className="font-bold">Other vaccines</h3>
                        )}
                        <ul className="divide-y rounded-xl border">
                            {others.map((i) => (
                                <li
                                    key={i.id}
                                    className="flex items-center justify-between gap-3 p-3"
                                >
                                    <div>
                                        <p className="font-bold">
                                            {i.vaccine?.name} · dose {i.dose_no}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {formatDate(i.date_given)}
                                            {i.given_by && ` · ${i.given_by}`}
                                            {i.lot_no && ` · Lot ${i.lot_no}`}
                                        </p>
                                    </div>
                                    {canRecord && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Remove ${i.vaccine?.code} dose ${i.dose_no}`}
                                            onClick={() =>
                                                onDelete(
                                                    i.id,
                                                    `${i.vaccine?.code} dose ${i.dose_no}`,
                                                )
                                            }
                                        >
                                            <Trash2 aria-hidden />
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {schedule.length === 0 && others.length === 0 && (
                    <EmptyState
                        icon={Syringe}
                        title="No immunizations recorded"
                        description="Vaccines given at the health center will be listed here."
                    />
                )}
            </CardContent>
        </Card>
    );
}

const conditionTone: Record<string, Tone | 'neutral'> = {
    active: 'danger',
    controlled: 'info',
    resolved: 'neutral',
};

function ConditionsCard({
    conditions,
    canRecord,
    onAdd,
    onEdit,
    onDelete,
}: {
    conditions: ResidentCondition[];
    canRecord: boolean;
    onAdd: () => void;
    onEdit: (c: ResidentCondition) => void;
    onDelete: (c: ResidentCondition) => void;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle>Medical conditions</CardTitle>
                    <CardDescription>
                        Diagnosed conditions and their current status.
                    </CardDescription>
                </div>
                {canRecord && (
                    <Button variant="outline" onClick={onAdd}>
                        <ClipboardPlus aria-hidden /> Add condition
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {conditions.length === 0 ? (
                    <EmptyState
                        icon={FileHeart}
                        title="No conditions recorded"
                    />
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {conditions.map((c) => (
                            <li
                                key={c.id}
                                className="flex flex-wrap items-start justify-between gap-3 p-4"
                            >
                                <div className="space-y-1">
                                    <p className="flex flex-wrap items-center gap-2 font-bold">
                                        {c.condition?.name}
                                        {c.condition?.icd10_code && (
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {c.condition.icd10_code}
                                            </span>
                                        )}
                                        <StatusBadge
                                            tone={conditionTone[c.status.value]}
                                        >
                                            {c.status.label}
                                        </StatusBadge>
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {c.diagnosed_date
                                            ? `Diagnosed ${formatDate(c.diagnosed_date)}`
                                            : 'Diagnosis date not recorded'}
                                        {c.recorded_by &&
                                            ` · recorded by ${c.recorded_by}`}
                                    </p>
                                    {c.notes && (
                                        <p className="text-sm whitespace-pre-line">
                                            {c.notes}
                                        </p>
                                    )}
                                </div>
                                {canRecord && (
                                    <RowActions
                                        label={c.condition?.name ?? 'condition'}
                                        onEdit={() => onEdit(c)}
                                        onDelete={() => onDelete(c)}
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function HistoryTab({ entries }: { entries: RecordHistoryEntry[] }) {
    if (entries.length === 0) {
        return <EmptyState icon={History} title="No changes recorded" />;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Change history</CardTitle>
                <CardDescription>
                    Latest 20 changes to this profile. Encrypted fields show as
                    [encrypted].
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ol className="space-y-4">
                    {entries.map((e) => (
                        <li key={e.id} className="rounded-xl border p-4">
                            <p className="font-bold">
                                {e.action} ·{' '}
                                <span className="font-normal text-muted-foreground">
                                    {formatDateTime(e.created_at)}
                                    {e.user && ` by ${e.user}`}
                                </span>
                            </p>
                            {e.new_values && e.old_values && (
                                <dl className="mt-2 grid gap-1 text-sm sm:grid-cols-[12rem_1fr]">
                                    {Object.keys(e.new_values).map((key) => (
                                        <div key={key} className="contents">
                                            <dt className="text-muted-foreground">
                                                {key.replaceAll('_', ' ')}
                                            </dt>
                                            <dd>
                                                <span className="line-through opacity-70">
                                                    {formatAuditValue(
                                                        e.old_values?.[key],
                                                    )}
                                                </span>{' '}
                                                →{' '}
                                                {formatAuditValue(
                                                    e.new_values?.[key],
                                                )}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </li>
                    ))}
                </ol>
            </CardContent>
        </Card>
    );
}

function RowActions({
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

function TabSkeleton() {
    return (
        <div className="space-y-3" aria-hidden>
            <Skeleton className="h-28 w-full" />
            <Skeleton className="h-28 w-full" />
        </div>
    );
}

ResidentsShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Residents', href: index() },
        { title: props.resident.full_name, href: show(props.resident.id) },
    ],
});
