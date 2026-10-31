import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { ContactSchedule } from '@/components/app/contact-schedule';
import { ExpiryBadge, StockLevelBadge } from '@/components/app/expiry-badge';
import { GrowthChart } from '@/components/app/growth-chart';
import { MapLegend } from '@/components/app/map-legend';
import { ImmunizationChecklist } from '@/components/app/immunization-checklist';
import {
    LazyCalendarView,
    LazyGeotagPicker,
    LazyHouseholdMap,
} from '@/components/app/lazy';
import { MedicalAlertBanner } from '@/components/app/medical-alert-banner';
import { OutbreakBanner } from '@/components/app/outbreak-banner';
import { PageHeader } from '@/components/app/page-header';
import { StatusStepper } from '@/components/app/status-stepper';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    GeotagValue,
    GrowthMeasurement,
    HouseholdMarker,
    MapConfig,
    MaternalContact,
    MedicalAlert,
    OutbreakAlert,
    PurokSummary,
    ScheduledDose,
} from '@/types';

/*
 * Design gallery for the additional modules (featured.md 9–17). Sample data
 * only — nothing here talks to the server except the calendar, which reads
 * the real /calendar/events feed. Registered only when APP_ENV=local.
 */

const today = new Date();
const iso = (days: number) =>
    new Date(today.getTime() + days * 86_400_000).toISOString().slice(0, 10);

const config: MapConfig = {
    center: [6.629, 124.606],
    zoom: 16,
    max_accuracy_m: 100,
};

const alerts: MedicalAlert[] = [
    {
        id: 1,
        alert_type: { value: 'allergy', label: 'Allergy' },
        description:
            'Penicillin: hives and difficulty breathing (2019). Do not give penicillins.',
        severity: { value: 'critical', label: 'Critical' },
        is_active: true,
        created_at: null,
    },
    {
        id: 2,
        alert_type: { value: 'chronic', label: 'Chronic condition' },
        description: 'Type 2 diabetes on insulin.',
        severity: { value: 'critical', label: 'Critical' },
        is_active: true,
        created_at: null,
    },
];

const outbreak: OutbreakAlert[] = [
    {
        id: 1,
        disease: { id: 17, name: 'Dengue' },
        purok: { id: 3, name: 'Purok 3' },
        scope_label: null,
        case_count: 3,
        threshold: { min_cases: 3, days_window: 14 },
        period_start: iso(-9),
        period_end: iso(-3),
        status: { value: 'active', label: 'Active' },
        acknowledged_at: null,
        closed_at: null,
        closing_note: null,
        created_at: null,
    },
];

const markers: HouseholdMarker[] = [
    {
        id: 1,
        household_no: 'HH-0001',
        lat: 6.6325,
        lng: 124.6012,
        purok_id: 1,
        purok: 'Purok 1',
        head: 'Dela Cruz, Rodrigo Santos',
        members: 5,
        flags: ['senior', 'pwd'],
    },
    {
        id: 2,
        household_no: 'HH-0002',
        lat: 6.6338,
        lng: 124.6031,
        purok_id: 1,
        purok: 'Purok 1',
        head: 'Gallardo, Ernesto Tolentino',
        members: 5,
        flags: ['pregnant', 'senior'],
    },
    {
        id: 3,
        household_no: 'HH-0003',
        lat: 6.6331,
        lng: 124.6068,
        purok_id: 2,
        purok: 'Purok 2',
        head: 'Abdulrahman, Mohammad Kusain',
        members: 5,
        flags: ['four_ps'],
    },
    {
        id: 5,
        household_no: 'HH-0005',
        lat: 6.6329,
        lng: 124.6114,
        purok_id: 3,
        purok: 'Purok 3',
        head: 'Santos, Jun Rey Aquino',
        members: 3,
        flags: ['active_case', 'pregnant'],
    },
    {
        id: 6,
        household_no: 'HH-0006',
        lat: 6.6321,
        lng: 124.6109,
        purok_id: 3,
        purok: 'Purok 3',
        head: 'Lumabao, Benjamin Cortez',
        members: 3,
        flags: ['active_case', 'senior'],
    },
    {
        id: 9,
        household_no: 'HH-0009',
        lat: 6.6292,
        lng: 124.6098,
        purok_id: 5,
        purok: 'Purok 5',
        head: 'Tolentino, Pedro Javier',
        members: 2,
        flags: [],
    },
];

const puroks: PurokSummary[] = [
    {
        id: 1,
        name: 'Purok 1',
        lat: 6.633,
        lng: 124.6015,
        households: 2,
        residents: 10,
        cases: 1,
        cases_by_disease: { Dengue: 1 },
    },
    {
        id: 2,
        name: 'Purok 2',
        lat: 6.6332,
        lng: 124.6065,
        households: 2,
        residents: 9,
        cases: 0,
        cases_by_disease: {},
    },
    {
        id: 3,
        name: 'Purok 3',
        lat: 6.6328,
        lng: 124.6112,
        households: 2,
        residents: 7,
        cases: 3,
        cases_by_disease: { Dengue: 3 },
    },
    {
        id: 4,
        name: 'Purok 4',
        lat: 6.6288,
        lng: 124.6012,
        households: 2,
        residents: 9,
        cases: 0,
        cases_by_disease: {},
    },
    {
        id: 5,
        name: 'Purok 5',
        lat: 6.629,
        lng: 124.61,
        households: 2,
        residents: 6,
        cases: 1,
        cases_by_disease: { 'Influenza-like illness': 1 },
    },
];

const contacts: MaternalContact[] = [
    {
        rule_id: 1,
        kind: 'prenatal',
        sequence: 1,
        label: '1st ANC contact (12 weeks)',
        due_date: iso(-126),
        status: 'done',
        visit_date: iso(-125),
        health_visit_id: 1,
    },
    {
        rule_id: 2,
        kind: 'prenatal',
        sequence: 2,
        label: '2nd ANC contact (20 weeks)',
        due_date: iso(-70),
        status: 'done',
        visit_date: iso(-70),
        health_visit_id: 2,
    },
    {
        rule_id: 3,
        kind: 'prenatal',
        sequence: 3,
        label: '3rd ANC contact (26 weeks)',
        due_date: iso(-28),
        status: 'overdue',
        visit_date: null,
        health_visit_id: null,
    },
    {
        rule_id: 4,
        kind: 'prenatal',
        sequence: 4,
        label: '4th ANC contact (30 weeks)',
        due_date: iso(0),
        status: 'due',
        visit_date: null,
        health_visit_id: null,
    },
    {
        rule_id: 5,
        kind: 'prenatal',
        sequence: 5,
        label: '5th ANC contact (34 weeks)',
        due_date: iso(28),
        status: 'upcoming',
        visit_date: null,
        health_visit_id: null,
    },
];

const doses: ScheduledDose[] = [
    ['BCG', 'Bacille Calmette-Guérin (BCG)', 1, 'given'],
    ['HEPB', 'Hepatitis B (birth dose)', 1, 'given'],
    ['PENTA', 'Pentavalent (DPT-HepB-Hib)', 1, 'given'],
    ['PENTA', 'Pentavalent (DPT-HepB-Hib)', 2, 'overdue'],
    ['PENTA', 'Pentavalent (DPT-HepB-Hib)', 3, 'upcoming'],
    ['MMR', 'Measles-Mumps-Rubella (MMR)', 1, 'due'],
    ['MMR', 'Measles-Mumps-Rubella (MMR)', 2, 'upcoming'],
].map(([code, name, dose, status], i) => ({
    vaccine_id: ['BCG', 'HEPB', 'PENTA', 'MMR'].indexOf(code as string) + 1,
    vaccine_code: code as string,
    vaccine_name: name as string,
    dose_no: dose as number,
    due_date: iso(-200 + i * 30),
    status: status as ScheduledDose['status'],
    date_given: status === 'given' ? iso(-200 + i * 30) : null,
}));

const growth: GrowthMeasurement[] = [0, 2, 4, 6, 9, 12].map((month, i) => ({
    id: i,
    measured_on: iso(-420 + month * 30),
    weight_kg: [3.3, 5.4, 6.5, 6.9, 7.3, 7.6][i],
    height_cm: [49.9, 57.8, 63.5, 66.4, 70.0, 73.0][i],
    muac_cm: null,
    nutritional_status: {
        value: i >= 4 ? 'underweight' : 'normal',
        label: i >= 4 ? 'Underweight' : 'Normal',
    },
    remarks: null,
}));

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {children}
            </CardContent>
        </Card>
    );
}

export default function DevModules() {
    const [geotag, setGeotag] = useState<GeotagValue | null>({
        latitude: 6.6329,
        longitude: 124.6114,
        accuracy_m: 140,
    });

    return (
        <>
            <Head title="Module components" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Module components"
                    description="Design gallery for modules 9–17. Switch light/dark in Settings → Appearance."
                />

                <Section
                    title="MedicalAlertBanner"
                    description="Top of the resident profile (full) and inside health forms (compact)."
                >
                    <MedicalAlertBanner alerts={alerts} />
                    <MedicalAlertBanner alerts={alerts} compact />
                </Section>

                <Section
                    title="OutbreakBanner"
                    description="Dashboard, while an outbreak alert is open. Counts only."
                >
                    <OutbreakBanner alerts={outbreak} />
                </Section>

                <Section
                    title="ExpiryBadge · StockLevelBadge"
                    description="Red = expired, orange = within 30 days, yellow = within 90 days. Text always states the days."
                >
                    <div className="flex flex-wrap gap-2">
                        <ExpiryBadge
                            expiryDate={iso(-10)}
                            band={0}
                            days={-10}
                        />
                        <ExpiryBadge expiryDate={iso(12)} band={30} days={12} />
                        <ExpiryBadge expiryDate={iso(45)} band={60} days={45} />
                        <ExpiryBadge expiryDate={iso(80)} band={90} days={80} />
                        <ExpiryBadge
                            expiryDate={iso(400)}
                            band={null}
                            days={400}
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <StockLevelBadge
                            onHand={0}
                            reorderLevel={100}
                            unit="tablet"
                        />
                        <StockLevelBadge
                            onHand={120}
                            reorderLevel={200}
                            unit="tablet"
                        />
                        <StockLevelBadge
                            onHand={580}
                            reorderLevel={200}
                            unit="tablet"
                        />
                    </div>
                </Section>

                <Section
                    title="StatusStepper"
                    description="Referral progress; cancelled shown as a stopped step."
                >
                    <StatusStepper
                        current="received"
                        steps={[
                            {
                                key: 'pending',
                                label: 'Pending',
                                at: `${iso(-5)}T09:30:00+08:00`,
                                by: 'Cristina Gelito, RM',
                            },
                            {
                                key: 'sent',
                                label: 'Sent',
                                at: `${iso(-5)}T10:10:00+08:00`,
                            },
                            {
                                key: 'received',
                                label: 'Received',
                                at: `${iso(-4)}T14:00:00+08:00`,
                            },
                            { key: 'completed', label: 'Completed' },
                        ]}
                    />
                    <StatusStepper
                        current="sent"
                        cancelled={{
                            label: 'Cancelled',
                            at: `${iso(-1)}T08:00:00+08:00`,
                        }}
                        steps={[
                            { key: 'pending', label: 'Pending' },
                            { key: 'sent', label: 'Sent' },
                            { key: 'received', label: 'Received' },
                            { key: 'completed', label: 'Completed' },
                        ]}
                    />
                </Section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Section
                        title="ContactSchedule"
                        description="Prenatal/postnatal contacts from the configurable schedule."
                    >
                        <ContactSchedule
                            contacts={contacts}
                            onRecord={() => undefined}
                        />
                    </Section>
                    <Section
                        title="ImmunizationChecklist"
                        description="One row per vaccine; due/overdue doses are buttons that open the record dialog."
                    >
                        <ImmunizationChecklist
                            doses={doses}
                            onRecord={() => undefined}
                        />
                    </Section>
                </div>

                <Section
                    title="GrowthChart"
                    description="Weight and length against age; OPT Plus status in the table."
                >
                    <GrowthChart measurements={growth} birthdate={iso(-420)} />
                </Section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Section
                        title="HouseholdMap — exact (health staff)"
                        description="Canvas markers coloured by the most important flag; popup with the family profile link."
                    >
                        <LazyHouseholdMap
                            config={config}
                            markers={markers}
                            className="h-96"
                        />
                        <MapLegend />
                    </Section>
                    <Section
                        title="HouseholdMap — purok level (Officials)"
                        description="One circle per purok at the Admin-set centre; size = cases in the last 30 days."
                    >
                        <LazyHouseholdMap
                            config={config}
                            puroks={puroks}
                            className="h-96"
                            label="Disease cases per purok"
                        />
                        <MapLegend mode="puroks" />
                    </Section>
                </div>

                <Section
                    title="GeotagPicker"
                    description="'Use my location' needs https; otherwise tap the map. This sample has poor GPS accuracy (140 m) to show the warning."
                >
                    <LazyGeotagPicker
                        config={config}
                        value={geotag}
                        onChange={setGeotag}
                    />
                </Section>

                <Section
                    title="CalendarView"
                    description="Live feed from /calendar/events (appointments for staff, programs for everyone)."
                >
                    <LazyCalendarView onEventClick={() => undefined} />
                </Section>
            </div>
        </>
    );
}

DevModules.layout = {
    breadcrumbs: [{ title: 'Module components', href: '/dev/modules' }],
};
