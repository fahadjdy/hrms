<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Info, RotateCcw, Search, SlidersHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import EmptyState from '@/components/EmptyState.vue';
import FormField from '@/components/FormField.vue';
import MonthNavigator from '@/components/MonthNavigator.vue';
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
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { useConfirmedAction } from '@/composables/useResourceForm';
import { show as attendanceShow } from '@/routes/employees/attendance';
import { edit as payrollSettings } from '@/routes/settings/payroll';
import { destroy, index, update } from '@/routes/short-hours';
import type { EmployeeBrief, Option, Paginated } from '@/types';

type ShortHours = {
    mode: string;
    mode_label: string;
    required_minutes: number;
    worked_minutes: number;
    short_minutes: number;
    hourly_rate: number;
    calculated_amount: number;
    adjusted_amount: number | null;
    adjustment_reason: string | null;
    final_amount: number;
};

type EmployeeRow = EmployeeBrief & { short_hours: ShortHours };

const props = defineProps<{
    employees: Paginated<EmployeeRow>;
    period: {
        year: number;
        month: number;
        start: string;
        end: string;
        label: string;
        days: number;
    };
    month: string;
    filters: { search: string };
    rule: {
        mode: string;
        mode_label: string;
        rate_type: string;
        fixed_rate: number | null;
        tolerance_minutes: number;
        modes: Option[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Short Hours', href: index() }],
    },
});

const { money, minutes, date } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('finance.manage'));

const { filters } = useQueryFilters(
    () => index.url(),
    { month: props.month, search: props.filters.search ?? '' },
    { debounced: ['search'] },
);

const ruleExplanation = computed(() => {
    switch (props.rule.mode) {
        case 'deduct':
            return 'The calculated amount is deducted from salary automatically.';
        case 'manual':
            return 'Nothing is deducted until you set an amount for the employee.';
        default:
            return 'Short hours are recorded here, but nothing is deducted unless you set an amount.';
    }
});

const rateExplanation = computed(() =>
    props.rule.rate_type === 'fixed' && props.rule.fixed_rate !== null
        ? `Each short hour is worth a fixed ${money(props.rule.fixed_rate)}.`
        : 'Each short hour is worth the employee’s own hourly salary rate.',
);

// Adjust dialog
const adjusting = ref<EmployeeRow | null>(null);
const adjustOpen = computed({
    get: () => adjusting.value !== null,
    set: (value: boolean) => {
        if (!value) {
            adjusting.value = null;
        }
    },
});

const form = useForm({
    month: props.month,
    amount: '' as number | string,
    reason: '',
});

function startAdjust(row: EmployeeRow): void {
    form.clearErrors();
    form.month = props.month;
    form.amount =
        row.short_hours.adjusted_amount ?? row.short_hours.calculated_amount;
    form.reason = row.short_hours.adjustment_reason ?? '';
    adjusting.value = row;
}

function saveAdjustment(): void {
    if (!adjusting.value) {
        return;
    }

    form.put(update.url(adjusting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            adjusting.value = null;
        },
    });
}

const removal = useConfirmedAction<EmployeeRow>();

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Employee', primary: true },
    { key: 'required', label: 'Required hours', align: 'right' },
    { key: 'worked', label: 'Actual hours', align: 'right' },
    { key: 'short', label: 'Short hours', align: 'right' },
    { key: 'rate', label: 'Hourly rate', align: 'right' },
    { key: 'calculated', label: 'Calculated amount', align: 'right' },
    { key: 'adjusted', label: 'Admin-adjusted amount', align: 'right' },
    { key: 'final', label: 'Final deduction', align: 'right' },
];
</script>

<template>
    <Head title="Short Hours" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Short Hours"
            description="Employees who worked fewer hours than required, what that is worth, and what is actually deducted from salary."
        />

        <!-- The company rule that decides the final deduction -->
        <div
            class="flex flex-col gap-3 rounded-lg border bg-card p-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="flex gap-3">
                <Info
                    class="mt-0.5 size-4 shrink-0 text-info"
                    aria-hidden="true"
                />
                <div class="text-sm">
                    <p class="font-medium">
                        Company rule: {{ rule.mode_label }}
                    </p>
                    <p class="mt-1 max-w-[75ch] text-muted-foreground">
                        {{ ruleExplanation }} {{ rateExplanation }}
                        <template v-if="rule.tolerance_minutes > 0">
                            A shortfall of up to
                            {{ rule.tolerance_minutes }} minutes in a day is
                            ignored.
                        </template>
                        An admin-adjusted amount always replaces the calculated
                        amount.
                    </p>
                </div>
            </div>
            <Button
                v-if="can('settings.manage')"
                variant="outline"
                size="sm"
                class="shrink-0"
                as-child
            >
                <Link :href="payrollSettings()">Change rule</Link>
            </Button>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <MonthNavigator v-model="filters.month" />
            <div class="relative sm:w-72">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    type="search"
                    placeholder="Employee name or ID"
                    aria-label="Search employees"
                    class="pl-9"
                />
            </div>
            <p class="text-sm text-muted-foreground sm:ml-auto">
                Payroll period {{ date(period.start) }} to
                {{ date(period.end) }}
            </p>
        </div>

        <DataTable :columns="columns" :rows="employees.data">
            <template #cell-name="{ row }">
                <EmployeeCell
                    :name="row.name"
                    :code="row.code"
                    :subtitle="row.department"
                    :photo-url="row.photo_url"
                    :href="
                        can('attendance.view')
                            ? attendanceShow(row.id, { query: { month } })
                            : undefined
                    "
                />
            </template>
            <template #cell-required="{ row }">
                <span class="tabular">
                    {{ minutes(row.short_hours.required_minutes) }}
                </span>
            </template>
            <template #cell-worked="{ row }">
                <span class="tabular">
                    {{ minutes(row.short_hours.worked_minutes) }}
                </span>
            </template>
            <template #cell-short="{ row }">
                <span class="tabular font-medium">
                    {{ minutes(row.short_hours.short_minutes) }}
                </span>
            </template>
            <template #cell-rate="{ row }">
                <span class="tabular">
                    {{ money(row.short_hours.hourly_rate) }}
                </span>
            </template>
            <template #cell-calculated="{ row }">
                <span class="tabular">
                    {{ money(row.short_hours.calculated_amount) }}
                </span>
            </template>
            <template #cell-adjusted="{ row }">
                <template v-if="row.short_hours.adjusted_amount !== null">
                    <span class="tabular">
                        {{ money(row.short_hours.adjusted_amount) }}
                    </span>
                    <span
                        v-if="row.short_hours.adjustment_reason"
                        class="block max-w-[28ch] text-xs text-muted-foreground md:ml-auto"
                    >
                        {{ row.short_hours.adjustment_reason }}
                    </span>
                </template>
                <span v-else class="text-muted-foreground">Not adjusted</span>
            </template>
            <template #cell-final="{ row }">
                <span class="tabular font-semibold">
                    {{ money(row.short_hours.final_amount) }}
                </span>
                <span class="mt-0.5 block">
                    <StatusBadge
                        :tone="
                            row.short_hours.adjusted_amount !== null
                                ? 'warning'
                                : row.short_hours.final_amount > 0
                                  ? 'negative'
                                  : 'neutral'
                        "
                    >
                        {{
                            row.short_hours.adjusted_amount !== null
                                ? 'Admin adjusted'
                                : row.short_hours.final_amount > 0
                                  ? 'Deducted'
                                  : 'Not deducted'
                        }}
                    </StatusBadge>
                </span>
            </template>
            <template v-if="canManage" #actions="{ row }">
                <Button variant="outline" size="sm" @click="startAdjust(row)">
                    <SlidersHorizontal />
                    Adjust
                </Button>
                <Button
                    v-if="row.short_hours.adjusted_amount !== null"
                    variant="ghost"
                    size="sm"
                    @click="removal.ask(row)"
                >
                    <RotateCcw />
                    Remove adjustment
                </Button>
            </template>
            <template #empty>
                <EmptyState
                    :title="`No short hours in ${period.label}`"
                    description="Nobody worked fewer hours than required in this period, or attendance has no check-in and check-out times yet."
                />
            </template>
        </DataTable>

        <DataPagination :page="employees" noun="employees" />
    </div>

    <Dialog v-model:open="adjustOpen">
        <DialogContent class="sm:max-w-md">
            <form
                v-if="adjusting"
                class="grid gap-5"
                @submit.prevent="saveAdjustment"
            >
                <DialogHeader>
                    <DialogTitle>Adjust short-hours deduction</DialogTitle>
                    <DialogDescription>
                        {{ adjusting.name }}, {{ period.label }}. The amount you
                        set is what payroll deducts, instead of the calculated
                        amount.
                    </DialogDescription>
                </DialogHeader>

                <dl
                    class="tabular grid grid-cols-3 gap-3 rounded-md bg-muted px-3 py-2 text-sm"
                >
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Short hours
                        </dt>
                        <dd class="font-medium">
                            {{ minutes(adjusting.short_hours.short_minutes) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Hourly rate
                        </dt>
                        <dd class="font-medium">
                            {{ money(adjusting.short_hours.hourly_rate) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Calculated
                        </dt>
                        <dd class="font-medium">
                            {{ money(adjusting.short_hours.calculated_amount) }}
                        </dd>
                    </div>
                </dl>

                <FormField
                    label="Deduction to apply"
                    for="short-hours-amount"
                    :error="form.errors.amount"
                    hint="Enter 0 to waive the deduction for this period."
                    required
                >
                    <Input
                        id="short-hours-amount"
                        v-model="form.amount"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        step="0.01"
                        required
                        class="tabular text-right"
                    />
                </FormField>

                <FormField
                    label="Reason"
                    for="short-hours-reason"
                    :error="form.errors.reason"
                    hint="Recorded in the audit log and shown in the payroll breakdown."
                    required
                >
                    <Input
                        id="short-hours-reason"
                        v-model="form.reason"
                        required
                        minlength="3"
                        maxlength="255"
                    />
                </FormField>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="adjusting = null"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        Save adjustment
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removal.target.value !== null"
        title="Remove this adjustment?"
        :description="`The company rule (${rule.mode_label}) will decide the short-hours deduction for ${removal.target.value?.name ?? 'this employee'} in ${period.label} again.`"
        confirm-label="Remove adjustment"
        :processing="removal.processing.value"
        @update:open="(value) => !value && (removal.target.value = null)"
        @confirm="
            removal.target.value &&
            removal.run('delete', destroy.url(removal.target.value.id), {
                month,
            })
        "
    />
</template>
