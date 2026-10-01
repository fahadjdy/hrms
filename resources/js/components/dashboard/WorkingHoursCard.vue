<script setup lang="ts">
import { computed } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import ChartCard from '@/components/charts/ChartCard.vue';
import { useFormat } from '@/composables/useFormat';
import type { DashboardAttendance } from '@/types';

const props = defineProps<{
    attendance: DashboardAttendance;
}>();

const { minutes } = useFormat();

const hours = computed(() => props.attendance.hours);

const workedShare = computed(() =>
    hours.value.required_minutes > 0
        ? Math.min(
              100,
              (hours.value.worked_minutes / hours.value.required_minutes) * 100,
          )
        : 0,
);

const departments = computed(() => props.attendance.hours_by_department);

const tiles = computed(() => [
    { label: 'Required hours', value: minutes(hours.value.required_minutes) },
    { label: 'Worked hours', value: minutes(hours.value.worked_minutes) },
    { label: 'Short hours', value: minutes(hours.value.short_minutes) },
    { label: 'Overtime hours', value: minutes(hours.value.overtime_minutes) },
]);
</script>

<template>
    <ChartCard
        title="Working hours"
        description="Required against worked hours, and worked hours by department"
        :empty="hours.required_minutes === 0 && hours.worked_minutes === 0"
        empty-text="No working hours recorded for this period"
        :table="{
            columns: ['Department', 'Worked', 'Short', 'Overtime'],
            rows: departments.map((row) => [
                row.label,
                minutes(row.worked),
                minutes(row.short),
                minutes(row.overtime),
            ]),
        }"
    >
        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4">
            <div v-for="tile in tiles" :key="tile.label">
                <dt class="text-xs text-muted-foreground">{{ tile.label }}</dt>
                <dd class="text-base font-semibold">{{ tile.value }}</dd>
            </div>
        </dl>

        <div
            class="mt-3 h-2 overflow-hidden rounded-full bg-info-soft"
            role="meter"
            aria-label="Worked hours as a share of required hours"
            :aria-valuenow="Math.round(workedShare)"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full rounded-full bg-chart-1"
                :style="{ width: `${workedShare}%` }"
            />
        </div>
        <p class="mt-1.5 text-xs text-muted-foreground">
            {{ Math.round(workedShare) }}% of required hours were worked.
        </p>

        <div class="mt-5">
            <BarChart
                horizontal
                format="minutes"
                label="Worked hours by department"
                :labels="departments.map((row) => row.label)"
                :series="[
                    {
                        label: 'Worked hours',
                        data: departments.map((row) => row.worked),
                    },
                ]"
            />
        </div>
    </ChartCard>
</template>
