<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Ban, HandCoins } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DetailList from '@/components/DetailList.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import BorrowTransactionBadge from '@/components/finance/BorrowTransactionBadge.vue';
import RecoveryDialog from '@/components/finance/RecoveryDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionCard from '@/components/SectionCard.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useConfirmedAction } from '@/composables/useResourceForm';
import { statusTone } from '@/lib/status';
import { destroy, index } from '@/routes/borrows';
import { show as employeeShow } from '@/routes/employees';
import type { EmployeeBrief } from '@/types';

type Borrow = {
    id: number;
    reference_no: string;
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
    reason: string | null;
    notes: string | null;
    source_reference: string | null;
    original_amount: number;
    installments_count: number | null;
    deduction_start_month: string | null;
    disbursed_at: string | null;
    can_cancel: boolean;
    can_recover: boolean;
};

type Installment = {
    id: number;
    sequence: number;
    due_month: string;
    amount: number;
    paid_amount: number;
    status: string;
    paid_at: string | null;
    via_payroll: boolean;
};

type Transaction = {
    id: number;
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
    borrow: Borrow;
    employee: EmployeeBrief;
    installments: Installment[];
    transactions: Transaction[];
    today: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Borrow / Advance', href: index() },
            { title: 'Borrow details', href: index() },
        ],
    },
});

const { money, date, dateTime, monthLabel } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('finance.manage'));

const recoveryOpen = ref(false);
const cancellation = useConfirmedAction<Borrow>();

const recoveredShare = computed(() =>
    props.borrow.amount > 0
        ? Math.min(100, (props.borrow.recovered / props.borrow.amount) * 100)
        : 0,
);

const payout = computed(() => {
    if (props.borrow.kind === 'existing') {
        return 'Carried in at joining (nothing paid out)';
    }

    if (props.borrow.disbursement_method === 'with_salary') {
        return props.borrow.disbursed_at
            ? `Given with the salary of ${props.borrow.disburse_period}`
            : `To be given with the salary of ${props.borrow.disburse_period}`;
    }

    return 'Given directly';
});

const details = computed(() => [
    {
        label: 'Kind',
        value:
            props.borrow.kind === 'existing'
                ? 'Existing borrow at joining'
                : 'New borrow',
    },
    { label: 'Borrow date', value: date(props.borrow.borrow_date) },
    { label: 'Payout', value: payout.value },
    {
        label: 'Original amount',
        value: money(props.borrow.original_amount),
    },
    {
        label: 'Monthly deduction',
        value:
            props.borrow.monthly_deduction > 0
                ? money(props.borrow.monthly_deduction)
                : 'Not set',
    },
    {
        label: 'Deductions start in',
        value: props.borrow.deduction_start_month
            ? monthLabel(props.borrow.deduction_start_month.slice(0, 7))
            : null,
    },
    { label: 'Reason', value: props.borrow.reason },
    { label: 'Source or reference', value: props.borrow.source_reference },
    { label: 'Notes', value: props.borrow.notes },
]);

const installmentColumns: DataTableColumn[] = [
    { key: 'sequence', label: 'No.', primary: true },
    { key: 'due_month', label: 'Month' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'paid_at', label: 'Recovered on' },
];

const transactionColumns: DataTableColumn[] = [
    { key: 'date', label: 'Date', primary: true },
    { key: 'type', label: 'Entry' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'balance_after', label: 'Balance after', align: 'right' },
    { key: 'notes', label: 'Notes' },
    { key: 'user', label: 'By' },
];

/** Given raises what is owed (+); recovered lowers it (-). */
const signedAmount = (transaction: Transaction): string => {
    if (['recovery', 'settlement'].includes(transaction.type)) {
        return `-${money(transaction.amount)}`;
    }

    if (['opening', 'disbursement'].includes(transaction.type)) {
        return `+${money(transaction.amount)}`;
    }

    return money(transaction.amount);
};
</script>

<template>
    <Head :title="`Borrow ${borrow.reference_no}`" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            :title="`Borrow ${borrow.reference_no}`"
            description="One borrow record: what was given, what has been recovered and what is still owed."
        >
            <StatusBadge :tone="statusTone('borrow', borrow.status)">
                {{ borrow.status_label }}
            </StatusBadge>
            <Button
                v-if="canManage && borrow.can_recover"
                @click="recoveryOpen = true"
            >
                <HandCoins />
                Record recovery
            </Button>
            <Button
                v-if="canManage && borrow.can_cancel"
                variant="outline"
                @click="cancellation.ask(borrow)"
            >
                <Ban />
                Cancel borrow
            </Button>
        </PageHeader>

        <div class="rounded-lg border bg-card p-4">
            <EmployeeCell
                :name="employee.name"
                :code="employee.code"
                :subtitle="
                    [employee.designation, employee.department]
                        .filter(Boolean)
                        .join(', ')
                "
                :photo-url="employee.photo_url"
                :href="
                    can('employees.view')
                        ? employeeShow(employee.id)
                        : undefined
                "
            />
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <StatCard
                label="Borrow given"
                :value="money(borrow.amount)"
                :hint="
                    borrow.kind === 'existing'
                        ? 'Balance carried in at joining'
                        : 'Amount given to the employee'
                "
            />
            <StatCard
                label="Recovered"
                :value="money(borrow.recovered)"
                hint="Deducted from salary or repaid"
                tone="positive"
            />
            <StatCard
                label="Outstanding"
                :value="money(borrow.outstanding)"
                hint="Still owed by the employee"
                :tone="borrow.outstanding > 0 ? 'warning' : 'neutral'"
            />
        </div>

        <SectionCard>
            <div class="flex items-baseline justify-between gap-4 text-sm">
                <span class="font-medium">Recovery progress</span>
                <span class="tabular text-muted-foreground">
                    {{ money(borrow.recovered) }} of
                    {{ money(borrow.amount) }} ({{
                        recoveredShare.toFixed(0)
                    }}%)
                </span>
            </div>
            <!-- The track is a lighter step of the same hue, so "recovered so far" reads across the whole bar. -->
            <div
                class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-positive-soft"
                role="progressbar"
                aria-label="Share of the borrow recovered"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-valuenow="Math.round(recoveredShare)"
            >
                <div
                    class="h-full rounded-full bg-positive transition-[width]"
                    :style="{ width: `${recoveredShare}%` }"
                />
            </div>
        </SectionCard>

        <SectionCard title="Details">
            <DetailList :items="details" :columns="3" />
        </SectionCard>

        <div class="grid gap-3">
            <div>
                <h2 class="text-sm font-semibold">Installment schedule</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Recovered installments are history. Pending ones are rebuilt
                    from the outstanding balance after every recovery.
                </p>
            </div>
            <DataTable
                :columns="installmentColumns"
                :rows="installments"
                empty-title="No installments scheduled"
                empty-description="This borrow has no monthly deduction, so nothing is recovered from salary automatically."
            >
                <template #cell-sequence="{ row }">
                    <span class="tabular font-medium">
                        Installment {{ row.sequence }}
                    </span>
                </template>
                <template #cell-due_month="{ row }">
                    {{ monthLabel(row.due_month.slice(0, 7)) }}
                </template>
                <template #cell-amount="{ row }">
                    <span class="tabular">{{ money(row.amount) }}</span>
                </template>
                <template #cell-status="{ row }">
                    <StatusBadge :tone="statusTone('installment', row.status)">
                        {{ row.status === 'paid' ? 'Recovered' : 'Pending' }}
                    </StatusBadge>
                    <span
                        v-if="row.via_payroll"
                        class="ml-2 text-xs text-muted-foreground"
                    >
                        via payroll
                    </span>
                </template>
                <template #cell-paid_at="{ row }">
                    <span class="tabular">
                        {{ row.paid_at ? dateTime(row.paid_at) : '-' }}
                    </span>
                </template>
            </DataTable>
        </div>

        <div class="grid gap-3">
            <div>
                <h2 class="text-sm font-semibold">Ledger</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Every entry that changed this borrow's balance, oldest
                    first. Entries are never edited; a correction is a new
                    entry.
                </p>
            </div>
            <DataTable
                :columns="transactionColumns"
                :rows="transactions"
                empty-title="No entries yet"
                empty-description="The first entry appears when this borrow is paid out with its salary."
            >
                <template #cell-date="{ row }">
                    <span class="tabular font-medium whitespace-nowrap">
                        {{ date(row.date) }}
                    </span>
                </template>
                <template #cell-type="{ row }">
                    <BorrowTransactionBadge
                        :type="row.type"
                        :label="row.type_label"
                    />
                    <span
                        v-if="row.via_payroll"
                        class="ml-2 text-xs text-muted-foreground"
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
            </DataTable>
        </div>

        <div>
            <Button variant="outline" as-child>
                <Link :href="index()">Back to all borrows</Link>
            </Button>
        </div>
    </div>

    <RecoveryDialog
        v-model:open="recoveryOpen"
        :borrows="[
            {
                id: borrow.id,
                label: borrow.reference_no,
                outstanding: borrow.outstanding,
            },
        ]"
        :borrow-id="borrow.id"
        :today="today"
    />

    <ConfirmDialog
        :open="cancellation.target.value !== null"
        title="Cancel this borrow?"
        :description="`${borrow.reference_no} will be marked as cancelled and its schedule removed. A borrow with recoveries cannot be cancelled.`"
        confirm-label="Cancel borrow"
        destructive
        :processing="cancellation.processing.value"
        @update:open="(value) => !value && (cancellation.target.value = null)"
        @confirm="cancellation.run('delete', destroy.url(borrow.id))"
    />
</template>
