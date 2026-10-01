<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Lock, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { statusTone } from '@/lib/status';
import { index, show, store } from '@/routes/payroll';
import type { Paginated } from '@/types';

type PayrollRow = {
    id: number;
    label: string;
    period_start: string;
    period_end: string;
    status: string;
    status_label: string;
    is_locked: boolean;
    employee_count: number;
    total_gross: number;
    total_earnings: number;
    total_deductions: number;
    total_borrow_given: number;
    total_borrow_recovery: number;
    total_net_payable: number;
    calculated_at: string | null;
    finalized_at: string | null;
};

const props = defineProps<{
    payrolls: Paginated<PayrollRow>;
    suggestedMonth: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Payroll', href: index() }],
    },
});

const { money, date, monthLabel } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('payroll.manage'));

const open = ref(false);
const form = useForm({
    month: props.suggestedMonth,
    notes: '',
});

function startPayroll(): void {
    form.month = props.suggestedMonth;
    form.notes = '';
    form.clearErrors();
    open.value = true;
}

function submit(): void {
    form.post(store.url(), {
        onSuccess: () => {
            open.value = false;
        },
    });
}

/** A deduction is money leaving the employee's pay, so it reads as a negative. */
const minus = (amount: number): string => (amount > 0 ? money(-amount) : '-');

const columns: DataTableColumn[] = [
    { key: 'label', label: 'Month', primary: true },
    { key: 'status', label: 'Status' },
    { key: 'employee_count', label: 'Employees', align: 'right' },
    { key: 'total_gross', label: 'Gross salary', align: 'right' },
    { key: 'total_deductions', label: 'Deductions', align: 'right' },
    { key: 'total_borrow_given', label: 'Borrow given', align: 'right' },
    {
        key: 'total_borrow_recovery',
        label: 'Borrow recovery',
        align: 'right',
    },
    { key: 'total_net_payable', label: 'Net payable', align: 'right' },
];
</script>

<template>
    <Head title="Payroll" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Payroll"
            description="One payroll per month. Each goes from draft to calculated, through admin review and adjustments, to finalized, which locks it."
        >
            <Button v-if="canManage" @click="startPayroll">
                <Plus />
                Start payroll
            </Button>
        </PageHeader>

        <DataTable :columns="columns" :rows="payrolls.data">
            <template #cell-label="{ row }">
                <Link
                    :href="show(row.id)"
                    class="font-medium underline-offset-4 hover:underline focus-visible:underline"
                >
                    {{ row.label }}
                </Link>
                <span class="tabular block text-xs text-muted-foreground">
                    {{ date(row.period_start) }} to {{ date(row.period_end) }}
                </span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :tone="statusTone('payroll', row.status)">
                    <Lock v-if="row.is_locked" class="size-3" />
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
            <template #cell-total_deductions="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ minus(row.total_deductions) }}
                </span>
            </template>
            <template #cell-total_borrow_given="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{
                        row.total_borrow_given > 0
                            ? money(row.total_borrow_given, { signed: true })
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
            <template #actions="{ row }">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="show(row.id)">Open</Link>
                </Button>
            </template>
            <template #empty>
                <div
                    class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                >
                    <p class="text-sm font-medium">No payroll has been run</p>
                    <p class="max-w-[52ch] text-sm text-muted-foreground">
                        Start the payroll for a month to calculate every
                        employee's pay from their salary, attendance, overtime,
                        borrow and deductions.
                    </p>
                    <Button
                        v-if="canManage"
                        size="sm"
                        class="mt-2"
                        @click="startPayroll"
                    >
                        <Plus />
                        Start payroll for {{ monthLabel(suggestedMonth) }}
                    </Button>
                </div>
            </template>
        </DataTable>

        <DataPagination :page="payrolls" noun="payrolls" />
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Start payroll</DialogTitle>
                    <DialogDescription>
                        This creates a draft for the month. Nothing is paid or
                        deducted until the payroll is finalized.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Salary month"
                    for="payroll-month"
                    :error="form.errors.month"
                    required
                >
                    <Input
                        id="payroll-month"
                        v-model="form.month"
                        type="month"
                        required
                    />
                </FormField>

                <FormField
                    label="Notes"
                    for="payroll-notes"
                    :error="form.errors.notes"
                    hint="Optional. Visible to admins on the payroll."
                >
                    <Textarea
                        id="payroll-notes"
                        v-model="form.notes"
                        rows="3"
                        maxlength="2000"
                    />
                </FormField>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        Start payroll
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
