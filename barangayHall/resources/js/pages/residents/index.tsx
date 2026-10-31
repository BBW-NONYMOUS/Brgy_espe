import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Archive,
    ArchiveRestore,
    Eye,
    MoreHorizontal,
    Pencil,
    Plus,
    Trash2,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import ResidentController from '@/actions/App/Http/Controllers/ResidentController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { ResidentBadges } from '@/components/app/resident-badges';
import { ArchiveResidentDialog } from '@/components/app/residents/archive-resident-dialog';
import { SearchFilters } from '@/components/app/search-filters';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatAge, formatDate } from '@/lib/format';
import { create, edit, index, show } from '@/routes/residents';
import type { Option, Paginated, Resident } from '@/types';

type Filters = {
    search: string;
    purok_id: number | null;
    sex: string | null;
    age_group: string | null;
    status: string | null;
    archived: boolean;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
};

type Props = {
    residents: Paginated<Resident>;
    filters: Filters;
    options: {
        puroks: Option[];
        sexes: Option<string>[];
        statuses: Option<string>[];
        ageGroups: Option<string>[];
    };
};

const DEFAULTS: Partial<Filters> = {
    search: '',
    purok_id: null,
    sex: null,
    age_group: null,
    status: null,
};

export default function ResidentsIndex({ residents, filters, options }: Props) {
    const { can } = usePage().props;
    const { query, update, setSort, setPage, reset, processing } =
        useTableQuery(filters, { only: ['residents', 'filters'] });
    const [archiving, setArchiving] = useState<Resident | null>(null);
    const [deleting, setDeleting] = useState<Resident | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const hasActiveFilters = Boolean(
        query.search ||
        query.purok_id ||
        query.sex ||
        query.age_group ||
        query.status,
    );

    const columns = useMemo<ColumnDef<Resident, unknown>[]>(
        () => [
            {
                id: 'name',
                header: 'Name',
                meta: { sortKey: 'last_name' },
                cell: ({ row }) => (
                    <div className="space-y-1">
                        <Link
                            href={show(row.original.id)}
                            className="font-bold underline-offset-4 hover:underline"
                        >
                            {row.original.full_name}
                        </Link>
                        <ResidentBadges resident={row.original} />
                        <div className="font-mono text-xs text-muted-foreground md:hidden">
                            {row.original.resident_code}
                        </div>
                    </div>
                ),
            },
            {
                id: 'code',
                header: 'Resident ID',
                meta: {
                    sortKey: 'resident_code',
                    hideOnMobile: true,
                    cellClassName: 'font-mono whitespace-nowrap',
                },
                cell: ({ row }) => row.original.resident_code,
            },
            {
                id: 'age',
                header: 'Age',
                meta: { sortKey: 'birthdate' },
                cell: ({ row }) => (
                    <div className="whitespace-nowrap">
                        <div>
                            {formatAge(
                                row.original.age,
                                row.original.birthdate,
                            )}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {formatDate(row.original.birthdate)}
                        </div>
                    </div>
                ),
            },
            {
                id: 'sex',
                header: 'Sex',
                meta: { hideOnMobile: true },
                cell: ({ row }) => row.original.sex.label,
            },
            {
                id: 'household',
                header: 'Household',
                meta: { hideOnMobile: true },
                cell: ({ row }) => (
                    <div className="whitespace-nowrap">
                        <div>{row.original.household?.household_no}</div>
                        <div className="text-xs text-muted-foreground">
                            {row.original.household?.purok?.name}
                        </div>
                    </div>
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                meta: { cellClassName: 'w-12 text-right' },
                cell: ({ row }) => {
                    const resident = row.original;
                    const archived = resident.archived_at !== null;

                    return (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Actions for ${resident.full_name}`}
                                >
                                    <MoreHorizontal aria-hidden />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <Link href={show(resident.id)}>
                                        <Eye aria-hidden /> View profile
                                    </Link>
                                </DropdownMenuItem>
                                {!archived && can.updateResidents && (
                                    <DropdownMenuItem asChild>
                                        <Link href={edit(resident.id)}>
                                            <Pencil aria-hidden /> Edit
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                                {!archived && can.archiveResidents && (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            onSelect={() =>
                                                setArchiving(resident)
                                            }
                                        >
                                            <Archive aria-hidden /> Archive
                                        </DropdownMenuItem>
                                    </>
                                )}
                                {archived && can.archiveResidents && (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            router.post(
                                                ResidentController.restore.url(
                                                    resident.id,
                                                ),
                                            )
                                        }
                                    >
                                        <ArchiveRestore aria-hidden /> Restore
                                    </DropdownMenuItem>
                                )}
                                {archived && can.forceDeleteResidents && (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onSelect={() => setDeleting(resident)}
                                    >
                                        <Trash2 aria-hidden /> Delete
                                        permanently
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    );
                },
            },
        ],
        [can],
    );

    return (
        <>
            <Head title="Residents" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title={query.archived ? 'Archived residents' : 'Residents'}
                    description={
                        query.archived
                            ? 'Residents who moved out, died, or were entered by mistake. Records are kept for the retention period.'
                            : 'Search by name, resident ID (KII-…), household number (HH-…) or birthdate (YYYY-MM-DD).'
                    }
                    actions={
                        can.createResidents && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus aria-hidden /> Register resident
                                </Link>
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
                            'e.g. Dela Cruz, Juan · KII-2026-00012 · HH-0005',
                        label: 'Search residents',
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
                        {
                            key: 'sex',
                            label: 'Sex',
                            allLabel: 'Any sex',
                            options: options.sexes,
                            value: query.sex,
                            onChange: (v) => update({ sex: v }),
                        },
                        {
                            key: 'age_group',
                            label: 'Age group',
                            allLabel: 'All ages',
                            options: options.ageGroups,
                            value: query.age_group,
                            onChange: (v) => update({ age_group: v }),
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            allLabel: 'Any status',
                            options: options.statuses,
                            value: query.status,
                            onChange: (v) => update({ status: v }),
                        },
                    ]}
                    hasActiveFilters={hasActiveFilters}
                    onReset={() => reset(DEFAULTS)}
                >
                    {can.archiveResidents && (
                        <div className="flex h-11 items-center gap-2 px-1">
                            <Switch
                                id="archived"
                                checked={query.archived}
                                onCheckedChange={(v) => update({ archived: v })}
                            />
                            <Label htmlFor="archived">Show archived</Label>
                        </div>
                    )}
                </SearchFilters>

                <DataTable
                    columns={columns}
                    data={residents}
                    sort={query}
                    onSort={setSort}
                    onPageChange={setPage}
                    perPage={query.per_page}
                    onPerPageChange={(n) => update({ per_page: n })}
                    itemLabel="residents"
                    caption="Residents"
                    rowHref={(r) => show(r.id).url}
                    processing={processing}
                    empty={
                        <EmptyState
                            icon={Users}
                            title={
                                hasActiveFilters
                                    ? 'No residents match your search'
                                    : query.archived
                                      ? 'No archived residents'
                                      : 'No residents registered yet'
                            }
                            description={
                                hasActiveFilters
                                    ? 'Check the spelling, try only the last name, or clear the filters.'
                                    : query.archived
                                      ? 'Archived records will appear here.'
                                      : 'Register the first resident to start the digital profile.'
                            }
                            action={
                                hasActiveFilters ? (
                                    <Button
                                        variant="outline"
                                        onClick={() => reset(DEFAULTS)}
                                    >
                                        Clear filters
                                    </Button>
                                ) : (
                                    can.createResidents &&
                                    !query.archived && (
                                        <Button asChild>
                                            <Link href={create()}>
                                                <Plus aria-hidden /> Register
                                                resident
                                            </Link>
                                        </Button>
                                    )
                                )
                            }
                        />
                    }
                />
            </div>

            {archiving && (
                <ArchiveResidentDialog
                    resident={archiving}
                    open
                    onOpenChange={(open) => !open && setArchiving(null)}
                />
            )}

            {deleting && (
                <ConfirmDialog
                    open
                    onOpenChange={(open) => !open && setDeleting(null)}
                    title={`Permanently delete ${deleting.full_name}?`}
                    description={
                        <>
                            <p>
                                This removes the resident and all of their
                                health records forever. It cannot be undone.
                            </p>
                            <p>
                                Allowed only after the retention period has
                                passed since archiving.
                            </p>
                        </>
                    }
                    confirmLabel="Delete permanently"
                    destructive
                    processing={deleteProcessing}
                    onConfirm={() =>
                        router.delete(
                            ResidentController.forceDestroy.url(deleting.id),
                            {
                                onStart: () => setDeleteProcessing(true),
                                onFinish: () => {
                                    setDeleteProcessing(false);
                                    setDeleting(null);
                                },
                            },
                        )
                    }
                />
            )}
        </>
    );
}

ResidentsIndex.layout = {
    breadcrumbs: [{ title: 'Residents', href: index() }],
};
