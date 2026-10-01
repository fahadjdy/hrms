<script setup lang="ts">
import {
    CategoryScale,
    Chart,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import type { ChartData, ChartOptions, Plugin } from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import { useChartFormat } from '@/composables/useChartFormat';
import { useChartTheme } from '@/composables/useChartTheme';
import type { ChartFormat } from '@/composables/useChartTheme';

Chart.register(
    CategoryScale,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
);

export type LineSeries = {
    label: string;
    data: number[];
    /** Series slot (0-5) in the fixed palette order. Defaults to its position. */
    slot?: number;
};

const props = withDefaults(
    defineProps<{
        labels: string[];
        series: LineSeries[];
        format?: ChartFormat;
        /** Describes the chart for screen readers. */
        label: string;
        height?: number;
    }>(),
    { format: 'number', height: 260 },
);

const { theme } = useChartTheme();
const { full, compact } = useChartFormat();

// Durations arrive in minutes and are plotted in hours, so the axis lands on
// round hour values instead of fractions of an hour.
const unit = computed(() => (props.format === 'minutes' ? 60 : 1));

const data = computed<ChartData<'line'>>(() => ({
    labels: props.labels,
    datasets: props.series.map((series, index) => {
        const color = theme.value.series[series.slot ?? index];

        return {
            label: series.label,
            data: series.data.map((value) => value / unit.value),
            borderColor: color,
            borderWidth: 2,
            borderJoinStyle: 'round',
            borderCapStyle: 'round',
            tension: 0.25,
            // A single series gets a light wash under the line; several do not.
            fill: props.series.length === 1,
            backgroundColor: `${color}1a`,
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHitRadius: 16,
            pointBackgroundColor: color,
            // The surface-colored ring keeps a hovered dot legible on the line.
            pointBorderColor: theme.value.surface,
            pointHoverBorderColor: theme.value.surface,
            pointHoverBorderWidth: 2,
        };
    }),
}));

/** A vertical hairline at the hovered position, so the reader aims at a month, not a line. */
const crosshair = computed<Plugin<'line'>>(() => ({
    id: 'crosshair',
    afterDatasetsDraw(chart) {
        const active = chart.tooltip?.getActiveElements() ?? [];

        if (active.length === 0) {
            return;
        }

        const { ctx, chartArea } = chart;
        const x = active[0].element.x;

        ctx.save();
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.lineWidth = 1;
        ctx.strokeStyle = theme.value.border;
        ctx.stroke();
        ctx.restore();
    },
}));

const options = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 250 },
    interaction: { mode: 'index', intersect: false },
    scales: {
        x: {
            border: { color: theme.value.border },
            grid: { display: false },
            ticks: {
                color: theme.value.text,
                font: { size: 11 },
                maxRotation: 0,
                autoSkipPadding: 12,
            },
        },
        y: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: theme.value.grid, lineWidth: 1 },
            ticks: {
                color: theme.value.text,
                font: { size: 11 },
                maxTicksLimit: 5,
                callback: (value) =>
                    compact(Number(value) * unit.value, props.format),
            },
        },
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: theme.value.ink,
            titleColor: theme.value.surface,
            bodyColor: theme.value.surface,
            padding: 10,
            cornerRadius: 6,
            boxWidth: 10,
            boxHeight: 2,
            callbacks: {
                label: (context) =>
                    ` ${full(Number(context.raw) * unit.value, props.format)}  ${context.dataset.label ?? ''}`,
            },
        },
    },
}));
</script>

<template>
    <div
        role="img"
        :aria-label="label"
        class="relative w-full"
        :style="{ height: `${height}px` }"
    >
        <Line :data="data" :options="options" :plugins="[crosshair]" />
    </div>
</template>
