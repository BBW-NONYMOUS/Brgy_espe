import { useForm } from '@inertiajs/react';
import { CalendarClock, Info } from 'lucide-react';
import { useState } from 'react';
import AppointmentController from '@/actions/App/Http/Controllers/Scheduling/AppointmentController';
import {
    DateField,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/app/fields';
import { ResidentPicker } from '@/components/app/scheduling/resident-picker';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import type { Appointment, ResidentOption } from '@/types';
import type { AppointmentsPageProps } from '@/types/pages/scheduling';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Edit an existing appointment; omit to book a new one. */
    appointment?: Appointment | null;
    /** New booking: date/time picked on the calendar. */
    initialDate?: string | null;
    initialTime?: string | null;
    options: AppointmentsPageProps['options'];
    residentOptions: ResidentOption[] | undefined;
    onSaved?: () => void;
};

/** "2026-11-04T09:00:00+08:00" → ["2026-11-04", "09:00"] (server time is Asia/Manila). */
export function splitDateTime(
    iso: string | null | undefined,
): [string, string] {
    return iso ? [iso.slice(0, 10), iso.slice(11, 16)] : ['', ''];
}

/**
 * Book or edit an appointment (Sheet from the right; full width on phones).
 */
export function AppointmentSheet({
    open,
    onOpenChange,
    appointment,
    initialDate,
    initialTime,
    options,
    residentOptions,
    onSaved,
}: Props) {
    const editing = !!appointment;
    const [date, time] = splitDateTime(appointment?.scheduled_at);
    const [resident, setResident] = useState<ResidentOption | null>(null);

    const form = useForm({
        resident_id: '',
        appointment_type_id: appointment?.type
            ? String(appointment.type.id)
            : '',
        date: (date || initialDate || '') as string | null,
        time: time || initialTime || '09:00',
        assigned_to: appointment?.assigned_to
            ? String(appointment.assigned_to.id)
            : '',
        status: appointment?.status.value ?? 'scheduled',
        notes: appointment?.notes ?? '',
    });
    // The server validates `scheduled_at` (built from date + time), not the form's own keys.
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = () => {
        form.transform((data) => ({
            ...(editing
                ? { status: data.status }
                : { resident_id: data.resident_id }),
            appointment_type_id: data.appointment_type_id,
            scheduled_at: data.date
                ? `${data.date} ${data.time || '09:00'}:00`
                : '',
            assigned_to: data.assigned_to || null,
            // Omitted when the role cannot see notes, so they are never wiped.
            ...(appointment && appointment.notes === undefined
                ? {}
                : { notes: data.notes || null }),
        }));
        form.submit(
            editing
                ? AppointmentController.update(appointment.id)
                : AppointmentController.store(),
            {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    onSaved?.();
                },
            },
        );
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                    className="flex min-h-full flex-col"
                >
                    <SheetHeader>
                        <SheetTitle>
                            {editing ? 'Edit appointment' : 'Book appointment'}
                        </SheetTitle>
                        <SheetDescription>
                            {editing
                                ? appointment.resident?.full_name
                                : 'Choose the resident, type and time.'}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-5 px-4">
                        {appointment?.is_generated && (
                            <Alert>
                                <Info aria-hidden />
                                <AlertDescription>
                                    Generated from the schedule. Changing the
                                    date or the assigned staff keeps it from
                                    being re-planned automatically.
                                </AlertDescription>
                            </Alert>
                        )}

                        {!editing && (
                            <ResidentPicker
                                id="resident_id"
                                value={resident}
                                onChange={(r) => {
                                    setResident(r);
                                    form.setData(
                                        'resident_id',
                                        r ? String(r.value) : '',
                                    );
                                }}
                                options={residentOptions}
                                error={errors.resident_id}
                            />
                        )}

                        <SelectField
                            id="appointment_type_id"
                            label="Type"
                            required
                            value={form.data.appointment_type_id}
                            onChange={(v) =>
                                form.setData('appointment_type_id', v)
                            }
                            options={options.types}
                            error={errors.appointment_type_id}
                        />

                        <div className="grid grid-cols-[1fr_auto] gap-4">
                            <DateField
                                id="appointment_date"
                                label="Date"
                                required
                                disableFuture={false}
                                value={form.data.date}
                                onChange={(v) => form.setData('date', v)}
                                error={errors.scheduled_at}
                            />
                            <TextField
                                id="appointment_time"
                                label="Time"
                                type="time"
                                required
                                step={900}
                                value={form.data.time}
                                onChange={(v) => form.setData('time', v)}
                            />
                        </div>

                        <SelectField
                            id="assigned_to"
                            label="Assigned to"
                            // Radix Select cannot use '' as a value, so "none" stands for unassigned.
                            value={form.data.assigned_to || 'none'}
                            onChange={(v) =>
                                form.setData(
                                    'assigned_to',
                                    v === 'none' ? '' : v,
                                )
                            }
                            options={[
                                { value: 'none', label: 'Not assigned' },
                                ...options.staff,
                            ]}
                            error={errors.assigned_to}
                            description="Unassigned appointments are reported to every health worker if missed."
                        />

                        {editing && (
                            <SelectField
                                id="status"
                                label="Status"
                                required
                                value={form.data.status}
                                onChange={(v) =>
                                    form.setData(
                                        'status',
                                        v as typeof form.data.status,
                                    )
                                }
                                options={options.statuses}
                                error={errors.status}
                            />
                        )}

                        {(!appointment || appointment.notes !== undefined) && (
                            <TextareaField
                                id="notes"
                                label="Notes"
                                rows={3}
                                value={form.data.notes}
                                onChange={(v) => form.setData('notes', v)}
                                error={errors.notes}
                                placeholder="e.g. Bring the BP log and Mother & Child Book"
                            />
                        )}
                    </div>

                    <SheetFooter className="sticky bottom-0 flex-row justify-end border-t bg-background">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <CalendarClock aria-hidden />
                            )}
                            {editing ? 'Save changes' : 'Book appointment'}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
