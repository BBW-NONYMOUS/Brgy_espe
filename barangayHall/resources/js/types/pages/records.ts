/**
 * Page-prop shapes for the records screens built in Phase 6 (map,
 * household family profile, resident profile extras, visits list).
 * They mirror MapController, HouseholdController@show,
 * ResidentController@show and VisitListController.
 */
import type {
    EnumValue,
    HealthOptions,
    HouseholdMarker,
    MapConfig,
    MapFlag,
    Option,
    PurokSummary,
} from '@/types';

/** healthOptions on the resident profile (core keys + Phase 4 additions). */
export type ProfileHealthOptions = HealthOptions & {
    alertTypes: Option<string>[];
    alertSeverities: Option<string>[];
    riskFactors: Option[];
    nutritionalStatuses: Option<string>[];
    micronutrientTypes: Option<string>[];
    breastfeeding: Option<string>[];
    consentTypes: Option<string>[];
};

export type MapPageProps = {
    mode: 'exact' | 'aggregate';
    map: MapConfig;
    filters: { purok_id: number | null; flag: MapFlag | null; days: number };
    options: { puroks: Option[]; flags: MapFlag[] };
    markers?: HouseholdMarker[] | null;
    notGeotagged?:
        | { id: number; household_no: string; purok: string; address: string }[]
        | null;
    purokSummary?: PurokSummary[];
    canGeotag: boolean;
};

/** HouseholdController::memberHealth() */
export type MemberHealth = {
    pregnant: boolean;
    high_risk: boolean;
    under_five: boolean;
    critical_alerts: number;
    conditions: string[];
    active_cases: number;
    family_planning: string | null;
    family_planning_consent: boolean;
};

export type HouseholdGeotag = {
    is_geotagged: boolean;
    geotagged_at: string | null;
    geotagged_by: string | null;
    accuracy_m: number | null;
    has_consent: boolean;
    can_geotag: boolean;
};

export type WaterSourceOption = Option & { safe: boolean };
export type ToiletTypeOption = Option & { sanitary: boolean };

/** VisitListController row. Never carries complaint, diagnosis or notes. */
export type VisitListRow = {
    id: number;
    visit_date: string;
    visit_type: string | null;
    resident: {
        id: number;
        resident_code: string;
        full_name: string;
        age: number;
    };
    purok: string | null;
    attended_by: string | null;
    has_vitals: boolean;
    maternal: EnumValue | null;
};
