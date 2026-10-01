<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
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
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import {
    useConfirmedAction,
    useResourceForm,
} from '@/composables/useResourceForm';
import { destroy, index, store, update } from '@/routes/holidays';
import type { Option } from '@/types';

type Holiday = {
    id: number;
    name: string;
    type: string;
    type_label: string;
    description: string | null;
    date: string;
    weekday: string;
};

const props = defineProps<{
    year: number;
    today: string;
    holidays: Holiday[];
    types: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Holidays', href: index() }],
    },
});

const { date } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('attendance.manage'));

const { open, editingId, form, startCreate, startEdit, submit } =
    useResourceForm(() => ({
        name: '',
        date: '',
        type: 'public' as string | null,
        description: '',
    }));

const removal = useConfirmedAction<Holiday>();

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Holiday', primary: true },
    { key: 'date', label: 'Date' },
    { key: 'weekday', label: 'Day' },
    { key: 'type_label', label: 'Type' },
    { key: 'description', label: 'Description', hideOnMobile: true },
];

const upcomingCount = computed(
    () =>
        props.holidays.filter((holiday) => holiday.date >= props.today).length,
);

function showYear(year: number): void {
    router.get(index.url(), { year }, { preserveScroll: true });
}

/** A new holiday starts in the year being viewed, not always the current one. */
function addHoliday(): void {
    const sameYear = props.today.startsWith(String(props.year));

    startCreate({ date: sameYear ? props.today : `${props.year}-01-01` });
}

const save = () =>
    submit({ store: store.url(), update: (id) => update.url(id) });
</script>

<template>
    <Head title="Holidays" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Holidays"
            description="Company and public holidays. A holiday is never counted as absence and is left out of working days in attendance and payroll."
        >
            <Button v-if="canManage" @click="addHoliday">
                <Plus />
                Add holiday
            </Button>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    type="button"
                    :aria-label="`Show ${year - 1}`"
                    @click="showYear(year - 1)"
                >
                    <ChevronLeft />
                </Button>
                <span
                    class="tabular min-w-16 text-center text-sm font-semibold"
                    aria-live="polite"
                >
                    {{ year }}
                </span>
                <Button
                    variant="outline"
                    size="icon"
                    type="button"
                    :aria-label="`Show ${year + 1}`"
                    @click="showYear(year + 1)"
                >
                    <ChevronRight />
                </Button>
            </div>
            <p class="text-sm text-muted-foreground">
                {{ holidays.length }} holiday{{
                    holidays.length === 1 ? '' : 's'
                }}
                in {{ year
                }}<template v-if="upcomingCount > 0"
                    >, {{ upcomingCount }} still to come</template
                >
            </p>
        </div>

        <DataTable
            :columns="columns"
            :rows="holidays"
            :empty-title="`No holidays in ${year}`"
            empty-description="Add the public and company holidays for this year so they are not counted as absence."
        >
            <template #cell-name="{ row }">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ row.name }}</span>
                    <StatusBadge v-if="row.date === today" tone="positive">
                        Today
                    </StatusBadge>
                    <StatusBadge v-else-if="row.date < today">
                        Past
                    </StatusBadge>
                </div>
            </template>
            <template #cell-date="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.date) }}
                </span>
            </template>
            <template #cell-description="{ row }">
                <span class="text-muted-foreground">
                    {{ row.description ?? '-' }}
                </span>
            </template>
            <template v-if="canManage" #actions="{ row }">
                <Button
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="`Edit ${row.name}`"
                    @click="
                        startEdit(row.id, {
                            name: row.name,
                            date: row.date,
                            type: row.type,
                            description: row.description ?? '',
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
                        {{ editingId ? 'Edit holiday' : 'Add holiday' }}
                    </DialogTitle>
                    <DialogDescription>
                        Each date can hold one holiday.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Holiday name"
                    for="holiday-name"
                    :error="form.errors.name"
                    required
                >
                    <Input
                        id="holiday-name"
                        v-model="form.name"
                        required
                        maxlength="255"
                        placeholder="e.g. Diwali"
                    />
                </FormField>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField
                        label="Date"
                        for="holiday-date"
                        :error="form.errors.date"
                        required
                    >
                        <Input
                            id="holiday-date"
                            v-model="form.date"
                            type="date"
                            required
                        />
                    </FormField>
                    <FormField
                        label="Holiday type"
                        for="holiday-type"
                        :error="form.errors.type"
                        required
                    >
                        <NativeSelect
                            id="holiday-type"
                            v-model="form.type"
                            :options="types"
                        />
                    </FormField>
                </div>

                <FormField
                    label="Description"
                    for="holiday-description"
                    :error="form.errors.description"
                >
                    <Input
                        id="holiday-description"
                        v-model="form.description"
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
                        {{ editingId ? 'Save changes' : 'Add holiday' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removal.target.value !== null"
        title="Delete this holiday?"
        :description="`${removal.target.value?.name ?? ''} will be removed, and that date becomes a normal working day again unless it is a weekly off.`"
        confirm-label="Delete holiday"
        destructive
        :processing="removal.processing.value"
        @update:open="(value) => !value && (removal.target.value = null)"
        @confirm="
            removal.target.value &&
            removal.run('delete', destroy.url(removal.target.value.id))
        "
    />
</template>
