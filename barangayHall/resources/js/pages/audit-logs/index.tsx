import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ScrollText } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DataTable } from '@/components/app/data-table';
import { DatePicker } from '@/components/app/date-picker';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { SearchFilters } from '@/components/app/search-filters';
import { StatusBadge } from '@/components/app/status-badge';
import type { Tone } from '@/components/app/stat-card';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatAuditValue, formatDateTime } from '@/lib/format';
import { index } from '@/routes/audit-logs';
import type { AuditLogEntry, Option, Paginated } from '@/types';

type Filters = {
    search: string;
    user_id: number | null;
    action: string | null;
    table_name: string | null;
    date_from: string | null;
    date_to: string | null;
};

type Props = {
    logs: Paginated<AuditLogEntry>;
    filters: Filters;
    options: {
        users: Option[];
        actions: Option<string>[];
        tables: Option<string>[];
    };
};

const actionTone: Record<string, Tone | 'neutral'> = {
    created: 'success',
    updated: 'info',
    deleted: 'warning',
    restored: 'success',
    force_deleted: 'danger',
    viewed_sensitive: 'primary',
    exported: 'primary',
    login_failed: 'danger',
    locked: 'danger',
    password_reset: 'warning',
};

/**
 * Read-only audit trail. There is no edit or delete anywhere.
 */
export default function AuditLogsIndex({ logs, filters, options }: Props) {
    const { query, update, setPage, reset, processing } = useTableQuery(
        filters,
        { only: ['logs', 'filters'] },
    );
    const [selected, setSelected] = useState<AuditLogEntry | null>(null);

    const columns = useMemo<ColumnDef<AuditLogEntry, unknown>[]>(
        () => [
            {
                id: 'when',
                header: 'When',
                meta: { cellClassName: 'whitespace-nowrap' },
                cell: ({ row }) => formatDateTime(row.original.created_at),
            },
            {
                id: 'user',
                header: 'User',
                cell: ({ row }) =>
                    row.original.user?.name ?? (
                        <span className="text-muted-foreground">
                            System / unknown
                        </span>
                    ),
            },
            {
                id: 'action',
                header: 'Action',
                cell: ({ row }) => (
                    <StatusBadge
                        tone={
                            actionTone[row.original.action.value] ?? 'neutral'
                        }
                    >
                        {row.original.action.label}
                    </StatusBadge>
                ),
            },
            {
                id: 'description',
                header: 'Details',
                meta: { cellClassName: 'whitespace-normal min-w-64' },
                cell: ({ row }) => (
                    <button
                        type="button"
                        className="text-left underline-offset-4 hover:underline"
                        onClick={() => setSelected(row.original)}
                    >
                        {row.original.description ??
                            `${row.original.table_name} #${row.original.record_id}`}
                    </button>
                ),
            },
            {
                id: 'ip',
                header: 'IP address',
                meta: {
                    hideOnMobile: true,
                    cellClassName: 'font-mono text-sm',
                },
                cell: ({ row }) => row.original.ip_address,
            },
        ],
        [],
    );

    const active = Boolean(
        query.search ||
        query.user_id ||
        query.action ||
        query.table_name ||
        query.date_from ||
        query.date_to,
    );

    return (
        <>
            <Head title="Audit logs" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Audit logs"
                    description="Who did what, when, and to which record. Entries cannot be edited or deleted."
                />

                <SearchFilters
                    search={{
                        value: query.search,
                        onChange: (v) =>
                            update({ search: v }, { debounced: true }),
                        placeholder: 'Search details, e.g. KII-2026-00012',
                        label: 'Search audit logs',
                    }}
                    filters={[
                        {
                            key: 'user',
                            label: 'User',
                            allLabel: 'All users',
                            options: options.users,
                            value: query.user_id,
                            onChange: (v) =>
                                update({ user_id: v ? Number(v) : null }),
                        },
                        {
                            key: 'action',
                            label: 'Action',
                            allLabel: 'All actions',
                            options: options.actions,
                            value: query.action,
                            onChange: (v) => update({ action: v }),
                        },
                        {
                            key: 'table',
                            label: 'Record type',
                            allLabel: 'All records',
                            options: options.tables,
                            value: query.table_name,
                            onChange: (v) => update({ table_name: v }),
                        },
                    ]}
                    hasActiveFilters={active}
                    onReset={() =>
                        reset({
                            search: '',
                            user_id: null,
                            action: null,
                            table_name: null,
                            date_from: null,
                            date_to: null,
                        })
                    }
                >
                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <Label htmlFor="log_from" className="sr-only">
                                From date
                            </Label>
                            <DatePicker
                                id="log_from"
                                value={query.date_from}
                                onChange={(v) => update({ date_from: v })}
                                disableFuture
                                fromYear={2025}
                                placeholder="From"
                                className="sm:w-40"
                            />
                        </div>
                        <div>
                            <Label htmlFor="log_to" className="sr-only">
                                To date
                            </Label>
                            <DatePicker
                                id="log_to"
                                value={query.date_to}
                                onChange={(v) => update({ date_to: v })}
                                disableFuture
                                fromYear={2025}
                                placeholder="To"
                                className="sm:w-40"
                            />
                        </div>
                    </div>
                </SearchFilters>

                <DataTable
                    columns={columns}
                    data={logs}
                    onPageChange={setPage}
                    itemLabel="entries"
                    caption="Audit log entries"
                    processing={processing}
                    empty={
                        <EmptyState
                            icon={ScrollText}
                            title="No entries match these filters"
                        />
                    }
                />
            </div>

            <Sheet
                open={selected !== null}
                onOpenChange={(o) => !o && setSelected(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                    {selected && (
                        <>
                            <SheetHeader>
                                <SheetTitle>{selected.action.label}</SheetTitle>
                                <SheetDescription>
                                    {formatDateTime(selected.created_at)} ·{' '}
                                    {selected.user?.name ?? 'System / unknown'}
                                </SheetDescription>
                            </SheetHeader>
                            <div className="space-y-5 px-4 pb-6 text-sm">
                                <dl className="grid grid-cols-[8rem_1fr] gap-2">
                                    <dt className="text-muted-foreground">
                                        Details
                                    </dt>
                                    <dd>{selected.description}</dd>
                                    <dt className="text-muted-foreground">
                                        Record
                                    </dt>
                                    <dd className="font-mono">
                                        {selected.table_name
                                            ? `${selected.table_name} #${selected.record_id}`
                                            : '—'}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        IP address
                                    </dt>
                                    <dd className="font-mono">
                                        {selected.ip_address ?? '—'}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        Device
                                    </dt>
                                    <dd className="wrap-break-word">
                                        {selected.user_agent ?? '—'}
                                    </dd>
                                </dl>
                                {(selected.old_values ||
                                    selected.new_values) && (
                                    <table className="w-full border-collapse text-left">
                                        <caption className="mb-2 text-left font-bold">
                                            Values
                                        </caption>
                                        <thead>
                                            <tr className="border-b">
                                                <th className="py-1 pr-2">
                                                    Field
                                                </th>
                                                <th className="py-1 pr-2">
                                                    Before
                                                </th>
                                                <th className="py-1">After</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {Object.keys({
                                                ...selected.old_values,
                                                ...selected.new_values,
                                            }).map((key) => (
                                                <tr
                                                    key={key}
                                                    className="border-b align-top"
                                                >
                                                    <th
                                                        scope="row"
                                                        className="py-1 pr-2 font-normal text-muted-foreground"
                                                    >
                                                        {key.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </th>
                                                    <td className="py-1 pr-2 break-all">
                                                        {formatAuditValue(
                                                            selected
                                                                .old_values?.[
                                                                key
                                                            ],
                                                        )}
                                                    </td>
                                                    <td className="py-1 break-all">
                                                        {formatAuditValue(
                                                            selected
                                                                .new_values?.[
                                                                key
                                                            ],
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

AuditLogsIndex.layout = {
    breadcrumbs: [{ title: 'Audit logs', href: index() }],
};
