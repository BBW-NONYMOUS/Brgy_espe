import { Head, Link } from '@inertiajs/react';
import { Baby, CircleAlert } from 'lucide-react';
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
import { index, show } from '@/routes/child-health';
import type { ChildHealthIndexPageProps } from '@/types/pages/health';

const VIEWS = [
    { value: 'all', label: 'All under 5' },
    { value: 'overdue', label: 'Overdue vaccines' },
    { value: 'due', label: 'Vaccines due now' },
    { value: 'malnourished', label: 'Not normal nutrition' },
    { value: 'vitamin_a_due', label: 'Vitamin A due' },
] as const;

function ageLabel(months: number): string {
    return months < 24
        ? `${months} mo`
        : `${Math.floor(months / 12)} yr ${months % 12} mo`;
}

export default function ChildHealthIndex({
    childrenUnderFive: children,
    filters,
    options,
}: ChildHealthIndexPageProps) {
    const { query, update, reset, processing } = useTableQuery(filters, {
        only: ['childrenUnderFive', 'filters'],
    });
    const hasActiveFilters = Boolean(query.search || query.purok_id);

    return (
        <>
            <Head title="Child health" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Child health"
                    description="Children under five: immunization (DOH EPI), growth monitoring, vitamin A and deworming."
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
                                        : (v as ChildHealthIndexPageProps['filters']['status']),
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
                        search={{
                            value: query.search,
                            onChange: (value) =>
                                update({ search: value }, { debounced: true }),
                            placeholder: 'Child name or resident ID',
                            label: 'Search children',
                        }}
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
                        ]}
                        hasActiveFilters={hasActiveFilters}
                        onReset={() => reset({ search: '', purok_id: null })}
                    />
                </div>

                {children.length === 0 ? (
                    <EmptyState
                        icon={Baby}
                        title={
                            query.status || hasActiveFilters
                                ? 'No children match'
                                : 'No children under five'
                        }
                        description={
                            query.status === 'overdue'
                                ? 'Every child is up to date with their vaccines.'
                                : 'Children registered with a birthdate in the last five years appear here.'
                        }
                        action={
                            hasActiveFilters && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        reset({ search: '', purok_id: null })
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
                                Children under five
                            </caption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Child</TableHead>
                                    <TableHead>Age</TableHead>
                                    <TableHead>Immunization</TableHead>
                                    <TableHead>Nutrition</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Last weighed
                                    </TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Vitamin A
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {children.map((c) => (
                                    <TableRow
                                        key={c.id}
                                        className={cn(
                                            c.epi.overdue > 0 &&
                                                'bg-danger-soft/40',
                                        )}
                                    >
                                        <TableCell>
                                            <Link
                                                href={show(c.id)}
                                                className="font-bold underline-offset-4 hover:underline"
                                            >
                                                {c.full_name}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {c.resident_code} · {c.sex} ·{' '}
                                                {c.purok}
                                            </div>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {ageLabel(c.age_months)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="text-sm">
                                                {c.epi.given}/{c.epi.total}{' '}
                                                doses
                                            </div>
                                            <div className="flex flex-wrap gap-1">
                                                {c.epi.overdue > 0 && (
                                                    <StatusBadge
                                                        tone="danger"
                                                        icon={
                                                            <CircleAlert
                                                                aria-hidden
                                                            />
                                                        }
                                                    >
                                                        {c.epi.overdue} overdue
                                                    </StatusBadge>
                                                )}
                                                {c.epi.due > 0 && (
                                                    <StatusBadge tone="warning">
                                                        {c.epi.due} due
                                                    </StatusBadge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {c.nutrition ? (
                                                <StatusBadge
                                                    tone={
                                                        c.nutrition.value ===
                                                        'normal'
                                                            ? 'success'
                                                            : c.nutrition.value.startsWith(
                                                                    'severely',
                                                                )
                                                              ? 'danger'
                                                              : 'caution'
                                                    }
                                                >
                                                    {c.nutrition.label}
                                                </StatusBadge>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    Not weighed
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="hidden whitespace-nowrap md:table-cell">
                                            {c.last_weighed
                                                ? formatDate(c.last_weighed)
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            {c.vitamin_a_due ? (
                                                <StatusBadge tone="warning">
                                                    Due
                                                </StatusBadge>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    Up to date
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

ChildHealthIndex.layout = {
    breadcrumbs: [{ title: 'Child health', href: index() }],
};
