import type { RoleName } from '@/types/models';

/** Shared `auth.user` prop (HandleInertiaRequests::sharedUser). */
export type User = {
    id: number;
    username: string;
    name: string;
    email: string | null;
    role: { name: RoleName; label: string };
    must_change_password: boolean;
    two_factor_enabled: boolean;
    avatar?: string;
};

export type Auth = {
    user: User;
};

/** Shared `can` prop — UI hints only; the server re-checks every action. */
export type Can = {
    viewResidents: boolean;
    createResidents: boolean;
    updateResidents: boolean;
    archiveResidents: boolean;
    forceDeleteResidents: boolean;
    manageHouseholds: boolean;
    recordConsents: boolean;
    viewHealthRecords: boolean;
    recordHealth: boolean;
    viewReports: boolean;
    exportReports: boolean;
    manageUsers: boolean;
    viewAuditLogs: boolean;
    manageBackups: boolean;
    manageSettings: boolean;
    geotagHouseholds: boolean;
    viewHouseholdMap: boolean;
    viewAggregateMap: boolean;
    viewDiseases: boolean;
    recordDiseases: boolean;
    classifyDiseases: boolean;
    viewOutbreaks: boolean;
    manageOutbreaks: boolean;
    manageMaternal: boolean;
    manageReferrals: boolean;
    viewInventory: boolean;
    dispenseMedicine: boolean;
    manageInventory: boolean;
    manageAppointments: boolean;
    viewPrograms: boolean;
    managePrograms: boolean;
    manageSchedules: boolean;
};

/** One bell entry (HealthNotification payload, see app/Notifications). */
export type BellNotification = {
    id: string;
    category: string;
    title: string;
    message: string;
    url: string | null;
    level: 'info' | 'warning' | 'critical';
    read_at: string | null;
    created_at: string | null;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
