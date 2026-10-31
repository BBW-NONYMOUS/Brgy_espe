/**
 * Page props for the disease surveillance, maternal tracker and child
 * health screens (controllers in app/Http/Controllers/Surveillance and
 * app/Http/Controllers/Health).
 */
import type {
    MapConfig,
    Appointment,
    ChildSummary,
    DiseaseCase,
    DiseaseTrend,
    EnumValue,
    GrowthMeasurement,
    HealthVisit,
    MaternalContact,
    MedicalAlert,
    MicronutrientRecord,
    MicronutrientStatus,
    Option,
    OutbreakAlert,
    Paginated,
    Pregnancy,
    PurokSummary,
    ResidentOption,
    ScheduledDose,
} from '@/types';

export type DiseaseCasesPageProps = {
    cases: Paginated<DiseaseCase>;
    filters: {
        search: string;
        disease_id: number | null;
        purok_id: number | null;
        classification: string | null;
        outcome: string | null;
        date_from: string | null;
        date_to: string | null;
    };
    openAlerts: OutbreakAlert[];
    map: MapConfig;
    trend?: DiseaseTrend;
    perPurok?: PurokSummary[];
    options: {
        diseases: (Option & { category: string })[];
        puroks: Option[];
        classifications: Option<string>[];
        outcomes: Option<string>[];
    };
    residentOptions?: ResidentOption[];
};

export type OutbreakAlertsPageProps = {
    alerts: Paginated<OutbreakAlert>;
    filters: { status: string | null };
    options: { statuses: Option<string>[] };
    canManage: boolean;
};

export type PregnancyRow = Pregnancy & {
    next_contact: MaternalContact | null;
    overdue_contacts: number;
};

export type MaternalIndexPageProps = {
    pregnancies: PregnancyRow[];
    filters: {
        purok_id: number | null;
        risk_level: string | null;
        status: 'overdue' | 'due_soon' | 'postnatal' | null;
    };
    options: { puroks: Option[]; riskLevels: Option<string>[] };
};

export type MaternalShowPageProps = {
    pregnancy: Pregnancy;
    criticalAlerts: MedicalAlert[];
    contacts: MaternalContact[];
    visits: HealthVisit[];
    appointments: Appointment[];
    newborns: {
        id: number;
        resident_code: string;
        full_name: string;
        sex: string;
    }[];
    options: {
        riskFactors: Option[];
        riskLevels: Option<string>[];
        statuses: Option<string>[];
        deliveryPlaces: Option<string>[];
        attendants: Option<string>[];
        deliveryModes: Option<string>[];
        breastfeeding: Option<string>[];
        sexes: Option<string>[];
        relationships: Option<string>[];
        visitTypes: Option[];
    } | null;
};

export type ChildHealthIndexPageProps = {
    childrenUnderFive: ChildSummary[];
    filters: {
        search: string;
        purok_id: number | null;
        status: 'overdue' | 'due' | 'malnourished' | 'vitamin_a_due' | null;
    };
    options: { puroks: Option[] };
};

export type ChildHealthShowPageProps = {
    child: {
        id: number;
        resident_code: string;
        full_name: string;
        sex: EnumValue;
        birthdate: string;
        age_months: number;
        purok: string;
        household_id: number;
        mother: { id: number; full_name: string } | null;
    };
    criticalAlerts: MedicalAlert[];
    immunizationSchedule: ScheduledDose[];
    growth: GrowthMeasurement[];
    micronutrients: MicronutrientRecord[];
    micronutrientStatus: MicronutrientStatus;
    options: {
        nutritionalStatuses: Option<string>[];
        micronutrientTypes: Option<string>[];
        vaccines: (Option & { code: string; doses: number })[];
    } | null;
};
