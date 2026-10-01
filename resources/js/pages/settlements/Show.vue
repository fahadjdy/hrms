<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CircleAlert, Lock, RefreshCw } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DetailList from '@/components/DetailList.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionCard from '@/components/SectionCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/composables/useFormat';
import {
    toastFirstError,
    useConfirmedAction,
} from '@/composables/useResourceForm';
import { statusTone } from '@/lib/status';
import { show as employeeShow } from '@/routes/employees';
import {
    finalize,
    index,
    paid,
    store,
    update,
} from '@/routes/final-settlements';
import type { AttendanceSummary, EmployeeBrief, PayrollLine } from '@/types';

type Settlement = {
    id: number;
    last_salary: number;
    overtime_amount: number;
    bonus_amount: number;
    other_earnings_amount: number;
    unpaid_leave_deduction: number;
    short_hours_deduction: number;
    outstanding_borrow: number;
    other_deductions: number;
    adjustment_amount: number | null;
    adjustment_reason: string | null;
    net_amount: number;
    status: string;
    notes: string | null;
    period_start: string;
    period_end: string;
    is_locked: boolean;
    finalized_at: string | null;
    paid_at: string | null;
    salary_already_paid: boolean;
};

type SalaryInfo = {
    method_label: string;
    gross: number;
    per_day_rate: number;
    hourly_rate: number;
};

type LedgerRow = {
    key: string;
    label: string;
    /** Signed: what it adds to (or takes from) the settlement. */
    amount: number;
    /** The breakdown buckets whose lines explain this row. */
    buckets: string[];
};

const props = defineProps<{
    employee: EmployeeBrief & {
        joining_date: string;
        last_working_date: string | null;
        exit_type: string | null;
        exit_reason: string | null;
    };
    settlement: Settlement;
    lines: PayrollLine[];
    attendance: Partial<AttendanceSummary> | [];
    salary: SalaryInfo | null;
    warnings: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Final Settlement', href: index() },
            { title: 'Settlement statement', href: '#' },
        ],
    },
});

const { date, dateTime, money, minutes } = useFormat();

const statusLabels: Record<string, string> = {
    draft: 'Draft',
    finalized: 'Finalized',
    paid: 'Paid',
};

const adjustment = computed(() =>
    Number(props.settlement.adjustment_amount ?? 0),
);

const rows = computed<LedgerRow[]>(() => [
    {
        key: 'last_salary',
        label: 'Last salary',
        amount: props.settlement.last_salary,
        buckets: props.settlement.salary_already_paid
            ? []
            : ['gross_salary', 'attendance_deduction'],
    },
    {
        key: 'overtime',
        label: 'Overtime',
        amount: props.settlement.overtime_amount,
        buckets: ['overtime_amount'],
    },
    {
        key: 'bonus',
        label: 'Bonus',
        amount: props.settlement.bonus_amount,
        buckets: ['bonus_amount'],
    },
    {
        key: 'other_earnings',
        label: 'Other earnings',
        amount: props.settlement.other_earnings_amount,
        buckets: ['other_earnings_amount'],
    },
    {
        key: 'unpaid_leave',
        label: 'Unpaid leave',
        amount: -props.settlement.unpaid_leave_deduction,
        buckets: props.settlement.salary_already_paid
            ? []
            : ['unpaid_leave_deduction'],
    },
    {
        key: 'short_hours',
        label: 'Short hours',
        amount: -props.settlement.short_hours_deduction,
        buckets: props.settlement.salary_already_paid
            ? []
            : ['short_hours_deduction'],
    },
    {
        key: 'outstanding_borrow',
        label: 'Outstanding borrow',
        amount: -props.settlement.outstanding_borrow,
        buckets: ['borrow_recovery'],
    },
    {
        key: 'other_deductions',
        label: 'Other deductions',
        amount: -props.settlement.other_deductions,
        buckets: ['other_deductions'],
    },
]);

const deductionBuckets = [
    'attendance_deduction',
    'unpaid_leave_deduction',
    'short_hours_deduction',
    'borrow_recovery',
    'other_deductions',
];

/** The lines that explain one ledger row, each with its signed amount. */
const linesFor = (row: LedgerRow) =>
    props.lines
        .filter(
            (line) =>
                row.buckets.includes(line.bucket) &&
                // Recurring salary-structure deductions were taken by the payroll that already paid this period.
                !(
                    props.settlement.salary_already_paid &&
                    line.bucket === 'other_deductions' &&
                    !('deduction_id' in line.meta)
                ),
        )
        .map((line) => ({
            ...line,
            signed: deductionBuckets.includes(line.bucket)
                ? -line.amount
                : line.amount,
        }));

const signed = (amount: number): string =>
    amount === 0 ? money(0) : money(amount, { signed: true });

const summary = computed<Partial<AttendanceSummary>>(() =>
    Array.isArray(props.attendance) ? {} : props.attendance,
);

const attendanceItems = computed(() => [
    { label: 'Working days', value: summary.value.working_days ?? 0 },
    { label: 'Present', value: summary.value.present ?? 0 },
    {
        label: 'Absent',
        value: (summary.value.absent ?? 0) + (summary.value.unmarked ?? 0),
    },
    { label: 'Half days', value: summary.value.half_day ?? 0 },
    { label: 'Paid leave', value: summary.value.paid_leave ?? 0 },
    { label: 'Unpaid leave', value: summary.value.unpaid_leave ?? 0 },
    {
        label: 'Required hours',
        value: minutes(summary.value.required_minutes),
    },
    { label: 'Worked hours', value: minutes(summary.value.worked_minutes) },
    { label: 'Short hours', value: minutes(summary.value.short_minutes) },
    { label: 'Overtime', value: minutes(summary.value.overtime_minutes) },
]);

const form = useForm({
    adjustment_amount: props.settlement.adjustment_amount ?? 0,
    adjustment_reason: props.settlement.adjustment_reason ?? '',
    notes: props.settlement.notes ?? '',
});

const saveAdjustment = () =>
    form.put(update.url(props.employee.id), {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.settlement) {
                toastFirstError({ settlement: errors.settlement });
            }
        },
    });

const recalculating = ref(false);

function recalculate(): void {
    recalculating.value = true;

    router.post(
        store.url(props.employee.id),
        {},
        {
            preserveScroll: true,
            onError: toastFirstError,
            onFinish: () => {
                recalculating.value = false;
            },
        },
    );
}

const finalizing = useConfirmedAction<Settlement>();
const paying = useConfirmedAction<Settlement>();
</script>

<template>
    <Head :title="`Final settlement - ${employee.name}`" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Final settlement"
            :description="`For the period ${date(settlement.period_start)} to ${date(settlement.period_end)}. Every amount below shows how it was worked out.`"
        >
            <StatusBadge :tone="statusTone('settlement', settlement.status)">
                <Lock v-if="settlement.is_locked" class="size-3" />
                {{ statusLabels[settlement.status] ?? settlement.status }}
            </StatusBadge>
            <template v-if="!settlement.is_locked">
                <Button
                    variant="outline"
                    :disabled="recalculating"
                    @click="recalculate"
                >
                    <Spinner v-if="recalculating" />
                    <RefreshCw v-else />
                    Recalculate
                </Button>
                <Button @click="finalizing.ask(settlement)">
                    Finalize settlement
                </Button>
            </template>
            <Button
                v-else-if="settlement.status === 'finalized'"
                @click="paying.ask(settlement)"
            >
                Mark as paid
            </Button>
        </PageHeader>

        <div
            v-if="warnings.length > 0"
            role="alert"
            class="flex items-start gap-3 rounded-lg border border-warning/30 bg-warning-soft px-4 py-3 text-sm text-warning"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" />
            <ul class="grid gap-1">
                <li v-for="warning in warnings" :key="warning">
                    {{ warning }}
                </li>
            </ul>
        </div>

        <div
            class="grid items-start gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
        >
            <div class="grid gap-5">
                <SectionCard
                    title="Settlement statement"
                    description="Earnings add to the settlement, deductions take from it."
                    flush
                >
                    <p
                        v-if="settlement.salary_already_paid"
                        class="border-b bg-info-soft px-4 py-3 text-sm text-info sm:px-5"
                    >
                        The salary for this period was already paid through a
                        finalized payroll, so only what that payroll did not
                        cover is settled here.
                    </p>

                    <table class="w-full text-sm">
                        <caption class="sr-only">
                            Final settlement statement
                        </caption>
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-left font-medium sm:px-5"
                                >
                                    Item
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-right font-medium sm:px-5"
                                >
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in rows" :key="row.key">
                                <tr class="border-b">
                                    <th
                                        scope="row"
                                        class="px-4 pt-3 text-left align-top font-medium sm:px-5"
                                        :class="
                                            linesFor(row).length > 0
                                                ? 'pb-1'
                                                : 'pb-3'
                                        "
                                    >
                                        {{ row.label }}
                                    </th>
                                    <td
                                        class="tabular px-4 pt-3 text-right align-top font-medium whitespace-nowrap sm:px-5"
                                        :class="[
                                            linesFor(row).length > 0
                                                ? 'pb-1'
                                                : 'pb-3',
                                            row.amount < 0
                                                ? 'text-negative'
                                                : '',
                                        ]"
                                    >
                                        {{ signed(row.amount) }}
                                    </td>
                                </tr>
                                <tr
                                    v-for="line in linesFor(row)"
                                    :key="line.code"
                                    class="border-b text-muted-foreground"
                                >
                                    <td
                                        class="px-4 py-1.5 pl-8 sm:px-5 sm:pl-10"
                                    >
                                        <span class="text-foreground">
                                            {{ line.label }}
                                        </span>
                                        <span class="block text-xs">
                                            {{ line.note }}
                                        </span>
                                    </td>
                                    <td
                                        class="tabular px-4 py-1.5 text-right align-top text-xs whitespace-nowrap sm:px-5"
                                    >
                                        {{ signed(line.signed) }}
                                    </td>
                                </tr>
                            </template>
                            <tr class="border-b">
                                <th
                                    scope="row"
                                    class="px-4 py-3 text-left font-medium sm:px-5"
                                >
                                    Admin adjustment
                                    <span
                                        v-if="settlement.adjustment_reason"
                                        class="block text-xs font-normal text-muted-foreground"
                                    >
                                        {{ settlement.adjustment_reason }}
                                    </span>
                                </th>
                                <td
                                    class="tabular px-4 py-3 text-right align-top font-medium whitespace-nowrap sm:px-5"
                                    :class="
                                        adjustment < 0 ? 'text-negative' : ''
                                    "
                                >
                                    {{ signed(adjustment) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th
                                    scope="row"
                                    class="ledger-total px-4 py-3 text-left text-base font-semibold sm:px-5"
                                >
                                    Final settlement
                                </th>
                                <td
                                    class="ledger-total tabular px-4 py-3 text-right text-base font-semibold whitespace-nowrap sm:px-5"
                                    :class="
                                        settlement.net_amount < 0
                                            ? 'text-negative'
                                            : ''
                                    "
                                >
                                    {{ money(settlement.net_amount) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <p
                        class="px-4 py-3 text-sm sm:px-5"
                        :class="
                            settlement.net_amount < 0
                                ? 'text-negative'
                                : 'text-muted-foreground'
                        "
                    >
                        <template v-if="settlement.net_amount < 0">
                            The deductions are more than the earnings:
                            {{ employee.name }} owes the company
                            {{ money(Math.abs(settlement.net_amount)) }}.
                        </template>
                        <template v-else-if="settlement.net_amount === 0">
                            Nothing is payable either way.
                        </template>
                        <template v-else>
                            The company pays {{ employee.name }}
                            {{ money(settlement.net_amount) }}.
                        </template>
                    </p>
                </SectionCard>

                <SectionCard
                    v-if="!settlement.is_locked"
                    title="Admin adjustment"
                    description="Add to or take from the settlement by hand. Use a negative amount to reduce it."
                >
                    <form class="grid gap-4" @submit.prevent="saveAdjustment">
                        <div class="grid gap-4 sm:grid-cols-[12rem_1fr]">
                            <FormField
                                label="Adjustment amount"
                                for="adjustment-amount"
                                :error="form.errors.adjustment_amount"
                                hint="0 for no adjustment"
                                required
                            >
                                <Input
                                    id="adjustment-amount"
                                    v-model="form.adjustment_amount"
                                    type="number"
                                    step="0.01"
                                    required
                                    class="tabular"
                                />
                            </FormField>
                            <FormField
                                label="Reason"
                                for="adjustment-reason"
                                :error="form.errors.adjustment_reason"
                                hint="Required when the amount is not 0"
                            >
                                <Input
                                    id="adjustment-reason"
                                    v-model="form.adjustment_reason"
                                    maxlength="255"
                                    placeholder="e.g. Notice period shortfall"
                                />
                            </FormField>
                        </div>
                        <FormField
                            label="Notes"
                            for="settlement-notes"
                            :error="form.errors.notes"
                        >
                            <Textarea
                                id="settlement-notes"
                                v-model="form.notes"
                                rows="3"
                                maxlength="2000"
                            />
                        </FormField>
                        <div>
                            <Button type="submit" :disabled="form.processing">
                                <Spinner v-if="form.processing" />
                                Save adjustment
                            </Button>
                        </div>
                    </form>
                </SectionCard>

                <SectionCard v-else-if="settlement.notes" title="Notes">
                    <p class="text-sm whitespace-pre-line">
                        {{ settlement.notes }}
                    </p>
                </SectionCard>
            </div>

            <div class="grid gap-5">
                <SectionCard title="Employee">
                    <EmployeeCell
                        :name="employee.name"
                        :code="employee.code"
                        :subtitle="employee.designation"
                        :photo-url="employee.photo_url"
                        :href="employeeShow(employee.id)"
                    />
                    <DetailList
                        class="mt-4"
                        :items="[
                            { label: 'Department', value: employee.department },
                            {
                                label: 'Joining date',
                                value: date(employee.joining_date),
                            },
                            {
                                label: 'Last working day',
                                value: date(employee.last_working_date),
                            },
                            { label: 'Exit type', value: employee.exit_type },
                            {
                                label: 'Exit reason',
                                value: employee.exit_reason,
                            },
                        ]"
                    />
                </SectionCard>

                <SectionCard
                    title="Final period"
                    :description="`Attendance from ${date(settlement.period_start)} to ${date(settlement.period_end)}`"
                >
                    <DetailList :items="attendanceItems" />
                    <p
                        v-if="salary && !settlement.salary_already_paid"
                        class="mt-4 border-t pt-3 text-xs text-muted-foreground"
                    >
                        Monthly salary {{ money(salary.gross) }}. Per-day rate
                        {{ money(salary.per_day_rate) }} ({{
                            salary.method_label.toLowerCase()
                        }}), hourly rate {{ money(salary.hourly_rate) }}.
                    </p>
                </SectionCard>

                <SectionCard v-if="settlement.is_locked" title="Status">
                    <DetailList
                        :items="[
                            {
                                label: 'Finalized',
                                value: dateTime(settlement.finalized_at),
                            },
                            {
                                label: 'Paid',
                                value: settlement.paid_at
                                    ? dateTime(settlement.paid_at)
                                    : 'Not yet',
                            },
                        ]"
                    />
                </SectionCard>

                <p class="text-sm">
                    <Link
                        :href="index()"
                        class="text-muted-foreground underline underline-offset-4 hover:text-foreground"
                    >
                        Back to all settlements
                    </Link>
                </p>
            </div>
        </div>
    </div>

    <ConfirmDialog
        :open="finalizing.target.value !== null"
        title="Finalize this settlement?"
        :description="`The settlement of ${money(settlement.net_amount)} is locked and can no longer be recalculated or adjusted. The outstanding borrow of ${money(settlement.outstanding_borrow)} is recovered and its borrow records are closed.`"
        confirm-label="Finalize settlement"
        :processing="finalizing.processing.value"
        @update:open="(value) => !value && (finalizing.target.value = null)"
        @confirm="finalizing.run('post', finalize.url(employee.id))"
    />

    <ConfirmDialog
        :open="paying.target.value !== null"
        title="Mark this settlement as paid?"
        :description="
            settlement.net_amount < 0
                ? `This records that ${employee.name} has repaid ${money(Math.abs(settlement.net_amount))} to the company.`
                : `This records that ${money(settlement.net_amount)} has been paid to ${employee.name}.`
        "
        confirm-label="Mark as paid"
        :processing="paying.processing.value"
        @update:open="(value) => !value && (paying.target.value = null)"
        @confirm="paying.run('post', paid.url(employee.id))"
    />
</template>
