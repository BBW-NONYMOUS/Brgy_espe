import { useForm } from '@inertiajs/react';
import ChildMeasurementController from '@/actions/App/Http/Controllers/Health/ChildMeasurementController';
import EmergencyContactController from '@/actions/App/Http/Controllers/Health/EmergencyContactController';
import MedicalAlertController from '@/actions/App/Http/Controllers/Health/MedicalAlertController';
import {
    CheckboxField,
    DateField,
    RadioField,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/app/fields';
import { FormDialog } from '@/components/app/residents/health-dialogs';
import type { DialogProps } from '@/components/app/residents/health-dialogs';
import { toDateOnly } from '@/lib/format';
import type { EmergencyContact, MedicalAlert } from '@/types';
import type { ProfileHealthOptions } from '@/types/pages/records';

/** Add or edit an emergency contact. Making one primary demotes the others. */
export function EmergencyContactDialog({
    residentId,
    contact,
    open,
    onOpenChange,
}: DialogProps & { residentId: number; contact?: EmergencyContact | null }) {
    const editing = !!contact;
    const form = useForm({
        name: contact?.name ?? '',
        relationship: contact?.relationship ?? '',
        phone: contact?.phone ?? '',
        is_primary: contact?.is_primary ?? false,
    });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={editing ? 'Edit emergency contact' : 'Add emergency contact'}
            submitLabel={editing ? 'Save changes' : 'Add contact'}
            processing={form.processing}
            onSubmit={() =>
                form.submit(
                    editing
                        ? EmergencyContactController.update(contact.id)
                        : EmergencyContactController.store(residentId),
                    {
                        preserveScroll: true,
                        onSuccess: () => onOpenChange(false),
                    },
                )
            }
        >
            <TextField
                id="contact_name"
                label="Name"
                required
                autoComplete="off"
                value={form.data.name}
                onChange={(v) => form.setData('name', v)}
                error={form.errors.name}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    id="contact_relationship"
                    label="Relationship"
                    required
                    placeholder="e.g. Daughter, Neighbor"
                    value={form.data.relationship}
                    onChange={(v) => form.setData('relationship', v)}
                    error={form.errors.relationship}
                />
                <TextField
                    id="contact_phone"
                    label="Phone number"
                    required
                    type="tel"
                    inputMode="tel"
                    placeholder="0917 123 4567"
                    value={form.data.phone}
                    onChange={(v) => form.setData('phone', v)}
                    error={form.errors.phone}
                />
            </div>
            <CheckboxField
                id="contact_primary"
                label="Primary contact (call first)"
                checked={form.data.is_primary}
                onChange={(v) => form.setData('is_primary', v)}
            />
        </FormDialog>
    );
}

/** Add or edit a medical alert. Critical ones become the red banner. */
export function MedicalAlertDialog({
    residentId,
    alert,
    options,
    open,
    onOpenChange,
}: DialogProps & {
    residentId: number;
    alert?: MedicalAlert | null;
    options: ProfileHealthOptions;
}) {
    const editing = !!alert;
    const form = useForm({
        alert_type: (alert?.alert_type.value ?? 'allergy') as string,
        description: alert?.description ?? '',
        severity: (alert?.severity.value ?? 'warning') as string,
        is_active: alert?.is_active ?? true,
    });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={editing ? 'Edit medical alert' : 'Add medical alert'}
            description="Critical alerts are shown in red at the top of the profile and on every health form."
            submitLabel={editing ? 'Save changes' : 'Add alert'}
            processing={form.processing}
            onSubmit={() =>
                form.submit(
                    editing
                        ? MedicalAlertController.update(alert.id)
                        : MedicalAlertController.store(residentId),
                    {
                        preserveScroll: true,
                        onSuccess: () => onOpenChange(false),
                    },
                )
            }
        >
            <SelectField
                id="alert_type"
                label="Type"
                required
                value={form.data.alert_type}
                onChange={(v) => form.setData('alert_type', v)}
                options={options.alertTypes}
                error={form.errors.alert_type}
            />
            <TextareaField
                id="alert_description"
                label="What staff need to know"
                required
                rows={3}
                placeholder="e.g. Penicillin allergy: hives and difficulty breathing (2019)."
                value={form.data.description}
                onChange={(v) => form.setData('description', v)}
                error={form.errors.description}
                description="Stored encrypted."
            />
            <RadioField
                id="alert_severity"
                label="Severity"
                required
                value={form.data.severity}
                onChange={(v) => form.setData('severity', v)}
                options={options.alertSeverities}
                error={form.errors.severity}
            />
            {editing && (
                <CheckboxField
                    id="alert_active"
                    label="Still applies"
                    description="Untick when the alert no longer applies; it stays in the history."
                    checked={form.data.is_active}
                    onChange={(v) => form.setData('is_active', v)}
                />
            )}
        </FormDialog>
    );
}

/** One weighing; the status is read from the OPT Plus chart. */
export function GrowthDialog({
    residentId,
    options,
    open,
    onOpenChange,
}: DialogProps & { residentId: number; options: ProfileHealthOptions }) {
    const form = useForm({
        measured_on: toDateOnly(new Date()) as string | null,
        weight_kg: '',
        height_cm: '',
        muac_cm: '',
        nutritional_status: '',
        remarks: '',
    });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Add measurement"
            description="Weigh and measure the child, then read the status from the OPT Plus chart."
            submitLabel="Save measurement"
            processing={form.processing}
            onSubmit={() => {
                form.transform((d) => ({
                    ...d,
                    height_cm: d.height_cm || null,
                    muac_cm: d.muac_cm || null,
                    nutritional_status: d.nutritional_status || null,
                }));
                form.submit(
                    ChildMeasurementController.storeGrowth(residentId),
                    {
                        preserveScroll: true,
                        onSuccess: () => onOpenChange(false),
                    },
                );
            }}
        >
            <DateField
                id="measured_on"
                label="Date measured"
                required
                value={form.data.measured_on}
                onChange={(v) => form.setData('measured_on', v)}
                error={form.errors.measured_on}
                fromYear={2015}
            />
            <div className="grid grid-cols-3 gap-4">
                <TextField
                    id="weight_kg"
                    label="Weight (kg)"
                    required
                    inputMode="decimal"
                    value={form.data.weight_kg}
                    onChange={(v) => form.setData('weight_kg', v)}
                    error={form.errors.weight_kg}
                />
                <TextField
                    id="height_cm"
                    label="Length/height (cm)"
                    inputMode="decimal"
                    value={form.data.height_cm}
                    onChange={(v) => form.setData('height_cm', v)}
                    error={form.errors.height_cm}
                />
                <TextField
                    id="muac_cm"
                    label="MUAC (cm)"
                    inputMode="decimal"
                    value={form.data.muac_cm}
                    onChange={(v) => form.setData('muac_cm', v)}
                    error={form.errors.muac_cm}
                />
            </div>
            <SelectField
                id="nutritional_status"
                label="Nutritional status (OPT Plus)"
                value={form.data.nutritional_status}
                onChange={(v) => form.setData('nutritional_status', v)}
                options={options.nutritionalStatuses}
                error={form.errors.nutritional_status}
            />
            <TextField
                id="growth_remarks"
                label="Remarks"
                value={form.data.remarks}
                onChange={(v) => form.setData('remarks', v)}
                error={form.errors.remarks}
            />
        </FormDialog>
    );
}

/** Vitamin A, deworming, iron or micronutrient powder given. */
export function MicronutrientDialog({
    residentId,
    options,
    open,
    onOpenChange,
}: DialogProps & { residentId: number; options: ProfileHealthOptions }) {
    const form = useForm({
        type: 'vitamin_a',
        dose: '',
        date_given: toDateOnly(new Date()) as string | null,
    });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Record supplement"
            submitLabel="Save"
            processing={form.processing}
            onSubmit={() =>
                form.submit(
                    ChildMeasurementController.storeMicronutrient(residentId),
                    {
                        preserveScroll: true,
                        onSuccess: () => onOpenChange(false),
                    },
                )
            }
        >
            <SelectField
                id="micronutrient_type"
                label="Given"
                required
                value={form.data.type}
                onChange={(v) => form.setData('type', v)}
                options={options.micronutrientTypes}
                error={form.errors.type}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    id="micronutrient_dose"
                    label="Dose"
                    placeholder="e.g. 200,000 IU · Albendazole 400 mg"
                    value={form.data.dose}
                    onChange={(v) => form.setData('dose', v)}
                    error={form.errors.dose}
                />
                <DateField
                    id="micronutrient_date"
                    label="Date given"
                    required
                    value={form.data.date_given}
                    onChange={(v) => form.setData('date_given', v)}
                    error={form.errors.date_given}
                    fromYear={2015}
                />
            </div>
        </FormDialog>
    );
}
