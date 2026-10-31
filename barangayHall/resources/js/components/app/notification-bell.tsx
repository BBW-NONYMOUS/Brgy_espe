import { Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    BellOff,
    CalendarDays,
    ClipboardList,
    Package,
    Siren,
    Stethoscope,
    Baby,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, open, readAll } from '@/routes/notifications';
import type { BellNotification } from '@/types';

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

/**
 * Header bell. The unread count is a small shared prop on every page; the
 * list itself is fetched only when the bell opens (partial reload of the
 * optional `notificationsLatest` prop — no extra API).
 */
export function NotificationBell() {
    const { unreadNotifications, notificationsLatest } = usePage().props;
    const [loading, setLoading] = useState(false);

    const load = (isOpen: boolean) => {
        if (!isOpen) {
            return;
        }

        setLoading(true);
        router.reload({
            only: ['notificationsLatest', 'unreadNotifications'],
            onFinish: () => setLoading(false),
        });
    };

    const label =
        unreadNotifications > 0
            ? `Notifications, ${unreadNotifications} unread`
            : 'Notifications';

    return (
        <Popover onOpenChange={load}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative size-11"
                    aria-label={label}
                >
                    <Bell aria-hidden className="size-5" />
                    {unreadNotifications > 0 && (
                        <span
                            aria-hidden
                            className="absolute top-1 right-1 flex min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-[0.6875rem] leading-5 font-bold text-destructive-foreground"
                        >
                            {unreadNotifications > 99
                                ? '99+'
                                : unreadNotifications}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="end"
                className="w-[min(24rem,calc(100vw-2rem))] p-0"
            >
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <h2 className="font-bold">Notifications</h2>
                    {unreadNotifications > 0 && (
                        <Button
                            variant="link"
                            size="sm"
                            className="h-auto p-0"
                            onClick={() =>
                                router.post(
                                    readAll().url,
                                    {},
                                    {
                                        preserveScroll: true,
                                        only: [
                                            'notificationsLatest',
                                            'unreadNotifications',
                                            'flash',
                                        ],
                                    },
                                )
                            }
                        >
                            Mark all as read
                        </Button>
                    )}
                </div>
                <div
                    className="max-h-[60vh] overflow-y-auto"
                    aria-busy={loading}
                >
                    {loading && !notificationsLatest ? (
                        <div className="flex flex-col gap-3 p-4">
                            {[0, 1, 2].map((i) => (
                                <Skeleton key={i} className="h-12 w-full" />
                            ))}
                        </div>
                    ) : notificationsLatest &&
                      notificationsLatest.length > 0 ? (
                        <ul className="divide-y">
                            {notificationsLatest.map((n) => {
                                const Icon = categoryIcon[n.category] ?? Bell;

                                return (
                                    <li key={n.id}>
                                        <Link
                                            href={open(n.id)}
                                            className={cn(
                                                'flex gap-3 px-4 py-3 hover:bg-accent focus-visible:bg-accent',
                                                !n.read_at && 'bg-secondary/50',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full',
                                                    levelClass[n.level],
                                                )}
                                            >
                                                <Icon
                                                    aria-hidden
                                                    className="size-4"
                                                />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-sm font-bold">
                                                    {n.title}
                                                    {!n.read_at && (
                                                        <span className="sr-only">
                                                            {' '}
                                                            (unread)
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="block text-sm text-muted-foreground">
                                                    {n.message}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {formatRelative(
                                                        n.created_at,
                                                    )}
                                                </span>
                                            </span>
                                            {!n.read_at && (
                                                <span
                                                    aria-hidden
                                                    className="mt-2 size-2 shrink-0 rounded-full bg-primary"
                                                />
                                            )}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    ) : (
                        <div className="flex flex-col items-center gap-2 px-4 py-8 text-center text-sm text-muted-foreground">
                            <BellOff aria-hidden className="size-6" />
                            You're all caught up.
                        </div>
                    )}
                </div>
                <div className="border-t p-2">
                    <Button asChild variant="ghost" className="w-full">
                        <Link href={index()}>See all notifications</Link>
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
