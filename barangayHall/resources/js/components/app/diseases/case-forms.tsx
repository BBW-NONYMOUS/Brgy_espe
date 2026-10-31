import { router, useForm, usePage } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { useState } from 'react';
import DiseaseCaseController from '@/actions/App/Http/Controllers/Surveillance/DiseaseCaseController';
import { Combobox } from '@/components/app/combobox';
import {
    DateField,
    RadioField,
    SelectField,
    TextareaField,
} from '@/components/app/fields';
import { describedBy, FormField } from '@/components/app/form-field';
import { FormDialog } from '@/components/app/maternal/form-dialog';
import type { DialogProps } from '@/components/app/maternal/form-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
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
import type { DiseaseCase, Option, ResidentOption } from '@/types';

type ResidentChoice = ResidentOption & { description?: string };

/**
 * "Report a case". BHWs (no diseases.classify) always report Suspected —
 * the server enforces that too; a Midwife/Admin may record it directly as
 * probable or confirmed. Saving queues the outbreak threshold check.
 */
export function ReportCaseSheet({
    open,
    onOpenChange,
    diseases,
    classifications,
    residentOptions,
}: DialogProps & {
    diseases: (Option & { category: string })[];
    classifications: Option<string>[];
    residentOptions: ResidentOption[];
}) {
    const { can } = usePage().props;
    const [resident, setResident] = useState<ResidentChoice | null>(null);
    const [searching, setSearching] = useState(false);
    const form = useForm({
        resident_id: null as number | null,
        disease_id: '',
        onset_date: toDateOnly(new Date()) as string | null,
        classification: 'suspected',
        notes: '',
    });
    const errors = form.errors as Record<string, string | undefined>;

    const search = (term: string) => {
        setSearching(true);
        router.reload({
            only: ['residentOptions'],
            data: { resident: term },
            onFinish: () => setSearching(false),
        });
    };

    const close = () => {
        form.reset();
        setResident(null);
        onOpenChange(false);
    };

    return (
        <Sheet
            open={open}
            onOpenChange={(o) => (o ? onOpenChange(o) : close())}
        >
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.submit(DiseaseCaseController.store(), {
                            preserveScroll: true,
                            onSuccess: close,
                        });
                    }}
                    className="flex min-h-full flex-col"
                >
                    <SheetHeader>
                        <SheetTitle>Report a disease case</SheetTitle>
                        <SheetDescription>
                            Category I diseases must also be reported to the RHU
                            immediately.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="grid flex-1 gap-5 px-4 pb-4">
                        <FormField
                            id="resident_id"
                            label="Resident"
                            required
                            error={errors.resident_id}
                            description="Search by last name, first name or resident ID."
                        >
                            <Combobox
                                id="resident_id"
                                value={resident}
                                onChange={(option) => {
                                    setResident(option);
                                    form.setData(
                                        'resident_id',
                                        option ? Number(option.value) : null,
                                    );
                                }}
                                options={residentOptions.map((r) => ({
                                    ...r,
                                    description: `${r.code} · ${r.age} yrs · ${r.purok}`,
                                }))}
                                onSearch={search}
                                loading={searching}
                                placeholder="Choose a resident"
                                emptyText="Type at least part of a name."
                                invalid={!!errors.resident_id}
                                describedBy={describedBy('resident_id', {
                                    description: true,
                                    error: errors.resident_id,
                                })}
                            />
                        </FormField>

                        <SelectField
                            id="disease_id"
                            label="Disease"
                            required
                            value={form.data.disease_id}
                            onChange={(v) => form.setData('disease_id', v)}
                            options={diseases.map((d) => ({
                                value: d.value,
                                label: d.category.startsWith('Category I ')
                                    ? `${d.label} — report immediately`
                                    : d.label,
                            }))}
                            error={errors.disease_id}
                        />

                        <DateField
                            id="onset_date"
                            label="Date symptoms started"
                            required
                            value={form.data.onset_date}
                            onChange={(v) => form.setData('onset_date', v)}
                            error={errors.onset_date}
                            fromYear={2000}
                        />

                        {can.classifyDiseases ? (
                            <RadioField
                                id="classification"
                                label="Classification"
                                required
                                value={form.data.classification}
                                onChange={(v) =>
                                    form.setData('classification', v)
                                }
                                options={classifications.filter(
                                    (c) => c.value !== 'ruled_out',
                                )}
                                error={errors.classification}
                            />
                        ) : (
                            <Alert>
                                <Info aria-hidden />
                                <AlertDescription>
                                    The case is saved as <b>Suspected</b>. The
                                    midwife confirms or rules it out after
                                    checking.
                                </AlertDescription>
                            </Alert>
                        )}

                        <TextareaField
                            id="case_notes"
                            label="Notes"
                            rows={3}
                            value={form.data.notes}
                            onChange={(v) => form.setData('notes', v)}
                            error={errors.notes}
                            description="Symptoms, test results, travel. Stored encrypted."
                        />
                    </div>

                    <SheetFooter className="sticky bottom-0 flex-row justify-end border-t bg-background">
                        <Button type="button" variant="outline" onClick={close}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Report case
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}

/** Midwife/Admin: confirm or rule out a case and record its outcome. */
export function ClassifyCaseDialog({
    open,
    onOpenChange,
    diseaseCase,
    classifications,
    outcomes,
}: DialogProps & {
    diseaseCase: DiseaseCase;
    classifications: Option<string>[];
    outcomes: Option<string>[];
}) {
    const form = useForm({
        classification: diseaseCase.classification.value as string,
        outcome: diseaseCase.outcome.value as string,
        outcome_date: diseaseCase.outcome_date,
        notes: diseaseCase.notes ?? '',
    });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`Update ${diseaseCase.disease?.name ?? 'case'}`}
            description={diseaseCase.resident?.full_name}
            submitLabel="Save"
            processing={form.processing}
            onSubmit={() =>
                form.submit(DiseaseCaseController.update(diseaseCase.id), {
                    preserveScroll: true,
                    onSuccess: () => onOpenChange(false),
                })
            }
        >
            <RadioField
                id="classify_classification"
                label="Classification"
                required
                value={form.data.classification}
                onChange={(v) => form.setData('classification', v)}
                options={classifications}
                error={form.errors.classification}
                description="Ruled-out cases no longer count toward outbreak alerts."
            />
            <RadioField
                id="classify_outcome"
                label="Outcome"
                required
                value={form.data.outcome}
                onChange={(v) =>
                    form.setData((d) => ({
                        ...d,
                        outcome: v,
                        outcome_date:
                            v === 'ongoing'
                                ? null
                                : (d.outcome_date ?? toDateOnly(new Date())),
                    }))
                }
                options={outcomes}
                error={form.errors.outcome}
            />
            {form.data.outcome !== 'ongoing' && (
                <DateField
                    id="classify_outcome_date"
                    label={
                        form.data.outcome === 'died'
                            ? 'Date of death'
                            : 'Date recovered'
                    }
                    required
                    value={form.data.outcome_date}
                    onChange={(v) => form.setData('outcome_date', v)}
                    error={form.errors.outcome_date}
                    fromYear={2000}
                />
            )}
            {!diseaseCase.restricted && (
                <TextareaField
                    id="classify_notes"
                    label="Notes"
                    rows={3}
                    value={form.data.notes}
                    onChange={(v) => form.setData('notes', v)}
                    error={form.errors.notes}
                />
            )}
        </FormDialog>
    );
}
