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
import { usePermissions } from '@/composables/usePermissions';
import {
    useConfirmedAction,
    useResourceForm,
} from '@/composables/useResourceForm';
import { destroy, index, store, update } from '@/routes/departments';

type Department = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    employees_count: number;
};

defineProps<{
    departments: Department[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Departments', href: index() }],
    },
});

const { can } = usePermissions();
const canManage = computed(() => can('employees.manage'));

const { open, editingId, form, startCreate, startEdit, submit } =
    useResourceForm(() => ({
        name: '',
        description: '',
        is_active: true,
    }));

const removal = useConfirmedAction<Department>();

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Department', primary: true },
    { key: 'description', label: 'Description' },
    { key: 'employees_count', label: 'Employees', align: 'right' },
    { key: 'is_active', label: 'Status' },
];

const save = () =>
    submit({ store: store.url(), update: (id) => update.url(id) });
</script>

<template>
    <Head title="Departments" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Departments"
            description="Departments group employees for filtering, reports and the payroll breakdown."
        >
            <Button v-if="canManage" @click="startCreate()">
                <Plus />
                Add department
            </Button>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="departments"
            empty-title="No departments yet"
            empty-description="Add your first department, then assign employees to it."
        >
            <template #cell-name="{ row }">
                <span class="font-medium">{{ row.name }}</span>
            </template>
            <template #cell-description="{ row }">
                <span class="text-muted-foreground">
                    {{ row.description ?? '-' }}
                </span>
            </template>
            <template #cell-employees_count="{ row }">
                <span class="tabular">{{ row.employees_count }}</span>
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
                            description: row.description ?? '',
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
                        {{ editingId ? 'Edit department' : 'Add department' }}
                    </DialogTitle>
                    <DialogDescription>
                        Inactive departments stay on existing employees but
                        cannot be chosen for new ones.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Name"
                    for="department-name"
                    :error="form.errors.name"
                    required
                >
                    <Input
                        id="department-name"
                        v-model="form.name"
                        required
                        maxlength="255"
                        placeholder="e.g. Finance"
                    />
                </FormField>

                <FormField
                    label="Description"
                    for="department-description"
                    :error="form.errors.description"
                >
                    <Input
                        id="department-description"
                        v-model="form.description"
                        maxlength="255"
                    />
                </FormField>

                <label class="flex items-center gap-3 text-sm">
                    <Switch v-model="form.is_active" />
                    Active
                </label>

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
                        {{ editingId ? 'Save changes' : 'Add department' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removal.target.value !== null"
        title="Delete this department?"
        :description="`${removal.target.value?.name ?? ''} will be removed. A department that still has employees cannot be deleted.`"
        confirm-label="Delete department"
        destructive
        :processing="removal.processing.value"
        @update:open="(value) => !value && (removal.target.value = null)"
        @confirm="
            removal.target.value &&
            removal.run('delete', destroy.url(removal.target.value.id))
        "
    />
</template>
