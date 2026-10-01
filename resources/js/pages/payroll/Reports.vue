<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionCard from '@/components/SectionCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { statusTone } from '@/lib/status';
import { index as payrollIndex, reports, show } from '@/routes/payroll';
import { exportMethod } from '@/routes/reports';

type PayrollRow = {
    id: number;
    label: string;
    status: string;
    status_label: string;
    employee_count: number;
    total_gross: number;
    total_earnings: number;
    total_deductions: number;
    total_borrow_given: number;
    total_borrow_recovery: number;
    total_net_payable: number;
};

type DepartmentRow = {
    department: string;
    employees: number;
    gross: number;
    overtime: number;
    bonus: number;
    attendance_deductions: number;
    short_hours: number;
    borrow_recovery: number;
    other_deductions: number;
    borrow_given: number;
    net_payable: number;
    is_total?: boolean;
};

const props = defineProps<{
    payrolls: PayrollRow[];
    selectedPayrollId: number | null;
    departments: DepartmentRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Payroll Reports', href: reports() }],
    },
});

const { money } = useFormat();
const { can } = usePermissions();

/** A deduction is money leaving the employee's pay, so it reads as a negative. */
const minus = (amount: number): string => (amount > 0 ? money(-amount) : '-');
const plus = (amount: number): string => (amount > 0 ? money(amount) : '-');

const selected = ref<number | null>(props.selectedPayrollId);

watch(
    () => props.selectedPayrollId,
    (value) => {
        selected.value = value;
    },
);

watch(selected, (value) => {
    if (value === null || value === props.selectedPayrollId) {
        return;
    }

    router.get(
        reports.url(),
        { payroll_id: value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['selectedPayrollId', 'departments'],
        },
    );
});

const selectedPayroll = computed(
    () =>
        props.payrolls.find((payroll) => payroll.id === selected.value) ?? null,
);

const payrollOptions = computed(() =>
    props.payrolls.map((payroll) => ({
        value: payroll.id,
        label: `${payroll.label} (${payroll.status_label})`,
    })),
);

const monthColumns: DataTableColumn[] = [
    { key: 'label', label: 'Month', primary: true },
    { key: 'status', label: 'Status' },
    { key: 'employee_count', label: 'Employees', align: 'right' },
    { key: 'total_gross', label: 'Gross salary', align: 'right' },
    { key: 'total_earnings', label: 'Total earnings', align: 'right' },
    { key: 'total_deductions', label: 'Deductions', align: 'right' },
    { key: 'total_borrow_given', label: 'Borrow given', align: 'right' },
    {
        key: 'total_borrow_recovery',
        label: 'Borrow recovery',
        align: 'right',
    },
    { key: 'total_net_payable', label: 'Net payable', align: 'right' },
];

const departmentColumns: DataTableColumn[] = [
    { key: 'department', label: 'Department', primary: true },
    { key: 'employees', label: 'Employees', align: 'right' },
    { key: 'gross', label: 'Gross salary', align: 'right' },
    { key: 'overtime', label: 'Overtime', align: 'right' },
    { key: 'bonus', label: 'Bonus and other earnings', align: 'right' },
    {
        key: 'attendance_deductions',
        label: 'Attendance and unpaid leave',
        align: 'right',
    },
    { key: 'short_hours', label: 'Short hours', align: 'right' },
    { key: 'borrow_recovery', label: 'Borrow recovery', align: 'right' },
    { key: 'other_deductions', label: 'Other deductions', align: 'right' },
    { key: 'borrow_given', label: 'Borrow given', align: 'right' },
    { key: 'net_payable', label: 'Net payable', align: 'right' },
];

const sumOf = (key: keyof DepartmentRow): number =>
    props.departments.reduce((total, row) => total + Number(row[key] ?? 0), 0);

/** The department rows closed by a total, so the columns can be checked against the payroll. */
const departmentRows = computed<DepartmentRow[]>(() =>
    props.departments.length < 2
        ? props.departments
        : [
              ...props.departments,
              {
                  department: 'Total',
                  employees: sumOf('employees'),
                  gross: sumOf('gross'),
                  overtime: sumOf('overtime'),
                  bonus: sumOf('bonus'),
                  attendance_deductions: sumOf('attendance_deductions'),
                  short_hours: sumOf('short_hours'),
                  borrow_recovery: sumOf('borrow_recovery'),
                  other_deductions: sumOf('other_deductions'),
                  borrow_given: sumOf('borrow_given'),
                  net_payable: sumOf('net_payable'),
                  is_total: true,
              },
          ],
);

const weight = (row: DepartmentRow): string =>
    row.is_total ? 'font-semibold' : '';
</script>

<template>
    <Head title="Payroll Reports" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Payroll Reports"
            description="Payroll totals month by month, and where one month's pay went by department. Borrow given is an advance and is reported separately from earnings."
        />

        <div v-if="payrolls.length === 0" class="rounded-lg border bg-card">
            <EmptyState
                title="No payroll to report on yet"
                description="Reports appear here once a payroll has been started and calculated."
            >
                <Button variant="outline" size="sm" as-child>
                    <Link :href="payrollIndex()">Go to payroll</Link>
                </Button>
            </EmptyState>
        </div>

        <template v-else>
            <section class="grid gap-3" aria-labelledby="monthly-heading">
                <div>
                    <h2 id="monthly-heading" class="text-sm font-semibold">
                        Month by month
                    </h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        The latest {{ payrolls.length }} payroll{{
                            payrolls.length === 1 ? '' : 's'
                        }}, newest first.
                    </p>
                </div>

                <DataTable :columns="monthColumns" :rows="payrolls">
                    <template #cell-label="{ row }">
                        <Link
                            :href="show(row.id)"
                            class="font-medium whitespace-nowrap underline-offset-4 hover:underline focus-visible:underline"
                        >
                            {{ row.label }}
                        </Link>
                    </template>
                    <template #cell-status="{ row }">
                        <StatusBadge :tone="statusTone('payroll', row.status)">
                            {{ row.status_label }}
                        </StatusBadge>
                    </template>
                    <template #cell-employee_count="{ row }">
                        <span class="tabular">{{ row.employee_count }}</span>
                    </template>
                    <template #cell-total_gross="{ row }">
                        <span class="tabular whitespace-nowrap">
                            {{ money(row.total_gross) }}
                        </span>
                    </template>
                    <template #cell-total_earnings="{ row }">
                        <span class="tabular whitespace-nowrap">
                            {{ money(row.total_earnings) }}
                        </span>
                    </template>
                    <template #cell-total_deductions="{ row }">
                        <span class="tabular whitespace-nowrap">
                            {{ minus(row.total_deductions) }}
                        </span>
                    </template>
                    <template #cell-total_borrow_given="{ row }">
                        <span class="tabular whitespace-nowrap">
                            {{
                                row.total_borrow_given > 0
                                    ? money(row.total_borrow_given, {
                                          signed: true,
                                      })
                                    : '-'
                            }}
                        </span>
                    </template>
                    <template #cell-total_borrow_recovery="{ row }">
                        <span class="tabular whitespace-nowrap">
                            {{ minus(row.total_borrow_recovery) }}
                        </span>
                    </template>
                    <template #cell-total_net_payable="{ row }">
                        <span class="tabular font-semibold whitespace-nowrap">
                            {{ money(row.total_net_payable) }}
                        </span>
                    </template>
                </DataTable>
            </section>

            <SectionCard
                title="Department breakdown"
                :description="
                    selectedPayroll
                        ? `How the ${selectedPayroll.label} payroll is split across departments.`
                        : 'Choose a payroll to see its split across departments.'
                "
            >
                <template #actions>
                    <NativeSelect
                        v-model="selected"
                        :options="payrollOptions"
                        aria-label="Payroll to break down by department"
                        class="w-auto min-w-[12rem]"
                    />
                    <Button
                        v-if="can('reports.view') && selected !== null"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <a
                            :href="
                                exportMethod.url('payroll', {
                                    query: { payroll_id: selected },
                                })
                            "
                        >
                            <Download />
                            Export CSV
                        </a>
                    </Button>
                </template>

                <DataTable
                    :columns="departmentColumns"
                    :rows="departmentRows"
                    row-key="department"
                    empty-title="Nothing calculated for this payroll"
                    empty-description="Calculate the payroll to see how it is split across departments."
                >
                    <template #cell-department="{ row }">
                        <span class="font-medium whitespace-nowrap">
                            {{ row.department }}
                        </span>
                    </template>
                    <template #cell-employees="{ row }">
                        <span class="tabular" :class="weight(row)">
                            {{ row.employees }}
                        </span>
                    </template>
                    <template #cell-gross="{ row }">
                        <span
                            class="tabular whitespace-nowrap"
                            :class="weight(row)"
                        >
                            {{ money(row.gross) }}
                        </span>
                    </template>
                    <template #cell-overtime="{ row }">
                        <span
                            class="tabular whitespace-nowrap"
                            :class="weight(row)"
                        >
                            {{ plus(row.overtime) }}
                        </span>
                    </template>
                    <template #cell-bonus="{ row }">
                        <span
                            class="tabular whitespace-nowrap"
                            :class="weight(row)"
                        >
                            {{ plus(row.bonus) }}
                        </span>
                    </template>
                    <template #cell-attendance_deductions="{ row }">
                        <span
                            class="tabular whitespace-nowrap"
                            :class="weight(row)"
                        >
                            {{ minus(row.attendance_deductions) }}
                        </span>
                    </template>
                    <template #cell-short_hours="{ row }">
                        <span
                            class="tabular whitespace-nowrap"
                            :class="weight(row)"
                        >
                            {{ minus(row.short_hours) }}
                        </span>
                    </template>
                    <template #cell-borrow_recovery="{ row }">
                        <span
                            class="tabular whitespace-nowrap"
                            :class="weight(row)"
                        >
                            {{ minus(row.borrow_recovery) }}
                        </span>
                    </template>
                    <template #cell-other_deductions="{ row }">
                        <span
                            class="tabular whitespace-nowrap"
                            :class="weight(row)"
                        >
                            {{ minus(row.other_deductions) }}
                        </span>
                    </template>
                    <template #cell-borrow_given="{ row }">
                        <span
                            class="tabular whitespace-nowrap"
                            :class="weight(row)"
                        >
                            {{
                                row.borrow_given > 0
                                    ? money(row.borrow_given, { signed: true })
                                    : '-'
                            }}
                        </span>
                    </template>
                    <template #cell-net_payable="{ row }">
                        <span class="tabular font-semibold whitespace-nowrap">
                            {{ money(row.net_payable) }}
                        </span>
                    </template>
                </DataTable>
            </SectionCard>
        </template>
    </div>
</template>
