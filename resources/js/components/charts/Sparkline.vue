<script setup lang="ts">
import { computed, useId } from 'vue';

const props = withDefaults(
    defineProps<{
        /** The values to plot, oldest first. */
        data: number[];
        /** Height in pixels; the line always fills the available width. */
        height?: number;
    }>(),
    { height: 36 },
);

// The line takes its color from the surrounding text color (`text-positive`…).
const gradientId = `spark-${useId()}`;

const VIEW_WIDTH = 100;
// Room above and below so the 2px line is never clipped at the extremes.
const PADDING = 3;

const points = computed(() => {
    const values = props.data;
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const step = values.length > 1 ? VIEW_WIDTH / (values.length - 1) : 0;
    const usable = props.height - PADDING * 2;

    return values.map((value, index) => ({
        x: index * step,
        // A flat series sits in the middle rather than on the floor.
        y:
            max === min
                ? props.height / 2
                : PADDING + usable - ((value - min) / range) * usable,
    }));
});

const line = computed(() =>
    points.value
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'}${point.x.toFixed(2)},${point.y.toFixed(2)}`,
        )
        .join(' '),
);

const area = computed(
    () => `${line.value} L${VIEW_WIDTH},${props.height} L0,${props.height} Z`,
);

const last = computed(() => points.value[points.value.length - 1]);
</script>

<template>
    <div
        class="relative w-full"
        :style="{ height: `${height}px` }"
        aria-hidden="true"
    >
        <svg
            class="absolute inset-0 size-full overflow-visible"
            :viewBox="`0 0 ${VIEW_WIDTH} ${height}`"
            preserveAspectRatio="none"
        >
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop
                        offset="0%"
                        stop-color="currentColor"
                        stop-opacity="0.22"
                    />
                    <stop
                        offset="100%"
                        stop-color="currentColor"
                        stop-opacity="0"
                    />
                </linearGradient>
            </defs>
            <path :d="area" :fill="`url(#${gradientId})`" />
            <path
                :d="line"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                vector-effect="non-scaling-stroke"
            />
        </svg>
        <!-- Drawn in HTML so the stretched SVG cannot squash it into an oval. -->
        <span
            v-if="last"
            class="absolute size-2 -translate-1/2 rounded-full bg-current ring-2 ring-card"
            :style="{ left: '100%', top: `${(last.y / height) * 100}%` }"
        />
    </div>
</template>
