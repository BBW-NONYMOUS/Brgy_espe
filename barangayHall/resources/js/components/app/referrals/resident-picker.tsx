import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Combobox } from '@/components/app/combobox';
import type { ComboboxOption } from '@/components/app/combobox';
import { describedBy, FormField } from '@/components/app/form-field';
import type { ResidentOption } from '@/types/modules';

export type PickedResident = ComboboxOption & ResidentOption;

/**
 * Resident search for forms on list pages. Typing partial-reloads the
 * page's optional `residentOptions` prop (`?resident=term`) — no JSON API.
 */
export function ResidentPicker({
    id = 'resident_id',
    label = 'Resident',
    value,
    onChange,
    options = [],
    error,
    description,
}: {
    id?: string;
    label?: string;
    value: PickedResident | null;
    onChange: (resident: PickedResident | null) => void;
    options?: ResidentOption[];
    error?: string;
    description?: string;
}) {
    const [searching, setSearching] = useState(false);

    const search = (term: string) => {
        if (term.trim().length < 2) {
            return;
        }

        setSearching(true);
        router.reload({
            only: ['residentOptions'],
            data: { resident: term },
            onFinish: () => setSearching(false),
        });
    };

    return (
        <FormField
            id={id}
            label={label}
            error={error}
            required
            description={
                description ??
                'Type at least 2 letters of the name or the resident ID.'
            }
        >
            <Combobox
                id={id}
                value={value}
                onChange={onChange}
                options={options.map((r) => ({
                    ...r,
                    description: `${r.code} · ${r.age} yrs · ${r.purok}`,
                }))}
                onSearch={search}
                loading={searching}
                placeholder="Search for a resident"
                searchPlaceholder="Name or resident ID…"
                emptyText="No resident found."
                invalid={!!error}
                describedBy={describedBy(id, { description: true, error })}
            />
        </FormField>
    );
}
