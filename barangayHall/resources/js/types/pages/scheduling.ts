/**
 * Props of the scheduling, programs, health-settings and dashboard pages
 * (controllers in app/Http/Controllers/Scheduling, Admin\HealthSettingsController
 * and DashboardController).
 */
import type {
    Appointment,
    AppointmentStatus,
    EnumValue,
    HealthProgram,
    Option,
    Paginated,
    ResidentOption,
} from '@/types';

export type ColoredOption = Option & { color: string };

export type AppointmentFilters = {
    date_from: string;
    date_to: string;
    status: AppointmentStatus | null;
    appointment_type_id: number | null;
    assigned_to: 'me' | 'unassigned' | null;
};

export type AppointmentsPageProps = {
    appointments: Paginated<Appointment>;
    filters: AppointmentFilters;
    counts: { today: number; missed_last_7_days: number };
    options: {
        types: (ColoredOption & { code: string })[];
        statuses: Option<AppointmentStatus>[];
        staff: Option[];
    };
    residentOptions?: ResidentOption[];
    selectedAppointment?: Appointment | null;
};

export type ProgramFormOptions = {
    types: ColoredOption[];
    targetGroups: Option<string>[];
    statuses: Option<string>[];
    puroks: Option[];
    staff: Option[];
};

export type ProgramsPageProps = {
    programs: Paginated<HealthProgram>;
    filters: { when: 'upcoming' | 'past'; program_type_id: number | null };
    options: ProgramFormOptions;
    canManage: boolean;
};

export type ProgramAttendee = {
    id: number;
    resident_id: number;
    resident_code: string;
    full_name: string;
    purok: string;
    attended_at: string;
};

export type EligibleResident = {
    id: number;
    resident_code: string;
    full_name: string;
    age: number;
    purok: string;
};

export type ProgramShowProps = {
    program: HealthProgram;
    eligibleCount: number;
    attendance: ProgramAttendee[] | null;
    attendanceByPurok: Record<string, number>;
    eligible?: EligibleResident[] | null;
    options: ProgramFormOptions | null;
    canManage: boolean;
};

// ------------------------------------------------------------ health settings

export type ThresholdRow = {
    id: number;
    disease_id: number;
    disease: string;
    scope: EnumValue<'purok' | 'barangay'>;
    min_cases: number;
    days_window: number;
    is_active: boolean;
};

export type DiseaseRow = {
    id: number;
    name: string;
    icd10_code: string | null;
    pidsr_category: EnumValue;
    is_notifiable: boolean;
    is_active: boolean;
};

export type FacilityRow = {
    id: number;
    name: string;
    type: EnumValue;
    address: string | null;
    contact_no: string | null;
    is_active: boolean;
};

export type PurokCenterRow = {
    id: number;
    name: string;
    center_latitude: number | null;
    center_longitude: number | null;
};

export type MaternalRuleRow = {
    id: number;
    kind: EnumValue<'prenatal' | 'postnatal'>;
    sequence: number;
    label: string;
    offset_days: number;
    is_active: boolean;
};

export type VaccineRuleRow = {
    id: number;
    vaccine_id: number;
    vaccine: string;
    vaccine_code: string;
    dose_no: number;
    due_age_days: number;
    min_interval_days: number | null;
};

export type HealthSettingsProps = {
    allowed: { lookups: boolean; schedules: boolean };
    thresholds: ThresholdRow[] | null;
    diseases: DiseaseRow[] | null;
    facilities: FacilityRow[] | null;
    puroks: PurokCenterRow[] | null;
    maternalRules: MaternalRuleRow[] | null;
    vaccineRules: VaccineRuleRow[] | null;
    options: {
        scopes: Option<string>[];
        pidsrCategories: Option<string>[];
        facilityTypes: Option<string>[];
        visitKinds: Option<string>[];
        diseases: (Option & { category: string })[];
        vaccines: (Option & { code: string; doses: number })[];
    };
    map: { center: [number, number]; zoom: number };
};

// ---------------------------------------------------------------- dashboard

export type ServiceStats = {
    expiring_batches?: number;
    low_stock_medicines?: number;
    appointments_today?: number;
    missed_appointments?: number;
    active_outbreaks?: number;
};

export type DiseaseTrendWeek = { week: string; cases: number };
