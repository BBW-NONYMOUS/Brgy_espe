import { Head } from '@inertiajs/react';
import { Construction } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';

type Props = {
    title: string;
    description: string;
};

/**
 * Stand-in for pages whose server side is finished (Phase 4) but whose
 * screen is built in Phase 6. Deliberately renders none of the props.
 */
export function ModulePlaceholder({ title, description }: Props) {
    return (
        <>
            <Head title={title} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader title={title} description={description} />
                <EmptyState
                    icon={Construction}
                    title="This screen is being built"
                    description="The data and permissions behind this page are ready; the screen arrives in the next phase."
                />
            </div>
        </>
    );
}
