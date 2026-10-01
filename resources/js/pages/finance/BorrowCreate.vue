<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import ChoiceCards from '@/components/finance/ChoiceCards.vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionCard from '@/components/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/composables/useFormat';
import { index, store } from '@/routes/borrows';
import type { IdName } from '@/types';

const props = defineProps<{
    employees: IdName[];
    selectedEmployeeId: number | null;
    today: string;
    currentMonth: string;
    nextMonth: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Borrow / Advance', href: index() },
            { title: 'New borrow', href: index() },
        ],
    },
});

const { money, monthLabel } = useFormat();

const form = useForm({
    employee_id: props.selectedEmployeeId,
    kind: 'new',
    amount: '' as number | string,
    opening_balance: '' as number | string,
    borrow_date: props.today,
    reason: '',
    monthly_deduction: '' as number | string,
    installments_count: '' as number | string,
    deduction_start_month: props.nextMonth,
    disbursement_method: 'direct',
    disburse_period: props.currentMonth,
    source_reference: '',
    notes: '',
});

const isExisting = computed(() => form.kind === 'existing');
const withSalary = computed(
    () => !isExisting.value && form.disbursement_method === 'with_salary',
);

/** What will be recovered: the balance still owed, not the original amount. */
const balance = computed(() => {
    const amount = Number(form.amount || 0);
    const opening = Number(form.opening_balance || 0);

    return isExisting.value && opening > 0 ? opening : amount;
});

/** The schedule that follows from whichever of the two inputs was filled in. */
const plan = computed(() => {
    const monthly = Number(form.monthly_deduction || 0);
    const count = Number(form.installments_count || 0);

    if (balance.value <= 0) {
        return null;
    }

    if (monthly > 0) {
        const deduction = Math.min(monthly, balance.value);

        return {
            monthly: deduction,
            installments: Math.ceil(balance.value / deduction),
        };
    }

    if (count > 0) {
        return {
            monthly: Math.ceil((balance.value / count) * 100) / 100,
            installments: Math.round(count),
        };
    }

    return null;
});

const lastMonth = computed(() => {
    if (!plan.value || !/^\d{4}-\d{2}$/.test(form.deduction_start_month)) {
        return null;
    }

    const [year, month] = form.deduction_start_month.split('-').map(Number);
    const last = new Date(year, month - 1 + plan.value.installments - 1, 1);

    return monthLabel(
        `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2, '0')}`,
    );
});

const kindChoices = [
    {
        value: 'new',
        title: 'New borrow',
        description:
            'The company gives the employee money now, to be recovered from future salary.',
    },
    {
        value: 'existing',
        title: 'Existing borrow at joining',
        description:
            'The employee already owed this money when they joined. Nothing is paid out; only the balance is recorded.',
    },
];

const payoutChoices = [
    {
        value: 'direct',
        title: 'Given directly',
        description:
            'The money was handed over or transferred outside payroll.',
    },
    {
        value: 'with_salary',
        title: 'Add borrow with salary',
        description:
            'Paid out together with a month’s salary when that payroll is finalized.',
    },
];

function submit(): void {
    form.transform((data) => ({
        ...data,
        opening_balance:
            data.kind === 'existing' && data.opening_balance !== ''
                ? data.opening_balance
                : null,
        monthly_deduction:
            data.monthly_deduction === '' ? null : data.monthly_deduction,
        installments_count:
            data.installments_count === '' ? null : data.installments_count,
        // An existing borrow was never paid out by this company.
        disbursement_method:
            data.kind === 'existing' ? 'direct' : data.disbursement_method,
        disburse_period:
            data.kind === 'new' && data.disbursement_method === 'with_salary'
                ? data.disburse_period
                : null,
    })).post(store.url());
}
</script>

<template>
    <Head title="New borrow" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="New borrow"
            description="Record money an employee owes the company. Each borrow is kept as its own record with its own schedule."
        />

        <form class="grid max-w-3xl gap-5" @submit.prevent="submit">
            <SectionCard title="Employee and kind">
                <div class="grid gap-5">
                    <FormField
                        label="Employee"
                        for="borrow-employee"
                        :error="form.errors.employee_id"
                        required
                    >
                        <NativeSelect
                            id="borrow-employee"
                            v-model="form.employee_id"
                            :options="
                                employees.map((employee) => ({
                                    value: employee.id,
                                    label: employee.name,
                                }))
                            "
                            placeholder="Choose an employee"
                            :invalid="!!form.errors.employee_id"
                        />
                    </FormField>

                    <ChoiceCards
                        v-model="form.kind"
                        legend="What kind of borrow is this?"
                        name="borrow-kind"
                        :choices="kindChoices"
                    />
                </div>
            </SectionCard>

            <SectionCard title="Amount">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        :label="
                            isExisting
                                ? 'Original borrow amount'
                                : 'Borrow amount'
                        "
                        for="borrow-amount"
                        :error="form.errors.amount"
                        required
                    >
                        <Input
                            id="borrow-amount"
                            v-model="form.amount"
                            type="number"
                            inputmode="decimal"
                            min="0.01"
                            step="0.01"
                            required
                            class="tabular text-right"
                        />
                    </FormField>

                    <FormField
                        v-if="isExisting"
                        label="Outstanding balance at joining"
                        for="borrow-opening"
                        :error="form.errors.opening_balance"
                        hint="What is still owed today. Leave empty if nothing has been repaid yet."
                    >
                        <Input
                            id="borrow-opening"
                            v-model="form.opening_balance"
                            type="number"
                            inputmode="decimal"
                            min="0.01"
                            step="0.01"
                            :max="form.amount || undefined"
                            class="tabular text-right"
                        />
                    </FormField>

                    <FormField
                        :label="
                            isExisting ? 'Original borrow date' : 'Borrow date'
                        "
                        for="borrow-date"
                        :error="form.errors.borrow_date"
                        required
                    >
                        <DatePicker
                            id="borrow-date"
                            v-model="form.borrow_date"
                            required
                        />
                    </FormField>

                    <FormField
                        label="Reason"
                        for="borrow-reason"
                        :error="form.errors.reason"
                        :class="isExisting ? 'sm:col-span-1' : ''"
                    >
                        <Input
                            id="borrow-reason"
                            v-model="form.reason"
                            maxlength="255"
                            placeholder="e.g. Medical expenses"
                        />
                    </FormField>
                </div>
            </SectionCard>

            <SectionCard v-if="!isExisting" title="How the money is paid out">
                <div class="grid gap-5">
                    <ChoiceCards
                        v-model="form.disbursement_method"
                        legend="Payout"
                        name="borrow-payout"
                        :choices="payoutChoices"
                    />

                    <template v-if="withSalary">
                        <FormField
                            label="Salary month"
                            for="borrow-disburse-period"
                            :error="form.errors.disburse_period"
                            class="sm:max-w-xs"
                            required
                        >
                            <DatePicker
                                id="borrow-disburse-period"
                                v-model="form.disburse_period"
                                mode="month"
                                required
                            />
                        </FormField>

                        <p
                            class="rounded-md bg-info-soft px-3 py-2 text-sm text-info"
                        >
                            {{ money(Number(form.amount || 0)) }} will be added
                            to the net payable of the
                            {{
                                /^\d{4}-\d{2}$/.test(form.disburse_period)
                                    ? monthLabel(form.disburse_period)
                                    : 'chosen'
                            }}
                            payroll as a separate “New borrow / advance” line.
                            It is an advance to be repaid, not salary income,
                            and it only becomes outstanding once that payroll is
                            finalized.
                        </p>
                    </template>
                </div>
            </SectionCard>

            <SectionCard
                title="Recovery from salary"
                description="Enter a monthly deduction, a number of installments, or both. The monthly deduction wins when both are given."
            >
                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField
                        label="Monthly deduction"
                        for="borrow-monthly"
                        :error="form.errors.monthly_deduction"
                        :hint="
                            plan && Number(form.monthly_deduction) > 0
                                ? `${plan.installments} installment${plan.installments === 1 ? '' : 's'}`
                                : undefined
                        "
                    >
                        <Input
                            id="borrow-monthly"
                            v-model="form.monthly_deduction"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                            class="tabular text-right"
                        />
                    </FormField>

                    <FormField
                        label="Number of installments"
                        for="borrow-installments"
                        :error="form.errors.installments_count"
                        :hint="
                            plan && !(Number(form.monthly_deduction) > 0)
                                ? `${money(plan.monthly)} per month`
                                : undefined
                        "
                    >
                        <Input
                            id="borrow-installments"
                            v-model="form.installments_count"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            max="600"
                            step="1"
                            class="tabular text-right"
                        />
                    </FormField>

                    <FormField
                        label="Deductions start in"
                        for="borrow-start-month"
                        :error="form.errors.deduction_start_month"
                        required
                    >
                        <DatePicker
                            id="borrow-start-month"
                            v-model="form.deduction_start_month"
                            mode="month"
                            required
                        />
                    </FormField>
                </div>

                <p
                    v-if="plan"
                    class="mt-4 rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground"
                >
                    <span class="tabular font-medium text-foreground">
                        {{ money(balance) }}
                    </span>
                    recovered at
                    <span class="tabular font-medium text-foreground">
                        {{ money(plan.monthly) }}
                    </span>
                    a month over {{ plan.installments }} installment{{
                        plan.installments === 1 ? '' : 's'
                    }}<template v-if="lastMonth"
                        >, the last one in
                        <span class="font-medium text-foreground">{{
                            lastMonth
                        }}</span></template
                    >.
                </p>
            </SectionCard>

            <SectionCard title="Reference">
                <div class="grid gap-4">
                    <FormField
                        label="Source or reference"
                        for="borrow-source"
                        :error="form.errors.source_reference"
                        hint="For example a voucher number, or the previous employer for an existing borrow."
                    >
                        <Input
                            id="borrow-source"
                            v-model="form.source_reference"
                            maxlength="255"
                        />
                    </FormField>

                    <FormField
                        label="Notes"
                        for="borrow-notes"
                        :error="form.errors.notes"
                    >
                        <Textarea
                            id="borrow-notes"
                            v-model="form.notes"
                            maxlength="2000"
                            rows="3"
                        />
                    </FormField>
                </div>
            </SectionCard>

            <div class="flex flex-wrap items-center gap-2">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Save borrow
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
