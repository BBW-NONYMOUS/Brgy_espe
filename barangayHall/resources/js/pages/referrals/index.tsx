import { Head, Link, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus, Send } from 'lucide-react';
import { useMemo, useState } from 'react';
import ReferralController from '@/actions/App/Http/Controllers/Health/ReferralController';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import {
    RadioField,
    SelectField,
    TextareaField,
} from '@/components/app/fields';
import { PageHeader } from '@/components/app/page-header';
import { FilterChips } from '@/components/app/referrals/filter-chips';
import {
    ReferralStatusBadge,
    UrgencyBadge,
} from '@/components/app/referrals/referral-badges';
import { ResidentPicker } from '@/components/app/referrals/resident-picker';
import type { PickedResident } from '@/components/app/referrals/resident-picker';
import { SearchFilters } from '@/components/app/search-filters';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatDateTime } from '@/lib/format';
import { index, show } from '@/routes/referrals';
import { show as residentShow } from '@/routes/residents';
import type { Referral, ReferralStatus } from '@/types/modules';
import type { ReferralsIndexProps } from '@/types/pages/services';

export default function ReferralsIndex({
    referrals,
    filters,
    options,
    residentOptions,
    canCreate,
}: ReferralsIndexProps) {
    const { query, update, setPage, reset, processing } = useTableQuery(
        filters,
        { only: ['referrals', 'filters'] },
    );
    const [creating, setCreating] = useState(false);

    const columns = useMemo<ColumnDef<Referral, unknown>[]>(
        () => [
            {
                id: 'referral_no',
                header: 'Referral',
                cell: ({ row }) => (
                    <Link
                        href={show(row.original.id)}
                        className="font-mono font-bold text-primary underline-offset-4 hover:underline"
                    >
                        {row.original.referral_no}
                    </Link>
                ),
            },
            {
                id: 'resident',
                header: 'Patient',
                cell: ({ row }) =>
                    row.original.resident && (
                        <div>
                            <Link
                                href={residentShow(row.original.resident.id)}
                                className="font-bold underline-offset-4 hover:underline"
                            >
                                {row.original.resident.full_name}
                            </Link>
                            <div className="text-sm text-muted-foreground">
                                {row.original.resident.age} yrs ·{' '}
                                {row.original.resident.sex.label}
                            </div>
                        </div>
                    ),
            },
            {
                id: 'facility',
                header: 'Referred to',
                meta: { hideOnMobile: true },
                cell: ({ row }) => row.original.facility?.name,
            },
            {
                id: 'urgency',
                header: 'Urgency',
                cell: ({ row }) => (
                    <UrgencyBadge urgency={row.original.urgency} />
                ),
            },
            {
                id: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <ReferralStatusBadge status={row.original.status} />
                ),
            },
            {
                id: 'referred_at',
                header: 'Referred',
                meta: { hideOnMobile: true },
                cell: ({ row }) => (
                    <div className="text-sm">
                        {formatDateTime(row.original.referred_at)}
                        <div className="text-muted-foreground">
                            {row.original.referred_by}
                        </div>
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Referrals" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Referrals"
                    description="Patients referred to the Rural Health Unit or a hospital, and where each referral stands."
                    actions={
                        canCreate && (
                            <Button onClick={() => setCreating(true)}>
                                <Plus aria-hidden /> New referral
                            </Button>
                        )
                    }
                />

                <FilterChips<ReferralStatus>
                    label="Filter by status"
                    value={query.status}
                    onChange={(status) => update({ status })}
                    chips={[
                        { value: null, label: 'All' },
                        ...options.statuses.map((s) => ({
                            value: s.value,
                            label: s.label,
                        })),
                    ]}
                />

                <SearchFilters
                    search={{
                        value: query.search,
                        onChange: (v) =>
                            update({ search: v }, { debounced: true }),
                        placeholder: 'Referral no. or patient name',
                        label: 'Search referrals',
                    }}
                    filters={[
                        {
                            key: 'urgency',
                            label: 'Urgency',
                            allLabel: 'Any urgency',
                            options: options.urgencies,
                            value: query.urgency,
                            onChange: (v) =>
                                update({
                                    urgency:
                                        v as ReferralsIndexProps['filters']['urgency'],
                                }),
                        },
                        {
                            key: 'facility',
                            label: 'Facility',
                            allLabel: 'All facilities',
                            options: options.facilities,
                            value: query.facility_id,
                            onChange: (v) =>
                                update({ facility_id: v ? Number(v) : null }),
                        },
                    ]}
                    hasActiveFilters={Boolean(
                        query.search ||
                        query.status ||
                        query.urgency ||
                        query.facility_id,
                    )}
                    onReset={() =>
                        reset({
                            search: '',
                            status: null,
                            urgency: null,
                            facility_id: null,
                        })
                    }
                />

                <DataTable
                    columns={columns}
                    data={referrals}
                    onPageChange={setPage}
                    rowHref={(r) => show(r.id).url}
                    itemLabel="referrals"
                    caption="Referrals"
                    processing={processing}
                    empty={
                        <EmptyState
                            icon={Send}
                            title="No referrals match"
                            description={
                                canCreate
                                    ? 'Create a referral when a patient needs care at the RHU or a hospital.'
                                    : undefined
                            }
                        />
                    }
                />
            </div>

            {creating && (
                <NewReferralSheet
                    options={options}
                    residentOptions={residentOptions}
                    onClose={() => setCreating(false)}
                />
            )}
        </>
    );
}

function NewReferralSheet({
    options,
    residentOptions,
    onClose,
}: {
    options: ReferralsIndexProps['options'];
    residentOptions?: ReferralsIndexProps['residentOptions'];
    onClose: () => void;
}) {
    const [resident, setResident] = useState<PickedResident | null>(null);
    const form = useForm({
        resident_id: '',
        facility_id: '',
        urgency: 'routine',
        reason: '',
    });

    return (
        <Sheet open onOpenChange={(o) => !o && onClose()}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <form
                    className="flex h-full flex-col"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.submit(ReferralController.store());
                    }}
                >
                    <SheetHeader>
                        <SheetTitle>New referral</SheetTitle>
                        <SheetDescription>
                            After saving, print the referral slip for the
                            patient to bring.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-1 flex-col gap-5 px-4">
                        <ResidentPicker
                            value={resident}
                            onChange={(r) => {
                                setResident(r);
                                form.setData(
                                    'resident_id',
                                    r ? String(r.value) : '',
                                );
                            }}
                            options={residentOptions}
                            error={form.errors.resident_id}
                        />
                        <SelectField
                            id="facility_id"
                            label="Refer to"
                            required
                            value={form.data.facility_id}
                            onChange={(v) => form.setData('facility_id', v)}
                            options={options.facilities.map((f) => ({
                                value: f.value,
                                label: `${f.label} (${f.type})`,
                            }))}
                            error={form.errors.facility_id}
                        />
                        <RadioField
                            id="urgency"
                            label="Urgency"
                            required
                            value={form.data.urgency}
                            onChange={(v) => form.setData('urgency', v)}
                            options={options.urgencies}
                            error={form.errors.urgency}
                        />
                        <TextareaField
                            id="reason"
                            label="Reason for referral"
                            required
                            rows={5}
                            value={form.data.reason}
                            onChange={(v) => form.setData('reason', v)}
                            error={form.errors.reason}
                            description="Findings and what the facility should do. This is printed on the slip."
                        />
                    </div>
                    <SheetFooter className="flex-row justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Create referral
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}

ReferralsIndex.layout = {
    breadcrumbs: [{ title: 'Referrals', href: index() }],
};
