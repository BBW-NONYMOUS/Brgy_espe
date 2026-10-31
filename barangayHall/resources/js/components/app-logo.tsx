import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { healthCenter } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-6 [--logo-pulse:var(--sidebar-primary)]" />
            </div>
            <div className="ml-1 grid flex-1 text-left leading-tight">
                <span className="truncate font-bold">
                    Brgy. {healthCenter.barangay}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    Health Center
                </span>
            </div>
        </>
    );
}
