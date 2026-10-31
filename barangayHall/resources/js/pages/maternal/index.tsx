import { Head, Link } from '@inertiajs/react';
import { Baby, CircleAlert, Stethoscope } from 'lucide-react';
import { contactStatus } from '@/components/app/contact-schedule';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { SearchFilters } from '@/components/app/search-filters';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/pregnancies';
import type { MaternalIndexPageProps } from '@/types/pages/health';

const VIEWS = [
    { value: 'all', label: 'All ongoing' },
    { value: 'overdue', label: 'Overdue contacts' },
    { value: 'due_soon', label: 'Due within 30 days' },
    { value: 'postnatal', label: 'Postnatal (6 weeks)' },
] as const;

export default function MaternalIndex({
    pregnancies,
    filters,
    options,
}: MaternalIndexPageProps) {
    const { query, update, reset, processing } = useTableQuery(filters, {
        only: ['pregnancies', 'filters'],
    });
    const postnatal = query.status === 'postnatal';
    const hasActiveFilters = Boolean(query.purok_id || query.risk_level);

    return (
        <>
            <Head title="Maternal tracker" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Maternal tracker"
                    description="Ongoing pregnancies with their due date, risk and next prenatal contact. Register a pregnancy from the woman's resident profile."
                />

                <div className="flex flex-col gap-3" data-print="hide">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={query.status ?? 'all'}
                        onValueChange={(v) =>
                            v &&
                            update({
                                status:
                                    v === 'all'
                                        ? null
                                        : (v as MaternalIndexPageProps['filters']['status']),
                            })
                        }
                        aria-label="Show"
                        className="flex-wrap"
                    >
                        {VIEWS.map((v) => (
                            <ToggleGroupItem
                                key={v.value}
                                value={v.value}
                                className="h-11 px-4"
                            >
                                {v.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <SearchFilters
                        filters={[
                            {
                                key: 'purok',
                                label: 'Purok',
                                allLabel: 'All puroks',
                                options: options.puroks,
                                value: query.purok_id,
                                onChange: (v) =>
                                    update({ purok_id: v ? Number(v) : null }),
                            },
                            {
                                key: 'risk',
                                label: 'Risk',
                                allLabel: 'Any risk',
                                options: options.riskLevels,
                                value: query.risk_level,
                                onChange: (v) => update({ risk_level: v }),
                            },
                        ]}
                        hasActiveFilters={hasActiveFilters}
                        onReset={() =>
                            reset({ purok_id: null, risk_level: null })
                        }
                    />
                </div>

                {pregnancies.length === 0 ? (
                    <EmptyState
                        icon={Baby}
                        title={
                            query.status === 'overdue'
                                ? 'No overdue prenatal or postnatal contacts'
                                : postnatal
                                  ? 'No deliveries in the last 6 weeks'
                                  : 'No pregnancies match'
                        }
                        description={
                            hasActiveFilters
                                ? 'Try another purok or risk level.'
                                : 'Pregnancies registered from resident profiles appear here.'
                        }
                        action={
                            hasActiveFilters && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        reset({
                                            purok_id: null,
                                            risk_level: null,
                                        })
                                    }
                                >
                                    Clear filters
                                </Button>
                            )
                        }
                    />
                ) : (
                    <div
                        className="overflow-x-auto rounded-xl border bg-card"
                        aria-busy={processing}
                    >
                        <Table>
                            <caption className="sr-only">
                                {postnatal
                                    ? 'Recent deliveries'
                                    : 'Ongoing pregnancies'}
                            </caption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Mother</TableHead>
                                    <TableHead>
                                        {postnatal ? 'Delivered' : 'AOG'}
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        EDD
                                    </TableHead>
                                    <TableHead>Risk</TableHead>
                                    <TableHead>Next contact</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pregnancies.map((p) => {
                                    const next = p.next_contact;

                                    return (
                                        <TableRow
                                            key={p.id}
                                            className={cn(
                                                p.overdue_contacts > 0 &&
                                                    'bg-danger-soft/40',
                                            )}
                                        >
                                            <TableCell>
                                                <Link
                                                    href={show(p.id)}
                                                    className="font-bold underline-offset-4 hover:underline"
                                                >
                                                    {p.resident?.full_name}
                                                </Link>
                                                <div className="text-xs text-muted-foreground">
                                                    {p.resident?.age} yrs ·{' '}
                                                    {p.resident?.purok} · G
                                                    {p.gravida}P{p.para}
                                                </div>
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {postnatal
                                                    ? formatDate(p.outcome_date)
                                                    : `${p.aog_weeks ?? '—'} weeks`}
                                            </TableCell>
                                            <TableCell className="hidden whitespace-nowrap md:table-cell">
                                                {formatDate(p.edd)}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    tone={
                                                        p.risk_level.value ===
                                                        'high'
                                                            ? 'danger'
                                                            : 'success'
                                                    }
                                                    icon={
                                                        p.risk_level.value ===
                                                        'high' ? (
                                                            <CircleAlert
                                                                aria-hidden
                                                            />
                                                        ) : undefined
                                                    }
                                                >
                                                    {p.risk_level.label}
                                                </StatusBadge>
                                            </TableCell>
                                            <TableCell>
                                                {next ? (
                                                    <div className="space-y-1">
                                                        <div className="text-sm">
                                                            {next.label}
                                                        </div>
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <StatusBadge
                                                                tone={
                                                                    contactStatus[
                                                                        next
                                                                            .status
                                                                    ].tone
                                                                }
                                                            >
                                                                {
                                                                    contactStatus[
                                                                        next
                                                                            .status
                                                                    ].label
                                                                }
                                                            </StatusBadge>
                                                            <span className="text-xs text-muted-foreground">
                                                                {formatDate(
                                                                    next.due_date,
                                                                )}
                                                            </span>
                                                            {p.overdue_contacts >
                                                                1 && (
                                                                <span className="text-xs font-bold text-destructive">
                                                                    {
                                                                        p.overdue_contacts
                                                                    }{' '}
                                                                    overdue
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        All contacts done
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Stethoscope aria-hidden className="size-4" />
                    Contacts follow the schedule in Settings (8 prenatal
                    contacts by default). Overdue = more than 14 days past the
                    due date without a visit.
                </p>
            </div>
        </>
    );
}

MaternalIndex.layout = {
    breadcrumbs: [{ title: 'Maternal tracker', href: index() }],
};
