import { useForm } from '@inertiajs/react';
import ResidentController from '@/actions/App/Http/Controllers/ResidentController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { RadioField, TextareaField } from '@/components/app/fields';
import type { Resident } from '@/types';

type Props = {
    resident: Pick<Resident, 'id' | 'full_name'>;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const reasons = [
    { value: 'moved', label: 'Moved out of the barangay' },
    { value: 'deceased', label: 'Deceased' },
    { value: 'active', label: 'Duplicate or wrong entry' },
];

/**
 * Archive = soft delete with a reason. Nothing is permanently deleted.
 */
export function ArchiveResidentDialog({ resident, open, onOpenChange }: Props) {
    const form = useForm({ status: 'moved', archive_reason: '' });

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    form.reset();
                    form.clearErrors();
                }

                onOpenChange(next);
            }}
            title={`Archive ${resident.full_name}?`}
            description={
                <p>
                    The record will be hidden from lists and reports but kept,
                    with all health records, for the retention period. It can be
                    restored at any time.
                </p>
            }
            confirmLabel="Archive resident"
            destructive
            processing={form.processing}
            confirmDisabled={form.data.archive_reason.trim().length < 5}
            onConfirm={() =>
                form.submit(ResidentController.destroy(resident.id), {
                    onSuccess: () => onOpenChange(false),
                })
            }
        >
            <div className="space-y-4">
                <RadioField
                    id="archive-status"
                    label="Reason"
                    required
                    value={form.data.status}
                    onChange={(v) => form.setData('status', v)}
                    options={reasons}
                    error={form.errors.status}
                />
                <TextareaField
                    id="archive_reason"
                    label="Details"
                    required
                    rows={2}
                    value={form.data.archive_reason}
                    onChange={(v) => form.setData('archive_reason', v)}
                    error={form.errors.archive_reason}
                    placeholder='e.g. "Moved to Tacurong City, March 2026"'
                />
            </div>
        </ConfirmDialog>
    );
}
