import { Check, X } from 'lucide-react';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

export type Step = {
    key: string;
    label: string;
    /** When this step was reached (null = not yet). */
    at?: string | null;
    by?: string | null;
};

type Props = {
    steps: Step[];
    /** Key of the current step. */
    current: string;
    /** The process was stopped (e.g. a cancelled referral). */
    cancelled?: { label: string; at?: string | null } | null;
    className?: string;
};

/**
 * Horizontal progress through a fixed sequence (referral: pending → sent →
 * received → completed). An ordered list for screen readers; the current
 * step is marked with aria-current="step". Wraps to a vertical list on phones.
 */
export function StatusStepper({
    steps,
    current,
    cancelled = null,
    className,
}: Props) {
    const currentIndex = steps.findIndex((s) => s.key === current);

    return (
        <ol
            aria-label="Progress"
            className={cn(
                'flex flex-col gap-3 sm:flex-row sm:gap-0',
                className,
            )}
        >
            {steps.map((step, i) => {
                // Steps up to the current one were reached; a cancelled process
                // still reached its current step, and the last step is done.
                const done =
                    i < currentIndex ||
                    (i === currentIndex &&
                        (cancelled !== null || i === steps.length - 1));
                const isCurrent = i === currentIndex && !cancelled;
                const stopped = cancelled !== null && i > currentIndex;

                return (
                    <li
                        key={step.key}
                        aria-current={isCurrent ? 'step' : undefined}
                        className="flex flex-1 items-start gap-3 sm:flex-col sm:items-center sm:text-center"
                    >
                        <div className="flex shrink-0 items-center sm:w-full">
                            <div
                                className={cn(
                                    'hidden h-0.5 flex-1 sm:block',
                                    i === 0
                                        ? 'invisible'
                                        : i <= currentIndex
                                          ? 'bg-primary'
                                          : 'bg-border',
                                )}
                            />
                            <span
                                className={cn(
                                    'flex size-9 shrink-0 items-center justify-center rounded-full border-2 text-sm font-bold',
                                    done &&
                                        'border-primary bg-primary text-primary-foreground',
                                    isCurrent &&
                                        !done &&
                                        'border-primary bg-secondary text-secondary-foreground',
                                    !done &&
                                        !isCurrent &&
                                        'border-border bg-card text-muted-foreground',
                                    stopped && 'opacity-60',
                                )}
                            >
                                {done ? (
                                    <Check aria-hidden className="size-4" />
                                ) : (
                                    i + 1
                                )}
                            </span>
                            <div
                                className={cn(
                                    'hidden h-0.5 flex-1 sm:block',
                                    i === steps.length - 1
                                        ? 'invisible'
                                        : i < currentIndex
                                          ? 'bg-primary'
                                          : 'bg-border',
                                )}
                            />
                        </div>
                        <div className="text-sm sm:mt-2">
                            <p
                                className={cn(
                                    'font-bold',
                                    !done &&
                                        !isCurrent &&
                                        'text-muted-foreground',
                                )}
                            >
                                {step.label}
                                {isCurrent && (
                                    <span className="sr-only"> (current)</span>
                                )}
                            </p>
                            {step.at && (
                                <p className="text-xs text-muted-foreground">
                                    {formatDateTime(step.at)}
                                    {step.by ? ` · ${step.by}` : ''}
                                </p>
                            )}
                        </div>
                    </li>
                );
            })}
            {cancelled && (
                <li className="flex items-start gap-3 sm:flex-col sm:items-center sm:text-center">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full border-2 border-destructive bg-danger-soft text-destructive">
                        <X aria-hidden className="size-4" />
                    </span>
                    <div className="text-sm sm:mt-2">
                        <p className="font-bold text-destructive">
                            {cancelled.label}
                        </p>
                        {cancelled.at && (
                            <p className="text-xs text-muted-foreground">
                                {formatDateTime(cancelled.at)}
                            </p>
                        )}
                    </div>
                </li>
            )}
        </ol>
    );
}
