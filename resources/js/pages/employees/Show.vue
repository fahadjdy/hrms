<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    Clock,
    HandCoins,
    LogOut,
    Pencil,
    ReceiptText,
    RotateCcw,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DetailList from '@/components/DetailList.vue';
import type { DetailItem } from '@/components/DetailList.vue';
import EmptyState from '@/components/EmptyState.vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import SectionCard from '@/components/SectionCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { useInitials } from '@/composables/useInitials';
import { usePermissions } from '@/composables/usePermissions';
import { useConfirmedAction } from '@/composables/useResourceForm';
import { statusTone } from '@/lib/status';
import { create as borrowCreate, show as borrowShow } from '@/routes/borrows';
import { edit, index, past, show } from '@/routes/employees';
import { show as attendanceShow } from '@/routes/employees/attendance';
import {
    destroy as exitDestroy,
    store as exitStore,
} from '@/routes/employees/exit';
import { show as salaryShow } from '@/routes/employees/salary';
import { update as shiftUpdate } from '@/routes/employees/shift';
import { show as settlementShow } from '@/routes/final-settlements';
import type { AttendanceSummary, EmployeeBrief, Option } from '@/types';

type Employee = EmployeeBrief & {
    first_name: string;
    last_name: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    postal_code: string | null;
    notes: string | null;
    exit_reason: string | null;
    exit_notes: string | null;
    status_label: string;
    is_past: boolean;
    gender: string | null;
    employment_type: string;
    date_of_birth: string | null;
    joining_date: string;
    probation_end_date: string | null;
    reporting_manager: string | null;
    exit_date: string | null;
    last_working_date: string | null;
    exit_type: string | null;
    settlement_status: string | null;
};

type Shift = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    required_minutes: number;
};

type BorrowItem = {
    id: number;
    reference_no: string;
    kind: string;
    amount: number;
    outstanding: number;
    monthly_deduction: number;
    borrow_date: string;
    status: string;
    status_label: string;
};

type LeaveBalance = {
    leave_type_id: number;
    name: string;
    code: string;
    is_paid: boolean;
    allocated: number;
    adjustment: number;
    used: number;
    pending: number;
    remaining: number;
};

type Activity = {
    id: number;
    action: string;
    description: string | null;
    user: string | null;
    created_at: string | null;
};

const props = defineProps<{
    employee: Employee;
    shift: (Shift & { source: string | null }) | null;
    shifts: Shift[];
    salary: {
        gross: number;
        effective_date: string;
        components: { name: string; type: string; amount: number }[];
        revisions_count: number;
    } | null;
    attendance: {
        period: { label: string; start: string; end: string };
        summary: AttendanceSummary;
    };
    borrows: {
        total_borrowed: number;
        total_recovered: number;
        total_outstanding: number;
        items: BorrowItem[];
    };
    leaveBalances: LeaveBalance[];
    activity: Activity[];
    exitTypes: Option[];
    today: string;
}>();

setLayoutProps({
    breadcrumbs: [
        props.employee.is_past
            ? { title: 'Past Employees', href: past() }
            : { title: 'Active Employees', href: index() },
        { title: props.employee.name, href: show(props.employee.id) },
    ],
});

const { money, date, dateTime, minutes, time, number, percent } = useFormat();
const { getInitials } = useInitials();
const { can } = usePermissions();

const canManage = computed(() => can('employees.manage'));

const details = computed<DetailItem[]>(() => [
    { label: 'Employee ID', value: props.employee.code },
    { label: 'Department', value: props.employee.department },
    { label: 'Designation', value: props.employee.designation },
    { label: 'Employment type', value: props.employee.employment_type },
    { label: 'Reporting manager', value: props.employee.reporting_manager },
    { label: 'Joining date', value: date(props.employee.joining_date) },
    {
        label: 'Probation ends on',
        value: props.employee.probation_end_date
            ? date(props.employee.probation_end_date)
            : null,
    },
    { label: 'Gender', value: props.employee.gender },
    {
        label: 'Date of birth',
        value: props.employee.date_of_birth
            ? date(props.employee.date_of_birth)
            : null,
    },
    { label: 'Phone', value: props.employee.phone },
    { label: 'Email', value: props.employee.email },
    {
        label: 'Address',
        value: [
            props.employee.address,
            props.employee.city,
            props.employee.state,
            props.employee.postal_code,
            props.employee.country,
        ]
            .filter(Boolean)
            .join(', '),
    },
]);

const exitDetails = computed<DetailItem[]>(() => [
    {
        label: 'Last working day',
        value: props.employee.last_working_date
            ? date(props.employee.last_working_date)
            : null,
    },
    {
        label: 'Exit date',
        value: props.employee.exit_date ? date(props.employee.exit_date) : null,
    },
    { label: 'Exit type', value: props.employee.exit_type },
    { label: 'Reason', value: props.employee.exit_reason },
]);

const summary = computed(() => props.attendance.summary);

const attendanceStats = computed<DetailItem[]>(() => [
    { label: 'Working days', value: summary.value.working_days },
    { label: 'Present', value: summary.value.present },
    { label: 'Absent', value: summary.value.absent },
    { label: 'Half day', value: summary.value.half_day },
    { label: 'Paid leave', value: summary.value.paid_leave },
    { label: 'Unpaid leave', value: summary.value.unpaid_leave },
    { label: 'Late', value: summary.value.late },
    { label: 'Not marked', value: summary.value.unmarked },
    { label: 'Required hours', value: minutes(summary.value.required_minutes) },
    { label: 'Worked hours', value: minutes(summary.value.worked_minutes) },
    { label: 'Short hours', value: minutes(summary.value.short_minutes) },
    { label: 'Overtime', value: minutes(summary.value.overtime_minutes) },
]);

const shiftSource: Record<string, string> = {
    employee: 'Set for this employee',
    gender: 'Gender-based company default',
    company: 'Company default shift',
};

const settlementLabel: Record<string, string> = {
    draft: 'Draft',
    finalized: 'Finalized',
    paid: 'Paid',
};

/* Leave company */
const exitOpen = ref(false);
const exitForm = useForm({
    exit_date: props.today,
    last_working_date: props.today,
    exit_type: props.exitTypes[0]?.value ?? 'resignation',
    exit_reason: '',
    exit_notes: '',
});

function submitExit(): void {
    exitForm.post(exitStore.url(props.employee.id), {
        preserveScroll: true,
        onSuccess: () => {
            exitOpen.value = false;
        },
    });
}

/* Reinstate */
const reinstate = useConfirmedAction<Employee>();

/* Work timing */
const shiftOpen = ref(false);
const shiftForm = useForm({
    work_shift_id: (props.shift?.source === 'employee'
        ? props.shift.id
        : null) as number | null,
    effective_from: props.today,
});

const shiftOptions = computed(() =>
    props.shifts.map((shift) => ({
        value: shift.id,
        label: `${shift.name} (${shift.start_time.slice(0, 5)} to ${shift.end_time.slice(0, 5)}, ${minutes(shift.required_minutes)} required)`,
    })),
);

function openShiftDialog(): void {
    shiftForm.work_shift_id =
        props.shift?.source === 'employee' ? props.shift.id : null;
    shiftForm.effective_from = props.today;
    shiftForm.clearErrors();
    shiftOpen.value = true;
}

function submitShift(): void {
    shiftForm.put(shiftUpdate.url(props.employee.id), {
        preserveScroll: true,
        onSuccess: () => {
            shiftOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="employee.name" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <header
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <div class="flex min-w-0 items-center gap-4">
                <Avatar class="size-16 shrink-0 rounded-md">
                    <AvatarImage
                        v-if="employee.photo_url"
                        :src="employee.photo_url"
                        :alt="employee.name"
                    />
                    <AvatarFallback class="rounded-md text-lg">
                        {{ getInitials(employee.name) }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1
                            class="text-xl font-semibold tracking-tight break-words"
                        >
                            {{ employee.name }}
                        </h1>
                        <StatusBadge
                            :tone="statusTone('employee', employee.status)"
                        >
                            {{ employee.status_label }}
                        </StatusBadge>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <span class="tabular">{{ employee.code }}</span>
                        <template v-if="employee.designation">
                            / {{ employee.designation }}
                        </template>
                        <template v-if="employee.department">
                            / {{ employee.department }}
                        </template>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="can('attendance.view')"
                    variant="outline"
                    as-child
                >
                    <Link :href="attendanceShow(employee.id)">
                        <CalendarDays />
                        Attendance calendar
                    </Link>
                </Button>
                <Button v-if="can('payroll.view')" variant="outline" as-child>
                    <Link :href="salaryShow(employee.id)">
                        <Wallet />
                        Salary
                    </Link>
                </Button>
                <Button
                    v-if="can('finance.manage') && !employee.is_past"
                    variant="outline"
                    as-child
                >
                    <Link
                        :href="
                            borrowCreate({
                                query: { employee_id: employee.id },
                            })
                        "
                    >
                        <HandCoins />
                        New borrow
                    </Link>
                </Button>
                <Button v-if="canManage" variant="outline" as-child>
                    <Link :href="edit(employee.id)">
                        <Pencil />
                        Edit
                    </Link>
                </Button>
                <Button
                    v-if="canManage && !employee.is_past"
                    variant="destructive"
                    @click="exitOpen = true"
                >
                    <LogOut />
                    Leave company
                </Button>
            </div>
        </header>

        <!-- Exit details replace the "Leave company" action once the employee has left. -->
        <SectionCard
            v-if="employee.is_past"
            title="Exit details"
            description="This employee has left. Attendance, salary, payroll, borrow and documents are all kept."
        >
            <template #actions>
                <StatusBadge
                    :tone="
                        employee.settlement_status
                            ? statusTone(
                                  'settlement',
                                  employee.settlement_status,
                              )
                            : 'neutral'
                    "
                >
                    Final settlement:
                    {{
                        employee.settlement_status
                            ? (settlementLabel[employee.settlement_status] ??
                              employee.settlement_status)
                            : 'Not started'
                    }}
                </StatusBadge>
            </template>

            <DetailList :items="exitDetails" />
            <p
                v-if="employee.exit_notes"
                class="mt-4 max-w-[70ch] text-sm whitespace-pre-line text-muted-foreground"
            >
                {{ employee.exit_notes }}
            </p>

            <div class="mt-4 flex flex-wrap gap-2 border-t pt-4">
                <Button v-if="can('settlements.manage')" as-child>
                    <Link :href="settlementShow(employee.id)">
                        <ReceiptText />
                        Open final settlement
                    </Link>
                </Button>
                <Button
                    v-if="
                        canManage &&
                        (!employee.settlement_status ||
                            employee.settlement_status === 'draft')
                    "
                    variant="outline"
                    @click="reinstate.ask(employee)"
                >
                    <RotateCcw />
                    Reinstate employee
                </Button>
            </div>
        </SectionCard>

        <div class="grid items-start gap-5 lg:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-5 lg:col-span-2">
                <SectionCard title="Details">
                    <DetailList :items="details" :columns="3" />
                    <div v-if="employee.notes" class="mt-4 border-t pt-4">
                        <p class="text-xs text-muted-foreground">Notes</p>
                        <p
                            class="mt-0.5 max-w-[70ch] text-sm whitespace-pre-line"
                        >
                            {{ employee.notes }}
                        </p>
                    </div>
                </SectionCard>

                <SectionCard
                    :title="`Attendance - ${attendance.period.label}`"
                    :description="`${date(attendance.period.start)} to ${date(attendance.period.end)}`"
                >
                    <template v-if="can('attendance.view')" #actions>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="attendanceShow(employee.id)">
                                Open calendar
                            </Link>
                        </Button>
                    </template>

                    <dl
                        class="tabular grid grid-cols-2 gap-x-6 gap-y-4 text-sm sm:grid-cols-3 xl:grid-cols-4"
                    >
                        <div v-for="stat in attendanceStats" :key="stat.label">
                            <dt class="text-xs text-muted-foreground">
                                {{ stat.label }}
                            </dt>
                            <dd class="mt-0.5 font-medium">{{ stat.value }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 border-t pt-3 text-sm">
                        <span class="text-muted-foreground">
                            Attendance rate
                        </span>
                        <span class="tabular ml-2 font-semibold">
                            {{ percent(summary.attendance_rate) }}
                        </span>
                        <span class="ml-2 text-xs text-muted-foreground">
                            Attended days over the
                            {{ summary.elapsed_working_days }} working days so
                            far; paid leave counts, a half day counts as half.
                        </span>
                    </p>
                </SectionCard>

                <SectionCard
                    v-if="can('finance.view')"
                    title="Borrow / advance"
                    description="Each borrow is tracked separately with its own balance."
                    flush
                >
                    <dl
                        class="tabular grid grid-cols-1 divide-y border-b text-sm sm:grid-cols-3 sm:divide-x sm:divide-y-0"
                    >
                        <div class="px-4 py-3 sm:px-5">
                            <dt class="text-xs text-muted-foreground">
                                Total borrowed
                            </dt>
                            <dd class="mt-0.5 text-base font-semibold">
                                {{ money(borrows.total_borrowed) }}
                            </dd>
                        </div>
                        <div class="px-4 py-3 sm:px-5">
                            <dt class="text-xs text-muted-foreground">
                                Total recovered
                            </dt>
                            <dd class="mt-0.5 text-base font-semibold">
                                {{ money(borrows.total_recovered) }}
                            </dd>
                        </div>
                        <div class="px-4 py-3 sm:px-5">
                            <dt class="text-xs text-muted-foreground">
                                Total outstanding
                            </dt>
                            <dd
                                class="mt-0.5 text-base font-semibold"
                                :class="
                                    borrows.total_outstanding > 0
                                        ? 'text-warning'
                                        : ''
                                "
                            >
                                {{ money(borrows.total_outstanding) }}
                            </dd>
                        </div>
                    </dl>

                    <EmptyState
                        v-if="borrows.items.length === 0"
                        title="No borrow records"
                        description="A borrow or salary advance given to this employee will be listed here."
                    />
                    <ul v-else class="divide-y">
                        <li
                            v-for="borrow in borrows.items"
                            :key="borrow.id"
                            class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-3 text-sm sm:px-5"
                        >
                            <div class="min-w-0">
                                <Link
                                    :href="borrowShow(borrow.id)"
                                    class="tabular font-medium underline-offset-4 hover:underline focus-visible:underline"
                                >
                                    {{ borrow.reference_no }}
                                </Link>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        borrow.kind === 'existing'
                                            ? 'Existing at joining'
                                            : 'New borrow'
                                    }}
                                    / {{ date(borrow.borrow_date) }}
                                    <template
                                        v-if="borrow.monthly_deduction > 0"
                                    >
                                        /
                                        {{ money(borrow.monthly_deduction) }}
                                        per month
                                    </template>
                                </p>
                            </div>
                            <div
                                class="tabular flex flex-wrap items-center gap-x-6 gap-y-1"
                            >
                                <span>
                                    <span class="text-xs text-muted-foreground">
                                        Amount
                                    </span>
                                    {{ money(borrow.amount) }}
                                </span>
                                <span>
                                    <span class="text-xs text-muted-foreground">
                                        Remaining
                                    </span>
                                    <span class="font-medium">
                                        {{
                                            money(
                                                borrow.status === 'cancelled'
                                                    ? 0
                                                    : borrow.outstanding,
                                            )
                                        }}
                                    </span>
                                </span>
                                <StatusBadge
                                    :tone="statusTone('borrow', borrow.status)"
                                >
                                    {{ borrow.status_label }}
                                </StatusBadge>
                            </div>
                        </li>
                    </ul>
                </SectionCard>

                <SectionCard
                    title="Recent activity"
                    description="The latest audited changes to this employee's records."
                >
                    <EmptyState
                        v-if="activity.length === 0"
                        title="No activity yet"
                        description="Changes to attendance, salary, borrow and leave will appear here."
                    />
                    <ol v-else class="grid gap-4 border-l pl-4">
                        <li
                            v-for="entry in activity"
                            :key="entry.id"
                            class="relative text-sm"
                        >
                            <span
                                class="absolute top-1.5 -left-[1.3125rem] size-2 rounded-full bg-border ring-4 ring-card"
                                aria-hidden="true"
                            />
                            <p>{{ entry.description ?? entry.action }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ dateTime(entry.created_at) }}
                                <template v-if="entry.user">
                                    by {{ entry.user }}
                                </template>
                            </p>
                        </li>
                    </ol>
                </SectionCard>
            </div>

            <div class="flex min-w-0 flex-col gap-5">
                <SectionCard title="Work timing">
                    <template v-if="canManage && !employee.is_past" #actions>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="openShiftDialog"
                        >
                            Change
                        </Button>
                    </template>

                    <div v-if="shift" class="flex items-start gap-3">
                        <Clock
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0 text-sm">
                            <p class="font-medium">{{ shift.name }}</p>
                            <p class="tabular mt-0.5">
                                {{ time(shift.start_time) }} to
                                {{ time(shift.end_time) }}
                            </p>
                            <p class="tabular text-muted-foreground">
                                {{ minutes(shift.required_minutes) }} required
                                per day
                            </p>
                            <StatusBadge
                                v-if="shift.source"
                                class="mt-2"
                                :tone="
                                    shift.source === 'employee'
                                        ? 'info'
                                        : 'neutral'
                                "
                            >
                                {{ shiftSource[shift.source] ?? shift.source }}
                            </StatusBadge>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No shift applies. Set a company default shift in the
                        attendance settings, or give this employee their own.
                    </p>
                </SectionCard>

                <SectionCard v-if="can('payroll.view')" title="Current salary">
                    <template #actions>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="salaryShow(employee.id)">
                                {{
                                    salary
                                        ? 'History and revisions'
                                        : 'Set salary'
                                }}
                            </Link>
                        </Button>
                    </template>

                    <template v-if="salary">
                        <p class="text-2xl font-semibold tracking-tight">
                            {{ money(salary.gross) }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Gross per month, effective
                            {{ date(salary.effective_date) }} /
                            {{ salary.revisions_count }}
                            {{
                                salary.revisions_count === 1
                                    ? 'revision'
                                    : 'revisions'
                            }}
                        </p>
                        <ul class="tabular mt-4 divide-y border-t text-sm">
                            <li
                                v-for="component in salary.components"
                                :key="`${component.type}-${component.name}`"
                                class="flex items-center justify-between gap-4 py-2"
                            >
                                <span class="min-w-0 truncate">
                                    {{ component.name }}
                                    <span
                                        v-if="component.type === 'deduction'"
                                        class="text-xs text-muted-foreground"
                                    >
                                        (deduction)
                                    </span>
                                </span>
                                <span>
                                    {{
                                        component.type === 'deduction'
                                            ? money(-component.amount)
                                            : money(component.amount)
                                    }}
                                </span>
                            </li>
                        </ul>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">
                        No salary is set yet. Payroll pays nothing for this
                        employee until a salary structure is added.
                    </p>
                </SectionCard>

                <SectionCard
                    v-if="can('leave.view')"
                    :title="`Leave balance ${today.slice(0, 4)}`"
                >
                    <p
                        v-if="leaveBalances.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No active leave types. Add leave types to track
                        balances.
                    </p>
                    <ul v-else class="tabular divide-y text-sm">
                        <li
                            v-for="balance in leaveBalances"
                            :key="balance.leave_type_id"
                            class="flex items-center justify-between gap-4 py-2 first:pt-0 last:pb-0"
                        >
                            <div class="min-w-0">
                                <p class="truncate">{{ balance.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ number(balance.used) }} used
                                    <template v-if="balance.pending > 0">
                                        / {{ number(balance.pending) }} pending
                                    </template>
                                    <template v-if="!balance.is_paid">
                                        / unpaid
                                    </template>
                                </p>
                            </div>
                            <p class="shrink-0 text-right">
                                <span class="font-medium">
                                    {{ number(balance.remaining) }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    of
                                    {{
                                        number(
                                            balance.allocated +
                                                balance.adjustment,
                                        )
                                    }}
                                    left
                                </span>
                            </p>
                        </li>
                    </ul>
                </SectionCard>
            </div>
        </div>
    </div>

    <!-- Leave company -->
    <Dialog v-model:open="exitOpen">
        <DialogContent class="sm:max-w-lg">
            <form class="grid gap-5" @submit.prevent="submitExit">
                <DialogHeader>
                    <DialogTitle>{{ employee.name }} is leaving</DialogTitle>
                    <DialogDescription>
                        The employee becomes a past employee and drops out of
                        future payroll. Nothing is deleted: attendance, salary,
                        payroll, borrow and documents stay on record.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Last working day"
                        for="exit-last-working-date"
                        :error="exitForm.errors.last_working_date"
                        hint="Salary is paid up to this day."
                        required
                    >
                        <Input
                            id="exit-last-working-date"
                            v-model="exitForm.last_working_date"
                            type="date"
                            :min="employee.joining_date"
                            required
                        />
                    </FormField>
                    <FormField
                        label="Exit date"
                        for="exit-date"
                        :error="exitForm.errors.exit_date"
                        hint="The date the exit is recorded for."
                        required
                    >
                        <Input
                            id="exit-date"
                            v-model="exitForm.exit_date"
                            type="date"
                            :min="employee.joining_date"
                            required
                        />
                    </FormField>
                    <FormField
                        label="Exit type"
                        for="exit-type"
                        :error="exitForm.errors.exit_type"
                        required
                    >
                        <NativeSelect
                            id="exit-type"
                            v-model="exitForm.exit_type"
                            :options="exitTypes"
                        />
                    </FormField>
                    <FormField
                        label="Reason"
                        for="exit-reason"
                        :error="exitForm.errors.exit_reason"
                    >
                        <Input
                            id="exit-reason"
                            v-model="exitForm.exit_reason"
                            maxlength="255"
                            placeholder="e.g. Moved to another city"
                        />
                    </FormField>
                    <FormField
                        label="Notes"
                        for="exit-notes"
                        :error="exitForm.errors.exit_notes"
                        class="sm:col-span-2"
                    >
                        <Textarea
                            id="exit-notes"
                            v-model="exitForm.exit_notes"
                            rows="3"
                            maxlength="5000"
                        />
                    </FormField>
                </div>

                <p
                    v-if="borrows.total_outstanding > 0"
                    class="rounded-md bg-warning-soft px-3 py-2 text-sm text-warning"
                >
                    {{ money(borrows.total_outstanding) }} of borrow is still
                    outstanding. It is recovered in the final settlement.
                </p>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="exitOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="exitForm.processing"
                    >
                        <Spinner v-if="exitForm.processing" />
                        Mark as past employee
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Work timing -->
    <Dialog v-model:open="shiftOpen">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-5" @submit.prevent="submitShift">
                <DialogHeader>
                    <DialogTitle>Change work timing</DialogTitle>
                    <DialogDescription>
                        An employee-specific shift takes priority over the
                        gender-based and company default shifts. Earlier dates
                        keep the timing they had.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Work shift"
                    for="shift-work-shift"
                    :error="shiftForm.errors.work_shift_id"
                >
                    <NativeSelect
                        id="shift-work-shift"
                        v-model="shiftForm.work_shift_id"
                        :options="shiftOptions"
                        placeholder="Use the default shift (gender or company)"
                    />
                </FormField>
                <FormField
                    label="Effective from"
                    for="shift-effective-from"
                    :error="shiftForm.errors.effective_from"
                    required
                >
                    <Input
                        id="shift-effective-from"
                        v-model="shiftForm.effective_from"
                        type="date"
                        required
                    />
                </FormField>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="shiftOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="shiftForm.processing">
                        <Spinner v-if="shiftForm.processing" />
                        Save work timing
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="reinstate.target.value !== null"
        title="Reinstate this employee?"
        :description="`${employee.name} becomes an active employee again and the exit details are cleared. Any draft final settlement is discarded.`"
        confirm-label="Reinstate employee"
        :processing="reinstate.processing.value"
        @update:open="(value) => !value && (reinstate.target.value = null)"
        @confirm="reinstate.run('delete', exitDestroy.url(employee.id))"
    />
</template>
