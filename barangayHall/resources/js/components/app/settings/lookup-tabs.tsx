import { router, useForm } from '@inertiajs/react';
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import HealthSettingsController from '@/actions/App/Http/Controllers/Admin/HealthSettingsController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import {
    CheckboxField,
    RadioField,
    SelectField,
    TextField,
} from '@/components/app/fields';
import { LazyGeotagPicker } from '@/components/app/lazy';
import {
    ActiveText,
    SettingsDialog,
} from '@/components/app/settings/settings-dialog';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { GeotagValue } from '@/types';
import type {
    DiseaseRow,
    FacilityRow,
    HealthSettingsProps,
    PurokCenterRow,
    ThresholdRow,
} from '@/types/pages/scheduling';

type Options = HealthSettingsProps['options'];

function EditButton({
    label,
    onClick,
}: {
    label: string;
    onClick: () => void;
}) {
    return (
        <Button
            variant="ghost"
            size="icon"
            className="size-11"
            aria-label={label}
            onClick={onClick}
        >
            <Pencil aria-hidden />
        </Button>
    );
}

// ------------------------------------------------------------ thresholds

export function ThresholdsTab({
    rows,
    options,
}: {
    rows: ThresholdRow[];
    options: Options;
}) {
    const [editing, setEditing] = useState<ThresholdRow | 'new' | null>(null);
    const [deleting, setDeleting] = useState<ThresholdRow | null>(null);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    An outbreak alert is raised when a disease reaches the
                    number of cases within the number of days. Checked when a
                    case is saved and every morning.
                </p>
                <Button onClick={() => setEditing('new')}>
                    <Plus aria-hidden /> Add threshold
                </Button>
            </div>
            <Table>
                <TableCaption className="sr-only">
                    Outbreak thresholds
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Disease</TableHead>
                        <TableHead>Rule</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="w-24 text-right">
                            <span className="sr-only">Actions</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((t) => (
                        <TableRow key={t.id}>
                            <TableCell className="font-medium">
                                {t.disease}
                            </TableCell>
                            <TableCell>
                                {t.min_cases} or more case
                                {t.min_cases === 1 ? '' : 's'} in{' '}
                                {t.scope.value === 'purok'
                                    ? 'the same purok'
                                    : 'the barangay'}{' '}
                                within {t.days_window} days
                            </TableCell>
                            <TableCell>
                                <ActiveText active={t.is_active} />
                            </TableCell>
                            <TableCell className="text-right whitespace-nowrap">
                                <EditButton
                                    label={`Edit threshold for ${t.disease}`}
                                    onClick={() => setEditing(t)}
                                />
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-11 text-destructive"
                                    aria-label={`Remove threshold for ${t.disease}`}
                                    onClick={() => setDeleting(t)}
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            {editing && (
                <ThresholdDialog
                    row={editing === 'new' ? null : editing}
                    options={options}
                    onClose={() => setEditing(null)}
                />
            )}
            {deleting && (
                <ConfirmDialog
                    open
                    onOpenChange={(o) => !o && setDeleting(null)}
                    title={`Remove the ${deleting.disease} threshold?`}
                    description={
                        <p>
                            No new alerts will be raised by this rule. Existing
                            alerts stay.
                        </p>
                    }
                    confirmLabel="Remove threshold"
                    destructive
                    onConfirm={() =>
                        router.delete(
                            HealthSettingsController.destroyThreshold.url(
                                deleting.id,
                            ),
                            {
                                preserveScroll: true,
                                onFinish: () => setDeleting(null),
                            },
                        )
                    }
                />
            )}
        </div>
    );
}

function ThresholdDialog({
    row,
    options,
    onClose,
}: {
    row: ThresholdRow | null;
    options: Options;
    onClose: () => void;
}) {
    const form = useForm({
        disease_id: row ? String(row.disease_id) : '',
        scope: (row?.scope.value ?? 'purok') as string,
        min_cases: row ? String(row.min_cases) : '3',
        days_window: row ? String(row.days_window) : '14',
        is_active: row?.is_active ?? true,
    });

    return (
        <SettingsDialog
            title={
                row ? `Edit ${row.disease} threshold` : 'Add outbreak threshold'
            }
            description="Example: 3 or more dengue cases in the same purok within 14 days."
            onClose={onClose}
            processing={form.processing}
            onSubmit={() =>
                form.submit(
                    row
                        ? HealthSettingsController.updateThreshold(row.id)
                        : HealthSettingsController.storeThreshold(),
                    {
                        preserveScroll: true,
                        onSuccess: onClose,
                    },
                )
            }
        >
            <SelectField
                id="disease_id"
                label="Disease"
                required
                value={form.data.disease_id}
                onChange={(v) => form.setData('disease_id', v)}
                options={options.diseases}
                error={form.errors.disease_id}
            />
            <RadioField
                id="scope"
                label="Count cases in"
                required
                value={form.data.scope}
                onChange={(v) => form.setData('scope', v)}
                options={options.scopes}
                error={form.errors.scope}
            />
            <div className="grid grid-cols-2 gap-4">
                <TextField
                    id="min_cases"
                    label="Number of cases"
                    type="number"
                    min={1}
                    required
                    value={form.data.min_cases}
                    onChange={(v) => form.setData('min_cases', v)}
                    error={form.errors.min_cases}
                />
                <TextField
                    id="days_window"
                    label="Within (days)"
                    type="number"
                    min={1}
                    max={365}
                    required
                    value={form.data.days_window}
                    onChange={(v) => form.setData('days_window', v)}
                    error={form.errors.days_window}
                />
            </div>
            <CheckboxField
                id="threshold_active"
                label="Active"
                checked={form.data.is_active}
                onChange={(v) => form.setData('is_active', v)}
            />
        </SettingsDialog>
    );
}

// --------------------------------------------------------------- diseases

export function DiseasesTab({
    rows,
    options,
}: {
    rows: DiseaseRow[];
    options: Options;
}) {
    const [editing, setEditing] = useState<DiseaseRow | 'new' | null>(null);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    Notifiable disease list following the DOH PIDSR categories.
                    Turn a disease off instead of deleting it.
                </p>
                <Button onClick={() => setEditing('new')}>
                    <Plus aria-hidden /> Add disease
                </Button>
            </div>
            <Table>
                <TableCaption className="sr-only">
                    Notifiable diseases
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Disease</TableHead>
                        <TableHead>ICD-10</TableHead>
                        <TableHead>PIDSR category</TableHead>
                        <TableHead>In use</TableHead>
                        <TableHead className="w-12">
                            <span className="sr-only">Edit</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((d) => (
                        <TableRow key={d.id}>
                            <TableCell className="font-medium">
                                {d.name}
                            </TableCell>
                            <TableCell className="font-mono">
                                {d.icd10_code ?? '—'}
                            </TableCell>
                            <TableCell>
                                <StatusBadge
                                    tone={
                                        d.pidsr_category.value === 'category_1'
                                            ? 'danger'
                                            : d.pidsr_category.value ===
                                                'category_2'
                                              ? 'warning'
                                              : 'neutral'
                                    }
                                >
                                    {d.pidsr_category.label}
                                </StatusBadge>
                            </TableCell>
                            <TableCell>
                                <ActiveText
                                    active={d.is_active}
                                    on="Yes"
                                    off="No"
                                />
                            </TableCell>
                            <TableCell className="text-right">
                                <EditButton
                                    label={`Edit ${d.name}`}
                                    onClick={() => setEditing(d)}
                                />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {editing && (
                <DiseaseDialog
                    row={editing === 'new' ? null : editing}
                    options={options}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

function DiseaseDialog({
    row,
    options,
    onClose,
}: {
    row: DiseaseRow | null;
    options: Options;
    onClose: () => void;
}) {
    const form = useForm({
        name: row?.name ?? '',
        icd10_code: row?.icd10_code ?? '',
        pidsr_category: (row?.pidsr_category.value ?? 'category_2') as string,
        is_notifiable: row?.is_notifiable ?? true,
        is_active: row?.is_active ?? true,
    });

    return (
        <SettingsDialog
            title={row ? `Edit ${row.name}` : 'Add disease'}
            onClose={onClose}
            processing={form.processing}
            onSubmit={() =>
                form.submit(
                    row
                        ? HealthSettingsController.updateDisease(row.id)
                        : HealthSettingsController.storeDisease(),
                    {
                        preserveScroll: true,
                        onSuccess: onClose,
                    },
                )
            }
        >
            <TextField
                id="disease_name"
                label="Name"
                required
                value={form.data.name}
                onChange={(v) => form.setData('name', v)}
                error={form.errors.name}
            />
            <TextField
                id="icd10_code"
                label="ICD-10 code"
                value={form.data.icd10_code}
                onChange={(v) => form.setData('icd10_code', v.toUpperCase())}
                error={form.errors.icd10_code}
            />
            <RadioField
                id="pidsr_category"
                label="PIDSR category"
                required
                value={form.data.pidsr_category}
                onChange={(v) => form.setData('pidsr_category', v)}
                options={options.pidsrCategories}
                error={form.errors.pidsr_category}
            />
            <CheckboxField
                id="is_notifiable"
                label="Notifiable (reported to the RHU)"
                checked={form.data.is_notifiable}
                onChange={(v) => form.setData('is_notifiable', v)}
            />
            <CheckboxField
                id="disease_active"
                label="In use (shown when reporting a case)"
                checked={form.data.is_active}
                onChange={(v) => form.setData('is_active', v)}
            />
        </SettingsDialog>
    );
}

// -------------------------------------------------------------- facilities

export function FacilitiesTab({
    rows,
    options,
}: {
    rows: FacilityRow[];
    options: Options;
}) {
    const [editing, setEditing] = useState<FacilityRow | 'new' | null>(null);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    Where patients can be referred.
                </p>
                <Button onClick={() => setEditing('new')}>
                    <Plus aria-hidden /> Add facility
                </Button>
            </div>
            <Table>
                <TableCaption className="sr-only">
                    Referral facilities
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Facility</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead className="hidden md:table-cell">
                            Address
                        </TableHead>
                        <TableHead className="hidden md:table-cell">
                            Contact
                        </TableHead>
                        <TableHead>In use</TableHead>
                        <TableHead className="w-12">
                            <span className="sr-only">Edit</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((f) => (
                        <TableRow key={f.id}>
                            <TableCell className="font-medium">
                                {f.name}
                            </TableCell>
                            <TableCell>{f.type.label}</TableCell>
                            <TableCell className="hidden md:table-cell">
                                {f.address ?? '—'}
                            </TableCell>
                            <TableCell className="hidden md:table-cell">
                                {f.contact_no ?? '—'}
                            </TableCell>
                            <TableCell>
                                <ActiveText
                                    active={f.is_active}
                                    on="Yes"
                                    off="No"
                                />
                            </TableCell>
                            <TableCell className="text-right">
                                <EditButton
                                    label={`Edit ${f.name}`}
                                    onClick={() => setEditing(f)}
                                />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {editing && (
                <FacilityDialog
                    row={editing === 'new' ? null : editing}
                    options={options}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

function FacilityDialog({
    row,
    options,
    onClose,
}: {
    row: FacilityRow | null;
    options: Options;
    onClose: () => void;
}) {
    const form = useForm({
        name: row?.name ?? '',
        type: (row?.type.value ?? 'hospital') as string,
        address: row?.address ?? '',
        contact_no: row?.contact_no ?? '',
        is_active: row?.is_active ?? true,
    });

    return (
        <SettingsDialog
            title={row ? `Edit ${row.name}` : 'Add facility'}
            onClose={onClose}
            processing={form.processing}
            onSubmit={() =>
                form.submit(
                    row
                        ? HealthSettingsController.updateFacility(row.id)
                        : HealthSettingsController.storeFacility(),
                    {
                        preserveScroll: true,
                        onSuccess: onClose,
                    },
                )
            }
        >
            <TextField
                id="facility_name"
                label="Name"
                required
                value={form.data.name}
                onChange={(v) => form.setData('name', v)}
                error={form.errors.name}
            />
            <RadioField
                id="facility_type"
                label="Type"
                required
                value={form.data.type}
                onChange={(v) => form.setData('type', v)}
                options={options.facilityTypes}
                error={form.errors.type}
            />
            <TextField
                id="facility_address"
                label="Address"
                value={form.data.address}
                onChange={(v) => form.setData('address', v)}
                error={form.errors.address}
            />
            <TextField
                id="facility_contact"
                label="Contact number"
                inputMode="tel"
                value={form.data.contact_no}
                onChange={(v) => form.setData('contact_no', v)}
                error={form.errors.contact_no}
            />
            <CheckboxField
                id="facility_active"
                label="In use (shown on the referral form)"
                checked={form.data.is_active}
                onChange={(v) => form.setData('is_active', v)}
            />
        </SettingsDialog>
    );
}

// ------------------------------------------------------------ purok centres

export function PurokCentersTab({
    rows,
    map,
}: {
    rows: PurokCenterRow[];
    map: HealthSettingsProps['map'];
}) {
    const [editing, setEditing] = useState<PurokCenterRow | null>(null);

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm text-muted-foreground">
                The Barangay Officials' map draws one circle per purok at this
                point — never at a household. Pick a public spot near the middle
                of the purok (e.g. the covered court or chapel).
            </p>
            <Table>
                <TableCaption className="sr-only">
                    Purok map centres
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Purok</TableHead>
                        <TableHead>Map centre</TableHead>
                        <TableHead className="w-12">
                            <span className="sr-only">Edit</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((p) => (
                        <TableRow key={p.id}>
                            <TableCell className="font-medium">
                                {p.name}
                            </TableCell>
                            <TableCell className="font-mono text-sm">
                                {p.center_latitude !== null &&
                                p.center_longitude !== null ? (
                                    `${p.center_latitude.toFixed(5)}, ${p.center_longitude.toFixed(5)}`
                                ) : (
                                    <span className="font-sans text-muted-foreground">
                                        Not set — this purok is not drawn on the
                                        Officials' map
                                    </span>
                                )}
                            </TableCell>
                            <TableCell className="text-right">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-11"
                                    aria-label={`Set the map centre of ${p.name}`}
                                    onClick={() => setEditing(p)}
                                >
                                    <MapPin aria-hidden />
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {editing && (
                <PurokCenterDialog
                    row={editing}
                    map={map}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

function PurokCenterDialog({
    row,
    map,
    onClose,
}: {
    row: PurokCenterRow;
    map: HealthSettingsProps['map'];
    onClose: () => void;
}) {
    const [value, setValue] = useState<GeotagValue | null>(
        row.center_latitude !== null && row.center_longitude !== null
            ? {
                  latitude: row.center_latitude,
                  longitude: row.center_longitude,
                  accuracy_m: null,
              }
            : null,
    );
    const form = useForm({
        center_latitude: row.center_latitude,
        center_longitude: row.center_longitude,
    });
    const errors = form.errors as Partial<Record<string, string>>;

    return (
        <SettingsDialog
            wide
            title={`Map centre of ${row.name}`}
            description="Tap the centre of the purok on the map (you can also stand there and use your location)."
            submitLabel="Save centre"
            onClose={onClose}
            processing={form.processing}
            onSubmit={() => {
                form.transform(() => ({
                    center_latitude: value?.latitude ?? null,
                    center_longitude: value?.longitude ?? null,
                }));
                form.submit(
                    HealthSettingsController.updatePurokCenter(row.id),
                    { preserveScroll: true, onSuccess: onClose },
                );
            }}
        >
            <LazyGeotagPicker
                config={{
                    center: map.center,
                    zoom: map.zoom,
                    max_accuracy_m: 100,
                }}
                value={value}
                onChange={setValue}
            />
            {(errors.center_latitude || errors.center_longitude) && (
                <p role="alert" className="text-sm text-destructive">
                    {errors.center_latitude ?? errors.center_longitude}
                </p>
            )}
        </SettingsDialog>
    );
}
