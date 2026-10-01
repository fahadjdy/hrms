<script setup lang="ts">
import { computed } from 'vue';
import SectionCard from '@/components/SectionCard.vue';
import { useFormat } from '@/composables/useFormat';
import type { AttendanceSummary } from '@/types';

const props = defineProps<{
    summary: AttendanceSummary;
    monthLabel: string;
}>();

const { minutes, percent } = useFormat();

const days = computed(() => [
    { label: 'Working days', value: props.summary.working_days },
    { label: 'Present', value: props.summary.present },
    { label: 'Absent', value: props.summary.absent },
    { label: 'Half day', value: props.summary.half_day },
    { label: 'Paid leave', value: props.summary.paid_leave },
    { label: 'Unpaid leave', value: props.summary.unpaid_leave },
    { label: 'Weekly off', value: props.summary.weekly_off },
    { label: 'Public holidays', value: props.summary.holidays },
    { label: 'WFH', value: props.summary.wfh },
    { label: 'Late', value: props.summary.late },
    ...(props.summary.unmarked > 0
        ? [{ label: 'Not marked', value: props.summary.unmarked }]
        : []),
]);

const hours = computed(() => [
    { label: 'Required hours', value: minutes(props.summary.required_minutes) },
    { label: 'Worked hours', value: minutes(props.summary.worked_minutes) },
    { label: 'Short hours', value: minutes(props.summary.short_minutes) },
    { label: 'Overtime', value: minutes(props.summary.overtime_minutes) },
]);
</script>

<template>
    <SectionCard
        :title="`Summary for ${monthLabel}`"
        description="Updates as soon as a day is marked or changed. Present includes late, short-hours and WFH days."
    >
        <dl
            class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3 lg:grid-cols-6"
        >
            <div v-for="item in days" :key="item.label">
                <dt class="text-xs text-muted-foreground">{{ item.label }}</dt>
                <dd class="tabular text-lg font-semibold">{{ item.value }}</dd>
            </div>
        </dl>

        <dl
            class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 border-t pt-4 text-sm sm:grid-cols-3 lg:grid-cols-6"
        >
            <div v-for="item in hours" :key="item.label">
                <dt class="text-xs text-muted-foreground">{{ item.label }}</dt>
                <dd class="tabular text-lg font-semibold">{{ item.value }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="text-xs text-muted-foreground">Attendance rate</dt>
                <dd class="text-lg font-semibold">
                    {{ percent(summary.attendance_rate) }}
                </dd>
                <dd class="text-xs text-muted-foreground">
                    Attended days (paid leave counts, a half day counts as half)
                    out of the {{ summary.elapsed_working_days }} working days
                    so far.
                </dd>
            </div>
        </dl>
    </SectionCard>
</template>
