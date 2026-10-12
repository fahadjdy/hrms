<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
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
import { toastFirstError } from '@/composables/useResourceForm';
import { destroy, update } from '@/routes/employees/attendance';
import type { AttendanceDay, AttendanceStatusOption } from '@/types';

const props = defineProps<{
    /** The employee being marked; null keeps the dialog closed. */
    employee: { id: number; name: string } | null;
    date: string;
    day: AttendanceDay | null;
    statuses: AttendanceStatusOption[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { date: formatDate, minutes } = useFormat();

const form = useForm({
    status: 'present' as string | null,
    check_in: '',
    check_out: '',
    worked_hours: '',
    notes: '',
    reason: '',
});

/** Absence and full-day leave have no working hours to enter. */
const takesHours = computed(
    () =>
        !['absent', 'paid_leave', 'unpaid_leave'].includes(form.status ?? '') &&
        !form.check_in &&
        !form.check_out,
);

const clearing = ref(false);

// Start from what is stored (or derived) for the day each time the dialog opens.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.status = props.day?.status ?? 'present';
    form.check_in = props.day?.check_in ?? '';
    form.check_out = props.day?.check_out ?? '';
    form.worked_hours = enteredHours(props.day);
    form.notes = props.day?.notes ?? '';
    form.reason = '';
});

/**
 * Hours typed as a decimal (6.5 = 6 h 30 min), sent as minutes. Only used
 * when no check-in / check-out is entered.
 */
function enteredHours(day: AttendanceDay | null): string {
    if (!day || day.state !== 'recorded' || day.check_in) {
        return '';
    }

    return day.worked_minutes !== day.required_minutes
        ? String(Math.round((day.worked_minutes / 60) * 100) / 100)
        : '';
}

function save(): void {
    if (!props.employee) {
        return;
    }

    form.transform(({ worked_hours, ...data }) => ({
        ...data,
        check_in: data.check_in || null,
        check_out: data.check_out || null,
        worked_minutes:
            takesHours.value && worked_hours !== ''
                ? Math.round(Number(worked_hours) * 60)
                : null,
        notes: data.notes || null,
        reason: data.reason || null,
    })).put(update.url({ employee: props.employee.id, date: props.date }), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}

/** Remove the stored record so the day falls back to its calendar status. */
function clearRecord(): void {
    if (!props.employee) {
        return;
    }

    clearing.value = true;

    router.delete(
        destroy.url({ employee: props.employee.id, date: props.date }),
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onError: toastFirstError,
            onFinish: () => {
                clearing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-5" @submit.prevent="save">
                <DialogHeader>
                    <DialogTitle>Mark attendance</DialogTitle>
                    <DialogDescription>
                        {{ employee?.name }} on {{ formatDate(date) }}. The
                        change is recorded in the audit log.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Status"
                    for="mark-status"
                    :error="form.errors.status"
                    required
                >
                    <NativeSelect
                        id="mark-status"
                        v-model="form.status"
                        :options="statuses"
                    />
                </FormField>

                <div class="grid grid-cols-2 gap-4">
                    <FormField
                        label="Check in"
                        for="mark-check-in"
                        :error="form.errors.check_in"
                    >
                        <DatePicker
                            id="mark-check-in"
                            v-model="form.check_in"
                            mode="time"
                        />
                    </FormField>
                    <FormField
                        label="Check out"
                        for="mark-check-out"
                        :error="form.errors.check_out"
                    >
                        <DatePicker
                            id="mark-check-out"
                            v-model="form.check_out"
                            mode="time"
                        />
                    </FormField>
                </div>
                <p class="-mt-3 text-xs text-muted-foreground">
                    Leave the times empty to count the full required hours. With
                    times, worked, short and overtime hours are calculated from
                    the employee's shift.
                </p>

                <FormField
                    v-if="takesHours"
                    label="Hours worked"
                    for="mark-worked-hours"
                    :error="(form.errors as Record<string, string>).worked_minutes"
                    :hint="`Optional, e.g. 6.5 for 6 h 30 min. Empty counts the full required ${day?.required_minutes ? minutes(day.required_minutes) : 'hours'}. Fewer hours become Short Hours and are deducted per the company's short-hours rule.`"
                >
                    <Input
                        id="mark-worked-hours"
                        v-model="form.worked_hours"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        max="24"
                        step="0.25"
                    />
                </FormField>

                <FormField
                    label="Reason / notes"
                    for="mark-notes"
                    :error="form.errors.notes"
                    hint="Optional, e.g. why fewer hours were worked. Shown on the day."
                >
                    <Input
                        id="mark-notes"
                        v-model="form.notes"
                        maxlength="255"
                    />
                </FormField>

                <FormField
                    label="Reason for the change"
                    for="mark-reason"
                    :error="form.errors.reason"
                    hint="Optional. Kept in the change history and the audit log."
                >
                    <Input
                        id="mark-reason"
                        v-model="form.reason"
                        maxlength="255"
                    />
                </FormField>

                <DialogFooter class="gap-2 sm:justify-between">
                    <Button
                        v-if="day?.state === 'recorded'"
                        type="button"
                        variant="ghost"
                        class="text-muted-foreground"
                        :disabled="clearing"
                        @click="clearRecord"
                    >
                        <Spinner v-if="clearing" />
                        Clear record
                    </Button>
                    <span v-else />
                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            <Spinner v-if="form.processing" />
                            Save attendance
                        </Button>
                    </div>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
