import { Check, ChevronsUpDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

export type ComboboxOption = {
    value: number | string;
    label: string;
    /** Second line, e.g. "Purok 3 · near the rice mill". */
    description?: string;
};

type Props<T extends ComboboxOption> = {
    id: string;
    value: T | null;
    onChange: (option: T | null) => void;
    options: T[];
    /** Server search (debounced 300 ms). Without it, options are filtered locally. */
    onSearch?: (term: string) => void;
    loading?: boolean;
    name?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    /** Extra item at the bottom, e.g. "+ Create new household". */
    footer?: ReactNode;
    invalid?: boolean;
    describedBy?: string;
    disabled?: boolean;
};

/**
 * Searchable select (Popover + Command). Used for choosing a household
 * while registering a resident.
 */
export function Combobox<T extends ComboboxOption>({
    id,
    value,
    onChange,
    options,
    onSearch,
    loading = false,
    name,
    placeholder = 'Select…',
    searchPlaceholder = 'Type to search…',
    emptyText = 'No matches found.',
    footer,
    invalid = false,
    describedBy,
    disabled = false,
}: Props<T>) {
    const [open, setOpen] = useState(false);
    const [term, setTerm] = useState('');
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(
        () => () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    const search = (next: string) => {
        setTerm(next);

        if (!onSearch) {
            return;
        }

        if (timer.current) {
            clearTimeout(timer.current);
        }

        timer.current = setTimeout(() => onSearch(next), 300);
    };

    return (
        <>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        aria-invalid={invalid || undefined}
                        aria-describedby={describedBy}
                        disabled={disabled}
                        className={cn(
                            'h-11 w-full justify-between border-input bg-transparent font-normal',
                            !value && 'text-muted-foreground',
                        )}
                    >
                        <span className="truncate">
                            {value ? value.label : placeholder}
                        </span>
                        <ChevronsUpDown className="opacity-50" aria-hidden />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-(--radix-popover-trigger-width) min-w-72 p-0"
                    align="start"
                >
                    <Command shouldFilter={!onSearch}>
                        <CommandInput
                            value={term}
                            onValueChange={search}
                            placeholder={searchPlaceholder}
                        />
                        <CommandList>
                            {loading ? (
                                <div className="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
                                    <Spinner /> Searching…
                                </div>
                            ) : (
                                <CommandEmpty>{emptyText}</CommandEmpty>
                            )}
                            {!loading && options.length > 0 && (
                                <CommandGroup>
                                    {options.map((option) => (
                                        <CommandItem
                                            key={option.value}
                                            value={`${option.label} ${option.description ?? ''}`}
                                            onSelect={() => {
                                                onChange(option);
                                                setOpen(false);
                                            }}
                                        >
                                            <Check
                                                className={cn(
                                                    'size-4',
                                                    value?.value ===
                                                        option.value
                                                        ? 'opacity-100'
                                                        : 'opacity-0',
                                                )}
                                                aria-hidden
                                            />
                                            <div className="min-w-0">
                                                <div className="truncate">
                                                    {option.label}
                                                </div>
                                                {option.description && (
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        {option.description}
                                                    </div>
                                                )}
                                            </div>
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            )}
                        </CommandList>
                        {footer && <div className="border-t p-1">{footer}</div>}
                    </Command>
                </PopoverContent>
            </Popover>
            {name && (
                <input type="hidden" name={name} value={value?.value ?? ''} />
            )}
        </>
    );
}
