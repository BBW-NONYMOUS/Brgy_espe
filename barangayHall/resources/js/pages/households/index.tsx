import { Head, Link, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Home, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { HouseholdDialog } from '@/components/app/residents/household-dialog';
import { SearchFilters } from '@/components/app/search-filters';
import { Button } from '@/components/ui/button';
import { useTableQuery } from '@/hooks/use-table-query';
import { index, show } from '@/routes/households';
import type { Household, Option, Paginated } from '@/types';

type Filters = { search: string; purok_id: number | null; per_page: number };

type Props = {
    households: Paginated<Household>;
    filters: Filters;
    options: { puroks: Option[] };
};

export default function HouseholdsIndex({
    households,
    filters,
    options,
}: Props) {
    const { can } = usePage().props;
    const { query, update, setPage, reset, processing } = useTableQuery(
        filters,
        { only: ['households', 'filters'] },
    );
    const [creating, setCreating] = useState(false);
    const hasActiveFilters = Boolean(query.search || query.purok_id);

    const columns = useMemo<ColumnDef<Household, unknown>[]>(
        () => [
            {
                id: 'household_no',
                header: 'Household no.',
                cell: ({ row }) => (
                    <Link
                        href={show(row.original.id)}
                        className="font-mono font-bold underline-offset-4 hover:underline"
                    >
                        {row.original.household_no}
                    </Link>
                ),
            },
            {
                id: 'head',
                header: 'Household head',
                cell: ({ row }) =>
                    row.original.head?.full_name ?? (
                        <span className="text-muted-foreground">
                            No head assigned
                        </span>
                    ),
            },
            {
                id: 'purok',
                header: 'Purok',
                cell: ({ row }) => row.original.purok?.name,
            },
            {
                id: 'address',
                header: 'Address',
                meta: {
                    hideOnMobile: true,
                    cellClassName: 'whitespace-normal min-w-56',
                },
                cell: ({ row }) => row.original.address,
            },
            {
                id: 'members',
                header: 'Members',
                meta: {
                    cellClassName: 'text-right tabular-nums',
                    headerClassName: 'text-right',
                },
                cell: ({ row }) => row.original.members_count,
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Households" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Households"
                    description="Households and their members, by purok."
                    actions={
                        can.manageHouseholds && (
                            <Button onClick={() => setCreating(true)}>
                                <Plus aria-hidden /> New household
                            </Button>
                        )
                    }
                />

                <SearchFilters
                    search={{
                        value: query.search,
                        onChange: (value) =>
                            update({ search: value }, { debounced: true }),
                        placeholder:
                            "Household no., head's last name or address",
                        label: 'Search households',
                    }}
                    filters={[
                        {
                            key: 'purok',
                            label: 'Purok',
                            allLabel: 'All puroks',
                            options: options.puroks,
                            value: query.purok_id,
                            onChange: (v) =>
                                update({ purok_id: v ? Number(v) : null }),
                        },
                    ]}
                    hasActiveFilters={hasActiveFilters}
                    onReset={() => reset({ search: '', purok_id: null })}
                />

                <DataTable
                    columns={columns}
                    data={households}
                    onPageChange={setPage}
                    perPage={query.per_page}
                    onPerPageChange={(n) => update({ per_page: n })}
                    itemLabel="households"
                    caption="Households"
                    rowHref={(h) => show(h.id).url}
                    processing={processing}
                    empty={
                        <EmptyState
                            icon={Home}
                            title={
                                hasActiveFilters
                                    ? 'No households match your search'
                                    : 'No households yet'
                            }
                            description={
                                hasActiveFilters
                                    ? 'Try another household number or clear the filters.'
                                    : 'Create a household, then register its members.'
                            }
                            action={
                                hasActiveFilters ? (
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            reset({
                                                search: '',
                                                purok_id: null,
                                            })
                                        }
                                    >
                                        Clear filters
                                    </Button>
                                ) : (
                                    can.manageHouseholds && (
                                        <Button
                                            onClick={() => setCreating(true)}
                                        >
                                            <Plus aria-hidden /> New household
                                        </Button>
                                    )
                                )
                            }
                        />
                    }
                />
            </div>

            {creating && (
                <HouseholdDialog
                    open
                    onOpenChange={setCreating}
                    puroks={options.puroks}
                />
            )}
        </>
    );
}

HouseholdsIndex.layout = {
    breadcrumbs: [{ title: 'Households', href: index() }],
};
