import { Head, Link, useForm } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useState } from 'react';
import ReferralController from '@/actions/App/Http/Controllers/Health/ReferralController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { TextareaField } from '@/components/app/fields';
import { VitalChips } from '@/components/app/health-timeline';
import { MedicalAlertBanner } from '@/components/app/medical-alert-banner';
import { PageHeader } from '@/components/app/page-header';
import {
    ReferralStatusBadge,
    UrgencyBadge,
} from '@/components/app/referrals/referral-badges';
import { StatusStepper } from '@/components/app/status-stepper';
import type { Step } from '@/components/app/status-stepper';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatDateTime } from '@/lib/format';
import { index, show, slip } from '@/routes/referrals';
import { show as residentShow } from '@/routes/residents';
import type { EnumValue } from '@/types/models';
import type { ReferralStatus } from '@/types/modules';
import type { ReferralShowProps } from '@/types/pages/services';

const FLOW: { key: ReferralStatus; label: string }[] = [
    { key: 'pending', label: 'Pending' },
    { key: 'sent', label: 'Sent' },
    { key: 'received', label: 'Received' },
    { key: 'completed', label: 'Completed' },
];

export default function ReferralShow({
    referral,
    criticalAlerts,
    visit,
    nextStatuses,
}: ReferralShowProps) {
    const [target, setTarget] = useState<EnumValue<ReferralStatus> | null>(
        null,
    );
    const history = referral.history ?? [];
    const reached = (key: ReferralStatus) =>
        history.find((h) => h.to.value === key);
    const cancelled =
        referral.status.value === 'cancelled'
            ? reached('cancelled')
            : undefined;
    // The furthest step reached before any cancellation.
    const current = cancelled
        ? (history.filter((h) => h.to.value !== 'cancelled').at(-1)?.to.value ??
          'pending')
        : referral.status.value;

    const steps: Step[] = FLOW.map((s) => ({
        key: s.key,
        label: s.label,
        at: reached(s.key)?.at ?? null,
        by: reached(s.key)?.by ?? null,
    }));

    return (
        <>
            <Head title={`Referral ${referral.referral_no}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <MedicalAlertBanner alerts={criticalAlerts} />

                <PageHeader
                    back={{ href: index(), label: 'All referrals' }}
                    title={
                        <span className="font-mono">
                            {referral.referral_no}
                        </span>
                    }
                    meta={
                        <>
                            <UrgencyBadge urgency={referral.urgency} />
                            <ReferralStatusBadge status={referral.status} />
                        </>
                    }
                    description={`To ${referral.facility?.name ?? '—'} · referred ${formatDateTime(referral.referred_at)} by ${referral.referred_by ?? '—'}`}
                    actions={
                        // A PDF opened in a new tab (printing from the browser), not an Inertia visit.
                        <Button variant="outline" asChild>
                            <a
                                href={slip.url(referral.id)}
                                target="_blank"
                                rel="noopener"
                            >
                                <Printer aria-hidden /> Print referral slip
                                <span className="sr-only">
                                    {' '}
                                    (opens a PDF in a new tab)
                                </span>
                            </a>
                        </Button>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Progress</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-5">
                        <StatusStepper
                            steps={steps}
                            current={current}
                            cancelled={
                                cancelled
                                    ? { label: 'Cancelled', at: cancelled.at }
                                    : null
                            }
                        />
                        {nextStatuses.length > 0 && (
                            <div className="flex flex-wrap gap-2 border-t pt-4">
                                {nextStatuses.map((s) => (
                                    <Button
                                        key={s.value}
                                        variant={
                                            s.value === 'cancelled'
                                                ? 'outline'
                                                : 'default'
                                        }
                                        onClick={() => setTarget(s)}
                                    >
                                        {s.value === 'cancelled'
                                            ? 'Cancel referral'
                                            : `Mark as ${s.label.toLowerCase()}`}
                                    </Button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-5 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Patient</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {referral.resident && (
                                <dl className="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-2">
                                    <dt className="text-muted-foreground">
                                        Name
                                    </dt>
                                    <dd>
                                        <Link
                                            href={residentShow(
                                                referral.resident.id,
                                            )}
                                            className="font-bold text-primary underline-offset-4 hover:underline"
                                        >
                                            {referral.resident.full_name}
                                        </Link>
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        Resident ID
                                    </dt>
                                    <dd className="font-mono">
                                        {referral.resident.resident_code}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        Age / sex
                                    </dt>
                                    <dd>
                                        {referral.resident.age} yrs ·{' '}
                                        {referral.resident.sex.label}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        Facility
                                    </dt>
                                    <dd>
                                        {referral.facility?.name} (
                                        {referral.facility?.type.label})
                                    </dd>
                                </dl>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Reason for referral</CardTitle>
                        </CardHeader>
                        <CardContent className="whitespace-pre-line">
                            {referral.reason ?? (
                                <span className="text-muted-foreground">
                                    Hidden for your role.
                                </span>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Linked visit</CardTitle>
                            <CardDescription>
                                {visit
                                    ? `${visit.visit_type?.name ?? 'Visit'} on ${formatDate(visit.visit_date)}${visit.attended_by ? ` · ${visit.attended_by}` : ''}`
                                    : 'This referral was not made from a recorded visit.'}
                            </CardDescription>
                        </CardHeader>
                        {visit && (
                            <CardContent className="flex flex-col gap-3">
                                {visit.vitals ? (
                                    <VitalChips vitals={visit.vitals} />
                                ) : (
                                    <p className="text-muted-foreground">
                                        No vital signs recorded.
                                    </p>
                                )}
                                {visit.diagnosis && (
                                    <p>
                                        <span className="font-bold">
                                            Assessment:
                                        </span>{' '}
                                        {visit.diagnosis}
                                    </p>
                                )}
                            </CardContent>
                        )}
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Return slip / feedback</CardTitle>
                            <CardDescription>
                                What the facility reported back.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="whitespace-pre-line">
                            {referral.feedback_note ?? (
                                <span className="text-muted-foreground">
                                    No feedback yet.
                                </span>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {history.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Status history</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ol className="flex flex-col gap-2">
                                {history.map((h, i) => (
                                    <li key={i} className="text-sm">
                                        <span className="font-bold">
                                            {h.to.label}
                                        </span>{' '}
                                        · {formatDateTime(h.at)} · {h.by}
                                        {h.note && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                — {h.note}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        </CardContent>
                    </Card>
                )}
            </div>

            {target?.value === 'cancelled' && (
                <CancelDialog
                    referralId={referral.id}
                    onClose={() => setTarget(null)}
                />
            )}
            {target && target.value !== 'cancelled' && (
                <StatusDialog
                    referralId={referral.id}
                    status={target}
                    onClose={() => setTarget(null)}
                />
            )}
        </>
    );
}

function StatusDialog({
    referralId,
    status,
    onClose,
}: {
    referralId: number;
    status: EnumValue<ReferralStatus>;
    onClose: () => void;
}) {
    const completing = status.value === 'completed';
    const form = useForm({ status: status.value, note: '', feedback_note: '' });

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <form
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.submit(
                            ReferralController.updateStatus(referralId),
                            { preserveScroll: true, onSuccess: onClose },
                        );
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            Mark as {status.label.toLowerCase()}
                        </DialogTitle>
                        <DialogDescription>
                            {completing
                                ? 'Record what the facility reported on the return slip.'
                                : 'Add a note if useful (e.g. who received the patient).'}
                        </DialogDescription>
                    </DialogHeader>
                    {completing && (
                        <TextareaField
                            id="feedback_note"
                            label="Return slip / feedback"
                            rows={5}
                            value={form.data.feedback_note}
                            onChange={(v) => form.setData('feedback_note', v)}
                            error={form.errors.feedback_note}
                        />
                    )}
                    <TextareaField
                        id="note"
                        label="Note (optional)"
                        rows={2}
                        value={form.data.note}
                        onChange={(v) => form.setData('note', v)}
                        error={form.errors.note ?? form.errors.status}
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CancelDialog({
    referralId,
    onClose,
}: {
    referralId: number;
    onClose: () => void;
}) {
    const form = useForm({ status: 'cancelled', note: '' });

    return (
        <ConfirmDialog
            open
            onOpenChange={(o) => !o && onClose()}
            title="Cancel this referral?"
            description={
                <p>
                    The referral stays on record as cancelled. This cannot be
                    undone.
                </p>
            }
            confirmLabel="Cancel referral"
            cancelLabel="Keep referral"
            destructive
            processing={form.processing}
            onConfirm={() =>
                form.submit(ReferralController.updateStatus(referralId), {
                    preserveScroll: true,
                    onSuccess: onClose,
                })
            }
        >
            <TextareaField
                id="cancel-note"
                label="Reason (optional)"
                rows={2}
                value={form.data.note}
                onChange={(v) => form.setData('note', v)}
                error={form.errors.note ?? form.errors.status}
            />
        </ConfirmDialog>
    );
}

ReferralShow.layout = (props: ReferralShowProps) => ({
    breadcrumbs: [
        { title: 'Referrals', href: index() },
        { title: props.referral.referral_no, href: show(props.referral.id) },
    ],
});
