<script setup lang="ts">
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { attendanceClass } from '@/lib/status';
import type { AttendanceDay } from '@/types';

const props = defineProps<{
    days: AttendanceDay[];
    today: string;
}>();

defineEmits<{ select: [day: AttendanceDay] }>();

const { minutes } = useFormat();

const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/** Empty cells before the 1st so that it lands under its weekday (weeks start on Monday). */
const leading = computed(() => {
    if (props.days.length === 0) {
        return 0;
    }

    const [year, month, day] = props.days[0].date.split('-').map(Number);

    return (new Date(year, month - 1, day).getDay() + 6) % 7;
});

const describe = (day: AttendanceDay): string => {
    const status =
        day.status_label ??
        (day.state === 'unmarked'
            ? 'Not marked'
            : day.state === 'not_employed'
              ? 'Not employed'
              : 'Upcoming');

    return `${day.weekday} ${day.day}: ${status}${day.holiday_name ? `, ${day.holiday_name}` : ''}`;
};
</script>

<template>
    <div class="rounded-lg border bg-card">
        <div
            class="grid grid-cols-7 border-b text-center text-xs font-medium text-muted-foreground"
            aria-hidden="true"
        >
            <div v-for="weekday in weekdays" :key="weekday" class="py-2">
                {{ weekday }}
            </div>
        </div>

        <ol class="grid grid-cols-7">
            <li
                v-for="blank in leading"
                :key="`blank-${blank}`"
                class="border-r border-b bg-muted/30"
                aria-hidden="true"
            />
            <li
                v-for="day in days"
                :key="day.date"
                class="min-h-[5.5rem] border-r border-b lg:min-h-28 [&:nth-child(7n)]:border-r-0"
            >
                <button
                    type="button"
                    class="flex size-full flex-col items-start gap-1 p-1.5 text-left outline-none hover:bg-accent/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset sm:p-2"
                    :class="[
                        day.state === 'not_employed'
                            ? 'bg-muted/40 text-muted-foreground'
                            : '',
                        day.date === today ? 'bg-accent/50' : '',
                    ]"
                    :aria-label="describe(day)"
                    @click="$emit('select', day)"
                >
                    <span
                        class="flex w-full items-center justify-between gap-1"
                    >
                        <span
                            class="tabular text-sm"
                            :class="
                                day.date === today
                                    ? 'flex size-6 items-center justify-center rounded-full bg-primary font-semibold text-primary-foreground'
                                    : 'font-medium'
                            "
                        >
                            {{ day.day }}
                        </span>
                        <span
                            v-if="day.code"
                            class="rounded px-1.5 py-0.5 text-[0.6875rem] leading-none font-semibold"
                            :class="[
                                attendanceClass(day.status),
                                day.state === 'upcoming' ? 'opacity-60' : '',
                            ]"
                        >
                            {{ day.code }}
                        </span>
                        <span
                            v-else-if="day.state === 'unmarked'"
                            class="rounded border border-dashed border-warning px-1 py-0.5 text-[0.6875rem] leading-none text-warning"
                        >
                            ?
                        </span>
                    </span>

                    <!-- Times and hours need room: shown from the large breakpoint up, and in the day panel always. -->
                    <span
                        v-if="day.check_in && day.check_out"
                        class="tabular hidden text-xs text-muted-foreground lg:block"
                    >
                        {{ day.check_in }} - {{ day.check_out }}
                    </span>
                    <span
                        v-if="day.worked_minutes > 0"
                        class="tabular hidden text-xs lg:block"
                    >
                        {{ minutes(day.worked_minutes) }}
                    </span>
                    <span
                        v-if="day.holiday_name"
                        class="hidden w-full truncate text-xs text-muted-foreground lg:block"
                    >
                        {{ day.holiday_name }}
                    </span>
                    <span class="mt-auto flex flex-wrap gap-1">
                        <span
                            v-if="day.short_minutes > 0"
                            class="tabular rounded bg-warning-soft px-1 text-[0.6875rem] text-warning"
                            :title="`Short by ${minutes(day.short_minutes)}`"
                        >
                            <span class="hidden lg:inline">Short </span>-{{
                                minutes(day.short_minutes)
                            }}
                        </span>
                        <span
                            v-if="day.overtime_minutes > 0"
                            class="tabular rounded bg-info-soft px-1 text-[0.6875rem] text-info"
                            :title="`Overtime ${minutes(day.overtime_minutes)}`"
                        >
                            <span class="hidden lg:inline">OT </span>+{{
                                minutes(day.overtime_minutes)
                            }}
                        </span>
                    </span>
                </button>
            </li>
        </ol>
    </div>
</template>
