import { Head, useForm, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ArrowRightLeft,
    ClipboardCheck,
    MoreHorizontal,
    PackagePlus,
    Pencil,
    ScrollText,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import StockController from '@/actions/App/Http/Controllers/Inventory/StockController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { ExpiryBadge, StockLevelBadge } from '@/components/app/expiry-badge';
import { TextField } from '@/components/app/fields';
import {
    MedicineDialog,
    ReceiveStockDialog,
} from '@/components/app/inventory/stock-dialogs';
import { PageHeader } from '@/components/app/page-header';
import { FilterChips } from '@/components/app/referrals/filter-chips';
import { StatusBadge } from '@/components/app/status-badge';
import type { BadgeTone } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
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
import { formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/medicines';
import type {
    MedicineBatch,
    StockMovement,
    StockMovementType,
} from '@/types/modules';
import type { InventoryShowProps } from '@/types/pages/services';

const movementTone: Record<StockMovementType, BadgeTone> = {
    in: 'success',
    dispensed: 'primary',
    out: 'info',
    expired: 'danger',
    adjustment: 'warning',
};

type BatchAction = {
    kind: 'adjust' | 'transfer' | 'write-off';
    batch: MedicineBatch;
};

export default function InventoryShow({
    medicine,
    batches,
    movements,
    filters,
    options,
}: InventoryShowProps) {
    const { can } = usePage().props;
    const { query, update, setPage, processing } = useTableQuery(filters, {
        only: ['movements', 'filters'],
    });
    const [editing, setEditing] = useState(false);
    const [receiving, setReceiving] = useState(false);
    const [action, setAction] = useState<BatchAction | null>(null);
    const usable = batches
        .filter((b) => b.expiry_band !== 0)
        .reduce((sum, b) => sum + b.quantity_on_hand, 0);

    const columns = useMemo<ColumnDef<StockMovement, unknown>[]>(
        () => [
            {
                id: 'date',
                header: 'Date',
                cell: ({ row }) => (
                    <span className="whitespace-nowrap">
                        {formatDateTime(row.original.created_at)}
                    </span>
                ),
            },
            {
                id: 'type',
                header: 'Type',
                cell: ({ row }) => (
                    <StatusBadge tone={movementTone[row.original.type.value]}>
                        {row.original.type.label}
                    </StatusBadge>
                ),
            },
            {
                id: 'quantity',
                header: 'Quantity',
                meta: {
                    headerClassName: 'text-right',
                    cellClassName: 'text-right tabular-nums',
                },
                cell: ({ row }) => (
                    <span
                        className={cn(
                            'font-bold',
                            row.original.quantity < 0
                                ? 'text-destructive'
                                : 'text-success-soft-foreground',
                        )}
                    >
                        {row.original.quantity > 0 ? '+' : '−'}
                        {Math.abs(row.original.quantity).toLocaleString()}
                    </span>
                ),
            },
            {
                id: 'batch',
                header: 'Batch',
                cell: ({ row }) => (
                    <span className="font-mono">{row.original.batch_no}</span>
                ),
            },
            {
                id: 'resident',
                header: 'Given to',
                meta: { hideOnMobile: true },
                cell: ({ row }) => row.original.resident?.full_name ?? '—',
            },
            {
                id: 'by',
                header: 'By / reason',
                meta: { hideOnMobile: true },
                cell: ({ row }) => (
                    <div className="text-sm">
                        {row.original.user}
                        {row.original.reason && (
                            <div className="text-muted-foreground">
                                {row.original.reason}
                            </div>
                        )}
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title={medicine.display_name} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    back={{ href: index(), label: 'Medicine inventory' }}
                    title={`${medicine.generic_name} ${medicine.strength}`}
                    description={`${medicine.form.label}${medicine.brand_name ? ` · ${medicine.brand_name}` : ''} · issued per ${medicine.unit} · reorder at ${medicine.reorder_level.toLocaleString()}`}
                    meta={
                        <StockLevelBadge
                            onHand={usable}
                            reorderLevel={medicine.reorder_level}
                            unit={medicine.unit}
                        />
                    }
                    actions={
                        can.manageInventory && (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() => setEditing(true)}
                                >
                                    <Pencil aria-hidden /> Edit
                                </Button>
                                <Button onClick={() => setReceiving(true)}>
                                    <PackagePlus aria-hidden /> Receive stock
                                </Button>
                            </>
                        )
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Batches</CardTitle>
                        <CardDescription>
                            Dispensing takes the batch that expires first.
                            Expired batches are never dispensed.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {batches.length === 0 ? (
                            <EmptyState
                                icon={PackagePlus}
                                title="No batches yet"
                                description={
                                    can.manageInventory
                                        ? 'Receive the first delivery to put this medicine in stock.'
                                        : undefined
                                }
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableCaption className="sr-only">
                                        Batches of {medicine.display_name}
                                    </TableCaption>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Batch</TableHead>
                                            <TableHead>Expiry</TableHead>
                                            <TableHead className="text-right">
                                                On hand
                                            </TableHead>
                                            <TableHead className="hidden text-right md:table-cell">
                                                Received
                                            </TableHead>
                                            <TableHead className="hidden md:table-cell">
                                                Source
                                            </TableHead>
                                            {can.manageInventory && (
                                                <TableHead className="w-12 text-right">
                                                    <span className="sr-only">
                                                        Actions
                                                    </span>
                                                </TableHead>
                                            )}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {batches.map((b) => (
                                            <TableRow
                                                key={b.id}
                                                className={cn(
                                                    b.quantity_on_hand === 0 &&
                                                        'text-muted-foreground',
                                                )}
                                            >
                                                <TableCell className="font-mono font-bold">
                                                    {b.batch_no}
                                                </TableCell>
                                                <TableCell>
                                                    <ExpiryBadge
                                                        expiryDate={
                                                            b.expiry_date
                                                        }
                                                        band={b.expiry_band}
                                                        days={b.days_to_expiry}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {b.quantity_on_hand.toLocaleString()}
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        /{' '}
                                                        {b.quantity_received.toLocaleString()}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="hidden text-right md:table-cell">
                                                    {formatDate(b.received_at)}
                                                </TableCell>
                                                <TableCell className="hidden md:table-cell">
                                                    {b.source ?? '—'}
                                                </TableCell>
                                                {can.manageInventory && (
                                                    <TableCell className="text-right">
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-11"
                                                                    aria-label={`Actions for batch ${b.batch_no}`}
                                                                >
                                                                    <MoreHorizontal
                                                                        aria-hidden
                                                                    />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end">
                                                                <DropdownMenuItem
                                                                    onSelect={() =>
                                                                        setAction(
                                                                            {
                                                                                kind: 'adjust',
                                                                                batch: b,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <ClipboardCheck
                                                                        aria-hidden
                                                                    />{' '}
                                                                    Correct the
                                                                    count
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem
                                                                    disabled={
                                                                        b.quantity_on_hand ===
                                                                        0
                                                                    }
                                                                    onSelect={() =>
                                                                        setAction(
                                                                            {
                                                                                kind: 'transfer',
                                                                                batch: b,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <ArrowRightLeft
                                                                        aria-hidden
                                                                    />{' '}
                                                                    Transfer out
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem
                                                                    disabled={
                                                                        b.expiry_band !==
                                                                            0 ||
                                                                        b.quantity_on_hand ===
                                                                            0
                                                                    }
                                                                    onSelect={() =>
                                                                        setAction(
                                                                            {
                                                                                kind: 'write-off',
                                                                                batch: b,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2
                                                                        aria-hidden
                                                                    />{' '}
                                                                    Write off
                                                                    (expired)
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <section
                    aria-labelledby="ledger-heading"
                    className="flex flex-col gap-3"
                >
                    <h2 id="ledger-heading" className="text-lg font-bold">
                        Stock movements
                    </h2>
                    <FilterChips<StockMovementType>
                        label="Filter movements by type"
                        value={query.type}
                        onChange={(type) => update({ type })}
                        chips={[
                            { value: null, label: 'All' },
                            ...options.movementTypes.map((t) => ({
                                value: t.value,
                                label: t.label,
                            })),
                        ]}
                    />
                    <DataTable
                        columns={columns}
                        data={movements}
                        onPageChange={setPage}
                        itemLabel="movements"
                        caption="Stock movement ledger"
                        processing={processing}
                        empty={
                            <EmptyState
                                icon={ScrollText}
                                title="No movements"
                            />
                        }
                    />
                </section>
            </div>

            {editing && (
                <MedicineDialog
                    medicine={medicine}
                    forms={options.forms}
                    onClose={() => setEditing(false)}
                />
            )}
            {receiving && (
                <ReceiveStockDialog
                    medicine={medicine}
                    onClose={() => setReceiving(false)}
                />
            )}
            {action && action.kind !== 'write-off' && (
                <BatchChangeDialog
                    action={action}
                    unit={medicine.unit}
                    onClose={() => setAction(null)}
                />
            )}
            {action?.kind === 'write-off' && (
                <WriteOffDialog
                    batch={action.batch}
                    unit={medicine.unit}
                    onClose={() => setAction(null)}
                />
            )}
        </>
    );
}

function BatchChangeDialog({
    action,
    unit,
    onClose,
}: {
    action: BatchAction;
    unit: string;
    onClose: () => void;
}) {
    const adjusting = action.kind === 'adjust';
    const form = useForm({
        quantity: adjusting ? String(action.batch.quantity_on_hand) : '',
        reason: '',
    });

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <form
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.submit(
                            adjusting
                                ? StockController.adjust(action.batch.id)
                                : StockController.transferOut(action.batch.id),
                            { preserveScroll: true, onSuccess: onClose },
                        );
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {adjusting ? 'Correct the count' : 'Transfer out'} —
                            batch {action.batch.batch_no}
                        </DialogTitle>
                        <DialogDescription>
                            {adjusting
                                ? `The system has ${action.batch.quantity_on_hand.toLocaleString()} ${unit}(s). Enter what is actually on the shelf.`
                                : `Send stock out of the health station (e.g. back to the RHU). ${action.batch.quantity_on_hand.toLocaleString()} available.`}
                        </DialogDescription>
                    </DialogHeader>
                    <TextField
                        id="quantity"
                        label={
                            adjusting
                                ? `Counted quantity (${unit}s)`
                                : `Quantity to transfer (${unit}s)`
                        }
                        type="number"
                        min={adjusting ? 0 : 1}
                        max={
                            adjusting
                                ? undefined
                                : action.batch.quantity_on_hand
                        }
                        inputMode="numeric"
                        required
                        value={form.data.quantity}
                        onChange={(v) => form.setData('quantity', v)}
                        error={form.errors.quantity}
                    />
                    <TextField
                        id="reason"
                        label="Reason"
                        required
                        placeholder={
                            adjusting
                                ? 'e.g. Monthly physical count'
                                : 'e.g. Returned to Isulan RHU'
                        }
                        value={form.data.reason}
                        onChange={(v) => form.setData('reason', v)}
                        error={form.errors.reason}
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function WriteOffDialog({
    batch,
    unit,
    onClose,
}: {
    batch: MedicineBatch;
    unit: string;
    onClose: () => void;
}) {
    const form = useForm({ reason: '' });

    return (
        <ConfirmDialog
            open
            onOpenChange={(o) => !o && onClose()}
            title={`Write off batch ${batch.batch_no}?`}
            description={
                <p>
                    {batch.quantity_on_hand.toLocaleString()} expired {unit}(s)
                    are removed from stock for disposal. The ledger keeps the
                    record.
                </p>
            }
            confirmLabel="Write off"
            destructive
            processing={form.processing}
            onConfirm={() =>
                form.submit(StockController.writeOff(batch.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                })
            }
        >
            <TextField
                id="write-off-reason"
                label="Note (optional)"
                value={form.data.reason}
                onChange={(v) => form.setData('reason', v)}
                error={
                    form.errors.reason ??
                    (form.errors as Record<string, string | undefined>).batch
                }
            />
        </ConfirmDialog>
    );
}

InventoryShow.layout = (props: InventoryShowProps) => ({
    breadcrumbs: [
        { title: 'Medicine inventory', href: index() },
        { title: props.medicine.display_name, href: show(props.medicine.id) },
    ],
});
