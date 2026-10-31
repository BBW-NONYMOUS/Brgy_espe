import { Head, Link, usePage } from '@inertiajs/react';
import { MoreHorizontal, PackagePlus, Pill, Plus, Syringe } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { ExpiryBadge, StockLevelBadge } from '@/components/app/expiry-badge';
import {
    DispenseDialog,
    MedicineDialog,
    ReceiveStockDialog,
} from '@/components/app/inventory/stock-dialogs';
import { expiryOf } from '@/components/app/inventory/expiry';
import { PageHeader } from '@/components/app/page-header';
import { FilterChips } from '@/components/app/referrals/filter-chips';
import { SearchFilters } from '@/components/app/search-filters';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTableQuery } from '@/hooks/use-table-query';
import { index, show } from '@/routes/medicines';
import type { Medicine } from '@/types/modules';
import type { InventoryIndexProps } from '@/types/pages/services';

type QuickFilter = 'expiring' | 'low';

export default function InventoryIndex({
    medicines,
    filters,
    options,
    residentOptions,
    residentAlerts,
}: InventoryIndexProps) {
    const { can } = usePage().props;
    const { query, update, reset, processing } = useTableQuery(filters, {
        only: ['medicines', 'filters'],
    });
    const [dialog, setDialog] = useState<
        { kind: 'dispense' | 'receive'; medicine: Medicine } | 'add' | null
    >(null);
    const quick: QuickFilter | null = query.expiry
        ? 'expiring'
        : query.stock
          ? 'low'
          : null;

    return (
        <>
            <Head title="Medicine inventory" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Medicine inventory"
                    description="Usable stock excludes expired batches. Dispensing always takes the batch that expires first."
                    actions={
                        can.manageInventory && (
                            <Button onClick={() => setDialog('add')}>
                                <Plus aria-hidden /> Add medicine
                            </Button>
                        )
                    }
                />

                <FilterChips<QuickFilter>
                    label="Quick filters"
                    value={quick}
                    onChange={(v) =>
                        update({
                            expiry: v === 'expiring' ? 'soon' : null,
                            stock: v === 'low' ? 'low' : null,
                        })
                    }
                    chips={[
                        { value: null, label: 'All medicines' },
                        { value: 'expiring', label: 'Expiring (≤ 90 days)' },
                        { value: 'low', label: 'Low stock' },
                    ]}
                />

                <SearchFilters
                    search={{
                        value: query.search,
                        onChange: (v) =>
                            update({ search: v }, { debounced: true }),
                        placeholder: 'Generic or brand name',
                        label: 'Search medicines',
                    }}
                    hasActiveFilters={Boolean(
                        query.search || query.expiry || query.stock,
                    )}
                    onReset={() =>
                        reset({ search: '', expiry: null, stock: null })
                    }
                />

                {medicines.length === 0 ? (
                    <EmptyState
                        icon={Pill}
                        title="No medicines match"
                        description={
                            quick
                                ? 'Nothing needs attention for this filter.'
                                : undefined
                        }
                    />
                ) : (
                    <div
                        className="overflow-x-auto rounded-lg border bg-card"
                        aria-busy={processing}
                    >
                        <Table>
                            <TableCaption className="sr-only">
                                Medicines with usable stock, nearest expiry and
                                reorder level
                            </TableCaption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Medicine</TableHead>
                                    <TableHead>Usable stock</TableHead>
                                    <TableHead>Nearest expiry</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Expired, not yet written off
                                    </TableHead>
                                    <TableHead className="hidden text-right md:table-cell">
                                        Reorder level
                                    </TableHead>
                                    <TableHead className="w-12 text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {medicines.map((m) => {
                                    const expiry = m.nearest_expiry
                                        ? expiryOf(m.nearest_expiry)
                                        : null;

                                    return (
                                        <TableRow key={m.id}>
                                            <TableCell>
                                                <Link
                                                    href={show(m.id)}
                                                    className="font-bold text-primary underline-offset-4 hover:underline"
                                                >
                                                    {m.generic_name}{' '}
                                                    {m.strength}
                                                </Link>
                                                <div className="text-sm text-muted-foreground">
                                                    {m.form.label}
                                                    {m.brand_name
                                                        ? ` · ${m.brand_name}`
                                                        : ''}
                                                    {!m.is_active &&
                                                        ' · no longer stocked'}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <StockLevelBadge
                                                    onHand={
                                                        m.usable_on_hand ?? 0
                                                    }
                                                    reorderLevel={
                                                        m.reorder_level
                                                    }
                                                    unit={m.unit}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {expiry && m.nearest_expiry ? (
                                                    <ExpiryBadge
                                                        expiryDate={
                                                            m.nearest_expiry
                                                        }
                                                        band={expiry.band}
                                                        days={expiry.days}
                                                    />
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="hidden md:table-cell">
                                                {(m.expired_on_hand ?? 0) >
                                                0 ? (
                                                    <span className="font-bold text-destructive">
                                                        {m.expired_on_hand?.toLocaleString()}{' '}
                                                        {m.unit}(s) — write off
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        None
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="hidden text-right tabular-nums md:table-cell">
                                                {m.reorder_level.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {(can.dispenseMedicine ||
                                                    can.manageInventory) && (
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-11"
                                                                aria-label={`Actions for ${m.display_name}`}
                                                            >
                                                                <MoreHorizontal
                                                                    aria-hidden
                                                                />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            {can.dispenseMedicine && (
                                                                <DropdownMenuItem
                                                                    disabled={
                                                                        (m.usable_on_hand ??
                                                                            0) ===
                                                                        0
                                                                    }
                                                                    onSelect={() =>
                                                                        setDialog(
                                                                            {
                                                                                kind: 'dispense',
                                                                                medicine:
                                                                                    m,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <Syringe
                                                                        aria-hidden
                                                                    />{' '}
                                                                    Dispense
                                                                </DropdownMenuItem>
                                                            )}
                                                            {can.manageInventory && (
                                                                <DropdownMenuItem
                                                                    onSelect={() =>
                                                                        setDialog(
                                                                            {
                                                                                kind: 'receive',
                                                                                medicine:
                                                                                    m,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <PackagePlus
                                                                        aria-hidden
                                                                    />{' '}
                                                                    Receive
                                                                    stock
                                                                </DropdownMenuItem>
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            {dialog === 'add' && (
                <MedicineDialog
                    forms={options.forms}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog !== null &&
                dialog !== 'add' &&
                dialog.kind === 'receive' && (
                    <ReceiveStockDialog
                        medicine={dialog.medicine}
                        onClose={() => setDialog(null)}
                    />
                )}
            {dialog !== null &&
                dialog !== 'add' &&
                dialog.kind === 'dispense' && (
                    <DispenseDialog
                        medicine={dialog.medicine}
                        residentOptions={residentOptions}
                        residentAlerts={residentAlerts}
                        onClose={() => setDialog(null)}
                    />
                )}
        </>
    );
}

InventoryIndex.layout = {
    breadcrumbs: [{ title: 'Medicine inventory', href: index() }],
};
