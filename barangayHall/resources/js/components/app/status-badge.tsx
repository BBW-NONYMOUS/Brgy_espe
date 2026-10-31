import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { Tone } from '@/components/app/stat-card';

export type BadgeTone = Tone | 'caution' | 'neutral';

const toneClasses: Record<BadgeTone, string> = {
    primary: 'border-primary/20 bg-secondary text-secondary-foreground',
    success: 'border-success/25 bg-success-soft text-success-soft-foreground',
    warning: 'border-warning/40 bg-warning-soft text-warning-soft-foreground',
    info: 'border-info/25 bg-info-soft text-info-soft-foreground',
    danger: 'border-destructive/25 bg-danger-soft text-danger-soft-foreground',
    caution: 'border-caution/35 bg-caution-soft text-caution-soft-foreground',
    neutral: 'border-border bg-muted text-muted-foreground',
};

type Props = {
    tone?: BadgeTone;
    icon?: ReactNode;
    children: ReactNode;
    className?: string;
    title?: string;
};

/**
 * Soft-colored pill for flags and statuses. Color is never the only cue:
 * every badge also has a text label (WCAG 1.4.1).
 */
export function StatusBadge({
    tone = 'neutral',
    icon,
    children,
    className,
    title,
}: Props) {
    return (
        <span
            title={title}
            className={cn(
                'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-bold whitespace-nowrap [&_svg]:size-3.5',
                toneClasses[tone],
                className,
            )}
        >
            {icon}
            {children}
        </span>
    );
}
