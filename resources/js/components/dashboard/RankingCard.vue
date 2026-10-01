<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarOff,
    CalendarX,
    Clock,
    HandCoins,
    Timer,
    Trophy,
} from '@lucide/vue';
import type { Component } from 'vue';
import SectionCard from '@/components/SectionCard.vue';
import { useChartFormat } from '@/composables/useChartFormat';
import { show } from '@/routes/employees';
import type { Ranking } from '@/types';

const props = defineProps<{
    ranking: Ranking;
}>();

const { full } = useChartFormat();

const ICONS: Record<string, Component> = {
    highest_attendance: CalendarCheck,
    highest_overtime: Timer,
    most_hours: Clock,
    most_leave: CalendarOff,
    most_absent: CalendarX,
    highest_borrow: HandCoins,
};

const print = (value: number): string => full(value, props.ranking.format);

// Bars are scaled to the top value, so the list also reads as a small bar chart.
const width = (value: number): string => {
    const top = Math.max(...props.ranking.rows.map((row) => row.value), 0);

    return top > 0 ? `${Math.max(4, (value / top) * 100)}%` : '0%';
};
</script>

<template>
    <SectionCard
        :title="ranking.title"
        :description="ranking.metric"
        :icon="ICONS[ranking.key] ?? Trophy"
    >
        <p
            v-if="ranking.rows.length === 0"
            class="py-4 text-center text-sm text-muted-foreground"
        >
            Nothing recorded for this period.
        </p>
        <ol v-else class="grid gap-3">
            <li
                v-for="(row, index) in ranking.rows"
                :key="row.employee_id"
                class="grid gap-1"
            >
                <div class="flex items-baseline justify-between gap-3 text-sm">
                    <span class="flex min-w-0 items-baseline gap-2">
                        <span
                            class="tabular w-4 shrink-0 text-xs text-muted-foreground"
                        >
                            {{ index + 1 }}
                        </span>
                        <Link
                            :href="show(row.employee_id)"
                            class="truncate font-medium underline-offset-4 hover:underline focus-visible:underline"
                        >
                            {{ row.name }}
                        </Link>
                    </span>
                    <span class="tabular shrink-0 font-medium">
                        {{ print(row.value) }}
                    </span>
                </div>
                <div
                    class="ml-6 h-1.5 rounded-full bg-muted"
                    aria-hidden="true"
                >
                    <div
                        class="h-full rounded-full bg-chart-1"
                        :style="{ width: width(row.value) }"
                    />
                </div>
                <p v-if="row.detail" class="ml-6 text-xs text-muted-foreground">
                    {{ row.detail }}
                </p>
            </li>
        </ol>
    </SectionCard>
</template>
