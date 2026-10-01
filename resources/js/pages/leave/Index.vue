<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Ban, Check, Pencil, Plus, Search, X } from '@lucide/vue';
import { computed } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DatePicker from '@/components/DatePicker.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import {
    useConfirmedAction,
    useResourceForm,
} from '@/composables/useResourceForm';
import { statusTone } from '@/lib/status';
import { show as employeeShow } from '@/routes/employees';
import { index, store, update } from '@/routes/leaves';
import { update as decide } from '@/routes/leaves/decision';
import type { IdName, Option, Paginated } from '@/types';

type Decision = 'approve' | 'reject' | 'cancel';

type LeaveRow = {
    id: number;
    employee: { id: number; name: string; code: string };
    leave_type_id: number;
    leave_type: string;
    is_paid: boolean;
    start_date: string;
    end_date: string;
    is_half_day: boolean;
    days: number;
    status: string;
    status_label: string;
    reason: string | null;
    notes: string | null;
};

type LeaveType = { id: number; name: string; code: string; is_paid: boolean };

const props = defineProps<{
    leaves: Paginated<LeaveRow>;
    filters: {
        search?: string;
        status?: string;
        leave_type_id?: string;
        from?: string;
        to?: string;
    };
    leaveTypes: LeaveType[];
    statuses: Option[];
    employees: IdName[];
    counts: { pending: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Leave Records', href: index() }],
    },
});

const { date, number } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('leave.manage'));

const { filters, reset } = useQueryFilters(
    () => index.url(),
    {
        search: props.filters.search ?? '',
        status: props.filters.status ?? null,
        leave_type_id: props.filters.leave_type_id
            ? Number(props.filters.leave_type_id)
            : null,
        from: props.filters.from ?? '',
        to: props.filters.to ?? '',
    },
    { debounced: ['search'] },
);

const { open, editingId, form, startCreate, startEdit, submit } =
    useResourceForm(() => ({
        employee_id: null as number | null,
        leave_type_id: null as number | null,
        start_date: '',
        end_date: '',
        is_half_day: false,
        reason: '',
        notes: '',
        status: 'approved' as string | null,
    }));

const decision = useConfirmedAction<{ leave: LeaveRow; decision: Decision }>();

const columns: DataTableColumn[] = [
    { key: 'employee', label: 'Employee', primary: true },
    { key: 'leave_type', label: 'Leave type' },
    { key: 'dates', label: 'Dates' },
    { key: 'days', label: 'Days', align: 'right' },
    { key: 'reason', label: 'Reason', hideOnMobile: true },
    { key: 'status', label: 'Status' },
];

const leaveTypeOptions = computed(() =>
    props.leaveTypes.map((type) => ({
        value: type.id,
        label: `${type.name} (${type.is_paid ? 'paid' : 'unpaid'})`,
    })),
);

const employeeOptions = computed(() =>
    props.employees.map((employee) => ({
        value: employee.id,
        label: employee.name,
    })),
);

const saveAsOptions: Option[] = [
    { value: 'approved', label: 'Approved - write it to attendance now' },
    { value: 'pending', label: 'Pending - decide later' },
];

const decisionCopy: Record<
    Decision,
    {
        title: string;
        description: string;
        confirm: string;
        destructive: boolean;
    }
> = {
    approve: {
        title: 'Approve this leave?',
        description:
            'The leave is written into attendance for every working day it covers, and counts against the leave balance.',
        confirm: 'Approve leave',
        destructive: false,
    },
    reject: {
        title: 'Reject this leave?',
        description:
            'Nothing is written to attendance. A rejected leave cannot be edited afterwards.',
        confirm: 'Reject leave',
        destructive: true,
    },
    cancel: {
        title: 'Cancel this leave?',
        description:
            'The leave days are removed from attendance and no longer count against the leave balance.',
        confirm: 'Cancel leave',
        destructive: true,
    },
};

const activeDecision = computed(() =>
    decision.target.value ? decisionCopy[decision.target.value.decision] : null,
);

function edit(row: LeaveRow): void {
    startEdit(row.id, {
        employee_id: row.employee.id,
        leave_type_id: row.leave_type_id,
        start_date: row.start_date,
        end_date: row.end_date,
        is_half_day: row.is_half_day,
        reason: row.reason ?? '',
        notes: row.notes ?? '',
    });
}

function save(): void {
    // Half a day is always a single date.
    if (form.is_half_day) {
        form.end_date = form.start_date;
    }

    submit({ store: store.url(), update: (id) => update.url(id) });
}

function confirmDecision(): void {
    const target = decision.target.value;

    if (target) {
        decision.run('put', decide.url(target.leave.id), {
            decision: target.decision,
        });
    }
}
</script>

<template>
    <Head title="Leave Records" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Leave Records"
            description="Leave is added and decided by HR. Approved leave appears in attendance, and unpaid leave is deducted in payroll."
        >
            <Button v-if="canManage" @click="startCreate()">
                <Plus />
                Add leave
            </Button>
        </PageHeader>

        <p
            v-if="counts.pending > 0 && filters.status !== 'pending'"
            role="status"
            class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border bg-warning-soft px-3 py-2 text-sm text-warning"
        >
            {{ counts.pending }} leave request{{
                counts.pending === 1 ? ' is' : 's are'
            }}
            waiting for a decision.
            <button
                type="button"
                class="font-medium underline underline-offset-4"
                @click="filters.status = 'pending'"
            >
                Show pending leave
            </button>
        </p>

        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            <div class="relative">
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
            <NativeSelect
                v-model="filters.status"
                :options="statuses"
                placeholder="All statuses"
                aria-label="Status"
            />
            <NativeSelect
                v-model="filters.leave_type_id"
                :options="leaveTypeOptions"
                placeholder="All leave types"
                aria-label="Leave type"
            />
            <DatePicker
                v-model="filters.from"
                aria-label="Leave on or after"
                placeholder="From date"
                title="Leave on or after"
            />
            <DatePicker
                v-model="filters.to"
                aria-label="Leave on or before"
                placeholder="To date"
                title="Leave on or before"
            />
        </div>

        <DataTable :columns="columns" :rows="leaves.data">
            <template #cell-employee="{ row }">
                <EmployeeCell
                    :name="row.employee.name"
                    :code="row.employee.code"
                    :href="employeeShow(row.employee.id)"
                />
            </template>
            <template #cell-leave_type="{ row }">
                {{ row.leave_type }}
                <span class="block text-xs text-muted-foreground">
                    {{ row.is_paid ? 'Paid' : 'Unpaid' }}
                </span>
            </template>
            <template #cell-dates="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.start_date) }}
                    <template v-if="row.end_date !== row.start_date">
                        to {{ date(row.end_date) }}
                    </template>
                </span>
                <span
                    v-if="row.is_half_day"
                    class="block text-xs text-muted-foreground"
                >
                    Half day
                </span>
            </template>
            <template #cell-days="{ row }">
                <span class="tabular">{{ number(row.days) }}</span>
            </template>
            <template #cell-reason="{ row }">
                <span class="text-muted-foreground">
                    {{ row.reason ?? '-' }}
                </span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :tone="statusTone('leave', row.status)">
                    {{ row.status_label }}
                </StatusBadge>
            </template>
            <template v-if="canManage" #actions="{ row }">
                <template v-if="row.status === 'pending'">
                    <Button
                        variant="ghost"
                        size="sm"
                        :aria-label="`Approve leave of ${row.employee.name}`"
                        @click="
                            decision.ask({ leave: row, decision: 'approve' })
                        "
                    >
                        <Check />
                        Approve
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        :aria-label="`Reject leave of ${row.employee.name}`"
                        @click="
                            decision.ask({ leave: row, decision: 'reject' })
                        "
                    >
                        <X />
                        Reject
                    </Button>
                </template>
                <template
                    v-if="row.status === 'pending' || row.status === 'approved'"
                >
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Edit leave of ${row.employee.name}`"
                        :title="`Edit leave of ${row.employee.name}`"
                        @click="edit(row)"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Cancel leave of ${row.employee.name}`"
                        :title="`Cancel leave of ${row.employee.name}`"
                        @click="
                            decision.ask({ leave: row, decision: 'cancel' })
                        "
                    >
                        <Ban />
                    </Button>
                </template>
            </template>
            <template #empty>
                <div
                    class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                >
                    <p class="text-sm font-medium">No leave records found</p>
                    <p class="max-w-[48ch] text-sm text-muted-foreground">
                        Nothing matches these filters. Clear them to see all
                        leave, or add leave for an employee.
                    </p>
                    <Button variant="outline" size="sm" @click="reset">
                        Clear filters
                    </Button>
                </div>
            </template>
        </DataTable>

        <DataPagination :page="leaves" noun="leave records" />
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <form class="grid gap-5" @submit.prevent="save">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingId ? 'Edit leave' : 'Add leave' }}
                    </DialogTitle>
                    <DialogDescription>
                        Leave days are counted on working days only; weekly offs
                        and holidays inside the dates are skipped.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Employee"
                    for="leave-employee"
                    :error="form.errors.employee_id"
                    required
                >
                    <NativeSelect
                        id="leave-employee"
                        v-model="form.employee_id"
                        :options="employeeOptions"
                        placeholder="Choose an employee"
                        :disabled="editingId !== null"
                    />
                </FormField>

                <FormField
                    label="Leave type"
                    for="leave-type"
                    :error="form.errors.leave_type_id"
                    required
                >
                    <NativeSelect
                        id="leave-type"
                        v-model="form.leave_type_id"
                        :options="leaveTypeOptions"
                        placeholder="Choose a leave type"
                    />
                </FormField>

                <label class="flex items-center gap-3 text-sm">
                    <Switch v-model="form.is_half_day" />
                    Half day
                </label>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField
                        :label="form.is_half_day ? 'Date' : 'First day'"
                        for="leave-start"
                        :error="form.errors.start_date"
                        required
                    >
                        <DatePicker
                            id="leave-start"
                            v-model="form.start_date"
                            required
                        />
                    </FormField>
                    <FormField
                        v-if="!form.is_half_day"
                        label="Last day"
                        for="leave-end"
                        :error="form.errors.end_date"
                        required
                    >
                        <DatePicker
                            id="leave-end"
                            v-model="form.end_date"
                            :min="form.start_date"
                            required
                        />
                    </FormField>
                </div>

                <FormField
                    label="Reason"
                    for="leave-reason"
                    :error="form.errors.reason"
                >
                    <Input
                        id="leave-reason"
                        v-model="form.reason"
                        maxlength="255"
                    />
                </FormField>

                <FormField
                    label="Notes"
                    for="leave-notes"
                    :error="form.errors.notes"
                    hint="Internal notes, visible to HR only."
                >
                    <Textarea
                        id="leave-notes"
                        v-model="form.notes"
                        maxlength="2000"
                    />
                </FormField>

                <FormField
                    v-if="editingId === null"
                    label="Save as"
                    for="leave-status"
                    :error="form.errors.status"
                >
                    <NativeSelect
                        id="leave-status"
                        v-model="form.status"
                        :options="saveAsOptions"
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
                        {{ editingId ? 'Save changes' : 'Add leave' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="decision.target.value !== null"
        :title="activeDecision?.title ?? ''"
        :description="
            decision.target.value
                ? `${decision.target.value.leave.employee.name}, ${decision.target.value.leave.leave_type}, ${date(decision.target.value.leave.start_date)}${decision.target.value.leave.end_date !== decision.target.value.leave.start_date ? ` to ${date(decision.target.value.leave.end_date)}` : ''}. ${activeDecision?.description ?? ''}`
                : ''
        "
        :confirm-label="activeDecision?.confirm ?? 'Confirm'"
        :destructive="activeDecision?.destructive"
        :processing="decision.processing.value"
        @update:open="(value) => !value && (decision.target.value = null)"
        @confirm="confirmDecision"
    />
</template>
