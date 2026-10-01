<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import MonthNavigator from '@/components/MonthNavigator.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionCard from '@/components/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { show as employeeShow } from '@/routes/employees';
import { reports as payrollReports } from '@/routes/payroll';
import { exportMethod, index } from '@/routes/reports';
import type {
    AttendanceSummary,
    EmployeeBrief,
    IdName,
    Paginated,
} from '@/types';

type ReportRow = EmployeeBrief & {
    summary?: AttendanceSummary;
    borrow_count?: number;
    total_borrowed?: number;
    total_recovered?: number;
    total_outstanding?: number;
};

const props = defineProps<{
    report: 'attendance' | 'borrow';
    month: string;
    monthLabel: string;
    filters: { department_id: number | string | null; search: string };
    employees: Paginated<ReportRow>;
    departments: IdName[];
    payrolls: { id: number; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reports', href: index() }],
    },
});

const { money, minutes, percent } = useFormat();
const { can } = usePermissions();

const { filters } = useQueryFilters(
    () => index.url(),
    {
        report: props.report as string,
        month: props.month,
        department_id: props.filters.department_id
            ? Number(props.filters.department_id)
            : null,
        search: props.filters.search ?? '',
    },
    { debounced: ['search'] },
);

const reportTypes = [
    { value: 'attendance', label: 'Attendance report' },
    { value: 'borrow', label: 'Borrow report' },
];

const attendanceColumns: DataTableColumn[] = [
    { key: 'name', label: 'Employee', primary: true },
    { key: 'working_days', label: 'Working days', align: 'right' },
    { key: 'present', label: 'Present', align: 'right' },
    { key: 'absent', label: 'Absent', align: 'right' },
    { key: 'half_day', label: 'Half day', align: 'right' },
    { key: 'paid_leave', label: 'Paid leave', align: 'right' },
    { key: 'unpaid_leave', label: 'Unpaid leave', align: 'right' },
    { key: 'wfh', label: 'WFH', align: 'right' },
    { key: 'late', label: 'Late', align: 'right' },
    { key: 'required_minutes', label: 'Required', align: 'right' },
    { key: 'worked_minutes', label: 'Worked', align: 'right' },
    { key: 'short_minutes', label: 'Short', align: 'right' },
    { key: 'overtime_minutes', label: 'Overtime', align: 'right' },
    { key: 'attendance_rate', label: 'Attendance rate', align: 'right' },
];

const borrowColumns: DataTableColumn[] = [
    { key: 'name', label: 'Employee', primary: true },
    { key: 'department', label: 'Department' },
    { key: 'borrow_count', label: 'Borrow records', align: 'right' },
    { key: 'total_borrowed', label: 'Total borrowed', align: 'right' },
    { key: 'total_recovered', label: 'Total recovered', align: 'right' },
    { key: 'total_outstanding', label: 'Outstanding', align: 'right' },
];

const columns = computed(() =>
    props.report === 'attendance' ? attendanceColumns : borrowColumns,
);

const dayKeys = [
    'working_days',
    'present',
    'absent',
    'half_day',
    'paid_leave',
    'unpaid_leave',
    'wfh',
    'late',
] as const;

const minuteKeys = [
    'required_minutes',
    'worked_minutes',
    'short_minutes',
    'overtime_minutes',
] as const;

const moneyKeys = [
    'total_borrowed',
    'total_recovered',
    'total_outstanding',
] as const;

/** The CSV carries every employee that matches the filters, not just this page. */
const exportUrl = computed(() =>
    exportMethod.url(props.report, {
        query: {
            month: props.report === 'attendance' ? props.month : undefined,
            department_id: filters.department_id ?? undefined,
            search: filters.search || undefined,
        },
    }),
);

const payrollId = ref<number | null>(props.payrolls[0]?.id ?? null);

const payrollExportUrl = computed(() =>
    payrollId.value === null
        ? null
        : exportMethod.url('payroll', {
              query: { payroll_id: payrollId.value },
          }),
);
</script>

<template>
    <Head title="Reports" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Reports"
            description="Per-employee attendance and borrow totals. Each report can be downloaded as a CSV file for a spreadsheet."
        >
            <Button variant="outline" as-child>
                <a :href="exportUrl" download>
                    <Download />
                    Download CSV
                </a>
            </Button>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-2">
            <div
                role="group"
                aria-label="Report"
                class="inline-flex rounded-md border bg-card p-0.5"
            >
                <button
                    v-for="type in reportTypes"
                    :key="type.value"
                    type="button"
                    :aria-pressed="filters.report === type.value"
                    class="rounded-[5px] px-3 py-1.5 text-sm font-medium outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    :class="
                        filters.report === type.value
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="filters.report = type.value"
                >
                    {{ type.label }}
                </button>
            </div>
            <MonthNavigator
                v-if="report === 'attendance'"
                v-model="filters.month"
            />
        </div>

        <div class="grid gap-2 sm:grid-cols-2 lg:max-w-2xl">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    type="search"
                    placeholder="Name, ID or email"
                    aria-label="Search employees"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.department_id"
                :options="
                    departments.map((d) => ({ value: d.id, label: d.name }))
                "
                placeholder="All departments"
                aria-label="Department"
            />
        </div>

        <p class="text-sm text-muted-foreground">
            <template v-if="report === 'attendance'">
                Attendance for {{ monthLabel }}, current employees. Attendance
                rate is attended days (paid leave included, a half day counts as
                half) over the working days that have passed.
            </template>
            <template v-else>
                Every employee with a borrow or advance on record, including
                past employees. Borrowed is what was issued; outstanding is what
                is still owed.
            </template>
        </p>

        <DataTable
            :columns="columns"
            :rows="employees.data"
            :empty-title="
                report === 'attendance'
                    ? 'No employees match'
                    : 'No borrow records match'
            "
            empty-description="Try a different search or department."
        >
            <template #cell-name="{ row }">
                <EmployeeCell
                    :name="row.name"
                    :code="row.code"
                    :subtitle="report === 'attendance' ? row.department : null"
                    :photo-url="row.photo_url"
                    :href="
                        can('employees.view') ? employeeShow(row.id) : undefined
                    "
                />
            </template>
            <template #cell-department="{ row }">
                {{ row.department ?? '-' }}
            </template>

            <template v-for="key in dayKeys" #[`cell-${key}`]="{ row }">
                <span class="tabular">{{ row.summary?.[key] ?? 0 }}</span>
            </template>
            <template v-for="key in minuteKeys" #[`cell-${key}`]="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ minutes(row.summary?.[key]) }}
                </span>
            </template>
            <template #cell-attendance_rate="{ row }">
                <span class="tabular font-medium">
                    {{ percent(row.summary?.attendance_rate) }}
                </span>
            </template>

            <template #cell-borrow_count="{ row }">
                <span class="tabular">{{ row.borrow_count ?? 0 }}</span>
            </template>
            <template v-for="key in moneyKeys" #[`cell-${key}`]="{ row }">
                <span
                    class="tabular whitespace-nowrap"
                    :class="key === 'total_outstanding' ? 'font-medium' : ''"
                >
                    {{ money(row[key]) }}
                </span>
            </template>
        </DataTable>

        <DataPagination :page="employees" noun="employees" />

        <SectionCard
            title="Payroll export"
            description="One row per employee with every earning, deduction and the net payable of a payroll."
            class="max-w-3xl"
        >
            <div
                v-if="payrolls.length > 0"
                class="flex flex-col gap-3 sm:flex-row sm:items-end"
            >
                <div class="grid flex-1 gap-1.5">
                    <label for="payroll-export" class="text-sm font-medium">
                        Payroll
                    </label>
                    <NativeSelect
                        id="payroll-export"
                        v-model="payrollId"
                        :options="
                            payrolls.map((p) => ({
                                value: p.id,
                                label: p.label,
                            }))
                        "
                    />
                </div>
                <Button
                    variant="outline"
                    :as-child="payrollExportUrl !== null"
                    :disabled="payrollExportUrl === null"
                >
                    <a
                        v-if="payrollExportUrl"
                        :href="payrollExportUrl"
                        download
                    >
                        <Download />
                        Download payroll CSV
                    </a>
                    <template v-else>
                        <Download />
                        Download payroll CSV
                    </template>
                </Button>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No payroll has been calculated yet. Run a payroll to export it.
            </p>
            <p
                v-if="can('payroll.view')"
                class="mt-4 text-sm text-muted-foreground"
            >
                For month-by-month totals and the department breakdown, open
                <Link
                    :href="payrollReports()"
                    class="text-foreground underline underline-offset-4"
                >
                    Payroll Reports</Link
                >.
            </p>
        </SectionCard>
    </div>
</template>
