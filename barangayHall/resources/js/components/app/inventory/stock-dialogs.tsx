import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import MedicineController from '@/actions/App/Http/Controllers/Inventory/MedicineController';
import StockController from '@/actions/App/Http/Controllers/Inventory/StockController';
import {
    CheckboxField,
    DateField,
    SelectField,
    TextField,
} from '@/components/app/fields';
import { MedicalAlertBanner } from '@/components/app/medical-alert-banner';
import { ResidentPicker } from '@/components/app/referrals/resident-picker';
import type { PickedResident } from '@/components/app/referrals/resident-picker';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { toDateOnly } from '@/lib/format';
import type { EnumValue } from '@/types/models';
import type { MedicalAlert, Medicine, ResidentOption } from '@/types/modules';

function Footer({
    processing,
    label,
    onClose,
}: {
    processing: boolean;
    label: string;
    onClose: () => void;
}) {
    return (
        <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
                Cancel
            </Button>
            <Button type="submit" disabled={processing}>
                {processing && <Spinner />}
                {label}
            </Button>
        </DialogFooter>
    );
}

/**
 * Add a medicine to the list (no `medicine`) or edit one, incl. its reorder level.
 */
export function MedicineDialog({
    medicine,
    forms,
    onClose,
}: {
    medicine?: Medicine;
    forms: EnumValue[];
    onClose: () => void;
}) {
    const form = useForm({
        generic_name: medicine?.generic_name ?? '',
        brand_name: medicine?.brand_name ?? '',
        form: medicine?.form.value ?? '',
        strength: medicine?.strength ?? '',
        unit: medicine?.unit ?? '',
        reorder_level: medicine?.reorder_level ?? 0,
        is_active: medicine?.is_active ?? true,
    });

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <form
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.submit(
                            medicine
                                ? MedicineController.update(medicine.id)
                                : MedicineController.store(),
                            { preserveScroll: true, onSuccess: onClose },
                        );
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {medicine
                                ? `Edit ${medicine.display_name}`
                                : 'Add medicine'}
                        </DialogTitle>
                        <DialogDescription>
                            Stock is added by receiving batches; this only
                            describes the medicine.
                        </DialogDescription>
                    </DialogHeader>
                    <TextField
                        id="generic_name"
                        label="Generic name"
                        required
                        value={form.data.generic_name}
                        onChange={(v) => form.setData('generic_name', v)}
                        error={form.errors.generic_name}
                    />
                    <TextField
                        id="brand_name"
                        label="Brand name (optional)"
                        value={form.data.brand_name}
                        onChange={(v) => form.setData('brand_name', v)}
                        error={form.errors.brand_name}
                    />
                    <div className="grid gap-5 sm:grid-cols-2">
                        <SelectField
                            id="form"
                            label="Form"
                            required
                            value={form.data.form}
                            onChange={(v) => form.setData('form', v)}
                            options={forms}
                            error={form.errors.form}
                        />
                        <TextField
                            id="strength"
                            label="Strength"
                            required
                            placeholder="e.g. 500 mg"
                            value={form.data.strength}
                            onChange={(v) => form.setData('strength', v)}
                            error={form.errors.strength}
                        />
                        <TextField
                            id="unit"
                            label="Unit of issue"
                            required
                            placeholder="tablet, bottle, sachet"
                            value={form.data.unit}
                            onChange={(v) => form.setData('unit', v)}
                            error={form.errors.unit}
                        />
                        <TextField
                            id="reorder_level"
                            label="Reorder level"
                            type="number"
                            min={0}
                            inputMode="numeric"
                            required
                            value={form.data.reorder_level}
                            onChange={(v) =>
                                form.setData('reorder_level', Number(v))
                            }
                            error={form.errors.reorder_level}
                            description="Alert when usable stock falls to this."
                        />
                    </div>
                    {medicine && (
                        <CheckboxField
                            id="is_active"
                            label="Still stocked at the health station"
                            checked={form.data.is_active}
                            onChange={(v) => form.setData('is_active', v)}
                        />
                    )}
                    <Footer
                        processing={form.processing}
                        label={medicine ? 'Save changes' : 'Add medicine'}
                        onClose={onClose}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ReceiveStockDialog({
    medicine,
    onClose,
}: {
    medicine: Medicine;
    onClose: () => void;
}) {
    const form = useForm({
        batch_no: '',
        expiry_date: '',
        quantity: '',
        received_at: toDateOnly(new Date()),
        source: '',
    });

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <form
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.submit(StockController.receive(medicine.id), {
                            preserveScroll: true,
                            onSuccess: onClose,
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Receive stock</DialogTitle>
                        <DialogDescription>
                            {medicine.display_name} — copy the batch number and
                            expiry date from the box.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <TextField
                            id="batch_no"
                            label="Batch / lot no."
                            required
                            autoCapitalize="characters"
                            value={form.data.batch_no}
                            onChange={(v) => form.setData('batch_no', v)}
                            error={form.errors.batch_no}
                        />
                        {/* Native date input: expiry dates can be years ahead. */}
                        <TextField
                            id="expiry_date"
                            label="Expiry date"
                            type="date"
                            required
                            value={form.data.expiry_date}
                            onChange={(v) => form.setData('expiry_date', v)}
                            error={form.errors.expiry_date}
                        />
                        <TextField
                            id="quantity"
                            label={`Quantity (${medicine.unit}s)`}
                            type="number"
                            min={1}
                            inputMode="numeric"
                            required
                            value={form.data.quantity}
                            onChange={(v) => form.setData('quantity', v)}
                            error={form.errors.quantity}
                        />
                        <DateField
                            id="received_at"
                            label="Date received"
                            required
                            value={form.data.received_at}
                            onChange={(v) =>
                                form.setData('received_at', v ?? '')
                            }
                            error={form.errors.received_at}
                        />
                    </div>
                    <TextField
                        id="source"
                        label="Source (optional)"
                        placeholder="DOH, LGU Isulan, donation"
                        value={form.data.source}
                        onChange={(v) => form.setData('source', v)}
                        error={form.errors.source}
                    />
                    <Footer
                        processing={form.processing}
                        label="Receive"
                        onClose={onClose}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Dispense to a resident. Picking the resident partial-reloads their
 * critical alerts (allergies!) so they show before anything is given.
 * The server picks the batches (first-expire, first-out).
 */
export function DispenseDialog({
    medicine,
    residentOptions,
    residentAlerts = [],
    onClose,
}: {
    medicine: Medicine;
    residentOptions?: ResidentOption[];
    residentAlerts?: MedicalAlert[];
    onClose: () => void;
}) {
    const [resident, setResident] = useState<PickedResident | null>(null);
    const [checking, setChecking] = useState(false);
    const form = useForm({ resident_id: '', quantity: '', reason: '' });
    const available = medicine.usable_on_hand ?? 0;

    const pick = (r: PickedResident | null) => {
        setResident(r);
        form.setData('resident_id', r ? String(r.value) : '');

        if (r) {
            setChecking(true);
            router.reload({
                only: ['residentAlerts'],
                data: { resident_id: r.value },
                onFinish: () => setChecking(false),
            });
        }
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <form
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.submit(StockController.dispense(medicine.id), {
                            preserveScroll: true,
                            onSuccess: onClose,
                        });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            Dispense {medicine.display_name}
                        </DialogTitle>
                        <DialogDescription>
                            {available.toLocaleString()} {medicine.unit}(s)
                            usable. Taken from the batch that expires first.
                        </DialogDescription>
                    </DialogHeader>
                    <ResidentPicker
                        value={resident}
                        onChange={pick}
                        options={residentOptions}
                        error={form.errors.resident_id}
                    />
                    {resident &&
                        (checking ? (
                            <p
                                className="text-sm text-muted-foreground"
                                role="status"
                            >
                                Checking medical alerts…
                            </p>
                        ) : (
                            <MedicalAlertBanner
                                alerts={residentAlerts}
                                compact
                            />
                        ))}
                    {resident && !checking && residentAlerts.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No critical alerts recorded for this resident.
                        </p>
                    )}
                    <TextField
                        id="quantity"
                        label={`Quantity (${medicine.unit}s)`}
                        type="number"
                        min={1}
                        max={available}
                        inputMode="numeric"
                        required
                        value={form.data.quantity}
                        onChange={(v) => form.setData('quantity', v)}
                        error={form.errors.quantity}
                    />
                    <TextField
                        id="reason"
                        label="Note (optional)"
                        placeholder="e.g. 3 x a day for 5 days"
                        value={form.data.reason}
                        onChange={(v) => form.setData('reason', v)}
                        error={form.errors.reason}
                    />
                    <Footer
                        processing={form.processing}
                        label="Dispense"
                        onClose={onClose}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}
