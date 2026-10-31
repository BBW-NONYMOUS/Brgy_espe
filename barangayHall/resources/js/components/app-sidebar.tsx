import { Link, usePage } from '@inertiajs/react';
import {
    Baby,
    BarChart3,
    CalendarDays,
    ClipboardList,
    ClipboardPlus,
    DatabaseBackup,
    Home,
    LayoutGrid,
    Map as MapIcon,
    Package,
    ScrollText,
    Send,
    Settings2,
    Siren,
    Stethoscope,
    UserCog,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as appointments } from '@/routes/appointments';
import { index as auditLogs } from '@/routes/audit-logs';
import { index as backups } from '@/routes/backups';
import { index as childHealth } from '@/routes/child-health';
import { index as diseaseCases } from '@/routes/disease-cases';
import { index as healthSettings } from '@/routes/health-settings';
import { index as households } from '@/routes/households';
import { index as map } from '@/routes/map';
import { index as medicines } from '@/routes/medicines';
import { index as outbreakAlerts } from '@/routes/outbreak-alerts';
import { index as pregnancies } from '@/routes/pregnancies';
import { index as programs } from '@/routes/programs';
import { index as referrals } from '@/routes/referrals';
import { index as reports } from '@/routes/reports';
import { index as residents } from '@/routes/residents';
import { index as users } from '@/routes/users';
import { index as visits } from '@/routes/visits';
import type { NavItem } from '@/types';

/** Keep only the items whose condition is true. */
function items(...entries: [boolean, NavItem][]): NavItem[] {
    return entries.filter(([show]) => show).map(([, item]) => item);
}

export function AppSidebar() {
    const { can } = usePage().props;

    // Grouped as in featured.md; each item follows the user's permissions
    // (the server enforces them too). Empty groups are not rendered.
    const groups: { label: string; items: NavItem[] }[] = [
        {
            label: 'Overview',
            items: items(
                [
                    true,
                    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
                ],
                // Staff: appointments + programs; Officials: the program schedule.
                [
                    can.manageAppointments || can.viewPrograms,
                    {
                        title: 'Calendar',
                        href: can.manageAppointments
                            ? appointments({ query: { view: 'calendar' } })
                            : programs({ query: { view: 'calendar' } }),
                        icon: CalendarDays,
                    },
                ],
            ),
        },
        {
            label: 'Records',
            items: items(
                [
                    can.viewResidents,
                    { title: 'Residents', href: residents(), icon: Users },
                ],
                [
                    can.viewResidents,
                    { title: 'Households', href: households(), icon: Home },
                ],
                [
                    can.viewHouseholdMap || can.viewAggregateMap,
                    { title: 'Map', href: map(), icon: MapIcon },
                ],
            ),
        },
        {
            label: 'Health',
            items: items(
                [
                    can.viewHealthRecords,
                    { title: 'Visits', href: visits(), icon: ClipboardPlus },
                ],
                [
                    can.viewHealthRecords,
                    {
                        title: 'Maternal',
                        href: pregnancies(),
                        icon: Stethoscope,
                    },
                ],
                [
                    can.viewHealthRecords,
                    { title: 'Child health', href: childHealth(), icon: Baby },
                ],
                [
                    can.viewDiseases,
                    { title: 'Diseases', href: diseaseCases(), icon: Siren },
                ],
                [
                    !can.viewDiseases && can.viewOutbreaks,
                    {
                        title: 'Outbreak alerts',
                        href: outbreakAlerts(),
                        icon: Siren,
                    },
                ],
                [
                    can.viewHealthRecords,
                    { title: 'Referrals', href: referrals(), icon: Send },
                ],
            ),
        },
        {
            label: 'Services',
            items: items(
                [
                    can.manageAppointments,
                    {
                        title: 'Appointments',
                        href: appointments(),
                        icon: CalendarDays,
                    },
                ],
                [
                    can.viewPrograms,
                    {
                        title: 'Programs',
                        href: programs(),
                        icon: ClipboardList,
                    },
                ],
                [
                    can.viewInventory,
                    { title: 'Inventory', href: medicines(), icon: Package },
                ],
            ),
        },
        {
            label: 'Reports',
            items: items([
                can.viewReports,
                { title: 'Reports', href: reports(), icon: BarChart3 },
            ]),
        },
        {
            label: 'Admin',
            items: items(
                [
                    can.manageUsers,
                    { title: 'Users', href: users(), icon: UserCog },
                ],
                [
                    can.viewAuditLogs,
                    {
                        title: 'Audit logs',
                        href: auditLogs(),
                        icon: ScrollText,
                    },
                ],
                [
                    can.manageBackups,
                    { title: 'Backups', href: backups(), icon: DatabaseBackup },
                ],
                [
                    can.manageSettings || can.manageSchedules,
                    {
                        title: 'Settings',
                        href: healthSettings(),
                        icon: Settings2,
                    },
                ],
            ),
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {groups
                    .filter((group) => group.items.length > 0)
                    .map((group) => (
                        <NavMain
                            key={group.label}
                            items={group.items}
                            label={group.label}
                        />
                    ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
