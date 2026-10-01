<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionCard from '@/components/SectionCard.vue';
import ChoiceCards from '@/components/settings/ChoiceCards.vue';
import type { Choice } from '@/components/settings/ChoiceCards.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useFormat } from '@/composables/useFormat';
import { edit, update } from '@/routes/settings/payroll';
import type { Option } from '@/types';

const props = defineProps<{
    settings: {
        salary_calculation_method: string;
        payroll_cycle: string;
        payroll_period_start_day: number;
        salary_payment_day: number;
        overtime_rate_type: string;
        overtime_multiplier: number;
        overtime_fixed_rate: number | null;
        overtime_from_attendance: boolean;
        short_hours_mode: string;
        short_hours_rate_type: string;
        short_hours_fixed_rate: number | null;
        borrow_auto_deduct: boolean;
        borrow_max_deduction_percent: number | null;
    };
    calculationMethods: Option[];
    shortHoursModes: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Payroll settings', href: edit() }],
    },
});

const { money } = useFormat();

const form = useForm({
    salary_calculation_method: props.settings.salary_calculation_method,
    payroll_period_start_day: props.settings.payroll_period_start_day,
    salary_payment_day: props.settings.salary_payment_day,
    overtime_rate_type: props.settings.overtime_rate_type,
    overtime_multiplier: props.settings.overtime_multiplier,
    overtime_fixed_rate: props.settings.overtime_fixed_rate ?? '',
    overtime_from_attendance: props.settings.overtime_from_attendance,
    short_hours_mode: props.settings.short_hours_mode,
    short_hours_rate_type: props.settings.short_hours_rate_type,
    short_hours_fixed_rate: props.settings.short_hours_fixed_rate ?? '',
    borrow_auto_deduct: props.settings.borrow_auto_deduct,
    borrow_max_deduction_percent:
        props.settings.borrow_max_deduction_percent ?? '',
});

// Worked examples use a 30,000 salary in a 30-day month with 26 working days.
const methodDetails: Record<string, { description: string; example: string }> =
    {
        calendar_days: {
            description:
                'A day is worth the monthly salary divided by the days in that month. Weekly offs and holidays are paid days.',
            example: `${money(30000)} / 30 days = ${money(1000)} per day`,
        },
        working_days: {
            description:
                'A day is worth the monthly salary divided by the scheduled working days in that month.',
            example: `${money(30000)} / 26 working days = ${money(30000 / 26)} per day`,
        },
        fixed_30: {
            description:
                'A day is always worth the monthly salary divided by 30, whether the month has 28, 30 or 31 days.',
            example: `${money(30000)} / 30 = ${money(1000)} per day, every month`,
        },
    };

const methodChoices = computed<Choice[]>(() =>
    props.calculationMethods.map((method) => ({
        value: method.value,
        label: method.label,
        description: methodDetails[method.value]?.description ?? '',
        example: methodDetails[method.value]?.example,
    })),
);

const shortHoursDetails: Record<string, string> = {
    deduct: 'Short hours multiplied by the hourly rate are deducted from salary automatically.',
    record_only:
        'Short hours are shown on reports and in the payroll breakdown, but nothing is deducted.',
    manual: 'Nothing is deducted until you enter an amount for the employee on the Short Hours page.',
};

const shortHoursChoices = computed<Choice[]>(() =>
    props.shortHoursModes.map((mode) => ({
        value: mode.value,
        label: mode.label,
        description: shortHoursDetails[mode.value] ?? '',
    })),
);

const overtimeRateOptions = [
    { value: 'multiplier', label: 'Hourly salary rate x a multiplier' },
    { value: 'fixed', label: 'A fixed rate per hour' },
];

const shortHoursRateOptions = [
    { value: 'salary_hourly', label: "The employee's hourly salary rate" },
    { value: 'fixed', label: 'A fixed rate per hour' },
];

const dayOptions = Array.from({ length: 28 }, (_, index) => ({
    value: index + 1,
    label: String(index + 1),
}));

const periodExample = computed(() => {
    const start = Number(form.payroll_period_start_day);

    return start === 1
        ? 'The payroll period is the calendar month.'
        : `The September payroll runs from ${start} September to ${start - 1} October.`;
});

const overtimeExample = computed(() =>
    form.overtime_rate_type === 'multiplier'
        ? `An hourly salary rate of ${money(125)} pays ${money(125 * Number(form.overtime_multiplier || 0))} per overtime hour.`
        : 'Every overtime hour pays the same fixed rate, whatever the salary.',
);

const emptyToNull = (value: string | number): string | number | null =>
    value === '' ? null : value;

const save = () =>
    form
        .transform((data) => ({
            ...data,
            overtime_fixed_rate: emptyToNull(data.overtime_fixed_rate),
            short_hours_fixed_rate: emptyToNull(data.short_hours_fixed_rate),
            borrow_max_deduction_percent: emptyToNull(
                data.borrow_max_deduction_percent,
            ),
        }))
        .put(update.url(), { preserveScroll: true });
</script>

<template>
    <Head title="Payroll settings" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Payroll settings"
            description="The rules payroll follows for every employee. A change applies the next time a payroll is calculated; finalized payrolls are never altered."
        />

        <form class="grid max-w-3xl gap-5" @submit.prevent="save">
            <SectionCard
                title="Salary calculation method"
                description="Decides what one day of work is worth. Absences, unpaid leave and half days are deducted at this per-day rate."
            >
                <ChoiceCards
                    v-model="form.salary_calculation_method"
                    name="salary-calculation-method"
                    legend="Salary calculation method"
                    :choices="methodChoices"
                    :columns="3"
                />
                <p
                    v-if="form.errors.salary_calculation_method"
                    class="mt-2 text-sm text-negative"
                >
                    {{ form.errors.salary_calculation_method }}
                </p>
            </SectionCard>

            <SectionCard
                title="Payroll cycle"
                description="Payroll runs monthly."
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Payroll period starts on day"
                        for="period-start-day"
                        :error="form.errors.payroll_period_start_day"
                        :hint="periodExample"
                        required
                    >
                        <NativeSelect
                            id="period-start-day"
                            :model-value="form.payroll_period_start_day"
                            :options="dayOptions"
                            @update:model-value="
                                (value) =>
                                    (form.payroll_period_start_day = Number(
                                        value ?? 1,
                                    ))
                            "
                        />
                    </FormField>
                    <FormField
                        label="Salary payment day"
                        for="salary-payment-day"
                        :error="form.errors.salary_payment_day"
                        hint="Day of the following month on which salaries are paid"
                        required
                    >
                        <NativeSelect
                            id="salary-payment-day"
                            :model-value="form.salary_payment_day"
                            :options="dayOptions"
                            @update:model-value="
                                (value) =>
                                    (form.salary_payment_day = Number(
                                        value ?? 1,
                                    ))
                            "
                        />
                    </FormField>
                </div>
            </SectionCard>

            <SectionCard
                title="Overtime rules"
                description="The company's overtime rate. Overtime entries you add by hand carry their own rate or fixed amount."
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Overtime rate"
                        for="overtime-rate-type"
                        :error="form.errors.overtime_rate_type"
                        :hint="overtimeExample"
                        required
                    >
                        <NativeSelect
                            id="overtime-rate-type"
                            :model-value="form.overtime_rate_type"
                            :options="overtimeRateOptions"
                            @update:model-value="
                                (value) =>
                                    (form.overtime_rate_type = String(
                                        value ?? 'multiplier',
                                    ))
                            "
                        />
                    </FormField>
                    <FormField
                        v-if="form.overtime_rate_type === 'multiplier'"
                        label="Multiplier"
                        for="overtime-multiplier"
                        :error="form.errors.overtime_multiplier"
                        hint="1.5 means time and a half"
                        required
                    >
                        <Input
                            id="overtime-multiplier"
                            v-model="form.overtime_multiplier"
                            type="number"
                            min="0"
                            max="10"
                            step="0.05"
                            required
                            class="tabular"
                        />
                    </FormField>
                    <FormField
                        v-else
                        label="Fixed rate per hour"
                        for="overtime-fixed-rate"
                        :error="form.errors.overtime_fixed_rate"
                        required
                    >
                        <Input
                            id="overtime-fixed-rate"
                            v-model="form.overtime_fixed_rate"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            class="tabular"
                        />
                    </FormField>
                </div>
                <label class="mt-5 flex items-start gap-3 text-sm">
                    <Switch
                        v-model="form.overtime_from_attendance"
                        class="mt-0.5"
                    />
                    <span>
                        <span class="font-medium">
                            Pay overtime recorded in attendance
                        </span>
                        <span class="block text-muted-foreground">
                            Time worked beyond the required hours is paid at the
                            rate above, except on dates that already have an
                            overtime entry. When off, only overtime entries you
                            add are paid.
                        </span>
                    </span>
                </label>
            </SectionCard>

            <SectionCard
                title="Short-hours rule"
                description="What happens when an employee works less than the required hours. An amount you set by hand for an employee always takes priority."
            >
                <ChoiceCards
                    v-model="form.short_hours_mode"
                    name="short-hours-mode"
                    legend="Short-hours rule"
                    :choices="shortHoursChoices"
                    :columns="3"
                />
                <p
                    v-if="form.errors.short_hours_mode"
                    class="mt-2 text-sm text-negative"
                >
                    {{ form.errors.short_hours_mode }}
                </p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="One short hour is worth"
                        for="short-hours-rate-type"
                        :error="form.errors.short_hours_rate_type"
                        :hint="`e.g. 1 short hour at ${money(200)} per hour is a ${money(200)} adjustment`"
                        required
                    >
                        <NativeSelect
                            id="short-hours-rate-type"
                            :model-value="form.short_hours_rate_type"
                            :options="shortHoursRateOptions"
                            @update:model-value="
                                (value) =>
                                    (form.short_hours_rate_type = String(
                                        value ?? 'salary_hourly',
                                    ))
                            "
                        />
                    </FormField>
                    <FormField
                        v-if="form.short_hours_rate_type === 'fixed'"
                        label="Fixed rate per hour"
                        for="short-hours-fixed-rate"
                        :error="form.errors.short_hours_fixed_rate"
                        required
                    >
                        <Input
                            id="short-hours-fixed-rate"
                            v-model="form.short_hours_fixed_rate"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            class="tabular"
                        />
                    </FormField>
                </div>
            </SectionCard>

            <SectionCard
                title="Borrow deduction rules"
                description="How borrow and advance repayments are taken from salary. A recovery is never more than the borrow's outstanding balance, or than the pay that is left."
            >
                <label class="flex items-start gap-3 text-sm">
                    <Switch v-model="form.borrow_auto_deduct" class="mt-0.5" />
                    <span>
                        <span class="font-medium">
                            Deduct the monthly installment automatically
                        </span>
                        <span class="block text-muted-foreground">
                            Each active borrow's monthly deduction is added to
                            payroll from its start month. When off, a recovery
                            is only taken if you add it as a payroll adjustment.
                        </span>
                    </span>
                </label>

                <FormField
                    class="mt-5 sm:max-w-xs"
                    label="Maximum recovery (% of pay)"
                    for="borrow-max-percent"
                    :error="form.errors.borrow_max_deduction_percent"
                    hint="Limits total borrow recovery to this share of the month's pay. Leave empty for no limit"
                >
                    <Input
                        id="borrow-max-percent"
                        v-model="form.borrow_max_deduction_percent"
                        type="number"
                        min="1"
                        max="100"
                        step="1"
                        placeholder="No limit"
                        class="tabular"
                    />
                </FormField>
            </SectionCard>

            <div>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Save payroll settings
                </Button>
            </div>
        </form>
    </div>
</template>
