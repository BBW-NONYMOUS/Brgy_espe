import { useForm } from '@inertiajs/react';
import HealthVisitController from '@/actions/App/Http/Controllers/Health/HealthVisitController';
import {
    DateField,
    RadioField,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/app/fields';
import { MedicalAlertBanner } from '@/components/app/medical-alert-banner';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { toDateOnly } from '@/lib/format';
import type {
    HealthOptions,
    HealthVisit,
    MedicalAlert,
    Pregnancy,
    Resident,
} from '@/types';

type Props = {
    resident: Pick<Resident, 'id' | 'full_name'>;
    options: HealthOptions;
    /** Pregnancy records (ongoing or recently delivered) a visit can be linked to. */
    pregnancies: Pregnancy[];
    /** Edit an existing visit; omit for a new one. */
    visit?: HealthVisit | null;
    /** Critical medical alerts, shown above the form. */
    criticalAlerts?: MedicalAlert[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const num = (v: number | null | undefined) =>
    v === null || v === undefined ? '' : String(v);

/**
 * Quick entry of a consultation with vital signs, from the profile page.
 * Opens from the right on desktop/tablet and full width on phones.
 */
export function VisitSheet({
    resident,
    options,
    pregnancies,
    visit,
    criticalAlerts = [],
    open,
    onOpenChange,
}: Props) {
    const editing = !!visit;
    const linkable = pregnancies.filter((r) => r.status.value !== 'terminated');

    const form = useForm({
        visit_type_id: visit?.visit_type ? String(visit.visit_type.id) : '',
        visit_date: (visit?.visit_date ?? toDateOnly(new Date())) as
            | string
            | null,
        chief_complaint: visit?.chief_complaint ?? '',
        diagnosis: visit?.diagnosis ?? '',
        notes: visit?.notes ?? '',
        vitals: {
            bp_systolic: num(visit?.vitals?.bp_systolic),
            bp_diastolic: num(visit?.vitals?.bp_diastolic),
            weight_kg: num(visit?.vitals?.weight_kg),
            height_cm: num(visit?.vitals?.height_cm),
            temperature_c: num(visit?.vitals?.temperature_c),
            pulse_rate: num(visit?.vitals?.pulse_rate),
            respiratory_rate: num(visit?.vitals?.respiratory_rate),
        },
        maternal: {
            pregnancy_id: visit?.maternal
                ? String(visit.maternal.pregnancy_id)
                : '',
            kind: (visit?.maternal?.kind.value ?? 'prenatal') as string,
            fundal_height_cm: num(visit?.maternal?.fundal_height_cm),
            fetal_heart_rate: num(visit?.maternal?.fetal_heart_rate),
            remarks: visit?.maternal?.remarks ?? '',
        },
    });

    const errors = form.errors as Record<string, string | undefined>;
    const setVital = (key: keyof typeof form.data.vitals, value: string) =>
        form.setData('vitals', { ...form.data.vitals, [key]: value });
    const setMaternal = (key: keyof typeof form.data.maternal, value: string) =>
        form.setData('maternal', { ...form.data.maternal, [key]: value });

    const submit = () => {
        form.transform((data) => {
            const blankToNull = (o: Record<string, string>) =>
                Object.fromEntries(
                    Object.entries(o).map(([k, v]) => [k, v === '' ? null : v]),
                );

            return {
                ...data,
                vitals: blankToNull(data.vitals),
                maternal: data.maternal.pregnancy_id
                    ? blankToNull(data.maternal)
                    : null,
            };
        });

        form.submit(
            editing
                ? HealthVisitController.update(visit.id)
                : HealthVisitController.store(resident.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    onOpenChange(false);
                },
            },
        );
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                    className="flex min-h-full flex-col"
                >
                    <SheetHeader>
                        <SheetTitle>
                            {editing
                                ? 'Edit health visit'
                                : 'Record health visit'}
                        </SheetTitle>
                        <SheetDescription>
                            {resident.full_name}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="grid flex-1 gap-5 px-4 pb-4">
                        <MedicalAlertBanner alerts={criticalAlerts} compact />
                        <div className="grid gap-5 sm:grid-cols-2">
                            <SelectField
                                id="visit_type_id"
                                label="Type of visit"
                                required
                                value={form.data.visit_type_id}
                                onChange={(v) =>
                                    form.setData('visit_type_id', v)
                                }
                                options={options.visitTypes}
                                error={errors.visit_type_id}
                            />
                            <DateField
                                id="visit_date"
                                label="Date"
                                required
                                value={form.data.visit_date}
                                onChange={(v) => form.setData('visit_date', v)}
                                error={errors.visit_date}
                                fromYear={2000}
                            />
                        </div>
                        <TextareaField
                            id="chief_complaint"
                            label="Chief complaint"
                            rows={2}
                            value={form.data.chief_complaint}
                            onChange={(v) => form.setData('chief_complaint', v)}
                            error={errors.chief_complaint}
                            placeholder="e.g. Fever for 2 days"
                        />

                        <fieldset className="grid gap-4">
                            <legend className="mb-2 font-bold">
                                Vital signs
                            </legend>
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                <TextField
                                    id="bp_systolic"
                                    label="BP systolic"
                                    inputMode="numeric"
                                    value={form.data.vitals.bp_systolic}
                                    onChange={(v) => setVital('bp_systolic', v)}
                                    error={errors['vitals.bp_systolic']}
                                    placeholder="mmHg"
                                />
                                <TextField
                                    id="bp_diastolic"
                                    label="BP diastolic"
                                    inputMode="numeric"
                                    value={form.data.vitals.bp_diastolic}
                                    onChange={(v) =>
                                        setVital('bp_diastolic', v)
                                    }
                                    error={errors['vitals.bp_diastolic']}
                                    placeholder="mmHg"
                                />
                                <TextField
                                    id="temperature_c"
                                    label="Temp (°C)"
                                    inputMode="decimal"
                                    value={form.data.vitals.temperature_c}
                                    onChange={(v) =>
                                        setVital('temperature_c', v)
                                    }
                                    error={errors['vitals.temperature_c']}
                                    placeholder="36.5"
                                />
                                <TextField
                                    id="weight_kg"
                                    label="Weight (kg)"
                                    inputMode="decimal"
                                    value={form.data.vitals.weight_kg}
                                    onChange={(v) => setVital('weight_kg', v)}
                                    error={errors['vitals.weight_kg']}
                                />
                                <TextField
                                    id="height_cm"
                                    label="Height (cm)"
                                    inputMode="decimal"
                                    value={form.data.vitals.height_cm}
                                    onChange={(v) => setVital('height_cm', v)}
                                    error={errors['vitals.height_cm']}
                                />
                                <TextField
                                    id="pulse_rate"
                                    label="Pulse (/min)"
                                    inputMode="numeric"
                                    value={form.data.vitals.pulse_rate}
                                    onChange={(v) => setVital('pulse_rate', v)}
                                    error={errors['vitals.pulse_rate']}
                                />
                                <TextField
                                    id="respiratory_rate"
                                    label="Resp. rate (/min)"
                                    inputMode="numeric"
                                    value={form.data.vitals.respiratory_rate}
                                    onChange={(v) =>
                                        setVital('respiratory_rate', v)
                                    }
                                    error={errors['vitals.respiratory_rate']}
                                />
                            </div>
                        </fieldset>

                        {linkable.length > 0 && (
                            <fieldset className="grid gap-4 rounded-xl border p-4">
                                <legend className="px-1 font-bold">
                                    Prenatal / postnatal
                                </legend>
                                <SelectField
                                    id="pregnancy_id"
                                    label="Pregnancy"
                                    value={
                                        form.data.maternal.pregnancy_id ||
                                        'none'
                                    }
                                    onChange={(v) =>
                                        setMaternal(
                                            'pregnancy_id',
                                            v === 'none' ? '' : v,
                                        )
                                    }
                                    options={[
                                        {
                                            value: 'none',
                                            label: 'Not a prenatal/postnatal visit',
                                        },
                                        ...linkable.map((r) => ({
                                            value: r.id,
                                            label: `EDD ${r.edd} · ${r.status.label}`,
                                        })),
                                    ]}
                                    error={errors['maternal.pregnancy_id']}
                                />
                                {form.data.maternal.pregnancy_id && (
                                    <>
                                        <RadioField
                                            id="maternal_kind"
                                            label="Check-up"
                                            value={form.data.maternal.kind}
                                            onChange={(v) =>
                                                setMaternal('kind', v)
                                            }
                                            options={options.maternalVisitKinds}
                                            error={errors['maternal.kind']}
                                        />
                                        <div className="grid grid-cols-2 gap-4">
                                            <TextField
                                                id="fundal_height_cm"
                                                label="Fundal height (cm)"
                                                inputMode="decimal"
                                                value={
                                                    form.data.maternal
                                                        .fundal_height_cm
                                                }
                                                onChange={(v) =>
                                                    setMaternal(
                                                        'fundal_height_cm',
                                                        v,
                                                    )
                                                }
                                                error={
                                                    errors[
                                                        'maternal.fundal_height_cm'
                                                    ]
                                                }
                                            />
                                            <TextField
                                                id="fetal_heart_rate"
                                                label="Fetal heart rate (bpm)"
                                                inputMode="numeric"
                                                value={
                                                    form.data.maternal
                                                        .fetal_heart_rate
                                                }
                                                onChange={(v) =>
                                                    setMaternal(
                                                        'fetal_heart_rate',
                                                        v,
                                                    )
                                                }
                                                error={
                                                    errors[
                                                        'maternal.fetal_heart_rate'
                                                    ]
                                                }
                                            />
                                        </div>
                                        <TextField
                                            id="maternal_remarks"
                                            label="Remarks"
                                            value={form.data.maternal.remarks}
                                            onChange={(v) =>
                                                setMaternal('remarks', v)
                                            }
                                            error={errors['maternal.remarks']}
                                        />
                                    </>
                                )}
                            </fieldset>
                        )}

                        <Separator />
                        <TextareaField
                            id="diagnosis"
                            label="Diagnosis / assessment"
                            rows={2}
                            value={form.data.diagnosis}
                            onChange={(v) => form.setData('diagnosis', v)}
                            error={errors.diagnosis}
                            description="Stored encrypted."
                        />
                        <TextareaField
                            id="notes"
                            label="Notes / advice given"
                            rows={3}
                            value={form.data.notes}
                            onChange={(v) => form.setData('notes', v)}
                            error={errors.notes}
                        />
                    </div>

                    <SheetFooter className="sticky bottom-0 flex-row justify-end border-t bg-background">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {editing ? 'Save changes' : 'Save visit'}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
