import { Head, Link, router } from '@inertiajs/react';
import {
    Baby,
    Bell,
    BellOff,
    CalendarDays,
    CheckCheck,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Package,
    Siren,
    Stethoscope,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { FilterChips } from '@/components/app/referrals/filter-chips';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, open } from '@/routes/notifications';
import type { BellNotification } from '@/types/auth';
import type { NotificationsIndexProps } from '@/types/pages/services';

const categoryIcon: Record<string, LucideIcon> = {
    outbreak: Siren,
    inventory: Package,
    maternal: Baby,
    appointments: CalendarDays,
    referral: Stethoscope,
    program: ClipboardList,
};

const levelClass: Record<BellNotification['level'], string> = {
    critical: 'bg-danger-soft text-destructive',
    warning: 'bg-warning-soft text-warning-soft-foreground',
    info: 'bg-info-soft text-info-soft-foreground',
};

const levelLabel: Record<BellNotification['level'], string> = {
    critical: 'Urgent',
    warning: 'Needs attention',
    info: 'For your information',
};

export default function NotificationsIndex({
    notifications,
    filters,
}: NotificationsIndexProps) {
    const visit = (params: { unread?: boolean; page?: number }) =>
        router.get(
            index().url,
            {
                ...(params.unread ? { unread: 1 } : {}),
                ...(params.page && params.page > 1
                    ? { page: params.page }
                    : {}),
            },
            { preserveScroll: true, preserveState: true },
        );
    const hasUnread = notifications.data.some((n) => !n.read_at);

    return (
        <>
            <Head title="Notifications" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Notifications"
                    description="Alerts and reminders for your role. Opening one marks it as read."
                    actions={
                        hasUnread && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        NotificationController.markAllRead()
                                            .url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <CheckCheck aria-hidden /> Mark all as read
                            </Button>
                        )
                    }
                />

                <FilterChips<'unread'>
                    label="Show"
                    value={filters.unread ? 'unread' : null}
                    onChange={(v) => visit({ unread: v === 'unread' })}
                    chips={[
                        { value: null, label: 'All' },
                        { value: 'unread', label: 'Unread' },
                    ]}
                />

                {notifications.data.length === 0 ? (
                    <EmptyState
                        icon={BellOff}
                        title={
                            filters.unread
                                ? 'No unread notifications'
                                : 'No notifications yet'
                        }
                        description="You're all caught up."
                    />
                ) : (
                    <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                        {notifications.data.map((n) => {
                            const Icon = categoryIcon[n.category] ?? Bell;

                            return (
                                <li key={n.id}>
                                    <Link
                                        href={open(n.id)}
                                        className={cn(
                                            'flex gap-4 px-4 py-4 hover:bg-accent focus-visible:bg-accent',
                                            !n.read_at && 'bg-secondary/50',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-full',
                                                levelClass[n.level],
                                            )}
                                        >
                                            <Icon
                                                aria-hidden
                                                className="size-5"
                                            />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-bold">
                                                {n.title}
                                                {!n.read_at && (
                                                    <span className="sr-only">
                                                        {' '}
                                                        (unread)
                                                    </span>
                                                )}
                                            </span>
                                            <span className="block text-muted-foreground">
                                                {n.message}
                                            </span>
                                            <span className="mt-1 block text-sm text-muted-foreground">
                                                {levelLabel[n.level]} ·{' '}
                                                <time
                                                    dateTime={
                                                        n.created_at ??
                                                        undefined
                                                    }
                                                    title={formatDateTime(
                                                        n.created_at,
                                                    )}
                                                >
                                                    {formatRelative(
                                                        n.created_at,
                                                    )}
                                                </time>
                                            </span>
                                        </span>
                                        {!n.read_at && (
                                            <span
                                                aria-hidden
                                                className="mt-2 size-2.5 shrink-0 rounded-full bg-primary"
                                            />
                                        )}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}

                {notifications.last_page > 1 && (
                    <nav
                        aria-label="Notification pages"
                        className="flex items-center justify-between gap-3"
                    >
                        <p className="text-sm text-muted-foreground">
                            Showing {notifications.from}–{notifications.to} of{' '}
                            {notifications.total}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                disabled={notifications.current_page <= 1}
                                onClick={() =>
                                    visit({
                                        unread: filters.unread,
                                        page: notifications.current_page - 1,
                                    })
                                }
                            >
                                <ChevronLeft aria-hidden /> Newer
                            </Button>
                            <Button
                                variant="outline"
                                disabled={
                                    notifications.current_page >=
                                    notifications.last_page
                                }
                                onClick={() =>
                                    visit({
                                        unread: filters.unread,
                                        page: notifications.current_page + 1,
                                    })
                                }
                            >
                                Older <ChevronRight aria-hidden />
                            </Button>
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

NotificationsIndex.layout = {
    breadcrumbs: [{ title: 'Notifications', href: index() }],
};
