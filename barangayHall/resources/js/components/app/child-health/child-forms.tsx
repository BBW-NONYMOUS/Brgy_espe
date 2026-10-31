import { useForm } from '@inertiajs/react';
import ChildMeasurementController from '@/actions/App/Http/Controllers/Health/ChildMeasurementController';
import ImmunizationController from '@/actions/App/Http/Controllers/Health/ImmunizationController';
import { DateField, SelectField, TextField } from '@/components/app/fields';
import { FormDialog } from '@/components/app/maternal/form-dialog';
import type { DialogProps } from '@/components/app/maternal/form-dialog';
import { toDateOnly } from '@/lib/format';
import type { ScheduledDose } from '@/types';
import type { ChildHealthShowPageProps } from '@/types/pages/health';

type Options = NonNullable<ChildHealthShowPageProps['options']>;

/** Record a vaccine dose, prefilled from the clicked checklist tile. */
export function DoseDialog({
    childId,
    options,
    preset,
    open,
    onOpenChange,
}: DialogProps & {
    childId: number;
    options: Options;
    preset: ScheduledDose | null;
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
            description="Saving books the next dose automatically."
            submitLabel="Save dose"
            processing={form.processing}
            onSubmit={() =>
                form.submit(ImmunizationController.store(childId), {
                    preserveScroll: true,
                    onSuccess: () => onOpenChange(false),
                })
            }
        >
            <SelectField
                id="dose_vaccine"
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
                    id="dose_date"
                    label="Date given"
                    required
                    value={form.data.date_given}
                    onChange={(v) => form.setData('date_given', v)}
                    error={form.errors.date_given}
                    fromYear={2020}
                />
            </div>
            <TextField
                id="dose_lot"
                label="Lot number"
                value={form.data.lot_no}
                onChange={(v) => form.setData('lot_no', v)}
                error={form.errors.lot_no}
            />
        </FormDialog>
    );
}

/** One weighing; the status is read from the OPT Plus chart. */
export function GrowthDialog({
    childId,
    options,
    open,
    onOpenChange,
}: DialogProps & { childId: number; options: Options }) {
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
            description="Use the OPT Plus chart to read the nutritional status."
            submitLabel="Save measurement"
            processing={form.processing}
            onSubmit={() => {
                form.transform((d) => ({
                    ...d,
                    height_cm: d.height_cm || null,
                    muac_cm: d.muac_cm || null,
                    nutritional_status: d.nutritional_status || null,
                }));
                form.submit(ChildMeasurementController.storeGrowth(childId), {
                    preserveScroll: true,
                    onSuccess: () => onOpenChange(false),
                });
            }}
        >
            <DateField
                id="measured_on"
                label="Date measured"
                required
                value={form.data.measured_on}
                onChange={(v) => form.setData('measured_on', v)}
                error={form.errors.measured_on}
                fromYear={2020}
            />
            <div className="grid gap-4 sm:grid-cols-3">
                <TextField
                    id="weight_kg"
                    label="Weight (kg)"
                    type="number"
                    inputMode="decimal"
                    step="0.01"
                    required
                    value={form.data.weight_kg}
                    onChange={(v) => form.setData('weight_kg', v)}
                    error={form.errors.weight_kg}
                />
                <TextField
                    id="height_cm"
                    label="Length/height (cm)"
                    type="number"
                    inputMode="decimal"
                    step="0.1"
                    value={form.data.height_cm}
                    onChange={(v) => form.setData('height_cm', v)}
                    error={form.errors.height_cm}
                />
                <TextField
                    id="muac_cm"
                    label="MUAC (cm)"
                    type="number"
                    inputMode="decimal"
                    step="0.1"
                    value={form.data.muac_cm}
                    onChange={(v) => form.setData('muac_cm', v)}
                    error={form.errors.muac_cm}
                    description="6–59 months"
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

const DOSES: Record<string, string> = {
    vitamin_a: '200,000 IU',
    deworming: 'Albendazole 400 mg',
    iron: '',
    micronutrient_powder: '1 sachet',
};

/** Vitamin A, deworming, iron or micronutrient powder given. */
export function MicronutrientDialog({
    childId,
    ageMonths,
    options,
    preset,
    open,
    onOpenChange,
}: DialogProps & {
    childId: number;
    ageMonths: number;
    options: Options;
    preset?: string;
}) {
    const doseFor = (type: string) =>
        type === 'vitamin_a' && ageMonths < 12
            ? '100,000 IU'
            : type === 'deworming' && ageMonths < 24
              ? 'Albendazole 200 mg'
              : (DOSES[type] ?? '');
    const form = useForm({
        type: preset ?? 'vitamin_a',
        dose: doseFor(preset ?? 'vitamin_a'),
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
                    ChildMeasurementController.storeMicronutrient(childId),
                    {
                        preserveScroll: true,
                        onSuccess: () => onOpenChange(false),
                    },
                )
            }
        >
            <SelectField
                id="mn_type"
                label="Given"
                required
                value={form.data.type}
                onChange={(v) =>
                    form.setData((d) => ({ ...d, type: v, dose: doseFor(v) }))
                }
                options={options.micronutrientTypes}
                error={form.errors.type}
            />
            <div className="grid grid-cols-2 gap-4">
                <TextField
                    id="mn_dose"
                    label="Dose"
                    value={form.data.dose}
                    onChange={(v) => form.setData('dose', v)}
                    error={form.errors.dose}
                />
                <DateField
                    id="mn_date"
                    label="Date given"
                    required
                    value={form.data.date_given}
                    onChange={(v) => form.setData('date_given', v)}
                    error={form.errors.date_given}
                    fromYear={2020}
                />
            </div>
        </FormDialog>
    );
}
