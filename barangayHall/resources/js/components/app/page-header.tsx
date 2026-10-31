import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    title: ReactNode;
    description?: ReactNode;
    /** Buttons on the right (stack under the title on phones). */
    actions?: ReactNode;
    /** Badges or meta shown next to the title. */
    meta?: ReactNode;
    back?: { href: NonNullable<InertiaLinkProps['href']>; label: string };
    className?: string;
};

/**
 * Top of every page: optional back link, title (the page's only <h1>),
 * description and primary actions.
 */
export function PageHeader({
    title,
    description,
    actions,
    meta,
    back,
    className,
}: Props) {
    return (
        <header className={cn('flex flex-col gap-3', className)}>
            {back && (
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="-ml-2 w-fit text-muted-foreground"
                    data-print="hide"
                >
                    <Link href={back.href}>
                        <ArrowLeft aria-hidden />
                        {back.label}
                    </Link>
                </Button>
            )}
            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-2xl font-bold tracking-tight">
                            {title}
                        </h1>
                        {meta}
                    </div>
                    {description && (
                        <p className="text-muted-foreground">{description}</p>
                    )}
                </div>
                {actions && (
                    <div
                        className="flex shrink-0 flex-wrap items-center gap-2"
                        data-print="hide"
                    >
                        {actions}
                    </div>
                )}
            </div>
        </header>
    );
}
