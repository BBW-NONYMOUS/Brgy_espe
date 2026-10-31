import { Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import DuplicateCheckController from '@/actions/App/Http/Controllers/DuplicateCheckController';
import ResidentController from '@/actions/App/Http/Controllers/ResidentController';
import { Combobox } from '@/components/app/combobox';
import {
    CheckboxField,
    DateField,
    RadioField,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/app/fields';
import { FormField } from '@/components/app/form-field';
import { HouseholdDialog } from '@/components/app/residents/household-dialog';
import { SensitiveValue } from '@/components/app/sensitive-value';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, toDateOnly } from '@/lib/format';
import {
    index as residentsIndex,
    show as showResident,
} from '@/routes/residents';
import type {
    DuplicateMatch,
    HouseholdOption,
    Option,
    Resident,
} from '@/types';

export type ResidentFormOptions = {
    puroks: Option[];
    sexes: Option<string>[];
    civilStatuses: Option<string>[];
    relationships: Option<string>[];
    suffixes: string[];
};

type Props = {
    resident?: Resident;
    options: ResidentFormOptions;
    selectedHousehold: HouseholdOption | null;
    householdOptions?: HouseholdOption[];
    defaultConsentPurpose?: string;
};

/**
 * Register (no `resident`) or edit a resident. Long form split into
 * three Card sections; errors appear under each field.
 */
export function ResidentForm({
    resident,
    options,
    selectedHousehold,
    householdOptions = [],
    defaultConsentPurpose = '',
}: Props) {
    const editing = resident !== undefined;
    const page = usePage();
    const { can } = page.props;
    const [household, setHousehold] = useState<HouseholdOption | null>(
        selectedHousehold,
    );
    const [householdDialog, setHouseholdDialog] = useState(false);
    const [searching, setSearching] = useState(false);
    const [matches, setMatches] = useState<DuplicateMatch[]>([]);
    // Users without health.view_sensitive never receive the full number, so they cannot edit it.
    const canEditPhilHealth = !editing || resident.philhealth_no !== undefined;

    const form = useForm({
        household_id: selectedHousehold?.value ?? null,
        relationship_to_head: (resident?.relationship_to_head.value ??
            '') as string,
        last_name: resident?.last_name ?? '',
        first_name: resident?.first_name ?? '',
        middle_name: resident?.middle_name ?? '',
        suffix: resident?.suffix ?? '',
        sex: (resident?.sex.value ?? '') as string,
        birthdate: resident?.birthdate ?? (null as string | null),
        birthplace: resident?.birthplace ?? '',
        civil_status: (resident?.civil_status.value ?? 'single') as string,
        contact_no: resident?.contact_no ?? '',
        philhealth_no: resident?.philhealth_no ?? '',
        is_pwd: resident?.is_pwd ?? false,
        consent_given: false,
        consent_date: toDateOnly(new Date()) as string | null,
        consent_purpose: defaultConsentPurpose,
        confirm_duplicate: false,
    });

    const errors = form.errors as Record<string, string | undefined>;

    // Load the first household options for the combobox.
    useEffect(() => {
        router.reload({ only: ['householdOptions'] });
    }, []);

    // Live duplicate check (300 ms after the last keystroke).
    const { last_name, first_name, birthdate } = form.data;

    useEffect(() => {
        if (
            last_name.trim().length < 2 ||
            first_name.trim().length < 2 ||
            !birthdate
        ) {
            setMatches([]);

            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            const url = DuplicateCheckController.url({
                query: {
                    last_name,
                    first_name,
                    birthdate,
                    ignore_id: resident?.id ?? null,
                },
            });

            fetch(url, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((r) => (r.ok ? r.json() : { matches: [] }))
                .then((data: { matches: DuplicateMatch[] }) =>
                    setMatches(data.matches),
                )
                .catch(() => undefined);
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [last_name, first_name, birthdate, resident?.id]);

    // Live matches while typing; the server's check on save is the fallback.
    const duplicates =
        matches.length > 0 ? matches : (page.flash.duplicates ?? []);

    const searchHouseholds = (term: string) => {
        setSearching(true);
        router.reload({
            only: ['householdOptions'],
            data: { household_search: term },
            onFinish: () => setSearching(false),
        });
    };

    const submit = () => {
        form.transform((data) => {
            const { consent_given, consent_date, consent_purpose, ...rest } =
                data;

            return editing
                ? rest
                : { ...rest, consent_given, consent_date, consent_purpose };
        });

        form.submit(
            editing
                ? ResidentController.update(resident.id)
                : ResidentController.store(),
            { preserveScroll: true },
        );
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
            className="space-y-6"
            noValidate
        >
            {duplicates.length > 0 && (
                <Alert
                    className="border-warning/50 bg-warning-soft text-warning-soft-foreground"
                    role="alert"
                >
                    <AlertTriangle aria-hidden />
                    <AlertTitle>
                        Possible duplicate — this person may already be
                        registered
                    </AlertTitle>
                    <AlertDescription className="space-y-3 text-warning-soft-foreground">
                        <ul className="space-y-1">
                            {duplicates.map((match) => (
                                <li key={match.id}>
                                    <Link
                                        href={showResident(match.id)}
                                        className="font-bold underline"
                                        target="_blank"
                                    >
                                        {match.full_name}
                                    </Link>{' '}
                                    · {match.resident_code} · born{' '}
                                    {formatDate(match.birthdate)} ·{' '}
                                    {match.household_no}, {match.purok}
                                    {match.archived && ' (archived)'}
                                </li>
                            ))}
                        </ul>
                        <CheckboxField
                            id="confirm_duplicate"
                            label="I checked — this is a different person. Save anyway."
                            checked={form.data.confirm_duplicate}
                            onChange={(v) =>
                                form.setData('confirm_duplicate', v)
                            }
                            error={errors.duplicate}
                        />
                    </AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>1. Household</CardTitle>
                    <CardDescription>
                        Every resident belongs to a household in a purok.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 md:grid-cols-2">
                    <FormField
                        id="household_id"
                        label="Household"
                        required
                        error={errors.household_id}
                        description="Search by household no., head's last name, or address."
                    >
                        <Combobox
                            id="household_id"
                            value={
                                household
                                    ? {
                                          ...household,
                                          description: `${household.purok} · ${household.address}`,
                                      }
                                    : null
                            }
                            onChange={(option) => {
                                setHousehold(option);
                                form.setData(
                                    'household_id',
                                    option ? Number(option.value) : null,
                                );
                            }}
                            options={householdOptions.map((h) => ({
                                ...h,
                                description: `${h.purok} · ${h.address}`,
                            }))}
                            onSearch={searchHouseholds}
                            loading={searching}
                            placeholder="Choose a household"
                            invalid={!!errors.household_id}
                            footer={
                                can.manageHouseholds && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="w-full justify-start"
                                        onClick={() => setHouseholdDialog(true)}
                                    >
                                        <Plus aria-hidden /> Create new
                                        household
                                    </Button>
                                )
                            }
                        />
                    </FormField>
                    <SelectField
                        id="relationship_to_head"
                        label="Relationship to household head"
                        required
                        value={form.data.relationship_to_head}
                        onChange={(v) =>
                            form.setData('relationship_to_head', v)
                        }
                        options={options.relationships}
                        error={errors.relationship_to_head}
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>2. Personal information</CardTitle>
                    <CardDescription>
                        Fields marked * are required.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <TextField
                        id="last_name"
                        label="Last name"
                        required
                        autoComplete="off"
                        value={form.data.last_name}
                        onChange={(v) => form.setData('last_name', v)}
                        error={errors.last_name}
                        className="xl:col-span-1"
                    />
                    <TextField
                        id="first_name"
                        label="First name"
                        required
                        autoComplete="off"
                        value={form.data.first_name}
                        onChange={(v) => form.setData('first_name', v)}
                        error={errors.first_name}
                    />
                    <TextField
                        id="middle_name"
                        label="Middle name"
                        autoComplete="off"
                        value={form.data.middle_name}
                        onChange={(v) => form.setData('middle_name', v)}
                        error={errors.middle_name}
                    />
                    <SelectField
                        id="suffix"
                        label="Suffix"
                        value={form.data.suffix || 'none'}
                        onChange={(v) =>
                            form.setData('suffix', v === 'none' ? '' : v)
                        }
                        options={[
                            { value: 'none', label: 'None' },
                            ...options.suffixes.map((s) => ({
                                value: s,
                                label: s,
                            })),
                        ]}
                        error={errors.suffix}
                    />
                    <RadioField
                        id="sex"
                        label="Sex"
                        required
                        value={form.data.sex}
                        onChange={(v) => form.setData('sex', v)}
                        options={options.sexes}
                        error={errors.sex}
                    />
                    <DateField
                        id="birthdate"
                        label="Birthdate"
                        required
                        value={form.data.birthdate}
                        onChange={(v) => form.setData('birthdate', v)}
                        error={errors.birthdate}
                    />
                    <SelectField
                        id="civil_status"
                        label="Civil status"
                        required
                        value={form.data.civil_status}
                        onChange={(v) => form.setData('civil_status', v)}
                        options={options.civilStatuses}
                        error={errors.civil_status}
                    />
                    <TextField
                        id="birthplace"
                        label="Birthplace"
                        value={form.data.birthplace}
                        onChange={(v) => form.setData('birthplace', v)}
                        error={errors.birthplace}
                        placeholder="e.g. Isulan, Sultan Kudarat"
                    />
                    <TextField
                        id="contact_no"
                        label="Mobile number"
                        type="tel"
                        inputMode="tel"
                        value={form.data.contact_no}
                        onChange={(v) => form.setData('contact_no', v)}
                        error={errors.contact_no}
                        placeholder="09XX XXX XXXX"
                    />
                    {canEditPhilHealth ? (
                        <TextField
                            id="philhealth_no"
                            label="PhilHealth number"
                            inputMode="numeric"
                            autoComplete="off"
                            value={form.data.philhealth_no}
                            onChange={(v) => form.setData('philhealth_no', v)}
                            error={errors.philhealth_no}
                            placeholder="XX-XXXXXXXXX-X"
                            description="Stored encrypted."
                        />
                    ) : (
                        <FormField
                            id="philhealth_no"
                            label="PhilHealth number"
                            description="Only health staff can change this."
                        >
                            <div className="flex h-11 items-center">
                                <SensitiveValue
                                    masked={
                                        resident?.philhealth_no_masked ?? null
                                    }
                                    label="PhilHealth number"
                                />
                            </div>
                        </FormField>
                    )}
                    <div className="flex flex-col justify-center gap-3 md:col-span-2">
                        <CheckboxField
                            id="is_pwd"
                            label="Person with disability (PWD)"
                            checked={form.data.is_pwd}
                            onChange={(v) => form.setData('is_pwd', v)}
                        />
                    </div>
                </CardContent>
            </Card>

            {!editing && (
                <Card>
                    <CardHeader>
                        <CardTitle>
                            3. Consent (Data Privacy Act, RA 10173)
                        </CardTitle>
                        <CardDescription>
                            Explain how the information will be used before
                            saving. For minors, the parent or guardian gives
                            consent.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 md:grid-cols-2">
                        <CheckboxField
                            id="consent_given"
                            className="md:col-span-2"
                            label="The resident (or parent/guardian) agreed to have their personal and health information recorded."
                            checked={form.data.consent_given}
                            onChange={(v) => form.setData('consent_given', v)}
                            error={errors.consent_given}
                        />
                        <DateField
                            id="consent_date"
                            label="Date of consent"
                            required
                            value={form.data.consent_date}
                            onChange={(v) => form.setData('consent_date', v)}
                            error={errors.consent_date}
                        />
                        <TextareaField
                            id="consent_purpose"
                            label="Purpose"
                            required
                            rows={2}
                            value={form.data.consent_purpose}
                            onChange={(v) => form.setData('consent_purpose', v)}
                            error={errors.consent_purpose}
                        />
                    </CardContent>
                </Card>
            )}

            <div className="sticky bottom-0 z-10 -mx-4 flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6">
                <Button type="button" variant="outline" asChild>
                    <Link
                        href={
                            editing
                                ? showResident(resident.id)
                                : residentsIndex()
                        }
                    >
                        Cancel
                    </Link>
                </Button>
                <Button type="submit" disabled={form.processing} size="lg">
                    {form.processing && <Spinner />}
                    {editing ? 'Save changes' : 'Register resident'}
                </Button>
            </div>

            <HouseholdDialog
                open={householdDialog}
                onOpenChange={setHouseholdDialog}
                puroks={options.puroks}
                onCreated={(created) => {
                    setHousehold(created);
                    form.setData('household_id', Number(created.value));
                }}
            />
        </form>
    );
}
