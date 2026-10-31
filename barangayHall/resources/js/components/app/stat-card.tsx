import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ChevronRight } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

export type Tone = 'primary' | 'success' | 'warning' | 'info' | 'danger';

const toneClasses: Record<Tone, string> = {
    primary: 'bg-secondary text-secondary-foreground',
    success: 'bg-success-soft text-success-soft-foreground',
    warning: 'bg-warning-soft text-warning-soft-foreground',
    info: 'bg-info-soft text-info-soft-foreground',
    danger: 'bg-danger-soft text-danger-soft-foreground',
};

type Props = {
    label: string;
    value: number | null | undefined;
    icon: LucideIcon;
    tone?: Tone;
    /** Short explanation under the number, e.g. "aged 60 and above". */
    hint?: string;
    /** Makes the whole card a link (e.g. to the filtered list). */
    href?: NonNullable<InertiaLinkProps['href']>;
    className?: string;
};

/**
 * One headline number on the dashboard / reports page.
 */
export function StatCard({
    label,
    value,
    icon: Icon,
    tone = 'primary',
    hint,
    href,
    className,
}: Props) {
    const body = (
        <CardContent className="flex items-center gap-4 p-5">
            <span
                className={cn(
                    'flex size-12 shrink-0 items-center justify-center rounded-xl',
                    toneClasses[tone],
                )}
                aria-hidden
            >
                <Icon className="size-6" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="text-3xl leading-tight font-bold tabular-nums">
                    {formatNumber(value)}
                </p>
                {hint && (
                    <p className="line-clamp-2 text-xs text-muted-foreground">
                        {hint}
                    </p>
                )}
            </div>
            {href && (
                <ChevronRight
                    className="size-5 text-muted-foreground"
                    aria-hidden
                />
            )}
        </CardContent>
    );

    return (
        <Card
            className={cn(
                'gap-0 py-0',
                href &&
                    'transition-colors focus-within:ring-2 focus-within:ring-ring hover:bg-accent/50',
                className,
            )}
        >
            {href ? (
                <Link href={href} className="rounded-xl outline-none">
                    {body}
                </Link>
            ) : (
                body
            )}
        </Card>
    );
}

export function StatCardSkeleton({ className }: { className?: string }) {
    return (
        <Card className={cn('gap-0 py-0', className)} aria-hidden>
            <CardContent className="flex items-center gap-4 p-5">
                <Skeleton className="size-12 rounded-xl" />
                <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-8 w-16" />
                </div>
            </CardContent>
        </Card>
    );
}
