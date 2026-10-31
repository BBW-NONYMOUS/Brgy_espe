import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import ConsentController from '@/actions/App/Http/Controllers/Health/ConsentController';
import ImmunizationController from '@/actions/App/Http/Controllers/Health/ImmunizationController';
import PregnancyController from '@/actions/App/Http/Controllers/Health/PregnancyController';
import ResidentConditionController from '@/actions/App/Http/Controllers/Health/ResidentConditionController';
import {
    CheckboxField,
    DateField,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/app/fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { parseDateOnly, toDateOnly } from '@/lib/format';
import type {
    HealthOptions,
    Option,
    Pregnancy,
    ResidentCondition,
    ScheduledDose,
} from '@/types';

export type DialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    onSubmit,
    processing,
    submitLabel,
    children,
}: DialogProps & {
    title: string;
    description?: string;
    onSubmit: () => void;
    processing: boolean;
    submitLabel: string;
    children: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <form
                    onSubmit={(e: FormEvent) => {
                        e.preventDefault();
                        onSubmit();
                    }}
                    className="space-y-5"
                >
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description && (
                            <DialogDescription>{description}</DialogDescription>
                        )}
                    </DialogHeader>
                    {children}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** Record a vaccine dose; can be prefilled from the EPI schedule. */
export function ImmunizationDialog({
    residentId,
    options,
    preset,
    open,
    onOpenChange,
}: DialogProps & {
    residentId: number;
    options: HealthOptions;
    preset?: ScheduledDose | null;
}) {
    const form = useForm({
        vaccine_id: preset ? String(preset.vaccine_id) : '',
        dose_no: preset ? String(preset.dose_no) : '1',
        date_given: toDateOnly(new Date()) as string | null,
        lot_no: '',
        remarks: '',
    });
    const vaccine = options.vaccines.find(
        (v) => String(v.value) === form.data.vaccine_id,
    );

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Record immunization"
            description="Each dose can only be recorded once."
            submitLabel="Save dose"
            processing={form.processing}
            onSubmit={() =>
                form.submit(ImmunizationController.store(residentId), {
                    preserveScroll: true,
                    onSuccess: () => onOpenChange(false),
                })
            }
        >
            <SelectField
                id="vaccine_id"
                label="Vaccine"
                required
                value={form.data.vaccine_id}
                onChange={(v) =>
                    form.setData((d) => ({ ...d, vaccine_id: v, dose_no: '1' }))
                }
                options={options.vaccines.map((v) => ({
                    value: v.value,
                    label: `${v.code} — ${v.label}`,
                }))}
                error={form.errors.vaccine_id}
            />
            <div className="grid grid-cols-2 gap-4">
                <SelectField
                    id="dose_no"
                    label="Dose"
                    required
                    value={form.data.dose_no}
                    onChange={(v) => form.setData('dose_no', v)}
                    options={Array.from(
                        { length: vaccine?.doses ?? 1 },
                        (_, i) => ({ value: i + 1, label: `Dose ${i + 1}` }),
                    )}
                    error={form.errors.dose_no}
                />
                <DateField
                    id="date_given"
                    label="Date given"
                    required
                    value={form.data.date_given}
                    onChange={(v) => form.setData('date_given', v)}
                    error={form.errors.date_given}
                    fromYear={2000}
                />
            </div>
            <TextField
                id="lot_no"
                label="Lot number"
                value={form.data.lot_no}
                onChange={(v) => form.setData('lot_no', v)}
                error={form.errors.lot_no}
            />
            <TextField
                id="imm_remarks"
                label="Remarks"
                value={form.data.remarks}
                onChange={(v) => form.setData('remarks', v)}
                error={form.errors.remarks}
            />
        </FormDialog>
    );
}

/** Add or update a diagnosed condition. */
export function ConditionDialog({
    residentId,
    options,
    condition,
    open,
    onOpenChange,
}: DialogProps & {
    residentId: number;
    options: HealthOptions;
    condition?: ResidentCondition | null;
}) {
    const editing = !!condition;
    const form = useForm({
        condition_id: condition?.condition
            ? String(condition.condition.id)
            : '',
        diagnosed_date: (condition?.diagnosed_date ?? null) as string | null,
        status: (condition?.status.value ?? 'active') as string,
        notes: condition?.notes ?? '',
    });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={editing ? 'Update condition' : 'Add condition'}
            submitLabel={editing ? 'Save changes' : 'Add condition'}
            processing={form.processing}
            onSubmit={() =>
                form.submit(
                    editing
                        ? ResidentConditionController.update(condition.id)
                        : ResidentConditionController.store(residentId),
                    {
                        preserveScroll: true,
                        onSuccess: () => onOpenChange(false),
                    },
                )
            }
        >
            <SelectField
                id="condition_id"
                label="Condition"
                required
                value={form.data.condition_id}
                onChange={(v) => form.setData('condition_id', v)}
                options={options.conditions}
                error={form.errors.condition_id}
            />
            <div className="grid grid-cols-2 gap-4">
                <DateField
                    id="diagnosed_date"
                    label="Diagnosed on"
                    value={form.data.diagnosed_date}
                    onChange={(v) => form.setData('diagnosed_date', v)}
                    error={form.errors.diagnosed_date}
                />
                <SelectField
                    id="condition_status"
                    label="Status"
                    required
                    value={form.data.status}
                    onChange={(v) => form.setData('status', v)}
                    options={options.conditionStatuses}
                    error={form.errors.status}
                />
            </div>
            <TextareaField
                id="condition_notes"
                label="Notes"
                rows={2}
                value={form.data.notes}
                onChange={(v) => form.setData('notes', v)}
                error={form.errors.notes}
                description="Stored encrypted."
            />
        </FormDialog>
    );
}

/**
 * Start or update a pregnancy record. EDD defaults to LMP + 280 days. Any
 * ticked risk factor makes the pregnancy high-risk. "Delivered" is set by
 * recording the delivery in the maternal tracker, not here.
 */
export function MaternalDialog({
    residentId,
    options,
    record,
    open,
    onOpenChange,
}: DialogProps & {
    residentId: number;
    options: HealthOptions & { riskFactors?: Option[] };
    record?: Pregnancy | null;
}) {
    const editing = !!record;
    const delivered = record?.status.value === 'delivered';
    const statusOptions = options.maternalStatuses.filter(
        (s) => delivered || s.value !== 'delivered',
    );
    const form = useForm({
        risk_factor_ids: (record?.risk_factors ?? []).map((f) => f.id),
        lmp: (record?.lmp ?? null) as string | null,
        edd: (record?.edd ?? null) as string | null,
        gravida: record ? String(record.gravida) : '1',
        para: record ? String(record.para) : '0',
        risk_level: (record?.risk_level.value ?? 'low') as string,
        notes: record?.notes ?? '',
        status: (record?.status.value ?? 'ongoing') as string,
        outcome_date: (record?.outcome_date ?? null) as string | null,
    });
    const errors = form.errors as Record<string, string | undefined>;

    const setLmp = (lmp: string | null) => {
        const edd = lmp
            ? toDateOnly(
                  new Date(parseDateOnly(lmp).getTime() + 280 * 86_400_000),
              )
            : form.data.edd;
        form.setData((d) => ({ ...d, lmp, edd }));
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={editing ? 'Update pregnancy record' : 'New pregnancy record'}
            description="Expected delivery date is filled in from the LMP (LMP + 280 days); change it if ultrasound dating differs."
            submitLabel={editing ? 'Save changes' : 'Add pregnancy'}
            processing={form.processing}
            onSubmit={() => {
                // Status and outcome only exist on update (the server prohibits them on create).
                form.transform((data) =>
                    editing
                        ? data
                        : {
                              lmp: data.lmp,
                              edd: data.edd,
                              gravida: data.gravida,
                              para: data.para,
                              risk_level: data.risk_level,
                              risk_factor_ids: data.risk_factor_ids,
                              notes: data.notes,
                          },
                );
                form.submit(
                    editing
                        ? PregnancyController.update(record.id)
                        : PregnancyController.store(residentId),
                    {
                        preserveScroll: true,
                        onSuccess: () => onOpenChange(false),
                    },
                );
            }}
        >
            {errors.resident && (
                <p
                    role="alert"
                    className="rounded-lg bg-danger-soft p-3 text-sm text-danger-soft-foreground"
                >
                    {errors.resident}
                </p>
            )}
            <div className="grid grid-cols-2 gap-4">
                <DateField
                    id="lmp"
                    label="Last menstrual period"
                    value={form.data.lmp}
                    onChange={setLmp}
                    error={errors.lmp}
                />
                <DateField
                    id="edd"
                    label="Expected delivery"
                    required
                    disableFuture={false}
                    value={form.data.edd}
                    onChange={(v) => form.setData('edd', v)}
                    error={errors.edd}
                />
                <TextField
                    id="gravida"
                    label="Gravida (pregnancies)"
                    required
                    inputMode="numeric"
                    value={form.data.gravida}
                    onChange={(v) => form.setData('gravida', v)}
                    error={errors.gravida}
                />
                <TextField
                    id="para"
                    label="Para (births)"
                    required
                    inputMode="numeric"
                    value={form.data.para}
                    onChange={(v) => form.setData('para', v)}
                    error={errors.para}
                />
            </div>
            {options.riskFactors && options.riskFactors.length > 0 && (
                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">
                        Risk factors
                    </legend>
                    <p className="text-sm text-muted-foreground">
                        Ticking any factor marks the pregnancy high-risk.
                    </p>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {options.riskFactors.map((f) => (
                            <CheckboxField
                                key={f.value}
                                id={`risk_factor_${f.value}`}
                                label={f.label}
                                checked={form.data.risk_factor_ids.includes(
                                    Number(f.value),
                                )}
                                onChange={(checked) =>
                                    form.setData(
                                        'risk_factor_ids',
                                        checked
                                            ? [
                                                  ...form.data.risk_factor_ids,
                                                  Number(f.value),
                                              ]
                                            : form.data.risk_factor_ids.filter(
                                                  (id) =>
                                                      id !== Number(f.value),
                                              ),
                                    )
                                }
                            />
                        ))}
                    </div>
                </fieldset>
            )}
            <SelectField
                id="risk_level"
                label="Risk"
                required
                value={
                    form.data.risk_factor_ids.length > 0
                        ? 'high'
                        : form.data.risk_level
                }
                onChange={(v) => form.setData('risk_level', v)}
                options={options.riskLevels}
                error={errors.risk_level}
                disabled={form.data.risk_factor_ids.length > 0}
                description={
                    form.data.risk_factor_ids.length > 0
                        ? 'High-risk because a risk factor is ticked.'
                        : undefined
                }
            />
            {editing && (
                <div className="grid grid-cols-2 gap-4">
                    <SelectField
                        id="maternal_status"
                        label="Status"
                        required
                        value={form.data.status}
                        onChange={(v) => form.setData('status', v)}
                        options={statusOptions}
                        disabled={delivered}
                        error={errors.status}
                    />
                    {form.data.status !== 'ongoing' && (
                        <DateField
                            id="outcome_date"
                            label={
                                delivered
                                    ? 'Date of delivery'
                                    : 'Date of pregnancy loss'
                            }
                            required
                            value={form.data.outcome_date}
                            onChange={(v) => form.setData('outcome_date', v)}
                            error={errors.outcome_date}
                        />
                    )}
                </div>
            )}
            <TextareaField
                id="maternal_notes"
                label="Notes"
                rows={2}
                value={form.data.notes}
                onChange={(v) => form.setData('notes', v)}
                error={errors.notes}
                description="Stored encrypted."
            />
        </FormDialog>
    );
}

/** Default purpose text per consent type (editable). */
export const CONSENT_PURPOSES: Record<string, string> = {
    health_profiling:
        'Barangay health profiling and delivery of primary health care services (RA 10173)',
    geotagging:
        'Recording the household location for health service delivery and disease surveillance (RA 10173)',
    family_planning:
        'Recording the family planning method for FP program services and reports (RA 10173)',
    sms_reminders:
        'Receiving appointment reminders by SMS; the mobile number is shared with the SMS provider (RA 10173)',
};

/** Record a new consent (e.g. renewed, or for a new purpose). */
export function ConsentDialog({
    residentId,
    consentTypes,
    defaultType = 'health_profiling',
    title = 'Record consent',
    description,
    open,
    onOpenChange,
}: DialogProps & {
    residentId: number;
    /** Choices for the consent type; omit to record general health profiling consent. */
    consentTypes?: { value: string; label: string }[];
    defaultType?: string;
    title?: string;
    description?: string;
}) {
    const form = useForm({
        consent_type: defaultType,
        consent_date: toDateOnly(new Date()) as string | null,
        purpose: CONSENT_PURPOSES[defaultType] ?? '',
    });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description={description}
            submitLabel="Save consent"
            processing={form.processing}
            onSubmit={() =>
                form.submit(ConsentController.store(residentId), {
                    preserveScroll: true,
                    onSuccess: () => onOpenChange(false),
                })
            }
        >
            {consentTypes && (
                <SelectField
                    id="new_consent_type"
                    label="Consent for"
                    required
                    value={form.data.consent_type}
                    onChange={(v) =>
                        form.setData((d) => ({
                            ...d,
                            consent_type: v,
                            purpose: CONSENT_PURPOSES[v] ?? d.purpose,
                        }))
                    }
                    options={consentTypes}
                    error={form.errors.consent_type}
                />
            )}
            <DateField
                id="new_consent_date"
                label="Date of consent"
                required
                value={form.data.consent_date}
                onChange={(v) => form.setData('consent_date', v)}
                error={form.errors.consent_date}
            />
            <TextareaField
                id="new_consent_purpose"
                label="Purpose"
                required
                rows={2}
                value={form.data.purpose}
                onChange={(v) => form.setData('purpose', v)}
                error={form.errors.purpose}
            />
        </FormDialog>
    );
}
