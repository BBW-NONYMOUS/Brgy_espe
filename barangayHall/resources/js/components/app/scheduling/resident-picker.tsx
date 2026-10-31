import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Combobox } from '@/components/app/combobox';
import { FormField, describedBy } from '@/components/app/form-field';
import type { ResidentOption } from '@/types';

type Props = {
    id: string;
    label?: string;
    value: ResidentOption | null;
    onChange: (resident: ResidentOption | null) => void;
    /** The page's optional `residentOptions` prop (filled by partial reload). */
    options: ResidentOption[] | undefined;
    error?: string;
    required?: boolean;
};

/**
 * Resident search for booking forms: each keystroke (debounced by the
 * Combobox) partial-reloads the page's `residentOptions` with ?resident=term.
 * No JSON endpoint is involved.
 */
export function ResidentPicker({
    id,
    label = 'Resident',
    value,
    onChange,
    options,
    error,
    required = true,
}: Props) {
    const [searching, setSearching] = useState(false);
    const description = 'Type a name or resident ID.';

    const search = (term: string) => {
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
            required={required}
            error={error}
            description={description}
        >
            <Combobox<ResidentOption>
                id={id}
                value={value}
                onChange={onChange}
                options={(options ?? []).map((r) => ({
                    ...r,
                    description: `${r.code} · ${r.age} yrs · ${r.purok}`,
                }))}
                onSearch={search}
                loading={searching}
                placeholder="Search residents"
                emptyText="No resident found. Check the spelling or the resident ID."
                invalid={!!error}
                describedBy={describedBy(id, { description, error })}
            />
        </FormField>
    );
}
