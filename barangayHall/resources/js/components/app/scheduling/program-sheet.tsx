import { useForm } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import HealthProgramController from '@/actions/App/Http/Controllers/Scheduling/HealthProgramController';
import {
    CheckboxField,
    DateField,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/app/fields';
import { splitDateTime } from '@/components/app/scheduling/appointment-sheet';
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
import type { HealthProgram } from '@/types';
import type { ProgramFormOptions } from '@/types/pages/scheduling';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Edit an existing program; omit to schedule a new one. */
    program?: HealthProgram | null;
    options: ProgramFormOptions;
};

/**
 * Schedule or edit a community health program: when and where, who it is
 * for (target group + puroks) and which staff run it.
 */
export function ProgramSheet({ open, onOpenChange, program, options }: Props) {
    const editing = !!program;
    const [startDate, startTime] = splitDateTime(program?.start_at);
    const [endDate, endTime] = splitDateTime(program?.end_at);

    const form = useForm({
        title: program?.title ?? '',
        program_type_id: program?.type ? String(program.type.id) : '',
        description: program?.description ?? '',
        start_date: (startDate || null) as string | null,
        start_time: startTime || '08:00',
        end_date: (endDate || null) as string | null,
        end_time: endTime || '12:00',
        venue: program?.venue ?? '',
        target_group: (program?.target_group.value ?? 'everyone') as string,
        status: (program?.status.value ?? 'scheduled') as string,
        purok_ids: (program?.puroks ?? []).map((p) => p.id),
        staff_ids: (program?.staff ?? []).map((s) => s.id),
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const toggle = (key: 'purok_ids' | 'staff_ids', id: number, on: boolean) =>
        form.setData(
            key,
            on
                ? [...form.data[key], id]
                : form.data[key].filter((x) => x !== id),
        );

    const submit = () => {
        form.transform((d) => ({
            title: d.title,
            program_type_id: d.program_type_id,
            description: d.description || null,
            start_at: d.start_date ? `${d.start_date} ${d.start_time}:00` : '',
            // Same-day programs: the end date defaults to the start date.
            end_at:
                (d.end_date ?? d.start_date)
                    ? `${d.end_date ?? d.start_date} ${d.end_time}:00`
                    : '',
            venue: d.venue,
            target_group: d.target_group,
            ...(editing ? { status: d.status } : {}),
            purok_ids: d.purok_ids,
            staff_ids: d.staff_ids,
        }));
        form.submit(
            editing
                ? HealthProgramController.update(program.id)
                : HealthProgramController.store(),
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                    className="flex min-h-full flex-col"
                >
                    <SheetHeader>
                        <SheetTitle>
                            {editing ? 'Edit program' : 'Schedule a program'}
                        </SheetTitle>
                        <SheetDescription>
                            It appears on everyone's calendar, including the
                            Barangay Officials'.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-5 px-4">
                        <TextField
                            id="title"
                            label="Title"
                            required
                            value={form.data.title}
                            onChange={(v) => form.setData('title', v)}
                            error={errors.title}
                            placeholder="e.g. Measles-rubella immunization day"
                        />
                        <SelectField
                            id="program_type_id"
                            label="Type"
                            required
                            value={form.data.program_type_id}
                            onChange={(v) => form.setData('program_type_id', v)}
                            options={options.types}
                            error={errors.program_type_id}
                        />

                        <fieldset className="grid gap-4">
                            <legend className="mb-2 font-bold">When</legend>
                            <div className="grid grid-cols-[1fr_auto] gap-4">
                                <DateField
                                    id="start_date"
                                    label="Starts"
                                    required
                                    disableFuture={false}
                                    value={form.data.start_date}
                                    onChange={(v) =>
                                        form.setData('start_date', v)
                                    }
                                    error={errors.start_at}
                                />
                                <TextField
                                    id="start_time"
                                    label="Time"
                                    type="time"
                                    required
                                    step={900}
                                    value={form.data.start_time}
                                    onChange={(v) =>
                                        form.setData('start_time', v)
                                    }
                                />
                            </div>
                            <div className="grid grid-cols-[1fr_auto] gap-4">
                                <DateField
                                    id="end_date"
                                    label="Ends"
                                    disableFuture={false}
                                    value={form.data.end_date}
                                    onChange={(v) =>
                                        form.setData('end_date', v)
                                    }
                                    error={errors.end_at}
                                    description="Leave empty for the same day."
                                />
                                <TextField
                                    id="end_time"
                                    label="Time"
                                    type="time"
                                    required
                                    step={900}
                                    value={form.data.end_time}
                                    onChange={(v) =>
                                        form.setData('end_time', v)
                                    }
                                />
                            </div>
                        </fieldset>

                        <TextField
                            id="venue"
                            label="Venue"
                            required
                            value={form.data.venue}
                            onChange={(v) => form.setData('venue', v)}
                            error={errors.venue}
                            placeholder="e.g. Kalawag II Barangay Health Station"
                        />
                        <SelectField
                            id="target_group"
                            label="For whom"
                            required
                            value={form.data.target_group}
                            onChange={(v) => form.setData('target_group', v)}
                            options={options.targetGroups}
                            error={errors.target_group}
                            description="Used to list eligible residents on the attendance sheet."
                        />

                        <fieldset>
                            <legend className="mb-1 font-bold">Puroks</legend>
                            <p className="mb-2 text-sm text-muted-foreground">
                                Leave all unticked for the whole barangay.
                            </p>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                {options.puroks.map((p) => (
                                    <CheckboxField
                                        key={p.value}
                                        id={`purok-${p.value}`}
                                        label={p.label}
                                        checked={form.data.purok_ids.includes(
                                            p.value,
                                        )}
                                        onChange={(on) =>
                                            toggle('purok_ids', p.value, on)
                                        }
                                    />
                                ))}
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend className="mb-2 font-bold">
                                Assigned staff
                            </legend>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {options.staff.map((s) => (
                                    <CheckboxField
                                        key={s.value}
                                        id={`staff-${s.value}`}
                                        label={s.label}
                                        checked={form.data.staff_ids.includes(
                                            s.value,
                                        )}
                                        onChange={(on) =>
                                            toggle('staff_ids', s.value, on)
                                        }
                                    />
                                ))}
                            </div>
                        </fieldset>

                        {editing && (
                            <SelectField
                                id="program_status"
                                label="Status"
                                required
                                value={form.data.status}
                                onChange={(v) => form.setData('status', v)}
                                options={options.statuses}
                                error={errors.status}
                            />
                        )}

                        <TextareaField
                            id="description"
                            label="Description"
                            rows={3}
                            value={form.data.description}
                            onChange={(v) => form.setData('description', v)}
                            error={errors.description}
                            placeholder="What will happen and what residents should bring"
                        />
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
                                <ClipboardList aria-hidden />
                            )}
                            {editing ? 'Save changes' : 'Schedule program'}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
