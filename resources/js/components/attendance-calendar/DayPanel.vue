<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { useFormat } from '@/composables/useFormat';
import { useConfirmedAction } from '@/composables/useResourceForm';
import { attendanceClass } from '@/lib/status';
import { destroy, update } from '@/routes/employees/attendance';
import type { AttendanceDay, AttendanceStatusOption } from '@/types';

export type AttendanceLogEntry = {
    id: number;
    user: string | null;
    old: Record<string, unknown> | null;
    new: Record<string, unknown> | null;
    reason: string | null;
    created_at: string | null;
};

const props = defineProps<{
    employeeId: number;
    day: AttendanceDay | null;
    statuses: AttendanceStatusOption[];
    logs: AttendanceLogEntry[];
    today: string;
    canEdit: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const { date, minutes, time, dateTime } = useFormat();

const form = useForm({
    status: 'present',
    check_in: '',
    check_out: '',
    notes: '',
    reason: '',
});

const removal = useConfirmedAction<AttendanceDay>();

// Start each day's form from what is currently on record for it.
watch(
    () => props.day,
    (day) => {
        if (!day) {
            return;
        }

        form.clearErrors();
        form.status = day.status ?? 'present';
        form.check_in = day.check_in ?? '';
        form.check_out = day.check_out ?? '';
        form.notes = day.notes ?? '';
        form.reason = '';
    },
);

const editable = computed(
    () =>
        props.canEdit &&
        props.day !== null &&
        props.day.state !== 'not_employed' &&
        props.day.date <= props.today,
);

/** Absence and leave have no working hours to enter. */
const takesTimes = computed(
    () => !['absent', 'paid_leave', 'unpaid_leave'].includes(form.status),
);

const stateNote = computed(() => {
    switch (props.day?.state) {
        case 'recorded':
            return props.day.source === 'manual'
                ? 'Marked by an admin.'
                : 'Recorded automatically.';
        case 'derived':
            return 'Not stored yet. This status follows from the company calendar and attendance mode.';
        case 'unmarked':
            return 'This working day has not been marked. Payroll treats an unmarked day as absent.';
        case 'upcoming':
            return 'This date is in the future.';
        case 'not_employed':
            return 'Outside the employment dates (before joining or after the last working day).';
        default:
            return '';
    }
});

const label = (status: unknown): string =>
    props.statuses.find((option) => option.value === status)?.label ??
    String(status ?? '-');

function save(): void {
    if (!props.day) {
        return;
    }

    form.transform((data) => ({
        ...data,
        check_in: takesTimes.value && data.check_in ? data.check_in : null,
        check_out: takesTimes.value && data.check_out ? data.check_out : null,
    })).put(update.url({ employee: props.employeeId, date: props.day.date }), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}

function clear(): void {
    if (!props.day) {
        return;
    }

    removal.run(
        'delete',
        destroy.url({ employee: props.employeeId, date: props.day.date }),
    );
    open.value = false;
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="w-full gap-0 overflow-y-auto sm:max-w-md">
            <template v-if="day">
                <SheetHeader class="border-b">
                    <SheetTitle
                        >{{ day.weekday }}, {{ date(day.date) }}</SheetTitle
                    >
                    <SheetDescription>{{ stateNote }}</SheetDescription>
                </SheetHeader>

                <div class="grid gap-6 p-4">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div class="col-span-2">
                            <dt class="text-xs text-muted-foreground">
                                Status
                            </dt>
                            <dd class="mt-1">
                                <span
                                    v-if="day.status"
                                    class="inline-block rounded px-2 py-0.5 text-sm font-medium"
                                    :class="attendanceClass(day.status)"
                                >
                                    {{ day.status_label }}
                                </span>
                                <span v-else class="text-muted-foreground">
                                    {{
                                        day.state === 'unmarked'
                                            ? 'Not marked'
                                            : '-'
                                    }}
                                </span>
                                <span
                                    v-if="day.holiday_name"
                                    class="ml-2 text-muted-foreground"
                                >
                                    {{ day.holiday_name }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Check in
                            </dt>
                            <dd class="tabular font-medium">
                                {{ time(day.check_in) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Check out
                            </dt>
                            <dd class="tabular font-medium">
                                {{ time(day.check_out) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Required hours
                            </dt>
                            <dd class="tabular font-medium">
                                {{ minutes(day.required_minutes) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Worked
                            </dt>
                            <dd class="tabular font-medium">
                                {{ minutes(day.worked_minutes) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Short hours
                            </dt>
                            <dd
                                class="tabular font-medium"
                                :class="
                                    day.short_minutes > 0 ? 'text-warning' : ''
                                "
                            >
                                {{ minutes(day.short_minutes) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Overtime
                            </dt>
                            <dd
                                class="tabular font-medium"
                                :class="
                                    day.overtime_minutes > 0 ? 'text-info' : ''
                                "
                            >
                                {{ minutes(day.overtime_minutes) }}
                            </dd>
                        </div>
                        <div v-if="day.late_minutes > 0" class="col-span-2">
                            <dt class="text-xs text-muted-foreground">
                                Late by
                            </dt>
                            <dd class="tabular font-medium">
                                {{ minutes(day.late_minutes) }}
                            </dd>
                        </div>
                        <div v-if="day.notes" class="col-span-2">
                            <dt class="text-xs text-muted-foreground">Notes</dt>
                            <dd>{{ day.notes }}</dd>
                        </div>
                    </dl>

                    <form
                        v-if="editable"
                        class="grid gap-4 border-t pt-5"
                        @submit.prevent="save"
                    >
                        <h3 class="text-sm font-semibold">
                            {{
                                day.state === 'recorded'
                                    ? 'Edit this day'
                                    : 'Mark this day'
                            }}
                        </h3>

                        <FormField
                            label="Status"
                            for="day-status"
                            :error="form.errors.status"
                            required
                        >
                            <NativeSelect
                                id="day-status"
                                v-model="form.status"
                                :options="statuses"
                            />
                        </FormField>

                        <div v-if="takesTimes" class="grid grid-cols-2 gap-3">
                            <FormField
                                label="Check in"
                                for="day-check-in"
                                :error="form.errors.check_in"
                            >
                                <Input
                                    id="day-check-in"
                                    v-model="form.check_in"
                                    type="time"
                                />
                            </FormField>
                            <FormField
                                label="Check out"
                                for="day-check-out"
                                :error="form.errors.check_out"
                            >
                                <Input
                                    id="day-check-out"
                                    v-model="form.check_out"
                                    type="time"
                                />
                            </FormField>
                            <p
                                class="col-span-2 -mt-1 text-xs text-muted-foreground"
                            >
                                With both times entered, worked, short and
                                overtime hours are worked out from the
                                employee's shift, and Present becomes Late or
                                Short Hours when the times say so. Leave the
                                times empty to count a full day.
                            </p>
                        </div>

                        <FormField
                            label="Notes"
                            for="day-notes"
                            :error="form.errors.notes"
                        >
                            <Input
                                id="day-notes"
                                v-model="form.notes"
                                maxlength="255"
                            />
                        </FormField>

                        <FormField
                            label="Reason for the change"
                            for="day-reason"
                            :error="form.errors.reason"
                            hint="Kept in the change history and the audit log."
                        >
                            <Input
                                id="day-reason"
                                v-model="form.reason"
                                maxlength="255"
                            />
                        </FormField>

                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <Button
                                v-if="day.state === 'recorded'"
                                type="button"
                                variant="ghost"
                                class="text-destructive hover:text-destructive"
                                @click="clear"
                            >
                                Clear record
                            </Button>
                            <span v-else />
                            <Button type="submit" :disabled="form.processing">
                                <Spinner v-if="form.processing" />
                                Save attendance
                            </Button>
                        </div>
                    </form>

                    <section v-if="logs.length" class="border-t pt-5">
                        <h3 class="text-sm font-semibold">Change history</h3>
                        <ol class="mt-3 grid gap-3">
                            <li
                                v-for="entry in logs"
                                :key="entry.id"
                                class="border-l-2 pl-3 text-sm"
                            >
                                <p>
                                    <template v-if="entry.old && entry.new">
                                        {{ label(entry.old.status) }} changed to
                                        {{ label(entry.new.status) }}
                                    </template>
                                    <template v-else-if="entry.new">
                                        Marked {{ label(entry.new.status) }}
                                    </template>
                                    <template v-else>Record cleared</template>
                                </p>
                                <p
                                    v-if="entry.reason"
                                    class="text-muted-foreground"
                                >
                                    {{ entry.reason }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ entry.user ?? 'System' }},
                                    {{ dateTime(entry.created_at) }}
                                </p>
                            </li>
                        </ol>
                    </section>
                </div>
            </template>
        </SheetContent>
    </Sheet>
</template>
