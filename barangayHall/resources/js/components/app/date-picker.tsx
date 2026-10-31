import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { formatDate, parseDateOnly, toDateOnly } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    /** "YYYY-MM-DD" or null. */
    value: string | null;
    onChange: (value: string | null) => void;
    /** When set, a hidden input submits the value with an Inertia <Form>. */
    name?: string;
    placeholder?: string;
    /** Earliest selectable year (birthdates: 1900). */
    fromYear?: number;
    /** Block dates after today (birthdates, visit dates). */
    disableFuture?: boolean;
    disabled?: boolean;
    invalid?: boolean;
    describedBy?: string;
    className?: string;
};

/**
 * Popover + Calendar date picker with month/year dropdowns, so a BHW can
 * reach a 1950 birthdate in two clicks.
 */
export function DatePicker({
    id,
    value,
    onChange,
    name,
    placeholder = 'Select a date',
    fromYear = 1900,
    disableFuture = false,
    disabled = false,
    invalid = false,
    describedBy,
    className,
}: Props) {
    const [open, setOpen] = useState(false);
    const selected = value ? parseDateOnly(value) : undefined;
    const today = new Date();

    return (
        <>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        aria-invalid={invalid || undefined}
                        aria-describedby={describedBy}
                        className={cn(
                            'h-11 w-full justify-start border-input bg-transparent text-left font-normal',
                            !value && 'text-muted-foreground',
                            className,
                        )}
                    >
                        <CalendarIcon aria-hidden />
                        {value ? formatDate(value, 'long') : placeholder}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        mode="single"
                        captionLayout="dropdown"
                        selected={selected}
                        defaultMonth={selected ?? today}
                        startMonth={new Date(fromYear, 0)}
                        endMonth={
                            disableFuture
                                ? today
                                : new Date(today.getFullYear() + 2, 11)
                        }
                        disabled={disableFuture ? { after: today } : undefined}
                        onSelect={(date) => {
                            onChange(date ? toDateOnly(date) : null);
                            setOpen(false);
                        }}
                        autoFocus
                    />
                </PopoverContent>
            </Popover>
            {name && <input type="hidden" name={name} value={value ?? ''} />}
        </>
    );
}
