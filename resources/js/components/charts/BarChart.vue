<script setup lang="ts">
import {
    BarElement,
    CategoryScale,
    Chart,
    LinearScale,
    Tooltip,
} from 'chart.js';
import type { ChartData, ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { useChartFormat } from '@/composables/useChartFormat';
import { useChartTheme } from '@/composables/useChartTheme';
import type { ChartFormat } from '@/composables/useChartTheme';

Chart.register(BarElement, CategoryScale, LinearScale, Tooltip);

export type BarSeries = {
    label: string;
    data: number[];
    /** Series slot (0-5) in the fixed palette order. Defaults to its position. */
    slot?: number;
};

const props = withDefaults(
    defineProps<{
        labels: string[];
        series: BarSeries[];
        format?: ChartFormat;
        /** Bars run left to right. Use for long category names. */
        horizontal?: boolean;
        /** Describes the chart for screen readers. */
        label: string;
        height?: number;
    }>(),
    { format: 'number', height: 260 },
);

const { theme } = useChartTheme();
const { full, compact } = useChartFormat();

// A horizontal chart grows with its categories so every bar keeps its room.
const chartHeight = computed(() =>
    props.horizontal
        ? Math.max(
              120,
              props.labels.length * (props.series.length * 22 + 16) + 40,
          )
        : props.height,
);

// Durations arrive in minutes and are plotted in hours, so the axis lands on
// round hour values instead of fractions of an hour.
const unit = computed(() => (props.format === 'minutes' ? 60 : 1));

const data = computed<ChartData<'bar'>>(() => ({
    labels: props.labels,
    datasets: props.series.map((series, index) => ({
        label: series.label,
        data: series.data.map((value) => value / unit.value),
        backgroundColor: theme.value.series[series.slot ?? index],
        hoverBackgroundColor: theme.value.series[series.slot ?? index],
        // Thin marks: the bar never fills its slot. Rounded at the data end only.
        maxBarThickness: 22,
        borderRadius: 4,
        borderSkipped: props.horizontal ? 'left' : 'bottom',
        // A 2px gap in the surface color separates neighbouring bars.
        borderColor: theme.value.surface,
        borderWidth: props.horizontal
            ? { top: 1, bottom: 1, left: 0, right: 0 }
            : { top: 0, bottom: 0, left: 1, right: 1 },
    })),
}));

const options = computed<ChartOptions<'bar'>>(() => {
    const valueAxis = {
        beginAtZero: true,
        border: { display: false },
        grid: { color: theme.value.grid, lineWidth: 1 },
        ticks: {
            color: theme.value.text,
            font: { size: 11 },
            maxTicksLimit: 5,
            callback: (value: string | number) =>
                compact(Number(value) * unit.value, props.format),
        },
    };
    const categoryAxis = {
        border: { color: theme.value.border },
        grid: { display: false },
        ticks: { color: theme.value.text, font: { size: 11 } },
    };

    return {
        indexAxis: props.horizontal ? 'y' : 'x',
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 250 },
        interaction: { mode: 'index', intersect: false },
        scales: props.horizontal
            ? { x: valueAxis, y: categoryAxis }
            : { x: categoryAxis, y: valueAxis },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: theme.value.ink,
                titleColor: theme.value.surface,
                bodyColor: theme.value.surface,
                padding: 10,
                cornerRadius: 6,
                boxWidth: 8,
                boxHeight: 8,
                usePointStyle: true,
                callbacks: {
                    label: (context) =>
                        ` ${full(Number(context.raw) * unit.value, props.format)}  ${context.dataset.label ?? ''}`,
                },
            },
        },
    };
});
</script>

<template>
    <div
        role="img"
        :aria-label="label"
        class="relative w-full"
        :style="{ height: `${chartHeight}px` }"
    >
        <Bar :data="data" :options="options" />
    </div>
</template>
