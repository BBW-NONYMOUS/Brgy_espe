/**
 * Page-prop types. Each type mirrors a Laravel API Resource in
 * app/Http/Resources — keep them in sync when a Resource changes.
 *
 * Fields marked optional (`?`) are only present when the viewer's role
 * allows it (e.g. diagnosis, notes, full PhilHealth number).
 */

export type EnumValue<T extends string = string> = { value: T; label: string };

export type Option<T extends string | number = number> = {
    value: T;
    label: string;
};

/** Laravel paginated ResourceCollection shape. */
export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
        path: string;
        links: {
            url: string | null;
            label: string;
            page: number | null;
            active: boolean;
        }[];
    };
};

export type Sex = 'male' | 'female';
export type CivilStatus =
    | 'single'
    | 'married'
    | 'widowed'
    | 'separated'
    | 'live_in';
export type RelationshipToHead =
    | 'head'
    | 'spouse'
    | 'child'
    | 'parent'
    | 'sibling'
    | 'grandchild'
    | 'grandparent'
    | 'in_law'
    | 'other_relative'
    | 'non_relative';
export type ResidentStatus = 'active' | 'moved' | 'deceased';
export type ConditionStatus = 'active' | 'controlled' | 'resolved';
export type MaternalStatus = 'ongoing' | 'delivered' | 'terminated';
export type RiskLevel = 'low' | 'high';
export type MaternalVisitKind = 'prenatal' | 'postnatal';
export type RoleName = 'admin' | 'bhw' | 'midwife' | 'official';

// ---------------------------------------------------------------- residents

/** App\Http\Resources\HouseholdResource */
export type Household = {
    id: number;
    household_no: string;
    address: string;
    /** 4Ps enrols households; members inherit it. */
    is_4ps: boolean;
    water_source_id: number | null;
    toilet_type_id: number | null;
    water_source?: string | null;
    toilet_type?: string | null;
    is_geotagged: boolean;
    /** Only for roles with map.view_exact (coordinates are personal data). */
    latitude?: number;
    longitude?: number;
    purok?: { id: number; name: string };
    head?: { id: number; resident_code: string; full_name: string } | null;
    members_count?: number;
    members?: Resident[];
};

/** App\Http\Resources\ResidentResource */
export type Resident = {
    id: number;
    resident_code: string;
    last_name: string;
    first_name: string;
    middle_name: string | null;
    suffix: string | null;
    full_name: string;
    sex: EnumValue<Sex>;
    birthdate: string;
    age: number;
    birthplace: string | null;
    civil_status: EnumValue<CivilStatus>;
    contact_no: string | null;
    philhealth_no_masked: string | null;
    /** Profile page only, and only for roles with health.view_sensitive. */
    philhealth_no?: string | null;
    is_pwd: boolean;
    /** Profile page only, and only for roles with health.view_sensitive. */
    blood_type?: string | null;
    /** From the household (present when the household is loaded). */
    is_4ps?: boolean;
    is_senior: boolean;
    is_pregnant?: boolean;
    relationship_to_head: EnumValue<RelationshipToHead>;
    status: EnumValue<ResidentStatus>;
    archive_reason: string | null;
    archived_at: string | null;
    household?: Household;
    created_by?: string | null;
    updated_by?: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type HouseholdOption = Option & { purok: string; address: string };

export type DuplicateMatch = {
    id: number;
    resident_code: string;
    full_name: string;
    birthdate: string;
    household_no: string;
    purok: string;
    archived: boolean;
};

export type HouseholdMember = {
    id: number;
    resident_code: string;
    full_name: string;
    age: number;
    sex: string;
    relationship_to_head: string;
};

/** App\Http\Resources\ConsentResource */
export type Consent = {
    id: number;
    consent_type: EnumValue<import('./modules').ConsentType>;
    consent_date: string;
    purpose: string;
    obtained_by?: string;
    revoked_at: string | null;
};

// ------------------------------------------------------------------- health

export type VitalSigns = {
    bp_systolic: number | null;
    bp_diastolic: number | null;
    weight_kg: number | null;
    height_cm: number | null;
    temperature_c: number | null;
    pulse_rate: number | null;
    respiratory_rate: number | null;
    bmi: number | null;
};

/** App\Http\Resources\HealthVisitResource */
export type HealthVisit = {
    id: number;
    visit_date: string;
    visit_type?: { id: number; name: string };
    attended_by?: string;
    restricted: boolean;
    chief_complaint?: string | null;
    diagnosis?: string | null;
    notes?: string | null;
    vitals: VitalSigns | null;
    maternal: {
        pregnancy_id: number;
        kind: EnumValue<MaternalVisitKind>;
        fundal_height_cm: number | null;
        fetal_heart_rate: number | null;
        remarks: string | null;
    } | null;
    created_at: string | null;
};

/** App\Http\Resources\ImmunizationResource */
export type Immunization = {
    id: number;
    vaccine?: { id: number; code: string; name: string };
    dose_no: number;
    date_given: string;
    given_by?: string;
    lot_no: string | null;
    remarks: string | null;
};

export type ScheduledDose = {
    vaccine_id: number;
    vaccine_code: string;
    vaccine_name: string;
    dose_no: number;
    due_date: string;
    status: 'given' | 'overdue' | 'due' | 'upcoming';
    date_given: string | null;
};

/** App\Http\Resources\ResidentConditionResource */
export type ResidentCondition = {
    id: number;
    condition?: { id: number; name: string; icd10_code: string | null };
    diagnosed_date: string | null;
    status: EnumValue<ConditionStatus>;
    restricted: boolean;
    notes?: string | null;
    recorded_by?: string;
};

/** App\Http\Resources\PregnancyResource */
export type Pregnancy = {
    id: number;
    lmp: string | null;
    edd: string;
    aog_weeks: number | null;
    gravida: number;
    para: number;
    risk_level: EnumValue<RiskLevel>;
    status: EnumValue<MaternalStatus>;
    outcome_date: string | null;
    restricted: boolean;
    notes?: string | null;
    visits_count?: number;
    resident?: {
        id: number;
        resident_code: string;
        full_name: string;
        age: number;
        purok: string | null;
    };
    risk_factors?: { id: number; name: string }[];
    delivery?: import('./modules').Delivery | null;
};

export type ResidentHealth = {
    timeline: HealthVisit[];
    conditions: ResidentCondition[];
    immunizations: Immunization[];
    immunizationSchedule: ScheduledDose[];
    pregnancies: Pregnancy[];
    emergencyContacts: import('./modules').EmergencyContact[];
    medicalAlerts: import('./modules').MedicalAlert[];
    growth: import('./modules').GrowthMeasurement[];
    micronutrients: import('./modules').MicronutrientRecord[];
    diseaseCases: import('./modules').DiseaseCase[];
    referrals: import('./modules').Referral[];
    appointments: import('./modules').Appointment[];
};

export type RecordHistoryEntry = {
    id: number;
    action: string;
    description: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    user: string | null;
    created_at: string;
};

export type HealthOptions = {
    visitTypes: Option[];
    vaccines: (Option & { code: string; doses: number })[];
    conditions: Option[];
    conditionStatuses: Option<ConditionStatus>[];
    riskLevels: Option<RiskLevel>[];
    maternalStatuses: Option<MaternalStatus>[];
    maternalVisitKinds: Option<MaternalVisitKind>[];
};

// ------------------------------------------------------------------ reports

export type ReportTotals = {
    residents: number;
    households: number;
    male: number;
    female: number;
    seniors: number;
    pwd: number;
    four_ps: number;
    infants: number;
    pregnant: number;
    children_due: number;
};

export type PurokPopulation = {
    purok: string;
    households: number;
    residents: number;
    male: number;
    female: number;
};

export type AgeSexRow = {
    age_group: string;
    male: number;
    female: number;
    total: number;
};

/** ReportService::summary() — aggregates only. */
export type ReportSummary = {
    period: { from: string; to: string };
    purok: string | null;
    totals: ReportTotals;
    population: PurokPopulation[];
    age_sex: AgeSexRow[];
    conditions: {
        condition: string;
        icd10_code: string | null;
        residents: number;
    }[];
    visits: { visit_type: string; visits: number }[];
    immunizations: { vaccine: string; doses: number }[];
};

/** ReportService::lists() — named residents, health workers only. */
export type ReportLists = {
    children_due: {
        resident_id: number;
        resident_code: string;
        name: string;
        birthdate: string;
        age_months: number;
        purok: string;
        household_no: string;
        pending: string[];
        earliest_due: string;
        overdue: boolean;
    }[];
    pregnant: {
        resident_id: number;
        resident_code: string;
        name: string;
        age: number;
        purok: string;
        edd: string;
        aog_weeks: number | null;
        gravida: number;
        para: number;
        risk_level: string;
    }[];
};

export type RecentActivity = {
    id: number;
    action: string;
    description: string | null;
    table_name: string | null;
    record_id: number | null;
    user: string | null;
    created_at: string;
};

// -------------------------------------------------------------------- admin

/** App\Http\Resources\UserResource */
export type StaffUser = {
    id: number;
    username: string;
    name: string;
    email: string | null;
    role?: { id: number; name: RoleName; label: string };
    is_active: boolean;
    must_change_password: boolean;
    is_locked: boolean;
    locked_until: string | null;
    last_login_at: string | null;
    two_factor_enabled: boolean;
    created_at: string | null;
};

export type Role = {
    id: number;
    name: RoleName;
    label: string;
    description: string | null;
};

/** App\Http\Resources\AuditLogResource */
export type AuditLogEntry = {
    id: number;
    created_at: string;
    user?: { id: number; name: string; username: string } | null;
    action: EnumValue;
    table_name: string | null;
    record_id: number | null;
    description: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
};

/** App\Http\Resources\BackupResource */
export type BackupEntry = {
    id: number;
    file_name: string;
    size_bytes: number | null;
    type: EnumValue<'scheduled' | 'manual'>;
    status: EnumValue<'running' | 'success' | 'failed' | 'pruned'>;
    error: string | null;
    created_by?: string | null;
    created_at: string;
    completed_at: string | null;
    downloadable: boolean;
};
