<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed } from 'vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { show as salaryShow } from '@/routes/employees/salary';
import { index } from '@/routes/salary';
import type { EmployeeBrief, IdName, Paginated } from '@/types';

type SalaryComponent = {
    name: string;
    type: string;
    amount: number;
};

type SalaryRow = EmployeeBrief & {
    gross: number | null;
    effective_date: string | null;
    components: SalaryComponent[];
    revisions_count: number;
};

const props = defineProps<{
    employees: Paginated<SalaryRow>;
    filters: {
        search?: string;
        department_id?: string;
    };
    departments: IdName[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Salary Structure', href: index() }],
    },
});

const { money, date } = useFormat();
const { can } = usePermissions();

const { filters, reset } = useQueryFilters(
    () => index.url(),
    {
        search: props.filters.search ?? '',
        department_id: props.filters.department_id
            ? Number(props.filters.department_id)
            : null,
    },
    { debounced: ['search'] },
);

const departmentOptions = computed(() =>
    props.departments.map((department) => ({
        value: department.id,
        label: department.name,
    })),
);

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Employee', primary: true },
    { key: 'department', label: 'Department' },
    { key: 'components', label: 'Components' },
    { key: 'effective_date', label: 'In effect from' },
    { key: 'revisions_count', label: 'Revisions', align: 'right' },
    { key: 'gross', label: 'Gross monthly salary', align: 'right' },
];

const earnings = (row: SalaryRow): SalaryComponent[] =>
    row.components.filter((component) => component.type === 'earning');

const deductionTotal = (row: SalaryRow): number =>
    row.components
        .filter((component) => component.type === 'deduction')
        .reduce((total, component) => total + Number(component.amount), 0);
</script>

<template>
    <Head title="Salary Structure" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Salary Structure"
            description="The salary in effect today for each current employee. Open an employee to see the components, revise the salary and read the full history."
        />

        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
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
                :options="departmentOptions"
                placeholder="All departments"
                aria-label="Department"
            />
        </div>

        <DataTable :columns="columns" :rows="employees.data">
            <template #cell-name="{ row }">
                <EmployeeCell
                    :name="row.name"
                    :code="row.code"
                    :subtitle="row.designation"
                    :photo-url="row.photo_url"
                    :href="salaryShow(row.id)"
                />
            </template>
            <template #cell-department="{ row }">
                {{ row.department ?? '-' }}
            </template>
            <template #cell-components="{ row }">
                <template v-if="row.gross !== null">
                    <span class="block max-w-[36ch]">
                        {{
                            earnings(row)
                                .map((component) => component.name)
                                .join(', ')
                        }}
                    </span>
                    <span
                        v-if="deductionTotal(row) > 0"
                        class="tabular block text-xs text-muted-foreground"
                    >
                        {{ money(-deductionTotal(row)) }} recurring deductions
                    </span>
                </template>
                <span v-else class="text-muted-foreground">-</span>
            </template>
            <template #cell-effective_date="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.effective_date) }}
                </span>
            </template>
            <template #cell-revisions_count="{ row }">
                <span class="tabular">{{ row.revisions_count }}</span>
            </template>
            <template #cell-gross="{ row }">
                <span
                    v-if="row.gross !== null"
                    class="tabular font-medium whitespace-nowrap"
                >
                    {{ money(row.gross) }}
                </span>
                <StatusBadge v-else tone="warning">No salary set</StatusBadge>
            </template>
            <template #actions="{ row }">
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="salaryShow(row.id)"
                        :aria-label="`${row.gross === null && can('payroll.manage') ? 'Set salary for' : 'Salary of'} ${row.name}`"
                    >
                        {{
                            row.gross === null && can('payroll.manage')
                                ? 'Set salary'
                                : 'View salary'
                        }}
                    </Link>
                </Button>
            </template>
            <template #empty>
                <div
                    class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                >
                    <p class="text-sm font-medium">No employees match</p>
                    <p class="max-w-[48ch] text-sm text-muted-foreground">
                        Try a different search, or clear the filters to see
                        every current employee.
                    </p>
                    <Button variant="outline" size="sm" @click="reset">
                        Clear filters
                    </Button>
                </div>
            </template>
        </DataTable>

        <DataPagination :page="employees" noun="employees" />
    </div>
</template>
