<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import LockedNote from '@/components/finance/LockedNote.vue';
import FormField from '@/components/FormField.vue';
import MonthNavigator from '@/components/MonthNavigator.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
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
import {
    useConfirmedAction,
    useResourceForm,
} from '@/composables/useResourceForm';
import { destroy, index, store, update } from '@/routes/deductions';
import type { IdName, Paginated } from '@/types';

type DeductionRow = {
    id: number;
    employee_id: number;
    employee: { id: number; name: string; code: string };
    date: string;
    title: string;
    amount: number;
    reason: string | null;
    is_locked: boolean;
};

const props = defineProps<{
    entries: Paginated<DeductionRow>;
    month: string;
    filters: { search: string };
    total: number;
    employees: IdName[];
    today: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Deductions', href: index() }],
    },
});

const { money, date, monthLabel } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('finance.manage'));

const { filters } = useQueryFilters(
    () => index.url(),
    { month: props.month, search: props.filters.search ?? '' },
    { debounced: ['search'] },
);

const { open, editingId, form, startCreate, startEdit, submit } =
    useResourceForm(() => ({
        employee_id: null as number | null,
        date: props.today,
        title: '',
        amount: '' as number | string,
        reason: '',
    }));

const removal = useConfirmedAction<DeductionRow>();

const columns: DataTableColumn[] = [
    { key: 'employee', label: 'Employee', primary: true },
    { key: 'date', label: 'Date' },
    { key: 'title', label: 'Deduction' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'reason', label: 'Reason' },
];

const edit = (row: DeductionRow) =>
    startEdit(row.id, {
        employee_id: row.employee_id,
        date: row.date,
        title: row.title,
        amount: row.amount,
        reason: row.reason ?? '',
    });

const save = () =>
    submit({ store: store.url(), update: (id) => update.url(id) });
</script>

<template>
    <Head title="Deductions" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Deductions"
            description="One-off deductions taken from an employee's next salary. A deduction that repeats every month belongs in the salary structure instead."
        >
            <Button v-if="canManage" @click="startCreate()">
                <Plus />
                Add deduction
            </Button>
        </PageHeader>

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
                    aria-label="Search deductions by employee"
                    class="pl-9"
                />
            </div>
        </div>

        <div class="sm:max-w-xs">
            <StatCard
                label="Total deductions"
                :value="money(total)"
                :hint="`One-off deductions dated in ${monthLabel(month)}`"
                :tone="total > 0 ? 'negative' : 'neutral'"
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
            <template #cell-amount="{ row }">
                <span class="tabular font-medium">{{ money(row.amount) }}</span>
            </template>
            <template #cell-reason="{ row }">
                <span class="text-muted-foreground">
                    {{ row.reason ?? '-' }}
                </span>
            </template>
            <template v-if="canManage" #actions="{ row }">
                <LockedNote v-if="row.is_locked" />
                <template v-else>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Edit ${row.title} of ${row.employee.name}`"
                        @click="edit(row)"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Delete ${row.title} of ${row.employee.name}`"
                        @click="removal.ask(row)"
                    >
                        <Trash2 />
                    </Button>
                </template>
            </template>
            <template #empty>
                <EmptyState
                    :title="`No deductions in ${monthLabel(month)}`"
                    description="Add a one-off deduction, or pick another month."
                >
                    <Button v-if="canManage" size="sm" @click="startCreate()">
                        <Plus />
                        Add deduction
                    </Button>
                </EmptyState>
            </template>
        </DataTable>

        <DataPagination :page="entries" noun="deductions" />
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-5" @submit.prevent="save">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingId ? 'Edit deduction' : 'Add deduction' }}
                    </DialogTitle>
                    <DialogDescription>
                        Deducted in the next payroll that covers this date, and
                        shown there as its own line.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Employee"
                    for="deduction-employee"
                    :error="form.errors.employee_id"
                    required
                >
                    <NativeSelect
                        id="deduction-employee"
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
                    label="What is it for?"
                    for="deduction-title"
                    :error="form.errors.title"
                    required
                >
                    <Input
                        id="deduction-title"
                        v-model="form.title"
                        required
                        maxlength="255"
                        placeholder="e.g. Uniform, damaged equipment"
                    />
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Amount"
                        for="deduction-amount"
                        :error="form.errors.amount"
                        required
                    >
                        <Input
                            id="deduction-amount"
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
                        label="Date"
                        for="deduction-date"
                        :error="form.errors.date"
                        required
                    >
                        <Input
                            id="deduction-date"
                            v-model="form.date"
                            type="date"
                            required
                        />
                    </FormField>
                </div>

                <FormField
                    label="Reason"
                    for="deduction-reason"
                    :error="form.errors.reason"
                >
                    <Input
                        id="deduction-reason"
                        v-model="form.reason"
                        maxlength="255"
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
                        {{ editingId ? 'Save changes' : 'Add deduction' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removal.target.value !== null"
        title="Delete this deduction?"
        :description="
            removal.target.value
                ? `“${removal.target.value.title}” of ${money(removal.target.value.amount)} for ${removal.target.value.employee.name} will be removed and not deducted.`
                : ''
        "
        confirm-label="Delete deduction"
        destructive
        :processing="removal.processing.value"
        @update:open="(value) => !value && (removal.target.value = null)"
        @confirm="
            removal.target.value &&
            removal.run('delete', destroy.url(removal.target.value.id))
        "
    />
</template>
