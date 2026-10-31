import { cn } from '@/lib/utils';

export type Chip<T extends string> = {
    value: T | null;
    label: string;
    count?: number;
};

/**
 * One-tap quick filters ("All · Pending · Sent …"). A radio-like group of
 * toggle buttons with aria-pressed; 44 px tall for tablets.
 */
export function FilterChips<T extends string>({
    label,
    chips,
    value,
    onChange,
    className,
}: {
    label: string;
    chips: Chip<T>[];
    value: T | null;
    onChange: (value: T | null) => void;
    className?: string;
}) {
    return (
        <div
            role="group"
            aria-label={label}
            className={cn('flex flex-wrap gap-2', className)}
        >
            {chips.map((chip) => {
                const active = chip.value === value;

                return (
                    <button
                        key={chip.value ?? 'all'}
                        type="button"
                        aria-pressed={active}
                        onClick={() => onChange(chip.value)}
                        className={cn(
                            'inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-bold transition-colors',
                            active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-card text-foreground hover:bg-accent',
                        )}
                    >
                        {chip.label}
                        {chip.count !== undefined && (
                            <span
                                className={cn(
                                    'rounded-full px-1.5 text-xs',
                                    active
                                        ? 'bg-primary-foreground/20'
                                        : 'bg-muted',
                                )}
                            >
                                {chip.count}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
