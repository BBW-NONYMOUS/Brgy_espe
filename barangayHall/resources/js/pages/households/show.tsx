import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    Droplets,
    MapPin,
    MapPinOff,
    Pencil,
    Printer,
    ShieldAlert,
    Toilet,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import GeotagController from '@/actions/App/Http/Controllers/GeotagController';
import HouseholdController from '@/actions/App/Http/Controllers/HouseholdController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { EmptyState } from '@/components/app/empty-state';
import { LazyGeotagPicker, LazyHouseholdMap } from '@/components/app/lazy';
import { PageHeader } from '@/components/app/page-header';
import { ResidentBadges } from '@/components/app/resident-badges';
import { ConsentDialog } from '@/components/app/residents/health-dialogs';
import { HouseholdDialog } from '@/components/app/residents/household-dialog';
import { StatusBadge } from '@/components/app/status-badge';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatAge, formatDateTime } from '@/lib/format';
import { index, show, summary } from '@/routes/households';
import {
    create as createResident,
    show as showResident,
} from '@/routes/residents';
import type {
    GeotagValue,
    Household,
    MapConfig,
    Option,
    Resident,
} from '@/types';
import type {
    HouseholdGeotag,
    MemberHealth,
    ToiletTypeOption,
    WaterSourceOption,
} from '@/types/pages/records';

type Props = {
    household: Household;
    members: Resident[];
    /** Health flags per member id; null for roles without health access. */
    memberHealth: Record<number, MemberHealth> | null;
    geotag: HouseholdGeotag;
    map: MapConfig;
    options: {
        puroks: Option[];
        waterSources: WaterSourceOption[];
        toiletTypes: ToiletTypeOption[];
    };
};

export default function HouseholdsShow({
    household,
    members,
    memberHealth,
    geotag,
    map,
    options,
}: Props) {
    const { can } = usePage().props;
    const [editing, setEditing] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [tagging, setTagging] = useState(false);
    const [untagging, setUntagging] = useState(false);
    const [consenting, setConsenting] = useState(false);

    const water = options.waterSources.find(
        (w) => w.label === household.water_source,
    );
    const toilet = options.toiletTypes.find(
        (t) => t.label === household.toilet_type,
    );

    return (
        <>
            <Head title={`Household ${household.household_no}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    back={{ href: index(), label: 'Households' }}
                    title={`Household ${household.household_no}`}
                    meta={
                        <>
                            <StatusBadge tone="primary">
                                {household.purok?.name}
                            </StatusBadge>
                            {household.is_4ps && (
                                <StatusBadge tone="warning">4Ps</StatusBadge>
                            )}
                        </>
                    }
                    description={household.address}
                    actions={
                        <>
                            {can.manageHouseholds && can.createResidents && (
                                <Button asChild>
                                    <Link
                                        href={createResident({
                                            query: {
                                                household_id: household.id,
                                            },
                                        })}
                                    >
                                        <UserPlus aria-hidden /> Add member
                                    </Link>
                                </Button>
                            )}
                            {can.viewHealthRecords && (
                                // A PDF opened in a new tab (printing from the browser), not an Inertia visit.
                                <Button variant="outline" asChild>
                                    <a
                                        href={summary.url(household.id)}
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <Printer aria-hidden /> Print family
                                        health summary
                                        <span className="sr-only">
                                            {' '}
                                            (opens a PDF in a new tab)
                                        </span>
                                    </a>
                                </Button>
                            )}
                            {can.manageHouseholds && (
                                <Button
                                    variant="outline"
                                    onClick={() => setEditing(true)}
                                >
                                    <Pencil aria-hidden /> Edit
                                </Button>
                            )}
                            {can.manageHouseholds && members.length === 0 && (
                                <Button
                                    variant="outline"
                                    onClick={() => setDeleting(true)}
                                >
                                    <Trash2 aria-hidden /> Delete
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Household data</CardTitle>
                            <CardDescription>
                                Water, sanitation and programs for the family
                                health profile.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-5 sm:grid-cols-2">
                                <Detail label="Household head">
                                    {household.head ? (
                                        <Link
                                            href={showResident(
                                                household.head.id,
                                            )}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            {household.head.full_name}
                                        </Link>
                                    ) : null}
                                </Detail>
                                <Detail label="4Ps (Pantawid Pamilya)">
                                    {household.is_4ps ? 'Yes' : 'No'}
                                </Detail>
                                <Detail label="Drinking water">
                                    {household.water_source && (
                                        <LookupValue
                                            icon={<Droplets aria-hidden />}
                                            text={household.water_source}
                                            good={water?.safe}
                                            goodLabel="Safe source"
                                            badLabel="Unsafe source"
                                        />
                                    )}
                                </Detail>
                                <Detail label="Toilet / sanitation">
                                    {household.toilet_type && (
                                        <LookupValue
                                            icon={<Toilet aria-hidden />}
                                            text={household.toilet_type}
                                            good={toilet?.sanitary}
                                            goodLabel="Sanitary"
                                            badLabel="Not sanitary"
                                        />
                                    )}
                                </Detail>
                            </dl>
                        </CardContent>
                    </Card>

                    <LocationCard
                        household={household}
                        geotag={geotag}
                        membersCount={members.length}
                        map={map}
                        onTag={() => setTagging(true)}
                        onUntag={() => setUntagging(true)}
                        onConsent={() => setConsenting(true)}
                        canRecordConsent={
                            can.recordConsents && household.head !== null
                        }
                    />
                </div>

                <MembersCard
                    household={household}
                    members={members}
                    memberHealth={memberHealth}
                />
            </div>

            {editing && (
                <HouseholdDialog
                    open
                    onOpenChange={setEditing}
                    puroks={options.puroks}
                    waterSources={options.waterSources}
                    toiletTypes={options.toiletTypes}
                    household={household}
                    members={members.map((m) => ({
                        id: m.id,
                        full_name: m.full_name,
                    }))}
                />
            )}

            {tagging && (
                <GeotagDialog
                    household={household}
                    map={map}
                    onOpenChange={setTagging}
                />
            )}

            {consenting && household.head && (
                <ConsentDialog
                    residentId={household.head.id}
                    defaultType="geotagging"
                    title="Record geo-tagging consent"
                    description={`Consent from ${household.head.full_name} (household head) to record where the household lives.`}
                    open
                    onOpenChange={setConsenting}
                />
            )}

            <ConfirmDialog
                open={untagging}
                onOpenChange={setUntagging}
                title={`Remove the location of ${household.household_no}?`}
                description={
                    <p>
                        The household disappears from the map until it is
                        geo-tagged again.
                    </p>
                }
                confirmLabel="Remove location"
                destructive
                processing={processing}
                onConfirm={() =>
                    router.delete(GeotagController.destroy.url(household.id), {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false);
                            setUntagging(false);
                        },
                    })
                }
            />

            <ConfirmDialog
                open={deleting}
                onOpenChange={setDeleting}
                title={`Delete household ${household.household_no}?`}
                description={
                    <p>
                        This household has no members. Deleting it cannot be
                        undone.
                    </p>
                }
                confirmLabel="Delete household"
                destructive
                processing={processing}
                onConfirm={() =>
                    router.delete(
                        HouseholdController.destroy.url(household.id),
                        {
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                        },
                    )
                }
            />
        </>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-1">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="font-bold">
                {children || (
                    <span className="font-normal text-muted-foreground">
                        Not recorded
                    </span>
                )}
            </dd>
        </div>
    );
}

function LookupValue({
    icon,
    text,
    good,
    goodLabel,
    badLabel,
}: {
    icon: ReactNode;
    text: string;
    good: boolean | undefined;
    goodLabel: string;
    badLabel: string;
}) {
    return (
        <span className="flex flex-col gap-1">
            <span className="flex items-start gap-2 [&_svg]:mt-1 [&_svg]:size-4 [&_svg]:shrink-0">
                {icon}
                {text}
            </span>
            {good !== undefined && (
                <StatusBadge
                    tone={good ? 'success' : 'danger'}
                    icon={good ? <Check aria-hidden /> : <X aria-hidden />}
                    className="w-fit"
                >
                    {good ? goodLabel : badLabel}
                </StatusBadge>
            )}
        </span>
    );
}

function LocationCard({
    household,
    geotag,
    map,
    onTag,
    onUntag,
    onConsent,
    canRecordConsent,
    membersCount,
}: {
    household: Household;
    membersCount: number;
    geotag: HouseholdGeotag;
    map: MapConfig;
    onTag: () => void;
    onUntag: () => void;
    onConsent: () => void;
    canRecordConsent: boolean;
}) {
    const hasPoint =
        household.latitude !== undefined && household.longitude !== undefined;

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle>Location</CardTitle>
                    <CardDescription>
                        {geotag.is_geotagged
                            ? `Geo-tagged ${formatDateTime(geotag.geotagged_at)}${geotag.geotagged_by ? ` by ${geotag.geotagged_by}` : ''}${geotag.accuracy_m !== null ? ` · GPS accuracy about ${geotag.accuracy_m} m` : ''}`
                            : 'Not geo-tagged yet.'}
                    </CardDescription>
                </div>
                {geotag.can_geotag && geotag.has_consent && (
                    <div className="flex shrink-0 flex-wrap justify-end gap-2">
                        <Button
                            variant={
                                geotag.is_geotagged ? 'outline' : 'default'
                            }
                            onClick={onTag}
                        >
                            <MapPin aria-hidden />
                            {geotag.is_geotagged ? 'Update' : 'Geo-tag'}
                        </Button>
                        {geotag.is_geotagged && (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-11"
                                aria-label="Remove location"
                                onClick={onUntag}
                            >
                                <MapPinOff aria-hidden />
                            </Button>
                        )}
                    </div>
                )}
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {!geotag.has_consent && (
                    <Alert>
                        <ShieldAlert aria-hidden />
                        <AlertTitle>Geo-tagging consent needed</AlertTitle>
                        <AlertDescription>
                            <p>
                                A household&apos;s location is personal data (RA
                                10173). Record a member&apos;s consent before
                                saving it.
                            </p>
                            {canRecordConsent && (
                                <Button
                                    variant="outline"
                                    className="mt-2"
                                    onClick={onConsent}
                                >
                                    Record consent from the household head
                                </Button>
                            )}
                        </AlertDescription>
                    </Alert>
                )}
                {hasPoint ? (
                    <LazyHouseholdMap
                        config={{ ...map, zoom: 17 }}
                        markers={[
                            {
                                id: household.id,
                                household_no: household.household_no,
                                lat: household.latitude as number,
                                lng: household.longitude as number,
                                purok_id: household.purok?.id ?? 0,
                                purok: household.purok?.name ?? '',
                                head: household.head?.full_name ?? null,
                                members: membersCount,
                                flags: [],
                            },
                        ]}
                        className="h-64"
                        label={`Location of household ${household.household_no}`}
                    />
                ) : (
                    geotag.is_geotagged && (
                        <p className="text-sm text-muted-foreground">
                            The exact location is only shown to health staff.
                        </p>
                    )
                )}
            </CardContent>
        </Card>
    );
}

function GeotagDialog({
    household,
    map,
    onOpenChange,
}: {
    household: Household;
    map: MapConfig;
    onOpenChange: (open: boolean) => void;
}) {
    const initial: GeotagValue | null =
        household.latitude !== undefined && household.longitude !== undefined
            ? {
                  latitude: household.latitude,
                  longitude: household.longitude,
                  accuracy_m: null,
              }
            : null;
    const form = useForm<{
        latitude: number | null;
        longitude: number | null;
        accuracy_m: number | null;
    }>({
        latitude: initial?.latitude ?? null,
        longitude: initial?.longitude ?? null,
        accuracy_m: null,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const value =
        form.data.latitude !== null && form.data.longitude !== null
            ? {
                  latitude: form.data.latitude,
                  longitude: form.data.longitude,
                  accuracy_m: form.data.accuracy_m,
              }
            : null;
    const message = errors.consent ?? errors.latitude ?? errors.longitude;

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[95dvh] overflow-y-auto sm:max-w-2xl">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.submit(GeotagController.update(household.id), {
                            preserveScroll: true,
                            onSuccess: () => onOpenChange(false),
                        });
                    }}
                    className="space-y-5"
                >
                    <DialogHeader>
                        <DialogTitle>
                            Geo-tag household {household.household_no}
                        </DialogTitle>
                        <DialogDescription>
                            Stand at the house and tap &quot;Use my
                            location&quot;, or tap the house on the map.
                        </DialogDescription>
                    </DialogHeader>
                    <LazyGeotagPicker
                        config={map}
                        value={value}
                        onChange={(v) =>
                            form.setData({
                                latitude: v.latitude,
                                longitude: v.longitude,
                                accuracy_m: v.accuracy_m,
                            })
                        }
                    />
                    {message && (
                        <p role="alert" className="text-sm text-destructive">
                            {message}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || value === null}
                        >
                            {form.processing && <Spinner />}
                            Save location
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function MembersCard({
    household,
    members,
    memberHealth,
}: {
    household: Household;
    members: Resident[];
    memberHealth: Record<number, MemberHealth> | null;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    Members ({members.length})
                    {household.head && (
                        <span className="ml-2 text-base font-normal text-muted-foreground">
                            Head: {household.head.full_name}
                        </span>
                    )}
                </CardTitle>
                {memberHealth && (
                    <CardDescription>
                        Health flags for each member. Family planning methods
                        appear only with the member&apos;s consent.
                    </CardDescription>
                )}
            </CardHeader>
            <CardContent>
                {members.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="No members yet"
                        description="Register residents into this household."
                    />
                ) : (
                    <Table>
                        <caption className="sr-only">Household members</caption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Relationship</TableHead>
                                <TableHead>Age</TableHead>
                                {memberHealth && <TableHead>Health</TableHead>}
                                <TableHead className="hidden lg:table-cell">
                                    Resident ID
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {members.map((m) => (
                                <TableRow key={m.id}>
                                    <TableCell>
                                        <div className="space-y-1">
                                            <Link
                                                href={showResident(m.id)}
                                                className="font-bold underline-offset-4 hover:underline"
                                            >
                                                {m.full_name}
                                            </Link>
                                            <ResidentBadges resident={m} />
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {m.relationship_to_head.label}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {formatAge(m.age, m.birthdate)}
                                    </TableCell>
                                    {memberHealth && (
                                        <TableCell className="min-w-56 whitespace-normal">
                                            <HealthFlags
                                                health={memberHealth[m.id]}
                                            />
                                        </TableCell>
                                    )}
                                    <TableCell className="hidden font-mono lg:table-cell">
                                        {m.resident_code}
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

function HealthFlags({ health }: { health?: MemberHealth }) {
    if (!health) {
        return null;
    }

    const flags = [
        health.critical_alerts > 0 && (
            <StatusBadge key="alert" tone="danger">
                ⚠ {health.critical_alerts} critical alert
                {health.critical_alerts === 1 ? '' : 's'}
            </StatusBadge>
        ),
        health.pregnant && (
            <StatusBadge
                key="pregnant"
                tone={health.high_risk ? 'danger' : 'success'}
            >
                {health.high_risk ? 'High-risk pregnancy' : 'Pregnant'}
            </StatusBadge>
        ),
        health.under_five && (
            <StatusBadge key="u5" tone="info">
                Under 5
            </StatusBadge>
        ),
        health.active_cases > 0 && (
            <StatusBadge key="case" tone="danger">
                Active disease case
            </StatusBadge>
        ),
        ...health.conditions.map((c) => (
            <StatusBadge key={c} tone="neutral">
                {c}
            </StatusBadge>
        )),
    ].filter(Boolean);

    return (
        <div className="flex flex-col gap-1">
            {flags.length > 0 ? (
                <div className="flex flex-wrap gap-1">{flags}</div>
            ) : (
                <span className="text-sm text-muted-foreground">
                    No health flags
                </span>
            )}
            {health.family_planning ? (
                <span className="text-sm">FP: {health.family_planning}</span>
            ) : (
                !health.family_planning_consent && (
                    <span className="text-xs text-muted-foreground">
                        No FP consent
                    </span>
                )
            )}
        </div>
    );
}

HouseholdsShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Households', href: index() },
        { title: props.household.household_no, href: show(props.household.id) },
    ],
});
