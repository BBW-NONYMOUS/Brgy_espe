import { router } from '@inertiajs/react';
import type { ColumnDef, RowData } from '@tanstack/react-table';
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';
import type { MouseEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData extends RowData, TValue> {
        /** Server-side sort key; makes the header a sort button. */
        sortKey?: string;
        /** Hide on small screens (tablet portrait / phone). */
        hideOnMobile?: boolean;
        headerClassName?: string;
        cellClassName?: string;
    }
}

export type SortState = { sort?: string | null; direction?: string | null };

type Props<T> = {
    columns: ColumnDef<T, unknown>[];
    data: Paginated<T>;
    /** Current sort from the URL. */
    sort?: SortState;
    onSort?: (sortKey: string) => void;
    onPageChange: (page: number) => void;
    perPage?: number;
    onPerPageChange?: (perPage: number) => void;
    /** Noun for the "Showing 1–15 of 230 residents" line. */
    itemLabel?: string;
    /** Clicking a row opens this URL (the first column should also hold a real link for keyboard users). */
    rowHref?: (row: T) => string;
    /** Shown when there are no rows. */
    empty: ReactNode;
    processing?: boolean;
    caption: string;
};

/**
 * Server-side data table (TanStack Table in manual mode). Sorting and
 * pagination happen in Laravel; this component only renders the current
 * page and reports user intent through callbacks.
 */
export function DataTable<T>({
    columns,
    data,
    sort,
    onSort,
    onPageChange,
    perPage,
    onPerPageChange,
    itemLabel = 'records',
    rowHref,
    empty,
    processing = false,
    caption,
}: Props<T>) {
    'use no memo'; // TanStack Table is not React Compiler compatible.

    const table = useReactTable({
        data: data.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        manualSorting: true,
        pageCount: data.meta.last_page,
    });

    const openRow = (event: MouseEvent, row: T) => {
        if (!rowHref) {
            return;
        }

        // Let real links, buttons and menus inside the row work normally.
        if (
            (event.target as HTMLElement).closest(
                'a,button,[role="menuitem"],input,label',
            )
        ) {
            return;
        }

        router.visit(rowHref(row));
    };

    if (data.data.length === 0) {
        return <>{empty}</>;
    }

    return (
        <div className="space-y-3">
            <div
                className={cn(
                    'overflow-hidden rounded-xl border bg-card transition-opacity',
                    processing && 'opacity-60',
                )}
                aria-busy={processing}
            >
                <Table>
                    <caption className="sr-only">{caption}</caption>
                    <TableHeader className="bg-muted/60">
                        {table.getHeaderGroups().map((group) => (
                            <TableRow
                                key={group.id}
                                className="hover:bg-transparent"
                            >
                                {group.headers.map((header) => {
                                    const meta = header.column.columnDef.meta;

                                    return (
                                        <TableHead
                                            key={header.id}
                                            className={cn(
                                                'h-11 font-bold text-foreground',
                                                meta?.hideOnMobile &&
                                                    'hidden md:table-cell',
                                                meta?.headerClassName,
                                            )}
                                            aria-sort={
                                                meta?.sortKey &&
                                                sort?.sort === meta.sortKey
                                                    ? sort.direction === 'desc'
                                                        ? 'descending'
                                                        : 'ascending'
                                                    : undefined
                                            }
                                        >
                                            {header.isPlaceholder ? null : meta?.sortKey &&
                                              onSort ? (
                                                <SortButton
                                                    active={
                                                        sort?.sort ===
                                                        meta.sortKey
                                                    }
                                                    direction={sort?.direction}
                                                    onClick={() =>
                                                        onSort(
                                                            meta.sortKey as string,
                                                        )
                                                    }
                                                >
                                                    {flexRender(
                                                        header.column.columnDef
                                                            .header,
                                                        header.getContext(),
                                                    )}
                                                </SortButton>
                                            ) : (
                                                flexRender(
                                                    header.column.columnDef
                                                        .header,
                                                    header.getContext(),
                                                )
                                            )}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.map((row) => (
                            <TableRow
                                key={row.id}
                                onClick={(event) =>
                                    openRow(event, row.original)
                                }
                                className={cn(rowHref && 'cursor-pointer')}
                            >
                                {row.getVisibleCells().map((cell) => {
                                    const meta = cell.column.columnDef.meta;

                                    return (
                                        <TableCell
                                            key={cell.id}
                                            className={cn(
                                                'py-3',
                                                meta?.hideOnMobile &&
                                                    'hidden md:table-cell',
                                                meta?.cellClassName,
                                            )}
                                        >
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <DataTablePagination
                meta={data.meta}
                itemLabel={itemLabel}
                onPageChange={onPageChange}
                perPage={perPage}
                onPerPageChange={onPerPageChange}
            />
        </div>
    );
}

function SortButton({
    active,
    direction,
    onClick,
    children,
}: {
    active: boolean;
    direction?: string | null;
    onClick: () => void;
    children: ReactNode;
}) {
    const Icon = !active
        ? ArrowUpDown
        : direction === 'desc'
          ? ArrowDown
          : ArrowUp;

    return (
        <Button
            variant="ghost"
            size="sm"
            className="-ml-3 h-8 font-bold"
            onClick={onClick}
        >
            {children}
            <Icon
                className={cn('size-4', !active && 'text-muted-foreground')}
                aria-hidden
            />
            <span className="sr-only">
                {active
                    ? `, sorted ${direction === 'desc' ? 'descending' : 'ascending'}. Click to reverse.`
                    : ', click to sort'}
            </span>
        </Button>
    );
}

export function DataTablePagination({
    meta,
    itemLabel,
    onPageChange,
    perPage,
    onPerPageChange,
}: {
    meta: Paginated<unknown>['meta'];
    itemLabel: string;
    onPageChange: (page: number) => void;
    perPage?: number;
    onPerPageChange?: (perPage: number) => void;
}) {
    const first = meta.current_page <= 1;
    const last = meta.current_page >= meta.last_page;

    return (
        <nav
            className="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between"
            aria-label="Pagination"
            data-print="hide"
        >
            <p className="text-muted-foreground" aria-live="polite">
                Showing{' '}
                <strong className="text-foreground">
                    {formatNumber(meta.from)}–{formatNumber(meta.to)}
                </strong>{' '}
                of{' '}
                <strong className="text-foreground">
                    {formatNumber(meta.total)}
                </strong>{' '}
                {itemLabel}
            </p>
            <div className="flex flex-wrap items-center gap-2">
                {onPerPageChange && (
                    <div className="flex items-center gap-2">
                        <span
                            id="rows-per-page"
                            className="text-muted-foreground"
                        >
                            Rows per page
                        </span>
                        <Select
                            value={String(perPage ?? meta.per_page)}
                            onValueChange={(v) => onPerPageChange(Number(v))}
                        >
                            <SelectTrigger
                                className="h-9 w-[4.5rem]"
                                aria-labelledby="rows-per-page"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {[10, 15, 25, 50].map((n) => (
                                    <SelectItem key={n} value={String(n)}>
                                        {n}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}
                <span className="px-1 font-bold">
                    Page {meta.current_page} of {Math.max(meta.last_page, 1)}
                </span>
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-9"
                        disabled={first}
                        onClick={() => onPageChange(1)}
                        aria-label="First page"
                    >
                        <ChevronsLeft aria-hidden />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-9"
                        disabled={first}
                        onClick={() => onPageChange(meta.current_page - 1)}
                        aria-label="Previous page"
                    >
                        <ChevronLeft aria-hidden />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-9"
                        disabled={last}
                        onClick={() => onPageChange(meta.current_page + 1)}
                        aria-label="Next page"
                    >
                        <ChevronRight aria-hidden />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-9"
                        disabled={last}
                        onClick={() => onPageChange(meta.last_page)}
                        aria-label="Last page"
                    >
                        <ChevronsRight aria-hidden />
                    </Button>
                </div>
            </div>
        </nav>
    );
}

/**
 * Placeholder rows while a table's data loads (e.g. deferred props).
 */
export function DataTableSkeleton({
    columns = 5,
    rows = 8,
}: {
    columns?: number;
    rows?: number;
}) {
    return (
        <div className="overflow-hidden rounded-xl border bg-card" aria-hidden>
            <div className="flex gap-4 border-b bg-muted/60 p-3">
                {Array.from({ length: columns }, (_, i) => (
                    <Skeleton key={i} className="h-5 flex-1" />
                ))}
            </div>
            {Array.from({ length: rows }, (_, r) => (
                <div key={r} className="flex gap-4 border-b p-3 last:border-0">
                    {Array.from({ length: columns }, (_, i) => (
                        <Skeleton key={i} className="h-5 flex-1" />
                    ))}
                </div>
            ))}
        </div>
    );
}
