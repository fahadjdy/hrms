<script setup lang="ts">
import { computed } from 'vue';
import { attendanceClass } from '@/lib/status';
import type { AttendanceDay } from '@/types';

const props = defineProps<{
    day: Pick<AttendanceDay, 'state' | 'status' | 'status_label' | 'code'>;
}>();

/** Days without a status still say what they are, in words. */
const fallback = computed(() => {
    switch (props.day.state) {
        case 'unmarked':
            return 'Not marked';
        case 'upcoming':
            return 'Upcoming';
        case 'not_employed':
            return 'Not employed';
        default:
            return 'No record';
    }
});
</script>

<template>
    <span
        v-if="day.status"
        class="inline-flex w-fit items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="attendanceClass(day.status)"
    >
        <span class="font-semibold">{{ day.code }}</span>
        {{ day.status_label }}
    </span>
    <span
        v-else
        class="inline-flex w-fit items-center rounded-full border border-dashed px-2 py-0.5 text-xs whitespace-nowrap text-muted-foreground"
    >
        {{ fallback }}
    </span>
</template>
