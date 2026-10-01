<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
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
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import {
    useConfirmedAction,
    useResourceForm,
} from '@/composables/useResourceForm';
import { destroy, index, store, update } from '@/routes/leave-types';

type LeaveType = {
    id: number;
    name: string;
    code: string;
    is_paid: boolean;
    annual_allowance: number;
    is_active: boolean;
    leaves_count: number;
};

defineProps<{
    leaveTypes: LeaveType[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Leave Types', href: index() }],
    },
});

const { number } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('leave.manage'));

const { open, editingId, form, startCreate, startEdit, submit } =
    useResourceForm(() => ({
        name: '',
        code: '',
        is_paid: true,
        annual_allowance: 0,
        is_active: true,
    }));

const removal = useConfirmedAction<LeaveType>();

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Leave type', primary: true },
    { key: 'code', label: 'Code' },
    { key: 'is_paid', label: 'Pay' },
    { key: 'annual_allowance', label: 'Days per year', align: 'right' },
    { key: 'leaves_count', label: 'Leave recorded', align: 'right' },
    { key: 'is_active', label: 'Status' },
];

const save = () =>
    submit({ store: store.url(), update: (id) => update.url(id) });
</script>

<template>
    <Head title="Leave Types" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Leave Types"
            description="The kinds of leave HR can record. Paid leave keeps the salary whole; unpaid leave is deducted in payroll at the per-day rate."
        >
            <Button v-if="canManage" @click="startCreate()">
                <Plus />
                Add leave type
            </Button>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="leaveTypes"
            empty-title="No leave types yet"
            empty-description="Add leave types such as Casual Leave, Sick Leave or Unpaid Leave before recording leave."
        >
            <template #cell-name="{ row }">
                <span class="font-medium">{{ row.name }}</span>
            </template>
            <template #cell-code="{ row }">
                <span class="tabular">{{ row.code }}</span>
            </template>
            <template #cell-is_paid="{ row }">
                <StatusBadge :tone="row.is_paid ? 'info' : 'warning'">
                    {{ row.is_paid ? 'Paid' : 'Unpaid' }}
                </StatusBadge>
            </template>
            <template #cell-annual_allowance="{ row }">
                <span class="tabular">{{ number(row.annual_allowance) }}</span>
            </template>
            <template #cell-leaves_count="{ row }">
                <span class="tabular">{{ row.leaves_count }}</span>
            </template>
            <template #cell-is_active="{ row }">
                <StatusBadge :tone="row.is_active ? 'positive' : 'neutral'">
                    {{ row.is_active ? 'Active' : 'Inactive' }}
                </StatusBadge>
            </template>
            <template v-if="canManage" #actions="{ row }">
                <Button
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="`Edit ${row.name}`"
                    @click="
                        startEdit(row.id, {
                            name: row.name,
                            code: row.code,
                            is_paid: row.is_paid,
                            annual_allowance: row.annual_allowance,
                            is_active: row.is_active,
                        })
                    "
                >
                    <Pencil />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="`Delete ${row.name}`"
                    @click="removal.ask(row)"
                >
                    <Trash2 />
                </Button>
            </template>
        </DataTable>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-5" @submit.prevent="save">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingId ? 'Edit leave type' : 'Add leave type' }}
                    </DialogTitle>
                    <DialogDescription>
                        The yearly allowance is the default balance for every
                        employee. It can be changed per employee in Leave
                        Balance.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-[2fr_1fr]">
                    <FormField
                        label="Name"
                        for="leave-type-name"
                        :error="form.errors.name"
                        required
                    >
                        <Input
                            id="leave-type-name"
                            v-model="form.name"
                            required
                            maxlength="255"
                            placeholder="e.g. Casual Leave"
                        />
                    </FormField>
                    <FormField
                        label="Code"
                        for="leave-type-code"
                        :error="form.errors.code"
                        required
                    >
                        <Input
                            id="leave-type-code"
                            v-model="form.code"
                            required
                            maxlength="20"
                            placeholder="CL"
                        />
                    </FormField>
                </div>

                <FormField
                    label="Days per year"
                    for="leave-type-allowance"
                    :error="form.errors.annual_allowance"
                    hint="Use 0 for leave without a yearly limit, such as unpaid leave."
                    required
                >
                    <Input
                        id="leave-type-allowance"
                        v-model.number="form.annual_allowance"
                        type="number"
                        min="0"
                        max="366"
                        step="0.5"
                        required
                        class="tabular"
                    />
                </FormField>

                <div class="grid gap-3">
                    <label class="flex items-center gap-3 text-sm">
                        <Switch v-model="form.is_paid" />
                        <span>
                            Paid leave
                            <span class="block text-xs text-muted-foreground">
                                {{
                                    form.is_paid
                                        ? 'Salary is not reduced for these days.'
                                        : 'Each day is deducted from salary in payroll.'
                                }}
                            </span>
                        </span>
                    </label>
                    <label class="flex items-center gap-3 text-sm">
                        <Switch v-model="form.is_active" />
                        Active
                    </label>
                </div>

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
                        {{ editingId ? 'Save changes' : 'Add leave type' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removal.target.value !== null"
        title="Delete this leave type?"
        :description="`${removal.target.value?.name ?? ''} will be removed. A leave type that already has leave recorded cannot be deleted; mark it inactive instead.`"
        confirm-label="Delete leave type"
        destructive
        :processing="removal.processing.value"
        @update:open="(value) => !value && (removal.target.value = null)"
        @confirm="
            removal.target.value &&
            removal.run('delete', destroy.url(removal.target.value.id))
        "
    />
</template>
