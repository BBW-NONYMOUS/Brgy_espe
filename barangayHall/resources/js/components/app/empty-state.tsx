import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { cn } from '@/lib/utils';

type Props = {
    icon: LucideIcon;
    title: string;
    description?: ReactNode;
    /** e.g. a "Register resident" or "Clear filters" button. */
    action?: ReactNode;
    className?: string;
};

/**
 * Helpful empty state: says what is missing and what to do next.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: Props) {
    return (
        <Empty className={cn('border border-dashed', className)}>
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <Icon aria-hidden />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                {description && (
                    <EmptyDescription>{description}</EmptyDescription>
                )}
            </EmptyHeader>
            {action && <EmptyContent>{action}</EmptyContent>}
        </Empty>
    );
}
