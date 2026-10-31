import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCheck, Eye, ShieldCheck, Siren, XCircle } from 'lucide-react';
import { useState } from 'react';
import OutbreakAlertController from '@/actions/App/Http/Controllers/Surveillance/OutbreakAlertController';
import { EmptyState } from '@/components/app/empty-state';
import { TextareaField } from '@/components/app/fields';
import { FormDialog } from '@/components/app/maternal/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import type { BadgeTone } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as diseaseCases } from '@/routes/disease-cases';
import { index } from '@/routes/outbreak-alerts';
import type { OutbreakAlert } from '@/types';
import type { OutbreakAlertsPageProps } from '@/types/pages/health';

const statusTone: Record<string, BadgeTone> = {
    active: 'danger',
    monitoring: 'warning',
    closed: 'neutral',
};

export default function OutbreakAlerts({
    alerts,
    filters,
    options,
    canManage,
}: OutbreakAlertsPageProps) {
    const { can } = usePage().props;
    const [closing, setClosing] = useState<OutbreakAlert | null>(null);
    const [busy, setBusy] = useState<number | null>(null);

    const filter = (status: string) =>
        router.get(index().url, status ? { status } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    const act = (action: 'acknowledge' | 'monitor', alert: OutbreakAlert) =>
        router.post(
            OutbreakAlertController[action].url(alert.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(alert.id),
                onFinish: () => setBusy(null),
            },
        );

    return (
        <>
            <Head title="Outbreak alerts" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Outbreak alerts"
                    description="Raised automatically when cases of a disease reach the alert level set by the Admin. Counts only — no names."
                    actions={
                        can.viewDiseases && (
                            <Button asChild variant="outline">
                                <Link href={diseaseCases()}>
                                    <Eye aria-hidden /> View cases
                                </Link>
                            </Button>
                        )
                    }
                />

                <div data-print="hide">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={filters.status ?? 'all'}
                        onValueChange={(v) => v && filter(v === 'all' ? '' : v)}
                        aria-label="Show alerts"
                        className="flex-wrap"
                    >
                        <ToggleGroupItem value="all" className="h-11 px-4">
                            All
                        </ToggleGroupItem>
                        {options.statuses.map((s) => (
                            <ToggleGroupItem
                                key={s.value}
                                value={s.value}
                                className="h-11 px-4"
                            >
                                {s.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </div>

                {alerts.data.length === 0 ? (
                    <EmptyState
                        icon={ShieldCheck}
                        title={
                            filters.status
                                ? 'No alerts with this status'
                                : 'No outbreak alerts'
                        }
                        description="Alerts appear here when a disease passes its threshold in a purok or in the whole barangay."
                    />
                ) : (
                    <ul className="grid gap-4 lg:grid-cols-2">
                        {alerts.data.map((alert) => (
                            <li key={alert.id}>
                                <Card
                                    className={cn(
                                        'h-full',
                                        alert.status.value === 'active' &&
                                            'border-2 border-destructive',
                                    )}
                                >
                                    <CardHeader className="flex flex-row items-start justify-between gap-3">
                                        <div className="flex items-start gap-3">
                                            <Siren
                                                aria-hidden
                                                className={cn(
                                                    'mt-0.5 size-6 shrink-0',
                                                    alert.status.value ===
                                                        'closed'
                                                        ? 'text-muted-foreground'
                                                        : 'text-destructive',
                                                )}
                                            />
                                            <CardTitle className="text-lg">
                                                {alert.disease?.name} —{' '}
                                                {alert.purok?.name ??
                                                    'whole barangay'}
                                            </CardTitle>
                                        </div>
                                        <StatusBadge
                                            tone={
                                                statusTone[alert.status.value]
                                            }
                                        >
                                            {alert.status.label}
                                        </StatusBadge>
                                    </CardHeader>
                                    <CardContent className="space-y-2 text-sm">
                                        <p className="text-base">
                                            <span className="text-2xl font-bold">
                                                {alert.case_count}
                                            </span>{' '}
                                            cases between{' '}
                                            {formatDate(alert.period_start)} and{' '}
                                            {formatDate(alert.period_end)}
                                        </p>
                                        <p className="text-muted-foreground">
                                            Alert level:{' '}
                                            {alert.threshold.min_cases} or more
                                            cases within{' '}
                                            {alert.threshold.days_window} days ·
                                            raised{' '}
                                            {formatDateTime(alert.created_at)}
                                        </p>
                                        {alert.acknowledged_at && (
                                            <p>
                                                Acknowledged by{' '}
                                                {alert.acknowledged_by} on{' '}
                                                {formatDateTime(
                                                    alert.acknowledged_at,
                                                )}
                                            </p>
                                        )}
                                        {alert.closed_at && (
                                            <p>
                                                Closed by {alert.closed_by} on{' '}
                                                {formatDateTime(
                                                    alert.closed_at,
                                                )}
                                                {alert.closing_note &&
                                                    ` — “${alert.closing_note}”`}
                                            </p>
                                        )}
                                    </CardContent>
                                    {canManage &&
                                        alert.status.value !== 'closed' && (
                                            <CardFooter className="flex flex-wrap gap-2">
                                                {!alert.acknowledged_at && (
                                                    <Button
                                                        variant="outline"
                                                        disabled={
                                                            busy === alert.id
                                                        }
                                                        onClick={() =>
                                                            act(
                                                                'acknowledge',
                                                                alert,
                                                            )
                                                        }
                                                    >
                                                        <CheckCheck
                                                            aria-hidden
                                                        />{' '}
                                                        Acknowledge
                                                    </Button>
                                                )}
                                                {alert.status.value ===
                                                    'active' && (
                                                    <Button
                                                        variant="outline"
                                                        disabled={
                                                            busy === alert.id
                                                        }
                                                        onClick={() =>
                                                            act(
                                                                'monitor',
                                                                alert,
                                                            )
                                                        }
                                                    >
                                                        <Eye aria-hidden /> Move
                                                        to monitoring
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="outline"
                                                    onClick={() =>
                                                        setClosing(alert)
                                                    }
                                                >
                                                    <XCircle aria-hidden />{' '}
                                                    Close alert
                                                </Button>
                                            </CardFooter>
                                        )}
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}

                {alerts.meta.last_page > 1 && (
                    <nav
                        aria-label="Pages"
                        className="flex items-center justify-between gap-2"
                        data-print="hide"
                    >
                        <Button
                            variant="outline"
                            disabled={!alerts.links.prev}
                            onClick={() =>
                                alerts.links.prev &&
                                router.get(
                                    alerts.links.prev,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Previous
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            Page {alerts.meta.current_page} of{' '}
                            {alerts.meta.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={!alerts.links.next}
                            onClick={() =>
                                alerts.links.next &&
                                router.get(
                                    alerts.links.next,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Next
                        </Button>
                    </nav>
                )}
            </div>

            {closing && (
                <CloseAlertDialog
                    alert={closing}
                    onOpenChange={(open) => !open && setClosing(null)}
                />
            )}
        </>
    );
}

function CloseAlertDialog({
    alert,
    onOpenChange,
}: {
    alert: OutbreakAlert;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ closing_note: '' });

    return (
        <FormDialog
            open
            onOpenChange={onOpenChange}
            title={`Close the ${alert.disease?.name} alert?`}
            description="A closed alert never re-opens. If cases rise again, a new alert is raised."
            submitLabel="Close alert"
            destructive
            processing={form.processing}
            onSubmit={() =>
                form.submit(OutbreakAlertController.close(alert.id), {
                    preserveScroll: true,
                    onSuccess: () => onOpenChange(false),
                })
            }
        >
            <TextareaField
                id="closing_note"
                label="What was done?"
                required
                rows={3}
                value={form.data.closing_note}
                onChange={(v) => form.setData('closing_note', v)}
                error={form.errors.closing_note}
                description="e.g. Clean-up drive done; no new cases for 2 weeks."
            />
        </FormDialog>
    );
}

OutbreakAlerts.layout = {
    breadcrumbs: [{ title: 'Outbreak alerts', href: index() }],
};
