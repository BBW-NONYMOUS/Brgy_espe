import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    AlertTriangle,
    DatabaseBackup,
    Download,
    ShieldCheck,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import BackupController from '@/actions/App/Http/Controllers/Admin/BackupController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import type { Tone } from '@/components/app/stat-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatBytes, formatDateTime, formatRelative } from '@/lib/format';
import { index } from '@/routes/backups';
import type { BackupEntry, Paginated } from '@/types';

type Props = {
    backups: Paginated<BackupEntry>;
    lastSuccessfulAt: string | null;
    schedule: {
        time: string;
        keepDaily: number;
        keepWeekly: number;
        keepManual: number;
    };
};

const statusTone: Record<BackupEntry['status']['value'], Tone | 'neutral'> = {
    running: 'info',
    success: 'success',
    failed: 'danger',
    pruned: 'neutral',
};

export default function BackupsIndex({
    backups,
    lastSuccessfulAt,
    schedule,
}: Props) {
    const [confirming, setConfirming] = useState(false);
    const [running, setRunning] = useState(false);
    const stale =
        !lastSuccessfulAt ||
        Date.now() - new Date(lastSuccessfulAt).getTime() > 2 * 86_400_000;

    const columns = useMemo<ColumnDef<BackupEntry, unknown>[]>(
        () => [
            {
                id: 'created',
                header: 'Date',
                meta: { cellClassName: 'whitespace-nowrap' },
                cell: ({ row }) => formatDateTime(row.original.created_at),
            },
            {
                id: 'type',
                header: 'Type',
                cell: ({ row }) => row.original.type.label,
            },
            {
                id: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <div className="space-y-1">
                        <StatusBadge
                            tone={statusTone[row.original.status.value]}
                        >
                            {row.original.status.label}
                        </StatusBadge>
                        {row.original.error && (
                            <p className="max-w-72 text-xs whitespace-normal text-destructive">
                                {row.original.error}
                            </p>
                        )}
                    </div>
                ),
            },
            {
                id: 'size',
                header: 'Size',
                meta: { hideOnMobile: true },
                cell: ({ row }) => formatBytes(row.original.size_bytes),
            },
            {
                id: 'by',
                header: 'By',
                meta: { hideOnMobile: true },
                cell: ({ row }) => row.original.created_by ?? 'Scheduler',
            },
            {
                id: 'download',
                header: () => <span className="sr-only">Download</span>,
                meta: { cellClassName: 'text-right' },
                cell: ({ row }) =>
                    row.original.downloadable && (
                        <Button variant="outline" size="sm" asChild>
                            {/* A normal link: the browser downloads the encrypted file. */}
                            <a
                                href={BackupController.download.url(
                                    row.original.id,
                                )}
                            >
                                <Download aria-hidden /> Download
                            </a>
                        </Button>
                    ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Backups" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Backups"
                    description="Encrypted copies of the database, made every night and on demand."
                    actions={
                        <Button onClick={() => setConfirming(true)}>
                            <DatabaseBackup aria-hidden /> Back up now
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    {stale ? (
                        <Alert variant="destructive" className="lg:col-span-2">
                            <AlertTriangle aria-hidden />
                            <AlertTitle>No recent backup</AlertTitle>
                            <AlertDescription>
                                {lastSuccessfulAt
                                    ? `The last successful backup was ${formatRelative(lastSuccessfulAt)}.`
                                    : 'No successful backup yet.'}{' '}
                                Check that the Windows Task Scheduler task is
                                running, or click “Back up now”.
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <Alert className="border-success/30 bg-success-soft text-success-soft-foreground lg:col-span-2">
                            <ShieldCheck aria-hidden />
                            <AlertTitle>Backups are up to date</AlertTitle>
                            <AlertDescription className="text-success-soft-foreground">
                                Last successful backup{' '}
                                {formatRelative(lastSuccessfulAt)} (
                                {formatDateTime(lastSuccessfulAt)}).
                            </AlertDescription>
                        </Alert>
                    )}
                    <Card className="gap-2 py-4">
                        <CardHeader className="px-4">
                            <CardTitle className="text-base">
                                Schedule
                            </CardTitle>
                            <CardDescription>
                                Every night at {schedule.time}. Keeps{' '}
                                {schedule.keepDaily} daily,{' '}
                                {schedule.keepWeekly} weekly and{' '}
                                {schedule.keepManual} manual backups.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-4 text-sm text-muted-foreground">
                            Restoring needs the APP_KEY from the .env file — see
                            docs/restore.md.
                        </CardContent>
                    </Card>
                </div>

                <DataTable
                    columns={columns}
                    data={backups}
                    onPageChange={(p) =>
                        router.get(
                            index.url(),
                            { page: p },
                            { preserveScroll: true, preserveState: true },
                        )
                    }
                    itemLabel="backups"
                    caption="Backup history"
                    empty={
                        <EmptyState
                            icon={DatabaseBackup}
                            title="No backups yet"
                            description="Click “Back up now” to create the first one."
                        />
                    }
                />
            </div>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Create a backup now?"
                description={
                    <p>
                        This takes a few seconds. You can keep working while it
                        runs.
                    </p>
                }
                confirmLabel="Back up now"
                processing={running}
                onConfirm={() =>
                    router.post(
                        BackupController.store.url(),
                        {},
                        {
                            onStart: () => setRunning(true),
                            onFinish: () => {
                                setRunning(false);
                                setConfirming(false);
                            },
                        },
                    )
                }
            />
        </>
    );
}

BackupsIndex.layout = {
    breadcrumbs: [{ title: 'Backups', href: index() }],
};
