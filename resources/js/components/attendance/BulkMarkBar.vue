<script setup lang="ts">
import { ref } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { AttendanceStatusOption } from '@/types';

export type BulkMark = {
    status: string;
    check_in: string | null;
    check_out: string | null;
};

defineProps<{
    /** How many employees are selected. */
    count: number;
    statuses: AttendanceStatusOption[];
    processing?: boolean;
}>();

const emit = defineEmits<{
    apply: [mark: BulkMark];
    clear: [];
}>();

const status = ref<string | number | null>('present');
const checkIn = ref('');
const checkOut = ref('');

function apply(): void {
    if (!status.value) {
        return;
    }

    emit('apply', {
        status: String(status.value),
        check_in: checkIn.value || null,
        check_out: checkOut.value || null,
    });
}
</script>

<template>
    <form
        class="flex flex-col gap-3 rounded-lg border border-primary/40 bg-accent p-3 text-sm lg:flex-row lg:items-end"
        aria-label="Mark selected employees"
        @submit.prevent="apply"
    >
        <p class="font-medium text-accent-foreground lg:pb-2">
            {{ count }} selected
        </p>
        <div class="grid flex-1 grid-cols-2 gap-3 sm:grid-cols-4 lg:max-w-2xl">
            <label class="col-span-2 grid gap-1 sm:col-span-2">
                <span class="text-xs text-muted-foreground"
                    >Mark selected as</span
                >
                <NativeSelect v-model="status" :options="statuses" />
            </label>
            <label class="grid gap-1">
                <span class="text-xs text-muted-foreground">Check in</span>
                <DatePicker
                    v-model="checkIn"
                    mode="time"
                    class="bg-background"
                />
            </label>
            <label class="grid gap-1">
                <span class="text-xs text-muted-foreground">Check out</span>
                <DatePicker
                    v-model="checkOut"
                    mode="time"
                    class="bg-background"
                />
            </label>
        </div>
        <div class="flex gap-2">
            <Button type="submit" :disabled="processing || count === 0">
                <Spinner v-if="processing" />
                Mark {{ count }} employee{{ count === 1 ? '' : 's' }}
            </Button>
            <Button type="button" variant="ghost" @click="emit('clear')">
                Clear selection
            </Button>
        </div>
    </form>
</template>
