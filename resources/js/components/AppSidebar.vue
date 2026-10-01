<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarCheck,
    CalendarOff,
    FileBarChart,
    HandCoins,
    History,
    LayoutGrid,
    ReceiptText,
    Settings,
    SlidersHorizontal,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, onUnmounted } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { index as adminCompanies } from '@/routes/admin/companies';
import { edit as adminSettings } from '@/routes/admin/settings';
import {
    calendar as attendanceCalendar,
    index as attendanceIndex,
} from '@/routes/attendance';
import { index as auditLogs } from '@/routes/audit-logs';
import { index as bonuses } from '@/routes/bonuses';
import { index as borrowRecoveries } from '@/routes/borrow-recoveries';
import { index as borrows } from '@/routes/borrows';
import { index as deductions } from '@/routes/deductions';
import { index as departments } from '@/routes/departments';
import { index as designations } from '@/routes/designations';
import { index as documents } from '@/routes/documents';
import {
    create as employeesCreate,
    index as employeesIndex,
    past as employeesPast,
} from '@/routes/employees';
import { index as finalSettlements } from '@/routes/final-settlements';
import { index as holidays } from '@/routes/holidays';
import { index as leaveBalances } from '@/routes/leave-balances';
import { index as leaveTypes } from '@/routes/leave-types';
import { index as leaves } from '@/routes/leaves';
import { index as overtime } from '@/routes/overtime';
import {
    index as payrollIndex,
    reports as payrollReports,
} from '@/routes/payroll';
import { index as reports } from '@/routes/reports';
import { index as salaryStructure } from '@/routes/salary';
import { index as salaryRevisions } from '@/routes/salary-revisions';
import { index as salarySlips } from '@/routes/salary-slips';
import { edit as attendanceSettings } from '@/routes/settings/attendance';
import { edit as companySettings } from '@/routes/settings/company';
import { edit as payrollSettings } from '@/routes/settings/payroll';
import { index as roles } from '@/routes/settings/roles';
import { index as shortHours } from '@/routes/short-hours';
import { edit as weeklyHolidays } from '@/routes/weekly-holidays';
import { index as workShifts } from '@/routes/work-shifts';
import type { NavGroup } from '@/types';

const page = usePage();
const { can } = usePermissions();

const isSuperAdmin = computed(() => page.props.auth.user?.is_super_admin);

const platformNav: NavGroup[] = [
    { title: 'Companies', icon: Building2, href: adminCompanies() },
    {
        title: 'Platform Settings',
        icon: SlidersHorizontal,
        href: adminSettings(),
    },
];

const companyNav: NavGroup[] = [
    { title: 'Dashboard', icon: LayoutGrid, href: dashboard() },
    {
        title: 'Employees',
        icon: Users,
        items: [
            {
                title: 'Active Employees',
                href: employeesIndex(),
                permission: 'employees.view',
            },
            {
                title: 'Past Employees',
                href: employeesPast(),
                permission: 'employees.view',
            },
            {
                title: 'Add Employee',
                href: employeesCreate(),
                permission: 'employees.manage',
            },
            {
                title: 'Departments',
                href: departments(),
                permission: 'employees.view',
            },
            {
                title: 'Designations',
                href: designations(),
                permission: 'employees.view',
            },
            {
                title: 'Salary History',
                href: salaryRevisions(),
                permission: 'payroll.view',
            },
            {
                title: 'Documents',
                href: documents(),
                permission: 'employees.view',
            },
        ],
    },
    {
        title: 'Attendance',
        icon: CalendarCheck,
        items: [
            {
                title: 'Daily Attendance',
                href: attendanceIndex(),
                permission: 'attendance.view',
            },
            {
                title: 'Attendance Calendar',
                href: attendanceCalendar(),
                matches: /^\/employees\/\d+\/attendance/,
                permission: 'attendance.view',
            },
            {
                title: 'Work Shifts',
                href: workShifts(),
                permission: 'attendance.view',
            },
            {
                title: 'Attendance Settings',
                href: attendanceSettings(),
                permission: 'settings.manage',
            },
            {
                title: 'Weekly Holidays',
                href: weeklyHolidays(),
                permission: 'attendance.view',
            },
            {
                title: 'Holidays',
                href: holidays(),
                permission: 'attendance.view',
            },
        ],
    },
    {
        title: 'Leave',
        icon: CalendarOff,
        items: [
            {
                title: 'Leave Records',
                href: leaves(),
                permission: 'leave.view',
            },
            {
                title: 'Leave Types',
                href: leaveTypes(),
                permission: 'leave.view',
            },
            {
                title: 'Leave Balance',
                href: leaveBalances(),
                permission: 'leave.view',
            },
        ],
    },
    {
        title: 'Payroll',
        icon: Wallet,
        items: [
            {
                title: 'Payroll',
                href: payrollIndex(),
                permission: 'payroll.view',
            },
            {
                title: 'Salary Structure',
                href: salaryStructure(),
                matches: /^\/employees\/\d+\/salary/,
                permission: 'payroll.view',
            },
            {
                title: 'Salary Revisions',
                href: salaryRevisions(),
                permission: 'payroll.view',
            },
            {
                title: 'Salary Slips',
                href: salarySlips(),
                permission: 'payroll.view',
            },
            {
                title: 'Payroll Reports',
                href: payrollReports(),
                permission: 'payroll.view',
            },
        ],
    },
    {
        title: 'Employee Finance',
        icon: HandCoins,
        items: [
            {
                title: 'Borrow / Advance',
                href: borrows(),
                permission: 'finance.view',
            },
            {
                title: 'Borrow Recovery',
                href: borrowRecoveries(),
                permission: 'finance.view',
            },
            { title: 'Overtime', href: overtime(), permission: 'finance.view' },
            {
                title: 'Short Hours',
                href: shortHours(),
                permission: 'finance.view',
            },
            {
                title: 'Deductions',
                href: deductions(),
                permission: 'finance.view',
            },
            { title: 'Bonuses', href: bonuses(), permission: 'finance.view' },
        ],
    },
    {
        title: 'Final Settlement',
        icon: ReceiptText,
        href: finalSettlements(),
        permission: 'settlements.manage',
    },
    {
        title: 'Reports',
        icon: FileBarChart,
        href: reports(),
        permission: 'reports.view',
    },
    {
        title: 'Settings',
        icon: Settings,
        items: [
            {
                title: 'Company',
                href: companySettings(),
                permission: 'settings.manage',
            },
            {
                title: 'Attendance',
                href: attendanceSettings(),
                permission: 'settings.manage',
            },
            {
                title: 'Payroll',
                href: payrollSettings(),
                permission: 'settings.manage',
            },
            { title: 'Leave', href: leaveTypes(), permission: 'leave.view' },
            {
                title: 'Work Shifts',
                href: workShifts(),
                permission: 'attendance.view',
            },
            {
                title: 'Roles & Permissions',
                href: roles(),
                permission: 'roles.manage',
            },
        ],
    },
    {
        title: 'Audit Logs',
        icon: History,
        href: auditLogs(),
        permission: 'audit.view',
    },
];

/** Only what the signed-in user is allowed to open. */
const groups = computed<NavGroup[]>(() => {
    if (isSuperAdmin.value) {
        return platformNav;
    }

    return companyNav
        .filter((group) => !group.permission || can(group.permission))
        .map((group) => ({
            ...group,
            items: group.items?.filter(
                (item) => !item.permission || can(item.permission),
            ),
        }))
        .filter((group) => group.href || (group.items?.length ?? 0) > 0);
});

const home = computed(() =>
    isSuperAdmin.value ? adminCompanies() : dashboard(),
);

// On a phone the sidebar is a drawer over the page: once the chosen page has
// loaded, close it so the page is what the user sees.
const { setOpenMobile } = useSidebar();
const stopClosingOnNavigate = router.on('navigate', () => setOpenMobile(false));

onUnmounted(stopClosingOnNavigate);
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="groups" />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
