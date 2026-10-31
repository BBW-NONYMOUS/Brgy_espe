import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/app/page-header';
import { ResidentForm } from '@/components/app/residents/resident-form';
import type { ResidentFormOptions } from '@/components/app/residents/resident-form';
import { index, show } from '@/routes/residents';
import type { HouseholdOption, Resident } from '@/types';

type Props = {
    resident: Resident;
    options: ResidentFormOptions;
    selectedHousehold: HouseholdOption | null;
    householdOptions?: HouseholdOption[];
};

export default function ResidentsEdit({
    resident,
    options,
    selectedHousehold,
    householdOptions,
}: Props) {
    return (
        <>
            <Head title={`Edit ${resident.full_name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`Edit ${resident.full_name}`}
                    description={`${resident.resident_code} · Changes are saved to the record's history.`}
                    back={{ href: show(resident.id), label: 'Back to profile' }}
                />
                <ResidentForm
                    resident={resident}
                    options={options}
                    selectedHousehold={selectedHousehold}
                    householdOptions={householdOptions}
                />
            </div>
        </>
    );
}

ResidentsEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Residents', href: index() },
        { title: props.resident.full_name, href: show(props.resident.id) },
        { title: 'Edit', href: show(props.resident.id) },
    ],
});
