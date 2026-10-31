/**
 * Page-prop types for the additional modules (featured.md 9–17). Each type
 * mirrors an API Resource or a controller-shaped array in app/ — keep them in
 * sync. Optional fields (`?`) are only sent when the viewer's role allows it.
 */
import type { EnumValue, Option, ScheduledDose } from './models';

// ------------------------------------------------------------------- enums

export type AlertSeverity = 'info' | 'warning' | 'critical';
export type MedicalAlertType = 'allergy' | 'chronic' | 'disability' | 'other';
export type ConsentType =
    | 'health_profiling'
    | 'geotagging'
    | 'family_planning'
    | 'sms_reminders';
export type CaseClassification =
    | 'suspected'
    | 'probable'
    | 'confirmed'
    | 'ruled_out';
export type CaseOutcome = 'ongoing' | 'recovered' | 'died';
export type OutbreakAlertStatus = 'active' | 'monitoring' | 'closed';
export type ReferralStatus =
    | 'pending'
    | 'sent'
    | 'received'
    | 'completed'
    | 'cancelled';
export type ReferralUrgency = 'routine' | 'urgent' | 'emergency';
export type StockMovementType =
    | 'in'
    | 'out'
    | 'dispensed'
    | 'expired'
    | 'adjustment';
export type AppointmentStatus =
    | 'scheduled'
    | 'completed'
    | 'missed'
    | 'cancelled';
export type ProgramStatus = 'scheduled' | 'completed' | 'cancelled';
export type NutritionalStatus =
    | 'normal'
    | 'underweight'
    | 'severely_underweight'
    | 'stunted'
    | 'severely_stunted'
    | 'wasted'
    | 'severely_wasted'
    | 'overweight'
    | 'obese';
export type MapFlag = 'pregnant' | 'senior' | 'active_case' | 'pwd' | 'four_ps';
/** 0 = expired; 30/60/90 = expires within that many days; null = later. */
export type ExpiryBand = 0 | 30 | 60 | 90 | null;
/** PregnancyScheduleService contact status. */
export type ContactStatus =
    | 'done'
    | 'due'
    | 'overdue'
    | 'upcoming'
    | 'missed'
    | 'not_applicable';

// ------------------------------------------------- contacts & medical alerts

/** App\Http\Resources\EmergencyContactResource */
export type EmergencyContact = {
    id: number;
    name: string;
    relationship: string;
    phone: string;
    is_primary: boolean;
};

/** App\Http\Resources\MedicalAlertResource */
export type MedicalAlert = {
    id: number;
    alert_type: EnumValue<MedicalAlertType>;
    description: string;
    severity: EnumValue<AlertSeverity>;
    is_active: boolean;
    recorded_by?: string;
    created_at: string | null;
};

// -------------------------------------------------------------------- map

/** HouseholdMapService::markers() — health staff only. */
export type HouseholdMarker = {
    id: number;
    household_no: string;
    lat: number;
    lng: number;
    purok_id: number;
    purok: string;
    head: string | null;
    members: number;
    flags: MapFlag[];
};

/** HouseholdMapService::purokSummary() — safe for Barangay Officials. */
export type PurokSummary = {
    id: number;
    name: string;
    lat: number | null;
    lng: number | null;
    households: number;
    residents: number;
    cases: number;
    cases_by_disease: Record<string, number>;
};

export type MapConfig = {
    center: [number, number];
    zoom: number;
    max_accuracy_m?: number;
};

/** Value produced by the geo-tag picker. */
export type GeotagValue = {
    latitude: number;
    longitude: number;
    accuracy_m: number | null;
};

// ---------------------------------------------------- disease surveillance

/** App\Http\Resources\DiseaseCaseResource */
export type DiseaseCase = {
    id: number;
    resident?: {
        id: number;
        resident_code: string;
        full_name: string;
        age: number;
    };
    disease?: {
        id: number;
        name: string;
        pidsr_category: EnumValue;
    };
    purok?: { id: number; name: string };
    onset_date: string;
    reported_date: string;
    classification: EnumValue<CaseClassification>;
    outcome: EnumValue<CaseOutcome>;
    outcome_date: string | null;
    restricted: boolean;
    notes?: string | null;
    reported_by?: string;
    classified_by?: string | null;
};

/** App\Http\Resources\OutbreakAlertResource — aggregate only. */
export type OutbreakAlert = {
    id: number;
    disease?: { id: number; name: string };
    purok?: { id: number; name: string } | null;
    scope_label: string | null;
    case_count: number;
    threshold: { min_cases: number; days_window: number };
    period_start: string;
    period_end: string;
    status: EnumValue<OutbreakAlertStatus>;
    acknowledged_by?: string | null;
    acknowledged_at: string | null;
    closed_by?: string | null;
    closed_at: string | null;
    closing_note: string | null;
    created_at: string | null;
};

export type DiseaseTrend = {
    weeks: string[];
    series: { disease: string; counts: number[] }[];
};

// ------------------------------------------------------- maternal & child

export type MaternalContact = {
    rule_id: number;
    kind: 'prenatal' | 'postnatal';
    sequence: number;
    label: string;
    due_date: string;
    status: ContactStatus;
    visit_date: string | null;
    health_visit_id: number | null;
};

/** App\Http\Resources\DeliveryResource */
export type Delivery = {
    id: number;
    delivery_time: string | null;
    place_type: EnumValue;
    place_name: string | null;
    attendant_type: EnumValue;
    delivery_mode: EnumValue;
    live_births: number;
    stillbirths: number;
    facility_based: boolean;
    skilled_attendant: boolean;
    remarks: string | null;
};

/** App\Http\Resources\GrowthMeasurementResource */
export type GrowthMeasurement = {
    id: number;
    measured_on: string;
    weight_kg: number;
    height_cm: number | null;
    muac_cm: number | null;
    nutritional_status: EnumValue<NutritionalStatus> | null;
    measured_by?: string;
    remarks: string | null;
};

/** App\Http\Resources\MicronutrientRecordResource */
export type MicronutrientRecord = {
    id: number;
    type: EnumValue;
    dose: string | null;
    date_given: string;
    given_by?: string;
};

export type MicronutrientStatus = Record<
    'vitamin_a' | 'deworming',
    { last: string | null; due: boolean; due_date: string | null }
>;

/** ChildHealthController::summary() row. */
export type ChildSummary = {
    id: number;
    resident_code: string;
    full_name: string;
    sex: string;
    age_months: number;
    purok: string;
    epi: { given: number; due: number; overdue: number; total: number };
    nutrition: EnumValue<NutritionalStatus> | null;
    last_weighed: string | null;
    vitamin_a_due: boolean;
};

export type { ScheduledDose };

// --------------------------------------------------------------- referrals

/** App\Http\Resources\ReferralResource */
export type Referral = {
    id: number;
    referral_no: string;
    resident?: {
        id: number;
        resident_code: string;
        full_name: string;
        age: number;
        sex: EnumValue;
    };
    facility?: { id: number; name: string; type: EnumValue };
    referred_by?: string;
    referred_at: string | null;
    urgency: EnumValue<ReferralUrgency>;
    status: EnumValue<ReferralStatus>;
    completed_at: string | null;
    restricted: boolean;
    /** Detail page only. */
    reason?: string;
    feedback_note?: string | null;
    history?: {
        from: EnumValue<ReferralStatus> | null;
        to: EnumValue<ReferralStatus>;
        by: string;
        note: string | null;
        at: string | null;
    }[];
};

// --------------------------------------------------------------- inventory

/** App\Http\Resources\MedicineBatchResource */
export type MedicineBatch = {
    id: number;
    batch_no: string;
    expiry_date: string;
    days_to_expiry: number;
    expiry_band: ExpiryBand;
    quantity_received: number;
    quantity_on_hand: number;
    received_at: string;
    source: string | null;
};

/** App\Http\Resources\MedicineResource */
export type Medicine = {
    id: number;
    generic_name: string;
    brand_name: string | null;
    display_name: string;
    form: EnumValue;
    strength: string;
    unit: string;
    reorder_level: number;
    is_active: boolean;
    usable_on_hand?: number;
    expired_on_hand?: number;
    nearest_expiry?: string | null;
    batches?: MedicineBatch[];
};

/** App\Http\Resources\StockMovementResource */
export type StockMovement = {
    id: number;
    type: EnumValue<StockMovementType>;
    quantity: number;
    batch_no?: string;
    resident?: { id: number; full_name: string } | null;
    user?: string;
    reason: string | null;
    created_at: string | null;
};

// ---------------------------------------------------- appointments & programs

/** App\Http\Resources\AppointmentResource */
export type Appointment = {
    id: number;
    resident?: { id: number; resident_code: string; full_name: string };
    type?: { id: number; code: string; name: string; color: string };
    scheduled_at: string | null;
    assigned_to?: { id: number; name: string } | null;
    status: EnumValue<AppointmentStatus>;
    is_generated: boolean;
    edited_manually: boolean;
    health_visit_id: number | null;
    notes?: string | null;
};

/** App\Http\Resources\HealthProgramResource */
export type HealthProgram = {
    id: number;
    title: string;
    type?: { id: number; name: string; color: string };
    description: string | null;
    start_at: string | null;
    end_at: string | null;
    venue: string;
    target_group: EnumValue;
    status: EnumValue<ProgramStatus>;
    puroks?: { id: number; name: string }[];
    staff?: { id: number; name: string }[];
    attendance_count?: number;
};

/** CalendarEventsController JSON (FullCalendar event input). */
export type CalendarEvent = {
    id: string;
    title: string;
    start: string;
    end: string;
    color: string;
    url?: string;
    extendedProps:
        | {
              kind: 'appointment';
              appointment_id: number;
              resident_id: number;
              status: AppointmentStatus;
          }
        | {
              kind: 'program';
              program_id: number;
              venue: string;
              status: ProgramStatus;
          };
};

export type ResidentOption = Option & {
    code: string;
    age: number;
    purok: string;
};
