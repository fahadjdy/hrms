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
import { destroy, index, store, update } from '@/routes/designations';

type Designation = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    employees_count: number;
};

defineProps<{
    designations: Designation[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Designations', href: index() }],
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

const removal = useConfirmedAction<Designation>();

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Designation', primary: true },
    { key: 'description', label: 'Description' },
    { key: 'employees_count', label: 'Employees', align: 'right' },
    { key: 'is_active', label: 'Status' },
];

const save = () =>
    submit({ store: store.url(), update: (id) => update.url(id) });
</script>

<template>
    <Head title="Designations" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Designations"
            description="Job titles such as Accountant or Sales Executive. They appear on the employee profile and the salary slip."
        >
            <Button v-if="canManage" @click="startCreate()">
                <Plus />
                Add designation
            </Button>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="designations"
            empty-title="No designations yet"
            empty-description="Add your first designation, then assign it to employees."
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
                        {{ editingId ? 'Edit designation' : 'Add designation' }}
                    </DialogTitle>
                    <DialogDescription>
                        Inactive designations stay on existing employees but
                        cannot be chosen for new ones.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Name"
                    for="designation-name"
                    :error="form.errors.name"
                    required
                >
                    <Input
                        id="designation-name"
                        v-model="form.name"
                        required
                        maxlength="255"
                        placeholder="e.g. Senior Accountant"
                    />
                </FormField>

                <FormField
                    label="Description"
                    for="designation-description"
                    :error="form.errors.description"
                >
                    <Input
                        id="designation-description"
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
                        {{ editingId ? 'Save changes' : 'Add designation' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removal.target.value !== null"
        title="Delete this designation?"
        :description="`${removal.target.value?.name ?? ''} will be removed. A designation that is still assigned to employees cannot be deleted.`"
        confirm-label="Delete designation"
        destructive
        :processing="removal.processing.value"
        @update:open="(value) => !value && (removal.target.value = null)"
        @confirm="
            removal.target.value &&
            removal.run('delete', destroy.url(removal.target.value.id))
        "
    />
</template>
