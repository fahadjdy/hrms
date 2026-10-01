<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, Eye, Search } from '@lucide/vue';
import { computed } from 'vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { show as employeeShow } from '@/routes/employees';
import { index as payrollIndex } from '@/routes/payroll';
import { index, show } from '@/routes/salary-slips';
import type { Paginated } from '@/types';

type SlipRow = {
    id: number;
    slip_number: string;
    month: string;
    employee_id: number;
    employee_name: string;
    employee_code: string;
    department: string | null;
    net_payable: number;
    generated_at: string | null;
};

const props = defineProps<{
    slips: Paginated<SlipRow>;
    filters: {
        search?: string;
        payroll_id?: string;
    };
    payrolls: { id: number; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Salary Slips', href: index() }],
    },
});

const { money } = useFormat();
const { can } = usePermissions();

const { filters, reset } = useQueryFilters(
    () => index.url(),
    {
        search: props.filters.search ?? '',
        payroll_id: props.filters.payroll_id
            ? Number(props.filters.payroll_id)
            : null,
    },
    { debounced: ['search'] },
);

const payrollOptions = computed(() =>
    props.payrolls.map((payroll) => ({
        value: payroll.id,
        label: payroll.label,
    })),
);

const hasFilters = computed(
    () => filters.search !== '' || filters.payroll_id !== null,
);

const columns: DataTableColumn[] = [
    { key: 'employee_name', label: 'Employee', primary: true },
    { key: 'month', label: 'Salary month' },
    { key: 'slip_number', label: 'Slip number' },
    { key: 'department', label: 'Department' },
    { key: 'net_payable', label: 'Net payable', align: 'right' },
];
</script>

<template>
    <Head title="Salary Slips" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Salary Slips"
            description="PDF salary slips for finalized payrolls. Employees have no login, so slips are opened and shared by admin and HR users only."
        />

        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    type="search"
                    placeholder="Employee name or ID"
                    aria-label="Search salary slips"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.payroll_id"
                :options="payrollOptions"
                placeholder="All finalized months"
                aria-label="Salary month"
            />
        </div>

        <DataTable :columns="columns" :rows="slips.data">
            <template #cell-employee_name="{ row }">
                <Link
                    v-if="can('employees.view')"
                    :href="employeeShow(row.employee_id)"
                    class="font-medium underline-offset-4 hover:underline focus-visible:underline"
                >
                    {{ row.employee_name }}
                </Link>
                <span v-else class="font-medium">{{ row.employee_name }}</span>
                <span class="block text-xs text-muted-foreground">
                    {{ row.employee_code }}
                </span>
            </template>
            <template #cell-slip_number="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ row.slip_number }}
                </span>
            </template>
            <template #cell-department="{ row }">
                {{ row.department ?? '-' }}
            </template>
            <template #cell-net_payable="{ row }">
                <span class="tabular font-medium whitespace-nowrap">
                    {{ money(row.net_payable) }}
                </span>
            </template>
            <template #actions="{ row }">
                <Button variant="ghost" size="sm" as-child>
                    <a
                        :href="show.url(row.id)"
                        target="_blank"
                        rel="noopener"
                        :aria-label="`View salary slip of ${row.employee_name} for ${row.month} (opens in a new tab)`"
                    >
                        <Eye />
                        View
                    </a>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a
                        :href="show.url(row.id, { query: { download: 1 } })"
                        :aria-label="`Download salary slip of ${row.employee_name} for ${row.month}`"
                    >
                        <Download />
                        Download
                    </a>
                </Button>
            </template>
            <template #empty>
                <div
                    class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                >
                    <template v-if="hasFilters">
                        <p class="text-sm font-medium">No salary slips match</p>
                        <p class="max-w-[48ch] text-sm text-muted-foreground">
                            Try a different employee or salary month.
                        </p>
                        <Button variant="outline" size="sm" @click="reset">
                            Clear filters
                        </Button>
                    </template>
                    <template v-else>
                        <p class="text-sm font-medium">No salary slips yet</p>
                        <p class="max-w-[52ch] text-sm text-muted-foreground">
                            A slip is created for every employee when a payroll
                            is finalized. Finalize a payroll to see its slips
                            here.
                        </p>
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="payrollIndex()">Go to payroll</Link>
                        </Button>
                    </template>
                </div>
            </template>
        </DataTable>

        <DataPagination :page="slips" noun="salary slips" />
    </div>
</template>
