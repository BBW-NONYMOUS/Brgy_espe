/**
 * Page props for the referral, inventory and notification screens. Shapes
 * mirror Health\ReferralController, Inventory\MedicineController and
 * NotificationController.
 */
import type { EnumValue, HealthVisit, Option, Paginated } from '@/types/models';
import type { BellNotification } from '@/types/auth';
import type {
    MedicalAlert,
    Medicine,
    MedicineBatch,
    Referral,
    ReferralStatus,
    ReferralUrgency,
    ResidentOption,
    StockMovement,
    StockMovementType,
} from '@/types/modules';

export type ReferralFilters = {
    search: string;
    status: ReferralStatus | null;
    urgency: ReferralUrgency | null;
    facility_id: number | null;
};

export type ReferralsIndexProps = {
    referrals: Paginated<Referral>;
    filters: ReferralFilters;
    options: {
        statuses: EnumValue<ReferralStatus>[];
        urgencies: EnumValue<ReferralUrgency>[];
        facilities: (Option & { type: string })[];
    };
    residentOptions?: ResidentOption[];
    canCreate: boolean;
};

export type ReferralShowProps = {
    referral: Referral;
    criticalAlerts: MedicalAlert[];
    visit: HealthVisit | null;
    nextStatuses: EnumValue<ReferralStatus>[];
};

export type InventoryFilters = {
    search: string;
    expiry: 'soon' | null;
    stock: 'low' | null;
};

export type InventoryIndexProps = {
    medicines: Medicine[];
    filters: InventoryFilters;
    options: { forms: EnumValue[] };
    residentOptions?: ResidentOption[];
    residentAlerts?: MedicalAlert[];
};

export type InventoryShowProps = {
    medicine: Medicine;
    batches: MedicineBatch[];
    movements: Paginated<StockMovement>;
    filters: { type: StockMovementType | null };
    options: {
        movementTypes: EnumValue<StockMovementType>[];
        forms: EnumValue[];
    };
};

/** Laravel LengthAwarePaginator JSON (not a ResourceCollection). */
export type PlainPaginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type NotificationsIndexProps = {
    notifications: PlainPaginator<BellNotification>;
    filters: { unread: boolean };
};
