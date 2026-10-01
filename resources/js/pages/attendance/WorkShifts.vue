<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DatePicker from '@/components/DatePicker.vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import {
    useConfirmedAction,
    useResourceForm,
} from '@/composables/useResourceForm';
import { destroy, index, store, update } from '@/routes/work-shifts';

type WorkShift = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    /** A fixed break, e.g. 13:00 to 14:00; null when the break is just the spare time. */
    break_start: string | null;
    break_end: string | null;
    required_minutes: number;
    break_minutes: number;
    is_active: boolean;
    assigned_employees: number;
    /** What this shift is the default for, e.g. "Company default", "Female staff". */
    default_for: string[];
};

defineProps<{
    shifts: WorkShift[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Work Shifts', href: index() }],
    },
});

const { minutes, time } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('attendance.manage'));

const { open, editingId, form, startCreate, startEdit, submit } =
    useResourceForm(() => ({
        name: '',
        start_time: '09:00',
        end_time: '18:00',
        break_start: null as string | null,
        break_end: null as string | null,
        required_minutes: 480,
        is_active: true,
    }));

const removal = useConfirmedAction<WorkShift>();

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Shift', primary: true },
    { key: 'timing', label: 'Timing' },
    { key: 'required_minutes', label: 'Required hours', align: 'right' },
    { key: 'break_minutes', label: 'Break', align: 'right' },
    { key: 'assigned_employees', label: 'Assigned employees', align: 'right' },
    { key: 'is_active', label: 'Status' },
];

/* Required time is entered as hours and minutes and stored as minutes. */
const requiredHours = computed({
    get: () => Math.floor(form.required_minutes / 60),
    set: (hours: number) => {
        form.required_minutes =
            Math.max(0, Number(hours) || 0) * 60 + (form.required_minutes % 60);
    },
});

const requiredMinutesPart = computed({
    get: () => form.required_minutes % 60,
    set: (mins: number) => {
        form.required_minutes =
            Math.floor(form.required_minutes / 60) * 60 +
            Math.min(59, Math.max(0, Number(mins) || 0));
    },
});

const toMinutes = (value: string): number | null => {
    const [hours, mins] = value.split(':').map(Number);

    return Number.isFinite(hours) && Number.isFinite(mins)
        ? hours * 60 + mins
        : null;
};

/** Minutes between start and end; an end before the start runs past midnight. */
const spanMinutes = computed(() => {
    const start = toMinutes(form.start_time);
    const end = toMinutes(form.end_time);

    if (start === null || end === null || start === end) {
        return null;
    }

    return end > start ? end - start : end + 1440 - start;
});

/** Length of the fixed break, when both of its times are filled in. */
const fixedBreakMinutes = computed(() => {
    const start = form.break_start ? toMinutes(form.break_start) : null;
    const end = form.break_end ? toMinutes(form.break_end) : null;

    if (start === null || end === null || start === end) {
        return null;
    }

    return end > start ? end - start : end + 1440 - start;
});

/** Time left in the shift after the required hours (and the fixed break). */
const breakMinutes = computed(() =>
    spanMinutes.value === null
        ? null
        : spanMinutes.value -
          (fixedBreakMinutes.value ?? 0) -
          form.required_minutes,
);

const save = () =>
    submit({ store: store.url(), update: (id) => update.url(id) });
</script>

<template>
    <Head title="Work Shifts" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Work Shifts"
            description="Reusable work timings. An employee's own shift comes first, then the shift for their gender, then the company default. Defaults are chosen in Attendance Settings."
        >
            <Button v-if="canManage" @click="startCreate()">
                <Plus />
                Add work shift
            </Button>
        </PageHeader>

        <DataTable
            :columns="columns"
            :rows="shifts"
            empty-title="No work shifts yet"
            empty-description="Add a shift with its start time, end time and required hours, then set it as a default or assign it to employees."
        >
            <template #cell-name="{ row }">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="font-medium">{{ row.name }}</span>
                    <StatusBadge
                        v-for="label in row.default_for"
                        :key="label"
                        tone="info"
                    >
                        {{ label }}
                    </StatusBadge>
                </div>
            </template>
            <template #cell-timing="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ time(row.start_time) }} to {{ time(row.end_time) }}
                </span>
            </template>
            <template #cell-required_minutes="{ row }">
                <span class="tabular">{{ minutes(row.required_minutes) }}</span>
            </template>
            <template #cell-break_minutes="{ row }">
                <span class="tabular whitespace-nowrap">
                    <template v-if="row.break_start && row.break_end">
                        {{ time(row.break_start) }} to {{ time(row.break_end) }}
                        <span class="text-muted-foreground">
                            ({{ minutes(row.break_minutes) }})
                        </span>
                    </template>
                    <template v-else>
                        {{ minutes(row.break_minutes) }}
                    </template>
                </span>
            </template>
            <template #cell-assigned_employees="{ row }">
                <span class="tabular">{{ row.assigned_employees }}</span>
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
                            start_time: row.start_time,
                            end_time: row.end_time,
                            break_start: row.break_start,
                            break_end: row.break_end,
                            required_minutes: row.required_minutes,
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
                        {{ editingId ? 'Edit work shift' : 'Add work shift' }}
                    </DialogTitle>
                    <DialogDescription>
                        Required hours are what an employee must work in the
                        shift. Set break times for a fixed unpaid break;
                        otherwise the rest of the shift is the break.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Shift name"
                    for="shift-name"
                    :error="form.errors.name"
                    required
                >
                    <Input
                        id="shift-name"
                        v-model="form.name"
                        required
                        maxlength="255"
                        placeholder="e.g. Morning Shift"
                    />
                </FormField>

                <div class="grid grid-cols-2 gap-4">
                    <FormField
                        label="Start time"
                        for="shift-start"
                        :error="form.errors.start_time"
                        required
                    >
                        <DatePicker
                            id="shift-start"
                            v-model="form.start_time"
                            mode="time"
                            required
                        />
                    </FormField>
                    <FormField
                        label="End time"
                        for="shift-end"
                        :error="form.errors.end_time"
                        required
                    >
                        <DatePicker
                            id="shift-end"
                            v-model="form.end_time"
                            mode="time"
                            required
                        />
                    </FormField>
                </div>

                <fieldset class="grid gap-1.5">
                    <legend class="mb-1.5 text-sm leading-none font-medium">
                        Break time
                        <span class="font-normal text-muted-foreground">
                            (optional)
                        </span>
                    </legend>
                    <div class="grid grid-cols-2 gap-4">
                        <DatePicker
                            id="shift-break-start"
                            v-model="form.break_start"
                            mode="time"
                            placeholder="From"
                            aria-label="Break starts"
                        />
                        <DatePicker
                            id="shift-break-end"
                            v-model="form.break_end"
                            mode="time"
                            placeholder="To"
                            aria-label="Break ends"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Only the part of the break an employee is at work for is
                        taken off their worked time.
                    </p>
                    <InputError
                        :message="
                            form.errors.break_start ?? form.errors.break_end
                        "
                    />
                </fieldset>

                <fieldset class="grid gap-1.5">
                    <legend class="mb-1.5 text-sm leading-none font-medium">
                        Required working time
                        <span class="text-negative" aria-hidden="true">*</span>
                    </legend>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex items-center gap-2">
                            <Input
                                id="shift-hours"
                                v-model.number="requiredHours"
                                type="number"
                                min="0"
                                max="24"
                                step="1"
                                required
                                class="tabular"
                            />
                            <Label for="shift-hours" class="font-normal">
                                hours
                            </Label>
                        </div>
                        <div class="flex items-center gap-2">
                            <Input
                                id="shift-minutes"
                                v-model.number="requiredMinutesPart"
                                type="number"
                                min="0"
                                max="59"
                                step="1"
                                class="tabular"
                            />
                            <Label for="shift-minutes" class="font-normal">
                                minutes
                            </Label>
                        </div>
                    </div>
                    <p
                        v-if="spanMinutes !== null && breakMinutes !== null"
                        class="tabular text-xs"
                        :class="
                            breakMinutes < 0
                                ? 'text-negative'
                                : 'text-muted-foreground'
                        "
                    >
                        <template v-if="breakMinutes < 0">
                            The shift is only
                            {{ minutes(spanMinutes) }} long<template
                                v-if="fixedBreakMinutes !== null"
                                >, with a
                                {{ minutes(fixedBreakMinutes) }} break</template
                            >, so the required time cannot be
                            {{ minutes(form.required_minutes) }}.
                        </template>
                        <template v-else-if="fixedBreakMinutes !== null">
                            {{ minutes(spanMinutes) }} shift -
                            {{ minutes(fixedBreakMinutes) }} break =
                            {{ minutes(spanMinutes - fixedBreakMinutes) }} at
                            work
                        </template>
                        <template v-else>
                            {{ minutes(spanMinutes) }} shift -
                            {{ minutes(form.required_minutes) }} required =
                            {{ minutes(breakMinutes) }} break
                        </template>
                    </p>
                    <InputError :message="form.errors.required_minutes" />
                </fieldset>

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
                        {{ editingId ? 'Save changes' : 'Add work shift' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removal.target.value !== null"
        title="Delete this work shift?"
        :description="`${removal.target.value?.name ?? ''} will be removed. A shift that is a default or is assigned to employees cannot be deleted; mark it inactive instead.`"
        confirm-label="Delete work shift"
        destructive
        :processing="removal.processing.value"
        @update:open="(value) => !value && (removal.target.value = null)"
        @confirm="
            removal.target.value &&
            removal.run('delete', destroy.url(removal.target.value.id))
        "
    />
</template>
