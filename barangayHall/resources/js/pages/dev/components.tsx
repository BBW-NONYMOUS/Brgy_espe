import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Baby,
    HeartPulse,
    Home,
    MoreHorizontal,
    Plus,
    Syringe,
    UserRound,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { AgeSexPyramid, PurokChart } from '@/components/app/charts';
import { Combobox } from '@/components/app/combobox';
import type { ComboboxOption } from '@/components/app/combobox';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable, DataTableSkeleton } from '@/components/app/data-table';
import { DatePicker } from '@/components/app/date-picker';
import { EmptyState } from '@/components/app/empty-state';
import { describedBy, FormField } from '@/components/app/form-field';
import { HealthTimeline } from '@/components/app/health-timeline';
import { PageHeader } from '@/components/app/page-header';
import { ResidentBadges } from '@/components/app/resident-badges';
import { SearchFilters } from '@/components/app/search-filters';
import { SensitiveValue } from '@/components/app/sensitive-value';
import { StatCard, StatCardSkeleton } from '@/components/app/stat-card';
import { StatusBadge } from '@/components/app/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { formatAge, formatDate } from '@/lib/format';
import type {
    AgeSexRow,
    HealthVisit,
    Paginated,
    PurokPopulation,
    Resident,
} from '@/types';

/*
 * Design-system gallery (local development only — see routes/web.php).
 * Every component rendered here with realistic sample data.
 */

const sampleResident: Resident = {
    id: 1,
    resident_code: 'KII-2026-00005',
    last_name: 'Villanueva',
    first_name: 'Lourdes',
    middle_name: 'Pascual',
    suffix: null,
    full_name: 'Villanueva, Lourdes Pascual',
    sex: { value: 'female', label: 'Female' },
    birthdate: '1945-09-30',
    age: 81,
    birthplace: 'Isulan, Sultan Kudarat',
    civil_status: { value: 'widowed', label: 'Widowed' },
    contact_no: null,
    philhealth_no_masked: '••-•••••••78-3',
    philhealth_no: '02-000456789-3',
    is_pwd: true,
    is_4ps: false,
    is_senior: true,
    is_pregnant: false,
    relationship_to_head: { value: 'in_law', label: 'In-law' },
    status: { value: 'active', label: 'Active' },
    archive_reason: null,
    archived_at: null,
    household: {
        id: 1,
        household_no: 'HH-0001',
        is_4ps: false,
        is_geotagged: true,
        water_source_id: null,
        toilet_type_id: null,
        address: 'Near Kalawag II Elementary School',
        purok: { id: 1, name: 'Purok 1' },
    },
    created_at: null,
    updated_at: null,
};

const residents: Resident[] = [
    sampleResident,
    {
        ...sampleResident,
        id: 2,
        resident_code: 'KII-2026-00009',
        last_name: 'Gallardo',
        first_name: 'Kristine Mae',
        middle_name: 'Lopez',
        full_name: 'Gallardo, Kristine Mae Lopez',
        birthdate: '1995-08-03',
        age: 31,
        civil_status: { value: 'married', label: 'Married' },
        is_pwd: false,
        is_senior: false,
        is_pregnant: true,
        is_4ps: true,
        household: {
            id: 2,
            household_no: 'HH-0002',
            is_4ps: false,
            is_geotagged: true,
            water_source_id: null,
            toilet_type_id: null,
            address: 'Along National Highway',
            purok: { id: 1, name: 'Purok 1' },
        },
    },
    {
        ...sampleResident,
        id: 3,
        resident_code: 'KII-2026-00010',
        last_name: 'Gallardo',
        first_name: 'Ethan',
        middle_name: 'Lopez',
        full_name: 'Gallardo, Ethan Lopez',
        sex: { value: 'male', label: 'Male' },
        birthdate: new Date(Date.now() - 120 * 86_400_000)
            .toISOString()
            .slice(0, 10),
        age: 0,
        civil_status: { value: 'single', label: 'Single' },
        is_pwd: false,
        is_senior: false,
        household: {
            id: 2,
            household_no: 'HH-0002',
            is_4ps: false,
            is_geotagged: true,
            water_source_id: null,
            toilet_type_id: null,
            address: 'Along National Highway',
            purok: { id: 1, name: 'Purok 1' },
        },
    },
    {
        ...sampleResident,
        id: 4,
        resident_code: 'KII-2026-00026',
        last_name: 'Lumabao',
        first_name: 'Corazon',
        middle_name: 'Medina',
        full_name: 'Lumabao, Corazon Medina',
        status: { value: 'deceased', label: 'Deceased' },
        archived_at: '2026-08-14T08:00:00+08:00',
        is_pwd: false,
        household: {
            id: 6,
            household_no: 'HH-0006',
            is_4ps: false,
            is_geotagged: true,
            water_source_id: null,
            toilet_type_id: null,
            address: 'Near the rice mill',
            purok: { id: 3, name: 'Purok 3' },
        },
    },
];

const page: Paginated<Resident> = {
    data: residents,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
        current_page: 1,
        from: 1,
        last_page: 4,
        per_page: 15,
        to: 4,
        total: 51,
        path: '',
        links: [],
    },
};

const columns: ColumnDef<Resident, unknown>[] = [
    {
        accessorKey: 'last_name',
        header: 'Name',
        meta: { sortKey: 'last_name' },
        cell: ({ row }) => (
            <div className="space-y-1">
                <a href="#" className="font-bold hover:underline">
                    {row.original.full_name}
                </a>
                <ResidentBadges resident={row.original} />
            </div>
        ),
    },
    {
        accessorKey: 'resident_code',
        header: 'Resident ID',
        meta: { sortKey: 'resident_code', cellClassName: 'font-mono' },
    },
    {
        accessorKey: 'birthdate',
        header: 'Age',
        meta: { sortKey: 'birthdate' },
        cell: ({ row }) => formatAge(row.original.age, row.original.birthdate),
    },
    {
        id: 'purok',
        header: 'Purok',
        meta: { hideOnMobile: true },
        cell: ({ row }) => row.original.household?.purok?.name,
    },
    {
        id: 'actions',
        header: () => <span className="sr-only">Actions</span>,
        cell: () => (
            <Button variant="ghost" size="icon" aria-label="Row actions">
                <MoreHorizontal aria-hidden />
            </Button>
        ),
    },
];

const visits: HealthVisit[] = [
    {
        id: 1,
        visit_date: '2026-10-21',
        visit_type: { id: 2, name: 'Follow-up' },
        attended_by: 'Cristina Gelito, RM',
        restricted: false,
        chief_complaint: 'Maintenance check-up',
        diagnosis: 'Essential hypertension',
        notes: 'Advised low-salt diet. Continue amlodipine 5 mg once daily.',
        vitals: {
            bp_systolic: 152,
            bp_diastolic: 94,
            weight_kg: 54.2,
            height_cm: 151,
            temperature_c: 36.6,
            pulse_rate: 82,
            respiratory_rate: 18,
            bmi: 23.8,
        },
        maternal: null,
        created_at: null,
    },
    {
        id: 2,
        visit_date: '2026-08-30',
        visit_type: { id: 3, name: 'Prenatal Check-up' },
        attended_by: 'Cristina Gelito, RM',
        restricted: true,
        vitals: {
            bp_systolic: 110,
            bp_diastolic: 70,
            weight_kg: 58,
            height_cm: 155,
            temperature_c: 36.8,
            pulse_rate: 80,
            respiratory_rate: 18,
            bmi: 24.1,
        },
        maternal: {
            pregnancy_id: 1,
            kind: { value: 'prenatal', label: 'Prenatal' },
            fundal_height_cm: 20,
            fetal_heart_rate: 142,
            remarks: null,
        },
        created_at: null,
    },
    {
        id: 3,
        visit_date: '2025-12-02',
        visit_type: { id: 1, name: 'General Consultation' },
        attended_by: 'Analiza Sumagaysay',
        restricted: false,
        chief_complaint: 'Fever for 2 days',
        diagnosis: 'Acute upper respiratory infection',
        notes: null,
        vitals: {
            bp_systolic: 118,
            bp_diastolic: 76,
            weight_kg: 54,
            height_cm: 151,
            temperature_c: 38.2,
            pulse_rate: 96,
            respiratory_rate: 20,
            bmi: 23.7,
        },
        maternal: null,
        created_at: null,
    },
];

const purokData: PurokPopulation[] = [1, 2, 3, 4, 5, 6, 7].map((n) => ({
    purok: `Purok ${n}`,
    households: 2 + (n % 3),
    residents: 6 + ((n * 7) % 9),
    male: 3 + ((n * 3) % 5),
    female: 3 + ((n * 5) % 6),
}));

const ageSex: AgeSexRow[] = [
    ['0–11 months', 2, 2],
    ['1–4', 1, 1],
    ['5–9', 1, 0],
    ['10–14', 2, 2],
    ['15–19', 0, 1],
    ['20–29', 3, 4],
    ['30–39', 3, 3],
    ['40–49', 2, 3],
    ['50–59', 2, 2],
    ['60–69', 2, 2],
    ['70–79', 1, 2],
    ['80+', 1, 1],
].map(([age_group, male, female]) => ({
    age_group: String(age_group),
    male: Number(male),
    female: Number(female),
    total: Number(male) + Number(female),
}));

const households: (ComboboxOption & { purok: string })[] = [
    {
        value: 1,
        label: 'HH-0001 — Dela Cruz, Rodrigo Santos',
        description: 'Purok 1 · Near Kalawag II Elementary School',
        purok: 'Purok 1',
    },
    {
        value: 2,
        label: 'HH-0002 — Gallardo, Ernesto Tolentino',
        description: 'Purok 1 · Along National Highway',
        purok: 'Purok 1',
    },
    {
        value: 3,
        label: 'HH-0003 — Abdulrahman, Mohammad Kusain',
        description: 'Purok 2 · Near the Masjid',
        purok: 'Purok 2',
    },
];

export default function ComponentGallery() {
    const [search, setSearch] = useState('');
    const [purok, setPurok] = useState<string | null>(null);
    const [birthdate, setBirthdate] = useState<string | null>('1990-05-12');
    const [household, setHousehold] = useState<
        (typeof households)[number] | null
    >(null);
    const [archiveOpen, setArchiveOpen] = useState(false);

    return (
        <>
            <Head title="Component gallery" />
            <div className="flex flex-1 flex-col gap-10 p-4 md:p-6">
                <PageHeader
                    title="Component gallery"
                    description="Health center theme and the reusable components used by every page (local development only)."
                    meta={<StatusBadge tone="info">Phase 4</StatusBadge>}
                    actions={
                        <Button>
                            <Plus aria-hidden /> Primary action
                        </Button>
                    }
                />

                <Section title="Colors & badges">
                    <div className="flex flex-wrap gap-2">
                        <Button>Primary</Button>
                        <Button variant="secondary">Secondary</Button>
                        <Button variant="outline">Outline</Button>
                        <Button variant="ghost">Ghost</Button>
                        <Button variant="destructive">Archive</Button>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <ResidentBadges resident={residents[0]} />
                        <ResidentBadges resident={residents[1]} />
                        <ResidentBadges resident={residents[2]} />
                        <ResidentBadges resident={residents[3]} />
                        <StatusBadge tone="danger">Overdue</StatusBadge>
                        <StatusBadge tone="success">Given</StatusBadge>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2">
                        <Alert>
                            <AlertTitle>Possible duplicate</AlertTitle>
                            <AlertDescription>
                                Gallardo, Ramon Abad (KII-2026-00008, born Apr
                                27, 1990) is already registered in HH-0002.
                            </AlertDescription>
                        </Alert>
                        <Alert variant="destructive">
                            <AlertTitle>Backup failed</AlertTitle>
                            <AlertDescription>
                                mysqldump: Access denied for user
                                &apos;root&apos;.
                            </AlertDescription>
                        </Alert>
                    </div>
                </Section>

                <Section title="StatCard">
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <StatCard
                            label="Residents"
                            value={1250}
                            icon={Users}
                            href="#"
                        />
                        <StatCard
                            label="Households"
                            value={312}
                            icon={Home}
                            tone="info"
                        />
                        <StatCard
                            label="Senior citizens"
                            value={148}
                            icon={UserRound}
                            tone="info"
                            hint="Aged 60 and above"
                        />
                        <StatCard
                            label="Pregnant women"
                            value={17}
                            icon={HeartPulse}
                            tone="success"
                        />
                        <StatCard
                            label="Children due for vaccines"
                            value={9}
                            icon={Syringe}
                            tone="warning"
                            hint="3 overdue"
                        />
                        <StatCardSkeleton />
                    </div>
                </Section>

                <Section title="SearchFilters + DataTable (server-side)">
                    <SearchFilters
                        search={{
                            value: search,
                            onChange: setSearch,
                            placeholder:
                                'Search name, resident ID, household no. or birthdate',
                            label: 'Search residents',
                        }}
                        filters={[
                            {
                                key: 'purok',
                                label: 'Purok',
                                allLabel: 'All puroks',
                                value: purok,
                                onChange: setPurok,
                                options: [1, 2, 3].map((n) => ({
                                    value: n,
                                    label: `Purok ${n}`,
                                })),
                            },
                            {
                                key: 'sex',
                                label: 'Sex',
                                allLabel: 'All sexes',
                                value: null,
                                onChange: () => {},
                                options: [
                                    { value: 'male', label: 'Male' },
                                    { value: 'female', label: 'Female' },
                                ],
                            },
                        ]}
                        hasActiveFilters={!!search || !!purok}
                        onReset={() => {
                            setSearch('');
                            setPurok(null);
                        }}
                    >
                        <div className="flex items-center gap-2">
                            <Switch id="archived" />
                            <Label htmlFor="archived">Show archived</Label>
                        </div>
                    </SearchFilters>
                    <DataTable
                        columns={columns}
                        data={page}
                        sort={{ sort: 'last_name', direction: 'asc' }}
                        onSort={() => {}}
                        onPageChange={() => {}}
                        perPage={15}
                        onPerPageChange={() => {}}
                        itemLabel="residents"
                        caption="Residents"
                        empty={null}
                    />
                    <DataTableSkeleton rows={3} />
                    <EmptyState
                        icon={Users}
                        title="No residents match your search"
                        description="Check the spelling, try only the last name, or clear the filters."
                        action={
                            <Button variant="outline">Clear filters</Button>
                        }
                    />
                </Section>

                <Section title="Form controls (FormField, DatePicker, Combobox)">
                    <Card>
                        <CardHeader>
                            <CardTitle>Personal information</CardTitle>
                            <CardDescription>
                                Fields marked * are required.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 md:grid-cols-2">
                            <FormField
                                id="last_name"
                                label="Last name"
                                required
                                error="Use letters, spaces, periods, apostrophes or hyphens only."
                            >
                                <Input
                                    id="last_name"
                                    className="h-11"
                                    defaultValue="D3la Cruz"
                                    aria-invalid
                                    aria-describedby={describedBy('last_name', {
                                        error: true,
                                    })}
                                />
                            </FormField>
                            <FormField
                                id="first_name"
                                label="First name"
                                required
                            >
                                <Input
                                    id="first_name"
                                    className="h-11"
                                    defaultValue="Juan"
                                />
                            </FormField>
                            <FormField
                                id="birthdate"
                                label="Birthdate"
                                required
                                description="Used to compute age — it is never typed in."
                            >
                                <DatePicker
                                    id="birthdate"
                                    value={birthdate}
                                    onChange={setBirthdate}
                                    disableFuture
                                    describedBy="birthdate-description"
                                />
                            </FormField>
                            <FormField
                                id="household"
                                label="Household"
                                required
                                description="Search by household no., head's last name or address."
                            >
                                <Combobox
                                    id="household"
                                    value={household}
                                    onChange={setHousehold}
                                    options={households}
                                    placeholder="Choose a household"
                                    footer={
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="w-full justify-start"
                                        >
                                            <Plus aria-hidden /> Create new
                                            household
                                        </Button>
                                    }
                                />
                            </FormField>
                            <FormField id="sex" label="Sex" required>
                                <RadioGroup
                                    id="sex"
                                    defaultValue="female"
                                    className="flex gap-6"
                                >
                                    <div className="flex items-center gap-2">
                                        <RadioGroupItem
                                            value="male"
                                            id="sex-male"
                                        />
                                        <Label htmlFor="sex-male">Male</Label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <RadioGroupItem
                                            value="female"
                                            id="sex-female"
                                        />
                                        <Label htmlFor="sex-female">
                                            Female
                                        </Label>
                                    </div>
                                </RadioGroup>
                            </FormField>
                            <FormField id="philhealth" label="PhilHealth no.">
                                <div className="flex h-11 items-center">
                                    <SensitiveValue
                                        value={sampleResident.philhealth_no}
                                        masked={
                                            sampleResident.philhealth_no_masked
                                        }
                                        label="PhilHealth number"
                                    />
                                </div>
                            </FormField>
                            <FormField
                                id="notes"
                                label="Notes"
                                className="md:col-span-2"
                            >
                                <Textarea
                                    id="notes"
                                    rows={3}
                                    placeholder="Optional"
                                />
                            </FormField>
                        </CardContent>
                    </Card>
                </Section>

                <Section title="Tabs + HealthTimeline">
                    <Tabs defaultValue="timeline">
                        <TabsList>
                            <TabsTrigger value="personal">
                                Personal Info
                            </TabsTrigger>
                            <TabsTrigger value="household">
                                Household
                            </TabsTrigger>
                            <TabsTrigger value="timeline">
                                Health Timeline
                            </TabsTrigger>
                            <TabsTrigger value="immunizations">
                                Immunizations
                            </TabsTrigger>
                            <TabsTrigger value="maternal">Maternal</TabsTrigger>
                        </TabsList>
                        <TabsContent value="timeline" className="pt-4">
                            <HealthTimeline
                                visits={visits}
                                renderActions={() => (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Visit actions"
                                    >
                                        <MoreHorizontal aria-hidden />
                                    </Button>
                                )}
                            />
                        </TabsContent>
                        <TabsContent value="personal" className="pt-4">
                            Born {formatDate(sampleResident.birthdate, 'long')}
                        </TabsContent>
                        <TabsContent value="immunizations" className="pt-4">
                            <EmptyState
                                icon={Baby}
                                title="No immunizations recorded"
                            />
                        </TabsContent>
                    </Tabs>
                    <HealthTimeline
                        visits={[]}
                        emptyAction={
                            <Button>
                                <Plus aria-hidden /> Record visit
                            </Button>
                        }
                    />
                </Section>

                <Section title="ConfirmDialog">
                    <ConfirmDialog
                        open={archiveOpen}
                        onOpenChange={setArchiveOpen}
                        trigger={
                            <Button variant="destructive">
                                Archive resident…
                            </Button>
                        }
                        title="Archive Lourdes Villanueva?"
                        description={
                            <p>
                                The record is hidden from lists but kept for 5
                                years, as required by the retention policy. You
                                can restore it at any time.
                            </p>
                        }
                        confirmLabel="Archive resident"
                        destructive
                        onConfirm={() => setArchiveOpen(false)}
                    >
                        <FormField id="archive_reason" label="Reason" required>
                            <Textarea
                                id="archive_reason"
                                rows={2}
                                placeholder='e.g. "Moved to Tacurong City, March 2026"'
                            />
                        </FormField>
                    </ConfirmDialog>
                </Section>

                <Section title="Charts">
                    <div className="grid gap-4 xl:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Residents per purok</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <PurokChart data={purokData} />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Age and sex</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <AgeSexPyramid data={ageSex} />
                            </CardContent>
                        </Card>
                    </div>
                </Section>
            </div>
        </>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-4">
            <h2 className="text-lg font-bold">{title}</h2>
            {children}
        </section>
    );
}

ComponentGallery.layout = {
    breadcrumbs: [{ title: 'Component gallery', href: '/dev/components' }],
};
