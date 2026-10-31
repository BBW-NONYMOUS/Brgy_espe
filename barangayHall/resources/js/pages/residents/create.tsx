import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/app/page-header';
import { ResidentForm } from '@/components/app/residents/resident-form';
import type { ResidentFormOptions } from '@/components/app/residents/resident-form';
import { create, index } from '@/routes/residents';
import type { HouseholdOption } from '@/types';

type Props = {
    options: ResidentFormOptions;
    defaultConsentPurpose: string;
    selectedHousehold: HouseholdOption | null;
    householdOptions?: HouseholdOption[];
};

export default function ResidentsCreate({
    options,
    defaultConsentPurpose,
    selectedHousehold,
    householdOptions,
}: Props) {
    return (
        <>
            <Head title="Register resident" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Register resident"
                    description="Create a resident profile. A resident ID is assigned automatically."
                    back={{ href: index(), label: 'Residents' }}
                />
                <ResidentForm
                    options={options}
                    selectedHousehold={selectedHousehold}
                    householdOptions={householdOptions}
                    defaultConsentPurpose={defaultConsentPurpose}
                />
            </div>
        </>
    );
}

ResidentsCreate.layout = {
    breadcrumbs: [
        { title: 'Residents', href: index() },
        { title: 'Register', href: create() },
    ],
};
