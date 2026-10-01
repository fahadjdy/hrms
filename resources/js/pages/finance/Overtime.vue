<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import ChoiceCards from '@/components/finance/ChoiceCards.vue';
import LockedNote from '@/components/finance/LockedNote.vue';
import FormField from '@/components/FormField.vue';
import MonthNavigator from '@/components/MonthNavigator.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import {
    useConfirmedAction,
    useResourceForm,
} from '@/composables/useResourceForm';
import { statusTone } from '@/lib/status';
import { destroy, index, store, update } from '@/routes/overtime';
import type { IdName, Option, Paginated } from '@/types';

type OvertimeRow = {
    id: number;
    employee_id: number;
    employee: { id: number; name: string; code: string };
    date: string;
    calculation_type: string;
    hours: number | null;
    rate: number | null;
    amount: number;
    reason: string | null;
    notes: string | null;
    status: string;
    status_label: string;
    is_locked: boolean;
};

const props = defineProps<{
    entries: Paginated<OvertimeRow>;
    month: string;
    filters: { search: string; status: string | null };
    totals: { amount: number; hours: number; pending: number };
    statuses: Option[];
    employees: IdName[];
    today: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Overtime', href: index() }],
    },
});

const { money, number, date, monthLabel } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('finance.manage'));

const { filters } = useQueryFilters(
    () => index.url(),
    {
        month: props.month,
        search: props.filters.search ?? '',
        status: props.filters.status ?? null,
    },
    { debounced: ['search'] },
);

const { open, editingId, form, startCreate, startEdit, submit } =
    useResourceForm(() => ({
        employee_id: null as number | null,
        date: props.today,
        calculation_type: 'hourly',
        hours: '' as number | string,
        rate: '' as number | string,
        amount: '' as number | string,
        reason: '',
        notes: '',
        status: 'approved',
    }));

const removal = useConfirmedAction<OvertimeRow>();

// "Paid" is set by payroll or a final settlement, never chosen by hand.
const editableStatuses = computed(() =>
    props.statuses.filter((status) => status.value !== 'paid'),
);

const isHourly = computed(() => form.calculation_type === 'hourly');
const hourlyTotal = computed(
    () => Number(form.hours || 0) * Number(form.rate || 0),
);

const typeChoices = [
    {
        value: 'hourly',
        title: 'Hours × rate',
        description: 'The amount is the overtime hours times a rate per hour.',
    },
    {
        value: 'fixed',
        title: 'Fixed amount',
        description: 'One agreed amount, whatever the hours.',
    },
];

/** Paid overtime can no longer change, whether it was paid by payroll or a settlement. */
const isLocked = (row: OvertimeRow): boolean =>
    row.is_locked || row.status === 'paid';

const columns: DataTableColumn[] = [
    { key: 'employee', label: 'Employee', primary: true },
    { key: 'date', label: 'Date' },
    { key: 'calculation', label: 'Calculation' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'reason', label: 'Reason' },
    { key: 'status', label: 'Status' },
];

const edit = (row: OvertimeRow) =>
    startEdit(row.id, {
        employee_id: row.employee_id,
        date: row.date,
        calculation_type: row.calculation_type,
        hours: row.hours ?? '',
        rate: row.rate ?? '',
        amount: row.calculation_type === 'fixed' ? row.amount : '',
        reason: row.reason ?? '',
        notes: row.notes ?? '',
        status: row.status,
    });

const save = () =>
    submit({ store: store.url(), update: (id) => update.url(id) });
</script>

<template>
    <Head title="Overtime" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Overtime"
            description="Overtime added by hand. Approved entries are paid with the next payroll and then locked."
        >
            <Button v-if="canManage" @click="startCreate()">
                <Plus />
                Add overtime
            </Button>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-3">
            <StatCard
                label="Overtime amount"
                :value="money(totals.amount)"
                :hint="`Approved and paid in ${monthLabel(month)}`"
            />
            <StatCard
                label="Overtime hours"
                :value="`${number(totals.hours, 2)} h`"
                hint="Hours on approved and paid entries"
            />
            <StatCard
                label="Waiting for approval"
                :value="number(totals.pending, 0)"
                hint="Pending entries are not paid until approved"
                :tone="totals.pending > 0 ? 'warning' : 'neutral'"
            />
        </div>

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            <MonthNavigator v-model="filters.month" />
            <div class="relative lg:w-72">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    type="search"
                    placeholder="Employee name or ID"
                    aria-label="Search overtime by employee"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.status"
                :options="statuses"
                placeholder="All statuses"
                aria-label="Status"
                class="lg:w-48"
            />
        </div>

        <DataTable :columns="columns" :rows="entries.data">
            <template #cell-employee="{ row }">
                <span class="block font-medium">{{ row.employee.name }}</span>
                <span class="text-xs text-muted-foreground">
                    {{ row.employee.code }}
                </span>
            </template>
            <template #cell-date="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.date) }}
                </span>
            </template>
            <template #cell-calculation="{ row }">
                <span v-if="row.calculation_type === 'fixed'">
                    Fixed amount
                </span>
                <span v-else class="tabular whitespace-nowrap">
                    {{ number(row.hours, 2) }} h × {{ money(row.rate) }}
                </span>
            </template>
            <template #cell-amount="{ row }">
                <span class="tabular font-medium">{{ money(row.amount) }}</span>
            </template>
            <template #cell-reason="{ row }">
                <span class="text-muted-foreground">
                    {{ row.reason ?? '-' }}
                </span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :tone="statusTone('overtime', row.status)">
                    {{ row.status_label }}
                </StatusBadge>
            </template>
            <template v-if="canManage" #actions="{ row }">
                <LockedNote v-if="isLocked(row)" text="Paid" />
                <template v-else>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Edit overtime of ${row.employee.name}`"
                        @click="edit(row)"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Delete overtime of ${row.employee.name}`"
                        @click="removal.ask(row)"
                    >
                        <Trash2 />
                    </Button>
                </template>
            </template>
            <template #empty>
                <EmptyState
                    :title="`No overtime in ${monthLabel(month)}`"
                    description="Add overtime for an employee, or pick another month."
                >
                    <Button v-if="canManage" size="sm" @click="startCreate()">
                        <Plus />
                        Add overtime
                    </Button>
                </EmptyState>
            </template>
        </DataTable>

        <DataPagination :page="entries" noun="entries" />
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
            <form class="grid gap-5" @submit.prevent="save">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingId ? 'Edit overtime' : 'Add overtime' }}
                    </DialogTitle>
                    <DialogDescription>
                        Approved overtime is added to the employee's next
                        payroll as its own line.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Employee"
                        for="overtime-employee"
                        :error="form.errors.employee_id"
                        required
                    >
                        <NativeSelect
                            id="overtime-employee"
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

                    <FormField
                        label="Date"
                        for="overtime-date"
                        :error="form.errors.date"
                        required
                    >
                        <Input
                            id="overtime-date"
                            v-model="form.date"
                            type="date"
                            required
                        />
                    </FormField>
                </div>

                <ChoiceCards
                    v-model="form.calculation_type"
                    legend="How is the amount worked out?"
                    name="overtime-type"
                    :choices="typeChoices"
                />

                <template v-if="isHourly">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Hours"
                            for="overtime-hours"
                            :error="form.errors.hours"
                            required
                        >
                            <Input
                                id="overtime-hours"
                                v-model="form.hours"
                                type="number"
                                inputmode="decimal"
                                min="0.25"
                                max="24"
                                step="0.25"
                                required
                                class="tabular text-right"
                            />
                        </FormField>
                        <FormField
                            label="Rate per hour"
                            for="overtime-rate"
                            :error="form.errors.rate"
                            required
                        >
                            <Input
                                id="overtime-rate"
                                v-model="form.rate"
                                type="number"
                                inputmode="decimal"
                                min="0"
                                step="0.01"
                                required
                                class="tabular text-right"
                            />
                        </FormField>
                    </div>
                    <p
                        class="tabular rounded-md bg-muted px-3 py-2 text-sm"
                        aria-live="polite"
                    >
                        {{ number(Number(form.hours || 0), 2) }}
                        {{ Number(form.hours) === 1 ? 'hour' : 'hours' }} ×
                        {{ money(Number(form.rate || 0)) }} =
                        <span class="font-semibold">
                            {{ money(hourlyTotal) }}
                        </span>
                    </p>
                </template>

                <FormField
                    v-else
                    label="Fixed overtime amount"
                    for="overtime-amount"
                    :error="form.errors.amount"
                    required
                >
                    <Input
                        id="overtime-amount"
                        v-model="form.amount"
                        type="number"
                        inputmode="decimal"
                        min="0.01"
                        step="0.01"
                        required
                        class="tabular text-right"
                    />
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Reason"
                        for="overtime-reason"
                        :error="form.errors.reason"
                    >
                        <Input
                            id="overtime-reason"
                            v-model="form.reason"
                            maxlength="255"
                            placeholder="e.g. Month-end closing"
                        />
                    </FormField>
                    <FormField
                        label="Status"
                        for="overtime-status"
                        :error="form.errors.status"
                        hint="Only approved overtime is paid."
                        required
                    >
                        <NativeSelect
                            id="overtime-status"
                            v-model="form.status"
                            :options="editableStatuses"
                        />
                    </FormField>
                </div>

                <FormField
                    label="Notes"
                    for="overtime-notes"
                    :error="form.errors.notes"
                >
                    <Textarea
                        id="overtime-notes"
                        v-model="form.notes"
                        maxlength="2000"
                        rows="2"
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
                        {{ editingId ? 'Save changes' : 'Add overtime' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removal.target.value !== null"
        title="Delete this overtime?"
        :description="
            removal.target.value
                ? `${money(removal.target.value.amount)} of overtime for ${removal.target.value.employee.name} on ${date(removal.target.value.date)} will be removed.`
                : ''
        "
        confirm-label="Delete overtime"
        destructive
        :processing="removal.processing.value"
        @update:open="(value) => !value && (removal.target.value = null)"
        @confirm="
            removal.target.value &&
            removal.run('delete', destroy.url(removal.target.value.id))
        "
    />
</template>
