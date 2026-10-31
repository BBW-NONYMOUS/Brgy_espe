import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Activity, Stethoscope } from 'lucide-react';
import { useMemo } from 'react';
import { DataTable } from '@/components/app/data-table';
import { DateField } from '@/components/app/fields';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { SearchFilters } from '@/components/app/search-filters';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatDate } from '@/lib/format';
import { show as showResident } from '@/routes/residents';
import { index } from '@/routes/visits';
import type { Option, Paginated } from '@/types';
import type { VisitListRow } from '@/types/pages/records';

type Filters = {
    search: string;
    visit_type_id: number | null;
    purok_id: number | null;
    date_from: string | null;
    date_to: string | null;
    mine: boolean;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
};

type Props = {
    visits: Paginated<VisitListRow>;
    filters: Filters;
    options: { visitTypes: Option[]; puroks: Option[] };
};

const DEFAULTS: Partial<Filters> = {
    search: '',
    visit_type_id: null,
    purok_id: null,
    date_from: null,
    date_to: null,
    mine: false,
};

/**
 * Health visits across all residents. Clinical text (complaint, diagnosis,
 * notes) is never listed; open the resident's health timeline for that.
 */
export default function VisitsIndex({ visits, filters, options }: Props) {
    const { query, update, setSort, setPage, reset, processing } =
        useTableQuery(filters, { only: ['visits', 'filters'] });

    const hasActiveFilters = Boolean(
        query.search ||
        query.visit_type_id ||
        query.purok_id ||
        query.date_from ||
        query.date_to ||
        query.mine,
    );

    const columns = useMemo<ColumnDef<VisitListRow, unknown>[]>(
        () => [
            {
                id: 'visit_date',
                header: 'Date',
                meta: {
                    sortKey: 'visit_date',
                    cellClassName: 'whitespace-nowrap',
                },
                cell: ({ row }) => formatDate(row.original.visit_date),
            },
            {
                id: 'resident',
                header: 'Resident',
                meta: { sortKey: 'resident' },
                cell: ({ row }) => (
                    <div className="space-y-0.5">
                        <Link
                            href={showResident(row.original.resident.id)}
                            className="font-bold underline-offset-4 hover:underline"
                        >
                            {row.original.resident.full_name}
                        </Link>
                        <div className="font-mono text-xs text-muted-foreground">
                            {row.original.resident.resident_code} ·{' '}
                            {row.original.resident.age} yrs
                        </div>
                    </div>
                ),
            },
            {
                id: 'type',
                header: 'Visit type',
                cell: ({ row }) => (
                    <div className="flex flex-wrap items-center gap-1">
                        <span>{row.original.visit_type}</span>
                        {row.original.maternal && (
                            <StatusBadge tone="primary">
                                {row.original.maternal.label}
                            </StatusBadge>
                        )}
                        {row.original.has_vitals && (
                            <StatusBadge
                                tone="neutral"
                                icon={<Activity aria-hidden />}
                            >
                                Vitals
                            </StatusBadge>
                        )}
                    </div>
                ),
            },
            {
                id: 'purok',
                header: 'Purok',
                meta: { hideOnMobile: true },
                cell: ({ row }) => row.original.purok,
            },
            {
                id: 'attended_by',
                header: 'Attended by',
                meta: { hideOnMobile: true },
                cell: ({ row }) => row.original.attended_by,
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Health visits" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Health visits"
                    description="Consultations and check-ups across all residents. Record a new visit from the resident's profile."
                />

                <SearchFilters
                    search={{
                        value: query.search,
                        onChange: (value) =>
                            update({ search: value }, { debounced: true }),
                        placeholder: 'Resident name or ID (KII-…)',
                        label: 'Search visits by resident',
                    }}
                    filters={[
                        {
                            key: 'visit_type',
                            label: 'Visit type',
                            allLabel: 'All visit types',
                            options: options.visitTypes,
                            value: query.visit_type_id,
                            onChange: (v) =>
                                update({ visit_type_id: v ? Number(v) : null }),
                        },
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
                    onReset={() => reset(DEFAULTS)}
                >
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:items-end">
                        <DateField
                            id="date_from"
                            label="From"
                            value={query.date_from}
                            onChange={(v) => update({ date_from: v })}
                            className="sm:w-44"
                        />
                        <DateField
                            id="date_to"
                            label="To"
                            value={query.date_to}
                            onChange={(v) => update({ date_to: v })}
                            className="sm:w-44"
                        />
                    </div>
                    <div className="flex h-11 items-center gap-2 px-1">
                        <Switch
                            id="mine"
                            checked={query.mine}
                            onCheckedChange={(v) => update({ mine: v })}
                        />
                        <Label htmlFor="mine">Attended by me</Label>
                    </div>
                </SearchFilters>

                <DataTable
                    columns={columns}
                    data={visits}
                    sort={query}
                    onSort={setSort}
                    onPageChange={setPage}
                    perPage={query.per_page}
                    onPerPageChange={(n) => update({ per_page: n })}
                    itemLabel="visits"
                    caption="Health visits"
                    rowHref={(v) => showResident(v.resident.id).url}
                    processing={processing}
                    empty={
                        <EmptyState
                            icon={Stethoscope}
                            title={
                                hasActiveFilters
                                    ? 'No visits match your filters'
                                    : 'No health visits recorded yet'
                            }
                            description={
                                hasActiveFilters
                                    ? 'Try a wider date range or clear the filters.'
                                    : "Visits recorded from residents' profiles appear here."
                            }
                            action={
                                hasActiveFilters && (
                                    <Button
                                        variant="outline"
                                        onClick={() => reset(DEFAULTS)}
                                    >
                                        Clear filters
                                    </Button>
                                )
                            }
                        />
                    }
                />
            </div>
        </>
    );
}

VisitsIndex.layout = {
    breadcrumbs: [{ title: 'Health visits', href: index() }],
};
