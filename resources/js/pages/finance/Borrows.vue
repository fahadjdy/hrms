<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionCard from '@/components/SectionCard.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { statusTone } from '@/lib/status';
import { index as recoveriesIndex } from '@/routes/borrow-recoveries';
import { create, index, show } from '@/routes/borrows';
import type { Option, Paginated } from '@/types';

type BorrowRow = {
    id: number;
    reference_no: string;
    employee: { id: number; name: string; code: string; is_past: boolean };
    kind: string;
    amount: number;
    recovered: number;
    outstanding: number;
    monthly_deduction: number;
    borrow_date: string;
    disbursement_method: string;
    disburse_period: string | null;
    status: string;
    status_label: string;
};

const props = defineProps<{
    borrows: Paginated<BorrowRow>;
    filters: { search?: string; status?: string; kind?: string };
    statuses: Option[];
    stats: {
        total_borrowed: number;
        total_recovered: number;
        total_outstanding: number;
        employees_with_borrow: number;
        active: number;
        fully_recovered: number;
        pending_disbursement: number;
        overdue: number;
    };
    highestOutstanding: {
        employee_id: number;
        name: string;
        code: string;
        outstanding: number;
        borrows: number;
    }[];
    upcomingDeductions: {
        id: number;
        borrow_id: number;
        reference_no: string;
        employee: string;
        due_month: string;
        amount: number;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Borrow / Advance', href: index() }],
    },
});

const { money, number, date, monthLabel } = useFormat();
const { can } = usePermissions();

const { filters } = useQueryFilters(
    () => index.url(),
    {
        search: props.filters.search ?? '',
        status: props.filters.status ?? null,
        kind: props.filters.kind ?? null,
    },
    { debounced: ['search'] },
);

const kinds = [
    { value: 'new', label: 'New borrow' },
    { value: 'existing', label: 'Existing at joining' },
];

const columns: DataTableColumn[] = [
    { key: 'reference_no', label: 'Borrow', primary: true },
    { key: 'employee', label: 'Employee' },
    { key: 'kind', label: 'Kind' },
    { key: 'amount', label: 'Borrow given', align: 'right' },
    { key: 'recovered', label: 'Recovered', align: 'right' },
    { key: 'outstanding', label: 'Outstanding', align: 'right' },
    { key: 'monthly_deduction', label: 'Monthly deduction', align: 'right' },
    { key: 'borrow_date', label: 'Date' },
    { key: 'status', label: 'Status' },
];

function clearFilters(): void {
    filters.search = '';
    filters.status = null;
    filters.kind = null;
}
</script>

<template>
    <Head title="Borrow / Advance" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Borrow / Advance"
            description="Money given to employees and how much of it has come back. Every borrow keeps its own balance, schedule and history."
        >
            <Button variant="outline" as-child>
                <Link :href="recoveriesIndex()">Recovery ledger</Link>
            </Button>
            <Button v-if="can('finance.manage')" as-child>
                <Link :href="create()">
                    <Plus />
                    New borrow
                </Link>
            </Button>
        </PageHeader>

        <!-- What was given, what came back, what is still owed -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
                label="Total borrowed"
                :value="money(stats.total_borrowed, { whole: true })"
                hint="All borrow and advance ever given"
            />
            <StatCard
                label="Total recovered"
                :value="money(stats.total_recovered, { whole: true })"
                hint="Recovered through salary or repaid"
                tone="positive"
            />
            <StatCard
                label="Outstanding"
                :value="money(stats.total_outstanding, { whole: true })"
                hint="Still owed by employees"
                :tone="stats.total_outstanding > 0 ? 'warning' : 'neutral'"
                :href="index.url({ query: { status: 'active' } })"
            />
            <StatCard
                label="Employees with borrow"
                :value="number(stats.employees_with_borrow, 0)"
                hint="Currently have an outstanding balance"
            />
            <StatCard
                label="Active borrows"
                :value="number(stats.active, 0)"
                hint="Being recovered"
                :href="index.url({ query: { status: 'active' } })"
            />
            <StatCard
                label="Fully recovered"
                :value="number(stats.fully_recovered, 0)"
                hint="Nothing left to recover"
                :href="index.url({ query: { status: 'recovered' } })"
            />
            <StatCard
                label="Pending disbursement"
                :value="number(stats.pending_disbursement, 0)"
                hint="To be paid out with an upcoming salary"
                :tone="stats.pending_disbursement > 0 ? 'info' : 'neutral'"
                :href="index.url({ query: { status: 'pending_disbursement' } })"
            />
            <StatCard
                label="Overdue"
                :value="number(stats.overdue, 0)"
                hint="Have an installment from an earlier month that was not recovered"
                :tone="stats.overdue > 0 ? 'warning' : 'neutral'"
            />
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <SectionCard
                title="Highest outstanding"
                description="Employees who owe the most, across all of their active borrows."
                flush
            >
                <EmptyState
                    v-if="highestOutstanding.length === 0"
                    title="Nothing outstanding"
                    description="No employee currently owes the company money."
                />
                <ol v-else class="divide-y">
                    <li
                        v-for="(row, position) in highestOutstanding"
                        :key="row.employee_id"
                        class="flex items-center gap-3 px-4 py-3 sm:px-5"
                    >
                        <span
                            class="tabular w-5 shrink-0 text-sm text-muted-foreground"
                        >
                            {{ position + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="
                                    index.url({ query: { search: row.code } })
                                "
                                class="block truncate text-sm font-medium underline-offset-4 hover:underline focus-visible:underline"
                            >
                                {{ row.name }}
                            </Link>
                            <span class="text-xs text-muted-foreground">
                                {{ row.code }} / {{ row.borrows }} active
                                {{ row.borrows === 1 ? 'borrow' : 'borrows' }}
                            </span>
                        </div>
                        <span class="tabular text-sm font-medium">
                            {{ money(row.outstanding) }}
                        </span>
                    </li>
                </ol>
            </SectionCard>

            <SectionCard
                title="Upcoming deductions"
                description="Installments due this month and next, to be recovered from salary."
                flush
            >
                <EmptyState
                    v-if="upcomingDeductions.length === 0"
                    title="No deductions scheduled"
                    description="No borrow has an installment due this month or next."
                />
                <ul v-else class="divide-y">
                    <li
                        v-for="row in upcomingDeductions"
                        :key="row.id"
                        class="flex items-center gap-3 px-4 py-3 sm:px-5"
                    >
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="show(row.borrow_id)"
                                class="block truncate text-sm font-medium underline-offset-4 hover:underline focus-visible:underline"
                            >
                                {{ row.employee }}
                            </Link>
                            <span class="text-xs text-muted-foreground">
                                {{ row.reference_no }} / due
                                {{ monthLabel(row.due_month.slice(0, 7)) }}
                            </span>
                        </div>
                        <span class="tabular text-sm font-medium">
                            {{ money(row.amount) }}
                        </span>
                    </li>
                </ul>
            </SectionCard>
        </div>

        <div class="grid gap-2 sm:grid-cols-3">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    type="search"
                    placeholder="Reference, employee name or ID"
                    aria-label="Search borrows"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.status"
                :options="statuses"
                placeholder="All statuses"
                aria-label="Status"
            />
            <NativeSelect
                v-model="filters.kind"
                :options="kinds"
                placeholder="New and existing"
                aria-label="Kind of borrow"
            />
        </div>

        <DataTable :columns="columns" :rows="borrows.data">
            <template #cell-reference_no="{ row }">
                <Link
                    :href="show(row.id)"
                    class="font-medium underline-offset-4 hover:underline focus-visible:underline"
                >
                    {{ row.reference_no }}
                </Link>
            </template>
            <template #cell-employee="{ row }">
                <span class="block">{{ row.employee.name }}</span>
                <span class="text-xs text-muted-foreground">
                    {{ row.employee.code }}
                    <template v-if="row.employee.is_past">
                        / past employee</template
                    >
                </span>
            </template>
            <template #cell-kind="{ row }">
                <StatusBadge
                    :tone="row.kind === 'existing' ? 'info' : 'neutral'"
                >
                    {{ row.kind === 'existing' ? 'Existing' : 'New' }}
                </StatusBadge>
            </template>
            <template #cell-amount="{ row }">
                <span class="tabular">{{ money(row.amount) }}</span>
            </template>
            <template #cell-recovered="{ row }">
                <span class="tabular">{{ money(row.recovered) }}</span>
            </template>
            <template #cell-outstanding="{ row }">
                <span class="tabular font-medium">
                    {{ money(row.outstanding) }}
                </span>
            </template>
            <template #cell-monthly_deduction="{ row }">
                <span class="tabular">
                    {{
                        row.monthly_deduction > 0
                            ? money(row.monthly_deduction)
                            : '-'
                    }}
                </span>
            </template>
            <template #cell-borrow_date="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.borrow_date) }}
                </span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :tone="statusTone('borrow', row.status)">
                    {{ row.status_label }}
                </StatusBadge>
                <span
                    v-if="
                        row.disbursement_method === 'with_salary' &&
                        row.disburse_period
                    "
                    class="mt-1 block text-xs text-muted-foreground"
                >
                    Paid with salary of {{ row.disburse_period }}
                </span>
            </template>
            <template #empty>
                <EmptyState
                    title="No borrows to show"
                    :description="
                        filters.search || filters.status || filters.kind
                            ? 'No borrow matches these filters.'
                            : 'Record a borrow an employee already had when joining, or a new one given by the company.'
                    "
                >
                    <Button
                        v-if="filters.search || filters.status || filters.kind"
                        variant="outline"
                        size="sm"
                        @click="clearFilters"
                    >
                        Clear filters
                    </Button>
                    <Button
                        v-else-if="can('finance.manage')"
                        size="sm"
                        as-child
                    >
                        <Link :href="create()">
                            <Plus />
                            New borrow
                        </Link>
                    </Button>
                </EmptyState>
            </template>
        </DataTable>

        <DataPagination :page="borrows" noun="borrows" />
    </div>
</template>
