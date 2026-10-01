<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { HandCoins, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import BorrowTransactionBadge from '@/components/finance/BorrowTransactionBadge.vue';
import RecoveryDialog from '@/components/finance/RecoveryDialog.vue';
import type { RecoverableBorrow } from '@/components/finance/RecoveryDialog.vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { index } from '@/routes/borrow-recoveries';
import { index as borrowsIndex, show as borrowShow } from '@/routes/borrows';
import type { Option, Paginated } from '@/types';

type TransactionRow = {
    id: number;
    borrow_id: number;
    reference_no: string;
    employee: { id: number; name: string; code: string };
    type: string;
    type_label: string;
    amount: number;
    balance_after: number;
    date: string;
    notes: string | null;
    user: string | null;
    via_payroll: boolean;
};

const props = defineProps<{
    transactions: Paginated<TransactionRow>;
    filters: { search?: string; type?: string; from?: string; to?: string };
    types: Option[];
    stats: { recovered_this_month: number; recovered_last_month: number };
    activeBorrows: RecoverableBorrow[];
    today: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Borrow / Advance', href: borrowsIndex() },
            { title: 'Borrow Recovery', href: index() },
        ],
    },
});

const { money, date } = useFormat();
const { can } = usePermissions();

const recoveryOpen = ref(false);

const { filters } = useQueryFilters(
    () => index.url(),
    {
        search: props.filters.search ?? '',
        type: props.filters.type ?? null,
        from: props.filters.from ?? '',
        to: props.filters.to ?? '',
    },
    { debounced: ['search'] },
);

const hasFilters = computed(
    () => !!(filters.search || filters.type || filters.from || filters.to),
);

function clearFilters(): void {
    filters.search = '';
    filters.type = null;
    filters.from = '';
    filters.to = '';
}

const difference = computed(
    () => props.stats.recovered_this_month - props.stats.recovered_last_month,
);

const columns: DataTableColumn[] = [
    { key: 'date', label: 'Date', primary: true },
    { key: 'employee', label: 'Employee' },
    { key: 'reference_no', label: 'Borrow' },
    { key: 'type', label: 'Entry' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'balance_after', label: 'Balance after', align: 'right' },
    { key: 'notes', label: 'Notes' },
    { key: 'user', label: 'By', hideOnMobile: true },
];

/** Given raises what is owed (+); recovered lowers it (-). */
const signedAmount = (row: TransactionRow): string => {
    if (['recovery', 'settlement'].includes(row.type)) {
        return `-${money(row.amount)}`;
    }

    if (['opening', 'disbursement'].includes(row.type)) {
        return `+${money(row.amount)}`;
    }

    return money(row.amount);
};
</script>

<template>
    <Head title="Borrow Recovery" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Borrow Recovery"
            description="The borrow ledger: every amount given to an employee and every amount recovered, across all borrows."
        >
            <Button
                v-if="can('finance.manage') && activeBorrows.length > 0"
                @click="recoveryOpen = true"
            >
                <HandCoins />
                Record recovery
            </Button>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-2 lg:max-w-2xl">
            <StatCard
                label="Recovered this month"
                :value="money(stats.recovered_this_month)"
                :hint="
                    stats.recovered_last_month > 0
                        ? `${money(Math.abs(difference))} ${difference >= 0 ? 'more' : 'less'} than last month`
                        : 'Through salary deductions and repayments'
                "
                tone="positive"
            />
            <StatCard
                label="Recovered last month"
                :value="money(stats.recovered_last_month)"
                hint="Through salary deductions and repayments"
            />
        </div>

        <div class="grid items-end gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    type="search"
                    placeholder="Reference, employee name or ID"
                    aria-label="Search the ledger"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.type"
                :options="types"
                placeholder="All entry types"
                aria-label="Entry type"
            />
            <FormField label="From" for="ledger-from">
                <Input
                    id="ledger-from"
                    v-model="filters.from"
                    type="date"
                    :max="filters.to || undefined"
                />
            </FormField>
            <FormField label="To" for="ledger-to">
                <Input
                    id="ledger-to"
                    v-model="filters.to"
                    type="date"
                    :min="filters.from || undefined"
                />
            </FormField>
        </div>

        <DataTable :columns="columns" :rows="transactions.data">
            <template #cell-date="{ row }">
                <span class="tabular font-medium whitespace-nowrap">
                    {{ date(row.date) }}
                </span>
            </template>
            <template #cell-employee="{ row }">
                <span class="block">{{ row.employee.name }}</span>
                <span class="text-xs text-muted-foreground">
                    {{ row.employee.code }}
                </span>
            </template>
            <template #cell-reference_no="{ row }">
                <Link
                    :href="borrowShow(row.borrow_id)"
                    class="underline-offset-4 hover:underline focus-visible:underline"
                >
                    {{ row.reference_no }}
                </Link>
            </template>
            <template #cell-type="{ row }">
                <BorrowTransactionBadge
                    :type="row.type"
                    :label="row.type_label"
                />
                <span
                    v-if="row.via_payroll"
                    class="mt-1 block text-xs text-muted-foreground"
                >
                    via payroll
                </span>
            </template>
            <template #cell-amount="{ row }">
                <span class="tabular">{{ signedAmount(row) }}</span>
            </template>
            <template #cell-balance_after="{ row }">
                <span class="tabular font-medium">
                    {{ money(row.balance_after) }}
                </span>
            </template>
            <template #cell-notes="{ row }">
                <span class="text-muted-foreground">
                    {{ row.notes ?? '-' }}
                </span>
            </template>
            <template #cell-user="{ row }">
                {{ row.user ?? 'System' }}
            </template>
            <template #empty>
                <EmptyState
                    title="No ledger entries"
                    :description="
                        hasFilters
                            ? 'Nothing matches these filters.'
                            : 'Entries appear here when a borrow is given or recovered.'
                    "
                >
                    <Button
                        v-if="hasFilters"
                        variant="outline"
                        size="sm"
                        @click="clearFilters"
                    >
                        Clear filters
                    </Button>
                </EmptyState>
            </template>
        </DataTable>

        <DataPagination :page="transactions" noun="entries" />
    </div>

    <RecoveryDialog
        v-model:open="recoveryOpen"
        :borrows="activeBorrows"
        :today="today"
    />
</template>
