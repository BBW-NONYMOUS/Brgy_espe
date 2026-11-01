import { usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { AuthLayoutProps } from '@/types';

/**
 * Login / change-password layout: health-center panel on the left
 * (desktop and landscape tablet), form on the right.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { healthCenter } = usePage().props;

    return (
        <div className="grid min-h-dvh lg:grid-cols-2">
            <aside className="relative hidden flex-col justify-between overflow-hidden bg-primary p-10 text-primary-foreground lg:flex">
                <img
                    src="/images/barangay-hall.jpg"
                    alt=""
                    className="pointer-events-none absolute inset-0 size-full object-cover"
                    aria-hidden
                />
                {/* Tint keeps the panel text readable over the photo. */}
                <div
                    className="pointer-events-none absolute inset-0 bg-primary/80"
                    aria-hidden
                />
                <div className="relative flex items-center gap-3">
                    <AppLogoIcon className="size-12 [--logo-pulse:var(--primary)]" />
                    <div className="leading-tight">
                        <p className="text-lg font-bold">
                            Barangay Health Center
                        </p>
                        <p className="opacity-90">
                            Brgy. {healthCenter.barangay},{' '}
                            {healthCenter.municipality}, {healthCenter.province}
                        </p>
                    </div>
                </div>

                <div className="relative max-w-md space-y-4">
                    <p className="text-4xl leading-tight font-bold">
                        Resident Health Profiling System
                    </p>
                    <p className="text-lg opacity-90">
                        Find a resident in seconds, keep health records up to
                        date, and prepare reports without counting by hand.
                    </p>
                </div>

                <p className="relative flex items-center gap-2 text-sm opacity-90">
                    <ShieldCheck className="size-5 shrink-0" aria-hidden />
                    Health information is protected under the Data Privacy Act
                    of 2012 (RA 10173).
                </p>
            </aside>

            <main className="flex items-center justify-center p-6 sm:p-10">
                <div className="w-full max-w-md space-y-6">
                    <div className="flex items-center gap-3 lg:hidden">
                        <span className="flex size-11 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-7 [--logo-pulse:var(--primary)]" />
                        </span>
                        <div className="leading-tight">
                            <p className="font-bold">Barangay Health Center</p>
                            <p className="text-sm text-muted-foreground">
                                Brgy. {healthCenter.barangay},{' '}
                                {healthCenter.municipality}
                            </p>
                        </div>
                    </div>
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold">{title}</h1>
                        {description && (
                            <p className="text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
