import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/app/page-header';
import {
    DiseasesTab,
    FacilitiesTab,
    PurokCentersTab,
    ThresholdsTab,
} from '@/components/app/settings/lookup-tabs';
import {
    MaternalRulesTab,
    VaccineRulesTab,
} from '@/components/app/settings/schedule-tabs';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { index } from '@/routes/health-settings';
import type { HealthSettingsProps } from '@/types/pages/scheduling';

/**
 * Outbreak thresholds, the disease list, facilities and purok centres
 * (Admin) and the prenatal/postnatal and immunization schedules
 * (Midwife/Admin). Tabs follow the server's `allowed` flags.
 */
export default function HealthSettingsIndex({
    allowed,
    thresholds,
    diseases,
    facilities,
    puroks,
    maternalRules,
    vaccineRules,
    options,
    map,
}: HealthSettingsProps) {
    const tabs = [
        thresholds && {
            value: 'thresholds',
            label: 'Outbreak thresholds',
            content: <ThresholdsTab rows={thresholds} options={options} />,
        },
        diseases && {
            value: 'diseases',
            label: 'Diseases',
            content: <DiseasesTab rows={diseases} options={options} />,
        },
        maternalRules && {
            value: 'maternal',
            label: 'Prenatal & postnatal schedule',
            content: (
                <MaternalRulesTab rows={maternalRules} options={options} />
            ),
        },
        vaccineRules && {
            value: 'vaccines',
            label: 'Immunization schedule',
            content: <VaccineRulesTab rows={vaccineRules} options={options} />,
        },
        facilities && {
            value: 'facilities',
            label: 'Facilities',
            content: <FacilitiesTab rows={facilities} options={options} />,
        },
        puroks && {
            value: 'puroks',
            label: 'Purok map centres',
            content: <PurokCentersTab rows={puroks} map={map} />,
        },
    ].filter(
        (
            t,
        ): t is { value: string; label: string; content: React.JSX.Element } =>
            Boolean(t),
    );

    return (
        <>
            <Head title="Health settings" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Health settings"
                    description={
                        allowed.lookups
                            ? 'Rules the system uses for alerts, schedules and referrals. Changes apply to everyone.'
                            : 'Prenatal, postnatal and immunization schedules used for appointments and overdue alerts.'
                    }
                />
                <Tabs defaultValue={tabs[0]?.value} className="gap-4">
                    <TabsList className="h-auto w-full flex-wrap justify-start">
                        {tabs.map((t) => (
                            <TabsTrigger
                                key={t.value}
                                value={t.value}
                                className="min-h-10"
                            >
                                {t.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                    {tabs.map((t) => (
                        <TabsContent key={t.value} value={t.value}>
                            {t.content}
                        </TabsContent>
                    ))}
                </Tabs>
            </div>
        </>
    );
}

HealthSettingsIndex.layout = {
    breadcrumbs: [{ title: 'Health settings', href: index() }],
};
