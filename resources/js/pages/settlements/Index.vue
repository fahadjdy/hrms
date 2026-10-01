<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Search } from '@lucide/vue';
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
import { useQueryFilters } from '@/composables/useQueryFilters';
import { statusTone } from '@/lib/status';
import { index, show } from '@/routes/final-settlements';
import type { EmployeeBrief, Paginated } from '@/types';

type SettlementRow = EmployeeBrief & {
    last_working_date: string | null;
    exit_type: string | null;
    outstanding_borrow: number;
    settlement: { status: string; net_amount: number } | null;
};

const props = defineProps<{
    employees: Paginated<SettlementRow>;
    filters: { search?: string; status?: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Final Settlement', href: index() }],
    },
});

const { date, money } = useFormat();

const { filters, reset } = useQueryFilters(
    () => index.url(),
    {
        search: props.filters.search ?? '',
        status: props.filters.status ?? null,
    },
    { debounced: ['search'] },
);

const statusOptions = [
    { value: 'not_started', label: 'Not started' },
    { value: 'draft', label: 'Draft' },
    { value: 'finalized', label: 'Finalized' },
    { value: 'paid', label: 'Paid' },
];

const statusLabel = (row: SettlementRow): string =>
    statusOptions.find((option) => option.value === row.settlement?.status)
        ?.label ?? 'Not started';

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Employee', primary: true },
    { key: 'last_working_date', label: 'Last working day' },
    { key: 'exit_type', label: 'Exit type' },
    { key: 'outstanding_borrow', label: 'Outstanding borrow', align: 'right' },
    { key: 'net_amount', label: 'Final settlement', align: 'right' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <Head title="Final Settlement" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Final Settlement"
            description="Settle what is owed to, or by, each employee who has left: the last salary, overtime and bonuses, less unpaid leave, short hours, outstanding borrow and other deductions."
        />

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
                v-model="filters.status"
                :options="statusOptions"
                placeholder="All settlement statuses"
                aria-label="Settlement status"
            />
        </div>

        <DataTable :columns="columns" :rows="employees.data">
            <template #cell-name="{ row }">
                <EmployeeCell
                    :name="row.name"
                    :code="row.code"
                    :subtitle="row.department"
                    :photo-url="row.photo_url"
                    :href="show(row.id)"
                />
            </template>
            <template #cell-last_working_date="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.last_working_date) }}
                </span>
            </template>
            <template #cell-exit_type="{ row }">
                {{ row.exit_type ?? '-' }}
            </template>
            <template #cell-outstanding_borrow="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ money(row.outstanding_borrow) }}
                </span>
            </template>
            <template #cell-net_amount="{ row }">
                <span
                    v-if="row.settlement"
                    class="tabular font-medium whitespace-nowrap"
                    :class="
                        row.settlement.net_amount < 0 ? 'text-negative' : ''
                    "
                >
                    {{ money(row.settlement.net_amount) }}
                </span>
                <span v-else class="text-muted-foreground">-</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :tone="
                        row.settlement
                            ? statusTone('settlement', row.settlement.status)
                            : 'neutral'
                    "
                >
                    {{ statusLabel(row) }}
                </StatusBadge>
            </template>
            <template #actions="{ row }">
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="show(row.id)">
                        {{ row.settlement ? 'Open' : 'Prepare' }}
                        <ArrowRight />
                    </Link>
                </Button>
            </template>
            <template #empty>
                <div
                    class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                >
                    <p class="text-sm font-medium">
                        No past employees to settle
                    </p>
                    <p class="max-w-[52ch] text-sm text-muted-foreground">
                        A settlement is prepared after an employee leaves. Use
                        "Leave company" on the employee's profile, and they will
                        appear here.
                    </p>
                    <Button
                        v-if="filters.search || filters.status"
                        variant="outline"
                        size="sm"
                        @click="reset"
                    >
                        Clear filters
                    </Button>
                </div>
            </template>
        </DataTable>

        <DataPagination :page="employees" noun="past employees" />
    </div>
</template>
