<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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

const { date: formatDate } = useFormat();

const form = useForm({
    status: 'present' as string | null,
    check_in: '',
    check_out: '',
    notes: '',
});

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
    form.notes = props.day?.notes ?? '';
});

function save(): void {
    if (!props.employee) {
        return;
    }

    form.transform((data) => ({
        ...data,
        check_in: data.check_in || null,
        check_out: data.check_out || null,
        notes: data.notes || null,
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
                        <Input
                            id="mark-check-in"
                            v-model="form.check_in"
                            type="time"
                        />
                    </FormField>
                    <FormField
                        label="Check out"
                        for="mark-check-out"
                        :error="form.errors.check_out"
                    >
                        <Input
                            id="mark-check-out"
                            v-model="form.check_out"
                            type="time"
                        />
                    </FormField>
                </div>
                <p class="-mt-3 text-xs text-muted-foreground">
                    Leave the times empty to count the full required hours. With
                    times, worked, short and overtime hours are calculated from
                    the employee's shift.
                </p>

                <FormField
                    label="Notes"
                    for="mark-notes"
                    :error="form.errors.notes"
                >
                    <Input
                        id="mark-notes"
                        v-model="form.notes"
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
