<script setup lang="ts">
import { CalendarCheck } from '@lucide/vue';
import { computed } from 'vue';
import StackedBar from '@/components/charts/StackedBar.vue';
import type { StackedSegment } from '@/components/charts/StackedBar.vue';
import SectionCard from '@/components/SectionCard.vue';
import { useFormat } from '@/composables/useFormat';
import type { DashboardAttendance } from '@/types';

const props = defineProps<{
    attendance: DashboardAttendance;
}>();

const { number, percent } = useFormat();

/**
 * Each attendance category keeps its own color wherever it appears. The set
 * is colorblind-validated; the counts beside the swatches mean color is never
 * the only way to read it.
 */
const colors: Record<string, string> = {
    present: 'var(--chart-3)',
    wfh: 'var(--chart-1)',
    half_day: 'var(--chart-4)',
    leave: 'var(--chart-5)',
    absent: 'var(--chart-6)',
    weekly_off: 'var(--chart-neutral)',
};

const order = ['present', 'wfh', 'half_day', 'leave', 'absent', 'weekly_off'];

const segments = computed<StackedSegment[]>(() =>
    order
        .map((key) =>
            props.attendance.overview.find((item) => item.key === key),
        )
        .filter((item) => item !== undefined)
        .map((item) => ({
            key: item.key,
            label: item.label,
            value: item.value,
            color: colors[item.key],
        })),
);

const hasData = computed(() => segments.value.some((s) => s.value > 0));
</script>

<template>
    <SectionCard
        :icon="CalendarCheck"
        title="Attendance overview"
        description="Recorded attendance days in the selected period, by status"
    >
        <p
            v-if="!hasData"
            class="py-8 text-center text-sm text-muted-foreground"
        >
            No attendance has been recorded for this period yet.
        </p>
        <template v-else>
            <StackedBar
                :segments="segments"
                label="Attendance days by status"
                :format-value="(value) => number(value, 0)"
            />

            <div class="mt-5 grid gap-4 border-t pt-4 sm:grid-cols-[1fr_auto]">
                <div>
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="text-sm font-medium">Attendance rate</p>
                        <p class="text-lg font-semibold">
                            {{
                                attendance.rate === null
                                    ? '-'
                                    : percent(attendance.rate)
                            }}
                        </p>
                    </div>
                    <!-- A meter: the unfilled track is a lighter step of the same color. -->
                    <div
                        class="mt-2 h-2 overflow-hidden rounded-full bg-positive-soft"
                        role="meter"
                        aria-label="Attendance rate"
                        :aria-valuenow="attendance.rate ?? 0"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="h-full rounded-full bg-positive"
                            :style="{ width: `${attendance.rate ?? 0}%` }"
                        />
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ attendance.rate_explanation }}
                    </p>
                </div>
                <div class="sm:border-l sm:pl-4">
                    <p class="text-xs text-muted-foreground">Late arrivals</p>
                    <p class="text-lg font-semibold">
                        {{ number(attendance.late_days, 0) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        days, counted within present
                    </p>
                </div>
            </div>
        </template>
    </SectionCard>
</template>
