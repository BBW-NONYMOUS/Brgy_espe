import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import DeliveryController from '@/actions/App/Http/Controllers/Health/DeliveryController';
import HealthVisitController from '@/actions/App/Http/Controllers/Health/HealthVisitController';
import PregnancyController from '@/actions/App/Http/Controllers/Health/PregnancyController';
import {
    CheckboxField,
    DateField,
    RadioField,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/app/fields';
import { FormDialog } from '@/components/app/maternal/form-dialog';
import type { DialogProps } from '@/components/app/maternal/form-dialog';
import { MedicalAlertBanner } from '@/components/app/medical-alert-banner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import type { MaternalContact, MedicalAlert, Pregnancy } from '@/types';
import type { MaternalShowPageProps } from '@/types/pages/health';

type Options = NonNullable<MaternalShowPageProps['options']>;

/** Edit LMP/EDD, G/P, risk, or record a pregnancy loss. */
export function PregnancyEditDialog({
    pregnancy,
    options,
    open,
    onOpenChange,
}: DialogProps & { pregnancy: Pregnancy; options: Options }) {
    const delivered = pregnancy.status.value === 'delivered';
    const form = useForm({
        lmp: pregnancy.lmp,
        edd: pregnancy.edd as string | null,
        gravida: String(pregnancy.gravida),
        para: String(pregnancy.para),
        risk_level: pregnancy.risk_level.value as string,
        risk_factor_ids: (pregnancy.risk_factors ?? []).map((f) => f.id),
        notes: pregnancy.notes ?? '',
        status: pregnancy.status.value as string,
        outcome_date: pregnancy.outcome_date,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const statuses = options.statuses.filter((s) =>
        delivered ? s.value === 'delivered' : s.value !== 'delivered',
    );

    const toggleFactor = (id: number, on: boolean) =>
        form.setData(
            'risk_factor_ids',
            on
                ? [...form.data.risk_factor_ids, id]
                : form.data.risk_factor_ids.filter((f) => f !== id),
        );

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Edit pregnancy"
            description={
                delivered
                    ? 'Delivery details are recorded on the Delivery tab.'
                    : 'To record a delivery, use the Delivery tab.'
            }
            submitLabel="Save changes"
            processing={form.processing}
            className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl"
            onSubmit={() => {
                form.transform((d) => ({
                    ...d,
                    outcome_date:
                        d.status === 'ongoing' ? null : d.outcome_date,
                }));
                form.submit(PregnancyController.update(pregnancy.id), {
                    preserveScroll: true,
                    onSuccess: () => onOpenChange(false),
                });
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <DateField
                    id="preg_lmp"
                    label="Last menstrual period (LMP)"
                    value={form.data.lmp}
                    onChange={(v) => form.setData('lmp', v)}
                    error={errors.lmp}
                    fromYear={2020}
                />
                <DateField
                    id="preg_edd"
                    label="Expected delivery date (EDD)"
                    required
                    value={form.data.edd}
                    onChange={(v) => form.setData('edd', v)}
                    error={errors.edd}
                    disableFuture={false}
                    fromYear={2020}
                    description="Correct it if an ultrasound dated the pregnancy."
                />
                <TextField
                    id="preg_gravida"
                    label="Gravida (pregnancies)"
                    type="number"
                    inputMode="numeric"
                    min={1}
                    required
                    value={form.data.gravida}
                    onChange={(v) => form.setData('gravida', v)}
                    error={errors.gravida}
                />
                <TextField
                    id="preg_para"
                    label="Para (births)"
                    type="number"
                    inputMode="numeric"
                    min={0}
                    required
                    value={form.data.para}
                    onChange={(v) => form.setData('para', v)}
                    error={errors.para}
                />
            </div>

            <fieldset className="space-y-2">
                <legend className="mb-2 text-sm font-medium">
                    Risk factors{' '}
                    <span className="font-normal text-muted-foreground">
                        (any one makes the pregnancy high-risk)
                    </span>
                </legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    {options.riskFactors.map((f) => (
                        <CheckboxField
                            key={f.value}
                            id={`risk_${f.value}`}
                            label={f.label}
                            checked={form.data.risk_factor_ids.includes(
                                Number(f.value),
                            )}
                            onChange={(on) => toggleFactor(Number(f.value), on)}
                        />
                    ))}
                </div>
            </fieldset>

            <RadioField
                id="preg_risk_level"
                label="Risk level"
                required
                value={
                    form.data.risk_factor_ids.length > 0
                        ? 'high'
                        : form.data.risk_level
                }
                onChange={(v) => form.setData('risk_level', v)}
                options={options.riskLevels}
                error={errors.risk_level}
                description={
                    form.data.risk_factor_ids.length > 0
                        ? 'High risk because a risk factor is ticked.'
                        : 'Mark high risk if the midwife judges so even without a listed factor.'
                }
            />

            <div className="grid gap-4 sm:grid-cols-2">
                <SelectField
                    id="preg_status"
                    label="Status"
                    required
                    value={form.data.status}
                    onChange={(v) => form.setData('status', v)}
                    options={statuses}
                    error={errors.status}
                    disabled={delivered}
                />
                {form.data.status === 'terminated' && (
                    <DateField
                        id="preg_outcome_date"
                        label="Date of pregnancy loss"
                        required
                        value={form.data.outcome_date}
                        onChange={(v) => form.setData('outcome_date', v)}
                        error={errors.outcome_date}
                        fromYear={2020}
                    />
                )}
            </div>

            <TextareaField
                id="preg_notes"
                label="Notes"
                rows={3}
                value={form.data.notes}
                onChange={(v) => form.setData('notes', v)}
                error={errors.notes}
                description="Stored encrypted."
            />
        </FormDialog>
    );
}

type Newborn = {
    first_name: string;
    middle_name: string;
    last_name: string;
    sex: string;
    relationship_to_head: string;
    birth_weight_kg: string;
};

/**
 * Record the delivery. Ends the pregnancy, optionally registers the babies
 * as residents of the mother's household, and books postnatal and newborn
 * immunization appointments.
 */
export function DeliveryForm({
    pregnancy,
    options,
}: {
    pregnancy: Pregnancy;
    options: Options;
}) {
    const motherLast = pregnancy.resident?.full_name.split(',')[0] ?? '';
    const blankBaby = (): Newborn => ({
        first_name: '',
        middle_name: '',
        last_name: motherLast,
        sex: '',
        relationship_to_head: 'child',
        birth_weight_kg: '',
    });
    const form = useForm({
        delivery_date: toDateOnly(new Date()) as string | null,
        delivery_time: '',
        place_type: 'health_facility',
        place_name: '',
        attendant_type: 'midwife',
        delivery_mode: 'normal',
        live_births: '1',
        stillbirths: '0',
        remarks: '',
        newborns: [blankBaby()] as Newborn[],
    });
    const errors = form.errors as Record<string, string | undefined>;

    const setBaby = (i: number, key: keyof Newborn, value: string) =>
        form.setData(
            'newborns',
            form.data.newborns.map((b, j) =>
                j === i ? { ...b, [key]: value } : b,
            ),
        );

    return (
        <Card>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(DeliveryController.store(pregnancy.id), {
                        preserveScroll: true,
                    });
                }}
            >
                <CardHeader>
                    <CardTitle>Record delivery</CardTitle>
                    <CardDescription>
                        Ends the pregnancy and schedules postnatal check-ups.
                        Registering the baby here starts the immunization
                        schedule.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    {errors.pregnancy && (
                        <p role="alert" className="text-sm text-destructive">
                            {errors.pregnancy}
                        </p>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <DateField
                            id="delivery_date"
                            label="Date of delivery"
                            required
                            value={form.data.delivery_date}
                            onChange={(v) => form.setData('delivery_date', v)}
                            error={errors.delivery_date}
                            fromYear={2020}
                        />
                        <TextField
                            id="delivery_time"
                            label="Time of birth"
                            type="time"
                            value={form.data.delivery_time}
                            onChange={(v) => form.setData('delivery_time', v)}
                            error={errors.delivery_time}
                        />
                    </div>
                    <RadioField
                        id="place_type"
                        label="Place of delivery"
                        required
                        value={form.data.place_type}
                        onChange={(v) => form.setData('place_type', v)}
                        options={options.deliveryPlaces}
                        error={errors.place_type}
                    />
                    <TextField
                        id="place_name"
                        label="Name of facility / place"
                        value={form.data.place_name}
                        onChange={(v) => form.setData('place_name', v)}
                        error={errors.place_name}
                        placeholder="e.g. Isulan RHU birthing facility"
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            id="attendant_type"
                            label="Attended by"
                            required
                            value={form.data.attendant_type}
                            onChange={(v) => form.setData('attendant_type', v)}
                            options={options.attendants}
                            error={errors.attendant_type}
                        />
                        <SelectField
                            id="delivery_mode"
                            label="Type of delivery"
                            required
                            value={form.data.delivery_mode}
                            onChange={(v) => form.setData('delivery_mode', v)}
                            options={options.deliveryModes}
                            error={errors.delivery_mode}
                        />
                        <TextField
                            id="live_births"
                            label="Live births"
                            type="number"
                            inputMode="numeric"
                            min={0}
                            max={5}
                            required
                            value={form.data.live_births}
                            onChange={(v) => form.setData('live_births', v)}
                            error={errors.live_births}
                        />
                        <TextField
                            id="stillbirths"
                            label="Stillbirths"
                            type="number"
                            inputMode="numeric"
                            min={0}
                            max={5}
                            required
                            value={form.data.stillbirths}
                            onChange={(v) => form.setData('stillbirths', v)}
                            error={errors.stillbirths}
                        />
                    </div>

                    <fieldset className="space-y-4">
                        <legend className="text-base font-bold">
                            Register the baby (optional)
                        </legend>
                        {errors.newborns && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {errors.newborns}
                            </p>
                        )}
                        {form.data.newborns.map((baby, i) => (
                            <div
                                key={i}
                                className="space-y-4 rounded-lg border p-4"
                            >
                                <div className="flex items-center justify-between">
                                    <h4 className="font-bold">Baby {i + 1}</h4>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            form.setData(
                                                'newborns',
                                                form.data.newborns.filter(
                                                    (_, j) => j !== i,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 aria-hidden /> Remove
                                    </Button>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <TextField
                                        id={`nb_${i}_first`}
                                        label="First name"
                                        required
                                        value={baby.first_name}
                                        onChange={(v) =>
                                            setBaby(i, 'first_name', v)
                                        }
                                        error={
                                            errors[`newborns.${i}.first_name`]
                                        }
                                    />
                                    <TextField
                                        id={`nb_${i}_middle`}
                                        label="Middle name"
                                        value={baby.middle_name}
                                        onChange={(v) =>
                                            setBaby(i, 'middle_name', v)
                                        }
                                    />
                                    <TextField
                                        id={`nb_${i}_last`}
                                        label="Last name"
                                        required
                                        value={baby.last_name}
                                        onChange={(v) =>
                                            setBaby(i, 'last_name', v)
                                        }
                                        error={
                                            errors[`newborns.${i}.last_name`]
                                        }
                                    />
                                    <RadioField
                                        id={`nb_${i}_sex`}
                                        label="Sex"
                                        required
                                        value={baby.sex}
                                        onChange={(v) => setBaby(i, 'sex', v)}
                                        options={options.sexes}
                                        error={errors[`newborns.${i}.sex`]}
                                    />
                                    <SelectField
                                        id={`nb_${i}_rel`}
                                        label="Relationship to household head"
                                        required
                                        value={baby.relationship_to_head}
                                        onChange={(v) =>
                                            setBaby(
                                                i,
                                                'relationship_to_head',
                                                v,
                                            )
                                        }
                                        options={options.relationships.filter(
                                            (r) =>
                                                r.value !== 'head' &&
                                                r.value !== 'spouse',
                                        )}
                                        error={
                                            errors[
                                                `newborns.${i}.relationship_to_head`
                                            ]
                                        }
                                    />
                                    <TextField
                                        id={`nb_${i}_weight`}
                                        label="Birth weight (kg)"
                                        type="number"
                                        inputMode="decimal"
                                        step="0.01"
                                        value={baby.birth_weight_kg}
                                        onChange={(v) =>
                                            setBaby(i, 'birth_weight_kg', v)
                                        }
                                        error={
                                            errors[
                                                `newborns.${i}.birth_weight_kg`
                                            ]
                                        }
                                        description="Below 2.5 kg is low birth weight."
                                    />
                                </div>
                            </div>
                        ))}
                        {form.data.newborns.length <
                            Number(form.data.live_births || 0) && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    form.setData('newborns', [
                                        ...form.data.newborns,
                                        blankBaby(),
                                    ])
                                }
                            >
                                <Plus aria-hidden /> Add baby
                            </Button>
                        )}
                    </fieldset>

                    <TextareaField
                        id="delivery_remarks"
                        label="Remarks"
                        rows={3}
                        value={form.data.remarks}
                        onChange={(v) => form.setData('remarks', v)}
                        error={errors.remarks}
                        description="Complications, newborn screening done, etc. Stored encrypted."
                    />
                </CardContent>
                <CardFooter className="justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Save delivery
                    </Button>
                </CardFooter>
            </form>
        </Card>
    );
}

/**
 * Prenatal or postnatal check-up from the tracker: vital signs plus the
 * maternal findings. The server links it to the scheduled contact and
 * completes the matching appointment.
 */
export function MaternalVisitSheet({
    pregnancy,
    contact,
    options,
    alerts,
    open,
    onOpenChange,
}: DialogProps & {
    pregnancy: Pregnancy;
    contact: MaternalContact | null;
    options: Options;
    alerts: MedicalAlert[];
}) {
    const kind =
        contact?.kind ??
        (pregnancy.status.value === 'delivered' ? 'postnatal' : 'prenatal');
    const visitType = options.visitTypes.find((t) =>
        t.label.toLowerCase().startsWith(kind),
    );
    const form = useForm({
        visit_type_id: visitType ? String(visitType.value) : '',
        visit_date: toDateOnly(new Date()) as string | null,
        chief_complaint: '',
        notes: '',
        vitals: {
            bp_systolic: '',
            bp_diastolic: '',
            weight_kg: '',
            temperature_c: '',
        },
        maternal: {
            pregnancy_id: pregnancy.id,
            kind,
            fundal_height_cm: '',
            fetal_heart_rate: '',
            breastfeeding: '',
            family_planning_counseled: false,
            remarks: '',
        },
    });
    const errors = form.errors as Record<string, string | undefined>;
    const setVital = (key: keyof typeof form.data.vitals, v: string) =>
        form.setData('vitals', { ...form.data.vitals, [key]: v });
    const setMaternal = (
        key: keyof typeof form.data.maternal,
        v: string | boolean,
    ) => form.setData('maternal', { ...form.data.maternal, [key]: v });

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((d) => ({
                            ...d,
                            vitals: Object.fromEntries(
                                Object.entries(d.vitals).map(([k, v]) => [
                                    k,
                                    v === '' ? null : v,
                                ]),
                            ),
                            maternal: Object.fromEntries(
                                Object.entries(d.maternal).map(([k, v]) => [
                                    k,
                                    v === '' ? null : v,
                                ]),
                            ),
                        }));
                        form.submit(
                            HealthVisitController.store(
                                pregnancy.resident?.id ?? 0,
                            ),
                            {
                                preserveScroll: true,
                                onSuccess: () => onOpenChange(false),
                            },
                        );
                    }}
                    className="flex min-h-full flex-col"
                >
                    <SheetHeader>
                        <SheetTitle>
                            Record{' '}
                            {kind === 'prenatal' ? 'prenatal' : 'postnatal'}{' '}
                            check-up
                        </SheetTitle>
                        <SheetDescription>
                            {pregnancy.resident?.full_name}
                            {contact && ` · ${contact.label}`}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="grid flex-1 gap-5 px-4 pb-4">
                        <MedicalAlertBanner alerts={alerts} compact />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <SelectField
                                id="mv_visit_type"
                                label="Visit type"
                                required
                                value={form.data.visit_type_id}
                                onChange={(v) =>
                                    form.setData('visit_type_id', v)
                                }
                                options={options.visitTypes}
                                error={errors.visit_type_id}
                            />
                            <DateField
                                id="mv_visit_date"
                                label="Visit date"
                                required
                                value={form.data.visit_date}
                                onChange={(v) => form.setData('visit_date', v)}
                                error={errors.visit_date}
                                fromYear={2020}
                            />
                        </div>
                        <fieldset className="grid gap-4 sm:grid-cols-2">
                            <legend className="mb-2 font-bold">
                                Vital signs
                            </legend>
                            <TextField
                                id="mv_bp_sys"
                                label="BP systolic (mmHg)"
                                type="number"
                                inputMode="numeric"
                                value={form.data.vitals.bp_systolic}
                                onChange={(v) => setVital('bp_systolic', v)}
                                error={errors['vitals.bp_systolic']}
                            />
                            <TextField
                                id="mv_bp_dia"
                                label="BP diastolic (mmHg)"
                                type="number"
                                inputMode="numeric"
                                value={form.data.vitals.bp_diastolic}
                                onChange={(v) => setVital('bp_diastolic', v)}
                                error={errors['vitals.bp_diastolic']}
                            />
                            <TextField
                                id="mv_weight"
                                label="Weight (kg)"
                                type="number"
                                inputMode="decimal"
                                step="0.1"
                                value={form.data.vitals.weight_kg}
                                onChange={(v) => setVital('weight_kg', v)}
                                error={errors['vitals.weight_kg']}
                            />
                            <TextField
                                id="mv_temp"
                                label="Temperature (°C)"
                                type="number"
                                inputMode="decimal"
                                step="0.1"
                                value={form.data.vitals.temperature_c}
                                onChange={(v) => setVital('temperature_c', v)}
                                error={errors['vitals.temperature_c']}
                            />
                        </fieldset>
                        {kind === 'prenatal' ? (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextField
                                    id="mv_fundal"
                                    label="Fundal height (cm)"
                                    type="number"
                                    inputMode="decimal"
                                    step="0.5"
                                    value={form.data.maternal.fundal_height_cm}
                                    onChange={(v) =>
                                        setMaternal('fundal_height_cm', v)
                                    }
                                    error={errors['maternal.fundal_height_cm']}
                                />
                                <TextField
                                    id="mv_fhr"
                                    label="Fetal heart rate (bpm)"
                                    type="number"
                                    inputMode="numeric"
                                    value={form.data.maternal.fetal_heart_rate}
                                    onChange={(v) =>
                                        setMaternal('fetal_heart_rate', v)
                                    }
                                    error={errors['maternal.fetal_heart_rate']}
                                />
                            </div>
                        ) : (
                            <div className="grid gap-4">
                                <RadioField
                                    id="mv_breastfeeding"
                                    label="Breastfeeding"
                                    value={form.data.maternal.breastfeeding}
                                    onChange={(v) =>
                                        setMaternal('breastfeeding', v)
                                    }
                                    options={options.breastfeeding}
                                    error={errors['maternal.breastfeeding']}
                                />
                                <CheckboxField
                                    id="mv_fp"
                                    label="Family planning counselling given"
                                    checked={
                                        form.data.maternal
                                            .family_planning_counseled
                                    }
                                    onChange={(v) =>
                                        setMaternal(
                                            'family_planning_counseled',
                                            v,
                                        )
                                    }
                                />
                            </div>
                        )}
                        <TextareaField
                            id="mv_remarks"
                            label="Findings"
                            rows={3}
                            value={form.data.maternal.remarks}
                            onChange={(v) => setMaternal('remarks', v)}
                            error={errors['maternal.remarks']}
                            description="Stored encrypted."
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
                            Save check-up
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
