<script setup lang="ts">
import { useFormat } from '@/composables/useFormat';
import { attendanceClass } from '@/lib/status';
import type { AttendanceDay } from '@/types';

defineProps<{
    days: AttendanceDay[];
    today: string;
}>();

defineEmits<{ select: [day: AttendanceDay] }>();

const { minutes } = useFormat();

const statusText = (day: AttendanceDay): string =>
    day.status_label ??
    (day.state === 'unmarked'
        ? 'Not marked'
        : day.state === 'not_employed'
          ? 'Not employed'
          : 'Upcoming');
</script>

<template>
    <!-- The month as a list: every day keeps its full detail on a narrow screen. -->
    <ol class="divide-y rounded-lg border bg-card">
        <li v-for="day in days" :key="day.date">
            <button
                type="button"
                class="grid w-full grid-cols-[3rem_1fr_auto] items-center gap-3 px-3 py-2.5 text-left outline-none hover:bg-accent/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                :class="[
                    day.state === 'not_employed' ? 'text-muted-foreground' : '',
                    day.date === today ? 'bg-accent/50' : '',
                ]"
                @click="$emit('select', day)"
            >
                <span class="text-center leading-tight">
                    <span class="tabular block text-base font-semibold">
                        {{ String(day.day).padStart(2, '0') }}
                    </span>
                    <span class="block text-xs text-muted-foreground">
                        {{ day.weekday }}
                    </span>
                </span>

                <span class="min-w-0">
                    <span
                        class="inline-block rounded px-1.5 py-0.5 text-xs font-medium"
                        :class="
                            day.status
                                ? attendanceClass(day.status)
                                : day.state === 'unmarked'
                                  ? 'border border-dashed border-warning text-warning'
                                  : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ statusText(day) }}
                    </span>
                    <span
                        v-if="day.holiday_name"
                        class="ml-2 text-xs text-muted-foreground"
                    >
                        {{ day.holiday_name }}
                    </span>
                    <span
                        v-if="day.check_in && day.check_out"
                        class="tabular mt-1 block text-sm"
                    >
                        {{ day.check_in }} → {{ day.check_out }}
                    </span>
                </span>

                <span class="tabular text-right text-sm leading-tight">
                    <span
                        v-if="day.worked_minutes > 0"
                        class="block font-medium"
                    >
                        {{ minutes(day.worked_minutes) }}
                    </span>
                    <span
                        v-if="day.short_minutes > 0"
                        class="block text-xs text-warning"
                    >
                        Short {{ minutes(day.short_minutes) }}
                    </span>
                    <span
                        v-if="day.overtime_minutes > 0"
                        class="block text-xs text-info"
                    >
                        OT {{ minutes(day.overtime_minutes) }}
                    </span>
                </span>
            </button>
        </li>
    </ol>
</template>
