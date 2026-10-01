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
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import {
    useConfirmedAction,
    useResourceForm,
} from '@/composables/useResourceForm';
import { destroy, index, store, update } from '@/routes/bonuses';
import type { IdName, Paginated } from '@/types';

type BonusRow = {
    id: number;
    employee_id: number;
    employee: { id: number; name: string; code: string };
    date: string;
    type: string;
    title: string;
    amount: number;
    reason: string | null;
    is_locked: boolean;
};

const props = defineProps<{
    entries: Paginated<BonusRow>;
    month: string;
    filters: { search: string; type: string | null };
    total: number;
    employees: IdName[];
    today: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Bonuses', href: index() }],
    },
});

const { money, date, monthLabel } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('finance.manage'));

const { filters } = useQueryFilters(
    () => index.url(),
    {
        month: props.month,
        search: props.filters.search ?? '',
        type: props.filters.type ?? null,
    },
    { debounced: ['search'] },
);

const { open, editingId, form, startCreate, startEdit, submit } =
    useResourceForm(() => ({
        employee_id: null as number | null,
        date: props.today,
        type: 'bonus',
        title: '',
        amount: '' as number | string,
        reason: '',
    }));

const removal = useConfirmedAction<BonusRow>();

const typeOptions = [
    { value: 'bonus', label: 'Bonus' },
    { value: 'other_earning', label: 'Other earning' },
];

const typeChoices = [
    {
        value: 'bonus',
        title: 'Bonus',
        description:
            'A reward on top of salary, such as a festival or performance bonus.',
    },
    {
        value: 'other_earning',
        title: 'Other earning',
        description:
            'Any other one-off payment, such as a reimbursement or arrears.',
    },
];

const typeLabel = (type: string): string =>
    type === 'bonus' ? 'Bonus' : 'Other earning';

const columns: DataTableColumn[] = [
    { key: 'employee', label: 'Employee', primary: true },
    { key: 'date', label: 'Date' },
    { key: 'type', label: 'Type' },
    { key: 'title', label: 'Title' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'reason', label: 'Reason' },
];

const edit = (row: BonusRow) =>
    startEdit(row.id, {
        employee_id: row.employee_id,
        date: row.date,
        type: row.type,
        title: row.title,
        amount: row.amount,
        reason: row.reason ?? '',
    });

const save = () =>
    submit({ store: store.url(), update: (id) => update.url(id) });
</script>

<template>
    <Head title="Bonuses" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Bonuses"
            description="One-off bonuses and other earnings paid with an employee's next salary. Each one appears in payroll as its own line."
        >
            <Button v-if="canManage" @click="startCreate()">
                <Plus />
                Add bonus or earning
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
                    aria-label="Search bonuses by employee"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.type"
                :options="typeOptions"
                placeholder="Bonuses and other earnings"
                aria-label="Type"
                class="lg:w-64"
            />
        </div>

        <div class="sm:max-w-xs">
            <StatCard
                label="Total"
                :value="money(total)"
                :hint="`Bonuses and other earnings dated in ${monthLabel(month)}`"
                :tone="total > 0 ? 'positive' : 'neutral'"
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
            <template #cell-type="{ row }">
                <StatusBadge :tone="row.type === 'bonus' ? 'positive' : 'info'">
                    {{ typeLabel(row.type) }}
                </StatusBadge>
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
                    :title="`No bonuses or other earnings in ${monthLabel(month)}`"
                    description="Add one for an employee, or pick another month."
                >
                    <Button v-if="canManage" size="sm" @click="startCreate()">
                        <Plus />
                        Add bonus or earning
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
                        {{
                            editingId
                                ? 'Edit bonus or earning'
                                : 'Add bonus or earning'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Paid with the next payroll that covers this date, and
                        shown there as its own line.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Employee"
                    for="bonus-employee"
                    :error="form.errors.employee_id"
                    required
                >
                    <NativeSelect
                        id="bonus-employee"
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
                    v-model="form.type"
                    legend="Type"
                    name="bonus-type"
                    :choices="typeChoices"
                />

                <FormField
                    label="Title"
                    for="bonus-title"
                    :error="form.errors.title"
                    hint="Shown on the payroll breakdown and the salary slip."
                    required
                >
                    <Input
                        id="bonus-title"
                        v-model="form.title"
                        required
                        maxlength="255"
                        placeholder="e.g. Festival bonus"
                    />
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Amount"
                        for="bonus-amount"
                        :error="form.errors.amount"
                        required
                    >
                        <Input
                            id="bonus-amount"
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
                        for="bonus-date"
                        :error="form.errors.date"
                        required
                    >
                        <Input
                            id="bonus-date"
                            v-model="form.date"
                            type="date"
                            required
                        />
                    </FormField>
                </div>

                <FormField
                    label="Reason"
                    for="bonus-reason"
                    :error="form.errors.reason"
                >
                    <Input
                        id="bonus-reason"
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
                        {{
                            editingId ? 'Save changes' : 'Add bonus or earning'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removal.target.value !== null"
        title="Delete this entry?"
        :description="
            removal.target.value
                ? `“${removal.target.value.title}” of ${money(removal.target.value.amount)} for ${removal.target.value.employee.name} will be removed and not paid.`
                : ''
        "
        confirm-label="Delete entry"
        destructive
        :processing="removal.processing.value"
        @update:open="(value) => !value && (removal.target.value = null)"
        @confirm="
            removal.target.value &&
            removal.run('delete', destroy.url(removal.target.value.id))
        "
    />
</template>
