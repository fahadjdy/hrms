<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    Calculator,
    Download,
    FileText,
    Lock,
    LockOpen,
    RefreshCw,
    Search,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import PayrollWorkflow from '@/components/payroll/PayrollWorkflow.vue';
import SectionCard from '@/components/SectionCard.vue';
import StatCard from '@/components/StatCard.vue';
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
import { useQueryFilters } from '@/composables/useQueryFilters';
import { toastFirstError } from '@/composables/useResourceForm';
import { statusTone } from '@/lib/status';
import {
    calculate,
    destroy,
    finalize,
    index,
    reopen,
    review,
    show,
} from '@/routes/payroll';
import { show as itemShow } from '@/routes/payroll/items';
import { exportMethod } from '@/routes/reports';
import { index as salarySlips } from '@/routes/salary-slips';
import type { BreadcrumbItem, IdName, Option, Paginated } from '@/types';

type Payroll = {
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
    finalized_by: string | null;
    reopened_at: string | null;
    reopen_reason: string | null;
    notes: string | null;
};

type PayrollItemRow = {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_code: string;
    department_name: string | null;
    designation_name: string | null;
    gross_salary: number;
    overtime_amount: number;
    bonus_amount: number;
    other_earnings_amount: number;
    attendance_deduction: number;
    unpaid_leave_deduction: number;
    short_hours_deduction: number;
    borrow_recovery: number;
    other_deductions: number;
    borrow_given: number;
    net_salary: number;
    net_payable: number;
    present_days: number;
    absent_days: number;
    leave_days: number;
    short_minutes: number;
    overtime_minutes: number;
    is_adjusted: boolean;
    warnings: number;
};

const props = defineProps<{
    payroll: Payroll;
    items: Paginated<PayrollItemRow>;
    filters: {
        search?: string;
        department_id?: string;
        status?: string;
    };
    departments: IdName[];
    workflow: Option[];
}>();

setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
    breadcrumbs: [
        { title: 'Payroll', href: index() },
        { title: props.payroll.label, href: show(props.payroll.id) },
    ],
});

const { money, number, minutes, date, dateTime } = useFormat();
const { can } = usePermissions();

const canManage = computed(() => can('payroll.manage'));
const canFinalize = computed(() => can('payroll.finalize'));
const isDraft = computed(() => props.payroll.status === 'draft');
const isLocked = computed(() => props.payroll.is_locked);
const hasItems = computed(() => props.payroll.employee_count > 0);

/** A deduction is money leaving the employee's pay, so it reads as a negative. */
const minus = (amount: number): string => (amount > 0 ? money(-amount) : '-');
const plus = (amount: number): string => (amount > 0 ? money(amount) : '-');

const { filters, reset } = useQueryFilters(
    () => show.url(props.payroll.id),
    {
        search: props.filters.search ?? '',
        department_id: props.filters.department_id
            ? Number(props.filters.department_id)
            : null,
        status: props.filters.status ?? null,
    },
    { debounced: ['search'] },
);

// Which action is running, so only its button shows a spinner.
const acting = ref<string | null>(null);

function act(
    name: string,
    url: string,
    method: 'post' | 'delete' = 'post',
    onSuccess?: () => void,
): void {
    acting.value = name;

    router.visit(url, {
        method,
        preserveScroll: true,
        onSuccess: () => onSuccess?.(),
        onError: toastFirstError,
        onFinish: () => {
            acting.value = null;
        },
    });
}

const finalizeOpen = ref(false);
const deleteOpen = ref(false);
const reopenOpen = ref(false);
const reopenForm = useForm({ reason: '' });

function openReopen(): void {
    reopenForm.reset();
    reopenForm.clearErrors();
    reopenOpen.value = true;
}

function submitReopen(): void {
    reopenForm.post(reopen.url(props.payroll.id), {
        preserveScroll: true,
        onSuccess: () => {
            reopenOpen.value = false;
        },
    });
}

const reopenErrors = computed(
    () => reopenForm.errors as Record<string, string | undefined>,
);

const columns: DataTableColumn[] = [
    { key: 'employee_name', label: 'Employee', primary: true },
    { key: 'gross_salary', label: 'Gross salary', align: 'right' },
    { key: 'present_days', label: 'Present', align: 'right' },
    { key: 'absent_days', label: 'Absent', align: 'right' },
    { key: 'leave_days', label: 'Leave', align: 'right' },
    { key: 'short_minutes', label: 'Short hours', align: 'right' },
    { key: 'overtime_amount', label: 'Overtime', align: 'right' },
    { key: 'bonus_amount', label: 'Bonus', align: 'right' },
    { key: 'borrow_given', label: 'Borrow given', align: 'right' },
    { key: 'borrow_recovery', label: 'Borrow recovery', align: 'right' },
    { key: 'other_deductions', label: 'Other deductions', align: 'right' },
    { key: 'net_payable', label: 'Net payable', align: 'right' },
    { key: 'is_adjusted', label: 'Status' },
];

const departmentOptions = computed(() =>
    props.departments.map((department) => ({
        value: department.id,
        label: department.name,
    })),
);

const statusOptions: Option[] = [
    { value: 'adjusted', label: 'Adjusted' },
    { value: 'calculated', label: 'Calculated' },
];

const itemUrl = (row: PayrollItemRow) =>
    itemShow({ payroll: props.payroll.id, item: row.id });
</script>

<template>
    <Head :title="`Payroll - ${payroll.label}`" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            :title="`Payroll for ${payroll.label}`"
            :description="`Pay period ${date(payroll.period_start)} to ${date(payroll.period_end)}. Open an employee to see exactly how their pay was worked out.`"
        >
            <StatusBadge :tone="statusTone('payroll', payroll.status)">
                <Lock v-if="isLocked" class="size-3" />
                {{ payroll.status_label }}
            </StatusBadge>
        </PageHeader>

        <SectionCard>
            <div
                class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
            >
                <PayrollWorkflow :steps="workflow" :current="payroll.status" />

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        v-if="canManage && !isLocked"
                        :variant="isDraft ? 'default' : 'outline'"
                        :disabled="acting !== null"
                        @click="act('calculate', calculate.url(payroll.id))"
                    >
                        <Spinner v-if="acting === 'calculate'" />
                        <Calculator v-else-if="isDraft" />
                        <RefreshCw v-else />
                        {{ isDraft ? 'Calculate payroll' : 'Recalculate' }}
                    </Button>
                    <Button
                        v-if="canManage && payroll.status === 'calculated'"
                        variant="outline"
                        :disabled="acting !== null"
                        @click="act('review', review.url(payroll.id))"
                    >
                        <Spinner v-if="acting === 'review'" />
                        Start admin review
                    </Button>
                    <Button
                        v-if="canFinalize && !isLocked && !isDraft && hasItems"
                        :disabled="acting !== null"
                        @click="finalizeOpen = true"
                    >
                        <Lock />
                        Finalize payroll
                    </Button>
                    <Button
                        v-if="canFinalize && isLocked"
                        variant="outline"
                        @click="openReopen"
                    >
                        <LockOpen />
                        Reopen payroll
                    </Button>
                    <Button v-if="isLocked" variant="outline" as-child>
                        <Link
                            :href="
                                salarySlips({
                                    query: { payroll_id: payroll.id },
                                })
                            "
                        >
                            <FileText />
                            Salary slips
                        </Link>
                    </Button>
                    <Button
                        v-if="can('reports.view') && hasItems"
                        variant="outline"
                        as-child
                    >
                        <a
                            :href="
                                exportMethod.url('payroll', {
                                    query: { payroll_id: payroll.id },
                                })
                            "
                        >
                            <Download />
                            Export CSV
                        </a>
                    </Button>
                    <Button
                        v-if="canManage && !isLocked"
                        variant="ghost"
                        class="text-negative hover:text-negative"
                        :disabled="acting !== null"
                        @click="deleteOpen = true"
                    >
                        <Trash2 />
                        Delete
                    </Button>
                </div>
            </div>

            <p
                v-if="!isLocked && !isDraft"
                class="mt-4 max-w-[80ch] text-sm text-muted-foreground"
            >
                Recalculating picks up changes to attendance, overtime, borrow
                and deductions. Manual adjustments you have made are kept and
                applied on top of the new amounts.
            </p>
        </SectionCard>

        <div
            v-if="isLocked"
            role="status"
            class="flex items-start gap-3 rounded-lg border border-positive/30 bg-positive-soft px-4 py-3 text-sm text-positive"
        >
            <Lock class="mt-0.5 size-4 shrink-0" />
            <p>
                <span class="font-medium"
                    >This payroll is finalized and locked.</span
                >
                Finalized on {{ dateTime(payroll.finalized_at)
                }}<template v-if="payroll.finalized_by">
                    by {{ payroll.finalized_by }}</template
                >. Amounts can no longer change, and later edits to attendance
                or salary do not affect it. To correct something, reopen the
                payroll.
            </p>
        </div>

        <div
            v-else-if="payroll.reopen_reason"
            role="status"
            class="flex items-start gap-3 rounded-lg border border-warning/30 bg-warning-soft px-4 py-3 text-sm text-warning"
        >
            <LockOpen class="mt-0.5 size-4 shrink-0" />
            <p>
                <span class="font-medium">This payroll was reopened</span>
                on {{ dateTime(payroll.reopened_at) }}. Reason:
                {{ payroll.reopen_reason }}. The borrow given and recovered by
                the earlier finalization were reversed. Recalculate, review and
                finalize it again.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <StatCard
                label="Employees"
                :value="number(payroll.employee_count, 0)"
                hint="Included in this payroll"
            />
            <StatCard
                label="Gross salary"
                :value="money(payroll.total_gross, { whole: true })"
                hint="Salary before overtime and bonus"
            />
            <StatCard
                label="Total deductions"
                :value="
                    payroll.total_deductions > 0
                        ? money(-payroll.total_deductions, { whole: true })
                        : money(0, { whole: true })
                "
                hint="Attendance, short hours, borrow recovery and other"
                tone="negative"
            />
            <StatCard
                label="Borrow recovered"
                :value="money(payroll.total_borrow_recovery, { whole: true })"
                hint="Part of the deductions"
                tone="warning"
            />
            <StatCard
                label="Borrow given"
                :value="money(payroll.total_borrow_given, { whole: true })"
                hint="Advance paid with salary. Not an earning."
                tone="info"
            />
            <StatCard
                label="Net payable"
                :value="money(payroll.total_net_payable, { whole: true })"
                hint="Net salary plus borrow given"
                tone="positive"
            />
        </div>

        <!-- Nothing calculated yet -->
        <div v-if="!hasItems" class="rounded-lg border bg-card">
            <div
                class="flex flex-col items-center gap-2 px-6 py-12 text-center"
            >
                <Calculator class="size-8 text-muted-foreground/60" />
                <p class="text-sm font-medium">
                    This payroll has not been calculated
                </p>
                <p class="max-w-[56ch] text-sm text-muted-foreground">
                    Calculating works out each current employee's pay for
                    {{ payroll.label }} from their salary, attendance, overtime,
                    bonuses, borrow and deductions. You can review and adjust
                    everything before finalizing.
                </p>
                <Button
                    v-if="canManage && !isLocked"
                    class="mt-2"
                    :disabled="acting !== null"
                    @click="act('calculate', calculate.url(payroll.id))"
                >
                    <Spinner v-if="acting === 'calculate'" />
                    <Calculator v-else />
                    Calculate payroll
                </Button>
            </div>
        </div>

        <template v-else>
            <div class="grid gap-2 sm:grid-cols-3">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="filters.search"
                        type="search"
                        placeholder="Employee name or ID"
                        aria-label="Search employees in this payroll"
                        class="pl-9"
                    />
                </div>
                <NativeSelect
                    v-model="filters.department_id"
                    :options="departmentOptions"
                    placeholder="All departments"
                    aria-label="Department"
                />
                <NativeSelect
                    v-model="filters.status"
                    :options="statusOptions"
                    placeholder="Adjusted and calculated"
                    aria-label="Status"
                />
            </div>

            <DataTable :columns="columns" :rows="items.data">
                <template #cell-employee_name="{ row }">
                    <Link
                        :href="itemUrl(row)"
                        class="font-medium whitespace-nowrap underline-offset-4 hover:underline focus-visible:underline"
                    >
                        {{ row.employee_name }}
                    </Link>
                    <span
                        class="block text-xs whitespace-nowrap text-muted-foreground"
                    >
                        {{
                            [row.employee_code, row.department_name]
                                .filter(Boolean)
                                .join(' / ')
                        }}
                    </span>
                </template>
                <template #cell-gross_salary="{ row }">
                    <span class="tabular whitespace-nowrap">
                        {{ money(row.gross_salary) }}
                    </span>
                </template>
                <template #cell-present_days="{ row }">
                    <span class="tabular">{{ number(row.present_days) }}</span>
                </template>
                <template #cell-absent_days="{ row }">
                    <span class="tabular">{{ number(row.absent_days) }}</span>
                    <span
                        v-if="row.attendance_deduction > 0"
                        class="tabular block text-xs whitespace-nowrap text-muted-foreground"
                    >
                        {{ money(-row.attendance_deduction) }}
                    </span>
                </template>
                <template #cell-leave_days="{ row }">
                    <span class="tabular">{{ number(row.leave_days) }}</span>
                    <span
                        v-if="row.unpaid_leave_deduction > 0"
                        class="tabular block text-xs whitespace-nowrap text-muted-foreground"
                    >
                        {{ money(-row.unpaid_leave_deduction) }}
                    </span>
                </template>
                <template #cell-short_minutes="{ row }">
                    <span class="tabular whitespace-nowrap">
                        {{
                            row.short_minutes > 0
                                ? minutes(row.short_minutes)
                                : '-'
                        }}
                    </span>
                    <span
                        v-if="row.short_minutes > 0"
                        class="tabular block text-xs whitespace-nowrap text-muted-foreground"
                    >
                        {{
                            row.short_hours_deduction > 0
                                ? money(-row.short_hours_deduction)
                                : 'Not deducted'
                        }}
                    </span>
                </template>
                <template #cell-overtime_amount="{ row }">
                    <span class="tabular whitespace-nowrap">
                        {{ plus(row.overtime_amount) }}
                    </span>
                </template>
                <template #cell-bonus_amount="{ row }">
                    <span class="tabular whitespace-nowrap">
                        {{ plus(row.bonus_amount) }}
                    </span>
                    <span
                        v-if="row.other_earnings_amount > 0"
                        class="tabular block text-xs whitespace-nowrap text-muted-foreground"
                    >
                        {{ money(row.other_earnings_amount, { signed: true }) }}
                        other earnings
                    </span>
                </template>
                <template #cell-borrow_given="{ row }">
                    <span
                        v-if="row.borrow_given > 0"
                        class="tabular inline-flex rounded-sm bg-info-soft px-1.5 py-0.5 font-medium whitespace-nowrap text-info"
                        title="Advance paid with salary. Not an earning."
                    >
                        {{ money(row.borrow_given, { signed: true }) }}
                    </span>
                    <span v-else>-</span>
                </template>
                <template #cell-borrow_recovery="{ row }">
                    <span class="tabular whitespace-nowrap">
                        {{ minus(row.borrow_recovery) }}
                    </span>
                </template>
                <template #cell-other_deductions="{ row }">
                    <span class="tabular whitespace-nowrap">
                        {{ minus(row.other_deductions) }}
                    </span>
                </template>
                <template #cell-net_payable="{ row }">
                    <span class="tabular font-semibold whitespace-nowrap">
                        {{ money(row.net_payable) }}
                    </span>
                </template>
                <template #cell-is_adjusted="{ row }">
                    <span class="inline-flex items-center gap-1.5">
                        <StatusBadge
                            :tone="row.is_adjusted ? 'warning' : 'info'"
                        >
                            {{ row.is_adjusted ? 'Adjusted' : 'Calculated' }}
                        </StatusBadge>
                        <span
                            v-if="row.warnings > 0"
                            class="inline-flex items-center gap-1 text-xs whitespace-nowrap text-warning"
                            :title="`${row.warnings} thing${row.warnings === 1 ? '' : 's'} to check. Open the breakdown for details.`"
                        >
                            <TriangleAlert class="size-3.5" />
                            <span>Check</span>
                        </span>
                    </span>
                </template>
                <template #actions="{ row }">
                    <Button variant="outline" size="sm" as-child>
                        <Link
                            :href="itemUrl(row)"
                            :aria-label="`Pay breakdown of ${row.employee_name}`"
                        >
                            Breakdown
                        </Link>
                    </Button>
                </template>
                <template #empty>
                    <div
                        class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                    >
                        <p class="text-sm font-medium">
                            No employees match these filters
                        </p>
                        <p class="max-w-[48ch] text-sm text-muted-foreground">
                            Try a different search, or clear the filters to see
                            everyone in this payroll.
                        </p>
                        <Button variant="outline" size="sm" @click="reset">
                            Clear filters
                        </Button>
                    </div>
                </template>
            </DataTable>

            <DataPagination :page="items" noun="employees" />
        </template>

        <SectionCard v-if="payroll.notes" title="Notes">
            <p class="max-w-[80ch] text-sm whitespace-pre-line">
                {{ payroll.notes }}
            </p>
        </SectionCard>
    </div>

    <ConfirmDialog
        v-model:open="finalizeOpen"
        :title="`Finalize the payroll for ${payroll.label}?`"
        description="Finalizing locks this payroll. It also posts its borrow entries: new borrows marked to be given with this salary are paid out, and the borrow recovery shown here is deducted from each borrow's balance. Overtime, bonuses and deductions included here are marked as paid, and salary slips become available."
        confirm-label="Finalize and lock"
        :processing="acting === 'finalize'"
        @confirm="
            act('finalize', finalize.url(payroll.id), 'post', () => {
                finalizeOpen = false;
            })
        "
    >
        <dl class="grid grid-cols-2 gap-3 rounded-md border p-3 text-sm">
            <div>
                <dt class="text-xs text-muted-foreground">Employees</dt>
                <dd class="tabular font-medium">
                    {{ payroll.employee_count }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Net payable</dt>
                <dd class="tabular font-medium">
                    {{ money(payroll.total_net_payable) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Borrow given</dt>
                <dd class="tabular font-medium">
                    {{ money(payroll.total_borrow_given) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Borrow recovered</dt>
                <dd class="tabular font-medium">
                    {{ money(payroll.total_borrow_recovery) }}
                </dd>
            </div>
        </dl>
    </ConfirmDialog>

    <ConfirmDialog
        v-model:open="deleteOpen"
        :title="`Delete the payroll for ${payroll.label}?`"
        description="The calculated amounts and any manual adjustments on this payroll are removed. Attendance, overtime, borrow and salary records are not touched, and you can start the payroll for this month again."
        confirm-label="Delete payroll"
        destructive
        :processing="acting === 'delete'"
        @confirm="act('delete', destroy.url(payroll.id), 'delete')"
    />

    <Dialog v-model:open="reopenOpen">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-5" @submit.prevent="submitReopen">
                <DialogHeader>
                    <DialogTitle>
                        Reopen the payroll for {{ payroll.label }}
                    </DialogTitle>
                    <DialogDescription>
                        Reopening unlocks the payroll so it can be corrected.
                        The borrow given and recovered by this payroll are
                        reversed, its salary slips are withdrawn, and the
                        reopening is recorded in the audit log with your name
                        and reason.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Reason for reopening"
                    for="reopen-reason"
                    :error="reopenErrors.reason"
                    hint="At least 5 characters. This is kept in the audit log."
                    required
                >
                    <Textarea
                        id="reopen-reason"
                        v-model="reopenForm.reason"
                        rows="3"
                        required
                        minlength="5"
                        maxlength="255"
                        placeholder="e.g. Attendance for 12 September was marked wrongly"
                    />
                </FormField>

                <InputError :message="reopenErrors.payroll" />

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="reopenOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="reopenForm.processing">
                        <Spinner v-if="reopenForm.processing" />
                        Reopen payroll
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
