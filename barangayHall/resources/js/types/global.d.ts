import type { Auth, BellNotification, Can } from '@/types/auth';
import type { DuplicateMatch, HouseholdOption } from '@/types/models';
import type { FlashToast } from '@/types/ui';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            healthCenter: {
                barangay: string;
                municipality: string;
                province: string;
            };
            auth: Auth;
            can: Can;
            sidebarOpen: boolean;
            unreadNotifications: number;
            /** Only present after a partial reload that asks for it. */
            notificationsLatest?: BellNotification[];
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: FlashToast;
            duplicates?: DuplicateMatch[];
            temporaryPassword?: { username: string; password: string };
            createdHousehold?: HouseholdOption | null;
        };
    }
}
