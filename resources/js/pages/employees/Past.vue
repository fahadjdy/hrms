<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ReceiptText, Search } from '@lucide/vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { statusTone } from '@/lib/status';
import { past, show } from '@/routes/employees';
import { show as settlementShow } from '@/routes/final-settlements';
import type { EmployeeBrief, IdName, Paginated } from '@/types';

type PastEmployeeRow = EmployeeBrief & {
    joining_date: string;
    last_working_date: string | null;
    exit_type: string | null;
    exit_reason: string | null;
    outstanding_borrow: number;
    settlement_status: string | null;
};

const props = defineProps<{
    employees: Paginated<PastEmployeeRow>;
    filters: {
        search?: string;
        department_id?: string;
    };
    departments: IdName[];
    totals: {
        past_employees: number;
        outstanding_borrow: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Past Employees', href: past() }],
    },
});

const { date, money, number } = useFormat();
const { can } = usePermissions();

const { filters, reset } = useQueryFilters(
    () => past.url(),
    {
        search: props.filters.search ?? '',
        department_id: props.filters.department_id
            ? Number(props.filters.department_id)
            : null,
    },
    { debounced: ['search'] },
);

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Employee', primary: true },
    { key: 'department', label: 'Department' },
    { key: 'joining_date', label: 'Joined' },
    { key: 'last_working_date', label: 'Last working day' },
    { key: 'exit_reason', label: 'Exit reason' },
    { key: 'outstanding_borrow', label: 'Outstanding borrow', align: 'right' },
    { key: 'settlement_status', label: 'Final settlement' },
];

const settlementLabel: Record<string, string> = {
    draft: 'Draft',
    finalized: 'Finalized',
    paid: 'Paid',
};
</script>

<template>
    <Head title="Past Employees" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Past Employees"
            description="People who have left the company. Their attendance, salary, payroll, borrow and documents stay on record, and they are left out of new payrolls."
        />

        <div class="grid gap-3 sm:grid-cols-2 lg:max-w-2xl">
            <StatCard
                label="Past employees"
                :value="number(totals.past_employees, 0)"
                hint="Everyone who has left so far"
            />
            <StatCard
                label="Outstanding borrow"
                :value="money(totals.outstanding_borrow)"
                :tone="totals.outstanding_borrow > 0 ? 'warning' : 'neutral'"
                hint="Still owed by past employees"
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
                    aria-label="Search past employees"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.department_id"
                :options="
                    departments.map((department) => ({
                        value: department.id,
                        label: department.name,
                    }))
                "
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
                    :href="show(row.id)"
                />
            </template>
            <template #cell-department="{ row }">
                {{ row.department ?? '-' }}
            </template>
            <template #cell-joining_date="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.joining_date) }}
                </span>
            </template>
            <template #cell-last_working_date="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.last_working_date) }}
                </span>
            </template>
            <template #cell-exit_reason="{ row }">
                <span v-if="row.exit_type || row.exit_reason">
                    {{ row.exit_type ?? '' }}
                    <span
                        v-if="row.exit_reason"
                        class="block text-xs text-muted-foreground"
                    >
                        {{ row.exit_reason }}
                    </span>
                </span>
                <span v-else>-</span>
            </template>
            <template #cell-outstanding_borrow="{ row }">
                <span
                    class="tabular"
                    :class="
                        row.outstanding_borrow > 0
                            ? 'font-medium text-warning'
                            : 'text-muted-foreground'
                    "
                >
                    {{ money(row.outstanding_borrow) }}
                </span>
            </template>
            <template #cell-settlement_status="{ row }">
                <StatusBadge
                    :tone="
                        row.settlement_status
                            ? statusTone('settlement', row.settlement_status)
                            : 'neutral'
                    "
                >
                    {{
                        row.settlement_status
                            ? (settlementLabel[row.settlement_status] ??
                              row.settlement_status)
                            : 'Not started'
                    }}
                </StatusBadge>
            </template>
            <template v-if="can('settlements.manage')" #actions="{ row }">
                <Button variant="ghost" size="icon-sm" as-child>
                    <Link
                        :href="settlementShow(row.id)"
                        :aria-label="`Final settlement of ${row.name}`"
                        :title="`Final settlement of ${row.name}`"
                    >
                        <ReceiptText />
                    </Link>
                </Button>
            </template>
            <template #empty>
                <div
                    class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                >
                    <template
                        v-if="filters.search || filters.department_id !== null"
                    >
                        <p class="text-sm font-medium">
                            No past employees match
                        </p>
                        <p class="max-w-[48ch] text-sm text-muted-foreground">
                            Try a different search, or clear the filters to see
                            everyone who has left.
                        </p>
                        <Button variant="outline" size="sm" @click="reset">
                            Clear filters
                        </Button>
                    </template>
                    <template v-else>
                        <p class="text-sm font-medium">No past employees</p>
                        <p class="max-w-[48ch] text-sm text-muted-foreground">
                            When someone leaves, use "Leave company" on their
                            profile. They move here with all of their history.
                        </p>
                    </template>
                </div>
            </template>
        </DataTable>

        <DataPagination :page="employees" noun="past employees" />
    </div>
</template>
