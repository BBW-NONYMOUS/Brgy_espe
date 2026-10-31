import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import HouseholdController from '@/actions/App/Http/Controllers/HouseholdController';
import { CheckboxField, SelectField, TextField } from '@/components/app/fields';
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
import type { Household, HouseholdOption, Option } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    puroks: Option[];
    /** Edit an existing household; omit to create one. */
    household?: Household;
    /** Members who can be chosen as head (edit only). */
    members?: { id: number; full_name: string }[];
    /** Family health profile lookups; the selects are shown when given. */
    waterSources?: Option[];
    toiletTypes?: Option[];
    /** Create from another form (resident registration): stay on that page. */
    onCreated?: (household: HouseholdOption) => void;
    trigger?: ReactNode;
};

/**
 * Create or edit a household. When `onCreated` is given, the server
 * returns to the current page and flashes the new household so the
 * registration form can select it.
 */
export function HouseholdDialog({
    open,
    onOpenChange,
    puroks,
    household,
    members = [],
    waterSources,
    toiletTypes,
    onCreated,
}: Props) {
    const editing = household !== undefined;
    // The resource sends lookup names; match them back to the option ids.
    const form = useForm({
        household_no: household?.household_no ?? '',
        purok_id: household?.purok?.id ? String(household.purok.id) : '',
        address: household?.address ?? '',
        is_4ps: household?.is_4ps ?? false,
        water_source_id: household?.water_source_id
            ? String(household.water_source_id)
            : '',
        toilet_type_id: household?.toilet_type_id
            ? String(household.toilet_type_id)
            : '',
        head_resident_id: household?.head?.id ? String(household.head.id) : '',
        stay: onCreated ? 1 : 0,
    });

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: (page: {
                flash: { createdHousehold?: HouseholdOption | null };
            }) => {
                if (onCreated && page.flash.createdHousehold) {
                    onCreated(page.flash.createdHousehold);
                }

                form.reset();
                onOpenChange(false);
            },
        };

        form.transform((data) => ({
            ...data,
            household_no: data.household_no || null,
            head_resident_id: data.head_resident_id || null,
            water_source_id: data.water_source_id || null,
            toilet_type_id: data.toilet_type_id || null,
        }));

        if (editing) {
            form.submit(HouseholdController.update(household.id), options);
        } else {
            form.submit(HouseholdController.store(), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                    className="space-y-5"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? `Edit household ${household.household_no}`
                                : 'New household'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Update the purok, address, household data or household head.'
                                : 'Leave the household number blank to assign the next number automatically.'}
                        </DialogDescription>
                    </DialogHeader>

                    <TextField
                        id="household_no"
                        label="Household no."
                        value={form.data.household_no}
                        onChange={(v) =>
                            form.setData('household_no', v.toUpperCase())
                        }
                        error={form.errors.household_no}
                        placeholder="Automatic, e.g. HH-0015"
                    />
                    <SelectField
                        id="purok_id"
                        label="Purok"
                        required
                        value={form.data.purok_id}
                        onChange={(v) => form.setData('purok_id', v)}
                        options={puroks}
                        error={form.errors.purok_id}
                    />
                    <TextField
                        id="address"
                        label="Address / landmark"
                        required
                        value={form.data.address}
                        onChange={(v) => form.setData('address', v)}
                        error={form.errors.address}
                        placeholder="e.g. Along National Highway, beside the sari-sari store"
                    />
                    {waterSources && (
                        <SelectField
                            id="water_source_id"
                            label="Drinking water source"
                            value={form.data.water_source_id}
                            onChange={(v) => form.setData('water_source_id', v)}
                            options={waterSources}
                            error={form.errors.water_source_id}
                        />
                    )}
                    {toiletTypes && (
                        <SelectField
                            id="toilet_type_id"
                            label="Toilet / sanitation"
                            value={form.data.toilet_type_id}
                            onChange={(v) => form.setData('toilet_type_id', v)}
                            options={toiletTypes}
                            error={form.errors.toilet_type_id}
                        />
                    )}
                    <CheckboxField
                        id="is_4ps"
                        label="4Ps (Pantawid Pamilyang Pilipino Program) household"
                        checked={form.data.is_4ps}
                        onChange={(v) => form.setData('is_4ps', v)}
                    />
                    {editing && members.length > 0 && (
                        <SelectField
                            id="head_resident_id"
                            label="Household head"
                            value={form.data.head_resident_id}
                            onChange={(v) =>
                                form.setData('head_resident_id', v)
                            }
                            options={members.map((m) => ({
                                value: m.id,
                                label: m.full_name,
                            }))}
                            error={form.errors.head_resident_id}
                        />
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {editing ? 'Save changes' : 'Create household'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
