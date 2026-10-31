import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import HealthSettingsController from '@/actions/App/Http/Controllers/Admin/HealthSettingsController';
import {
    CheckboxField,
    RadioField,
    SelectField,
    TextField,
} from '@/components/app/fields';
import {
    ActiveText,
    SettingsDialog,
} from '@/components/app/settings/settings-dialog';
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
import type {
    HealthSettingsProps,
    MaternalRuleRow,
    VaccineRuleRow,
} from '@/types/pages/scheduling';

type Options = HealthSettingsProps['options'];

/** Prenatal offsets are shown as weeks of gestation; postnatal as days after delivery. */
function describeOffset(
    rule: Pick<MaternalRuleRow, 'kind' | 'offset_days'>,
): string {
    if (rule.kind.value === 'prenatal') {
        const weeks = Math.floor(rule.offset_days / 7);
        const days = rule.offset_days % 7;

        return `${weeks} weeks${days ? ` ${days} days` : ''} of gestation`;
    }

    return `${rule.offset_days} day${rule.offset_days === 1 ? '' : 's'} after delivery`;
}

/** Age in days → "6 weeks", "9 months", "at birth". */
function describeAge(days: number): string {
    if (days === 0) {
        return 'At birth';
    }

    if (days % 7 === 0 && days < 180) {
        return `${days / 7} weeks`;
    }

    return days >= 270
        ? `${Math.round(days / 30.4)} months (${days} days)`
        : `${days} days`;
}

// -------------------------------------------------------- maternal contacts

export function MaternalRulesTab({
    rows,
    options,
}: {
    rows: MaternalRuleRow[];
    options: Options;
}) {
    const [editing, setEditing] = useState<MaternalRuleRow | 'new' | null>(
        null,
    );

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    Contacts every pregnancy should have. Changing them re-plans
                    upcoming appointments (ones staff have rescheduled stay as
                    they are).
                </p>
                <Button onClick={() => setEditing('new')}>
                    <Plus aria-hidden /> Add contact
                </Button>
            </div>
            {(['prenatal', 'postnatal'] as const).map((kind) => (
                <section key={kind} aria-labelledby={`rules-${kind}`}>
                    <h3 id={`rules-${kind}`} className="mb-1 font-bold">
                        {kind === 'prenatal'
                            ? 'Prenatal contacts'
                            : 'Postnatal contacts'}
                    </h3>
                    <Table>
                        <TableCaption className="sr-only">
                            {kind === 'prenatal'
                                ? 'Prenatal contacts'
                                : 'Postnatal contacts'}
                        </TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-12">No.</TableHead>
                                <TableHead>Label</TableHead>
                                <TableHead>When</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-12">
                                    <span className="sr-only">Edit</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows
                                .filter((r) => r.kind.value === kind)
                                .map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell>{r.sequence}</TableCell>
                                        <TableCell className="font-medium">
                                            {r.label}
                                        </TableCell>
                                        <TableCell>
                                            {describeOffset(r)}
                                        </TableCell>
                                        <TableCell>
                                            <ActiveText active={r.is_active} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-11"
                                                aria-label={`Edit ${r.label}`}
                                                onClick={() => setEditing(r)}
                                            >
                                                <Pencil aria-hidden />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                        </TableBody>
                    </Table>
                </section>
            ))}
            {editing && (
                <MaternalRuleDialog
                    row={editing === 'new' ? null : editing}
                    options={options}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

function MaternalRuleDialog({
    row,
    options,
    onClose,
}: {
    row: MaternalRuleRow | null;
    options: Options;
    onClose: () => void;
}) {
    const prenatalRow = (row?.kind.value ?? 'prenatal') === 'prenatal';
    const form = useForm({
        kind: (row?.kind.value ?? 'prenatal') as string,
        sequence: row ? String(row.sequence) : '',
        label: row?.label ?? '',
        // Prenatal: entered in weeks (+ days); postnatal: in days.
        weeks:
            row && prenatalRow ? String(Math.floor(row.offset_days / 7)) : '',
        days: row
            ? String(prenatalRow ? row.offset_days % 7 : row.offset_days)
            : '0',
        is_active: row?.is_active ?? true,
    });
    const errors = form.errors as Partial<Record<string, string>>;
    const prenatal = form.data.kind === 'prenatal';

    return (
        <SettingsDialog
            title={row ? `Edit ${row.label}` : 'Add contact to the schedule'}
            onClose={onClose}
            processing={form.processing}
            onSubmit={() => {
                form.transform((d) => ({
                    kind: d.kind,
                    sequence: d.sequence,
                    label: d.label,
                    offset_days:
                        d.kind === 'prenatal'
                            ? Number(d.weeks || 0) * 7 + Number(d.days || 0)
                            : Number(d.days || 0),
                    is_active: d.is_active,
                }));
                form.submit(
                    row
                        ? HealthSettingsController.updateMaternalRule(row.id)
                        : HealthSettingsController.storeMaternalRule(),
                    {
                        preserveScroll: true,
                        onSuccess: onClose,
                    },
                );
            }}
        >
            <RadioField
                id="rule_kind"
                label="Type of contact"
                required
                value={form.data.kind}
                onChange={(v) => form.setData('kind', v)}
                options={options.visitKinds}
                error={errors.kind}
            />
            <div className="grid grid-cols-[6rem_1fr] gap-4">
                <TextField
                    id="rule_sequence"
                    label="No."
                    type="number"
                    min={1}
                    required
                    value={form.data.sequence}
                    onChange={(v) => form.setData('sequence', v)}
                    error={errors.sequence}
                />
                <TextField
                    id="rule_label"
                    label="Label"
                    required
                    value={form.data.label}
                    onChange={(v) => form.setData('label', v)}
                    error={errors.label}
                    placeholder={
                        prenatal
                            ? 'e.g. 3rd ANC contact (26 weeks)'
                            : 'e.g. PNC day 7'
                    }
                />
            </div>
            {prenatal ? (
                <div className="grid grid-cols-2 gap-4">
                    <TextField
                        id="rule_weeks"
                        label="Weeks of gestation"
                        type="number"
                        min={0}
                        max={42}
                        required
                        value={form.data.weeks}
                        onChange={(v) => form.setData('weeks', v)}
                        error={errors.offset_days}
                    />
                    <TextField
                        id="rule_days"
                        label="+ days"
                        type="number"
                        min={0}
                        max={6}
                        value={form.data.days}
                        onChange={(v) => form.setData('days', v)}
                    />
                </div>
            ) : (
                <TextField
                    id="rule_days"
                    label="Days after delivery"
                    type="number"
                    min={0}
                    max={60}
                    required
                    value={form.data.days}
                    onChange={(v) => form.setData('days', v)}
                    error={errors.offset_days}
                />
            )}
            <CheckboxField
                id="rule_active"
                label="Active (used for new appointments and overdue checks)"
                checked={form.data.is_active}
                onChange={(v) => form.setData('is_active', v)}
            />
        </SettingsDialog>
    );
}

// --------------------------------------------------------- immunization (EPI)

export function VaccineRulesTab({
    rows,
    options,
}: {
    rows: VaccineRuleRow[];
    options: Options;
}) {
    const [editing, setEditing] = useState<VaccineRuleRow | 'new' | null>(null);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    When each EPI dose is due. A later dose is never due earlier
                    than its minimum gap after the previous dose.
                </p>
                <Button onClick={() => setEditing('new')}>
                    <Plus aria-hidden /> Add dose
                </Button>
            </div>
            <Table>
                <TableCaption className="sr-only">
                    Immunization schedule
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Vaccine</TableHead>
                        <TableHead>Dose</TableHead>
                        <TableHead>Due at age</TableHead>
                        <TableHead>Minimum gap</TableHead>
                        <TableHead className="w-12">
                            <span className="sr-only">Edit</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((r) => (
                        <TableRow key={r.id}>
                            <TableCell>
                                <span className="font-bold">
                                    {r.vaccine_code}
                                </span>
                                <span className="block text-sm text-muted-foreground">
                                    {r.vaccine}
                                </span>
                            </TableCell>
                            <TableCell>Dose {r.dose_no}</TableCell>
                            <TableCell>{describeAge(r.due_age_days)}</TableCell>
                            <TableCell>
                                {r.min_interval_days
                                    ? `${r.min_interval_days} days after dose ${r.dose_no - 1}`
                                    : '—'}
                            </TableCell>
                            <TableCell className="text-right">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-11"
                                    aria-label={`Edit ${r.vaccine_code} dose ${r.dose_no}`}
                                    onClick={() => setEditing(r)}
                                >
                                    <Pencil aria-hidden />
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {editing && (
                <VaccineRuleDialog
                    row={editing === 'new' ? null : editing}
                    options={options}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

function VaccineRuleDialog({
    row,
    options,
    onClose,
}: {
    row: VaccineRuleRow | null;
    options: Options;
    onClose: () => void;
}) {
    const form = useForm({
        vaccine_id: row ? String(row.vaccine_id) : '',
        dose_no: row ? String(row.dose_no) : '1',
        due_age_days: row ? String(row.due_age_days) : '',
        min_interval_days: row?.min_interval_days
            ? String(row.min_interval_days)
            : '',
    });

    return (
        <SettingsDialog
            title={
                row
                    ? `Edit ${row.vaccine_code} dose ${row.dose_no}`
                    : 'Add a dose to the schedule'
            }
            onClose={onClose}
            processing={form.processing}
            onSubmit={() => {
                form.transform((d) => ({
                    ...d,
                    min_interval_days: d.min_interval_days || null,
                }));
                form.submit(
                    row
                        ? HealthSettingsController.updateVaccineRule(row.id)
                        : HealthSettingsController.storeVaccineRule(),
                    {
                        preserveScroll: true,
                        onSuccess: onClose,
                    },
                );
            }}
        >
            <SelectField
                id="vaccine_id"
                label="Vaccine"
                required
                value={form.data.vaccine_id}
                onChange={(v) => form.setData('vaccine_id', v)}
                options={options.vaccines.map((v) => ({
                    value: v.value,
                    label: `${v.code} — ${v.label}`,
                }))}
                error={form.errors.vaccine_id}
            />
            <div className="grid grid-cols-2 gap-4">
                <TextField
                    id="dose_no"
                    label="Dose number"
                    type="number"
                    min={1}
                    max={10}
                    required
                    value={form.data.dose_no}
                    onChange={(v) => form.setData('dose_no', v)}
                    error={form.errors.dose_no}
                />
                <TextField
                    id="due_age_days"
                    label="Due at age (days)"
                    type="number"
                    min={0}
                    required
                    value={form.data.due_age_days}
                    onChange={(v) => form.setData('due_age_days', v)}
                    error={form.errors.due_age_days}
                    description={
                        form.data.due_age_days
                            ? describeAge(Number(form.data.due_age_days))
                            : '0 = at birth, 42 = 6 weeks, 270 = 9 months'
                    }
                />
            </div>
            <TextField
                id="min_interval_days"
                label="Minimum days after the previous dose"
                type="number"
                min={1}
                value={form.data.min_interval_days}
                onChange={(v) => form.setData('min_interval_days', v)}
                error={form.errors.min_interval_days}
                description="Leave empty for the first dose. DOH: at least 28 days between Penta, OPV and PCV doses."
            />
        </SettingsDialog>
    );
}
