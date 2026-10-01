<script setup lang="ts">
import { computed } from 'vue';

export type StackedSegment = {
    key: string;
    label: string;
    value: number;
    /** A CSS color, e.g. `var(--chart-1)`. */
    color: string;
};

const props = defineProps<{
    segments: StackedSegment[];
    /** Describes the bar for screen readers. */
    label: string;
    /** How to print one segment's value in the legend. */
    formatValue?: (value: number) => string;
}>();

const total = computed(() =>
    props.segments.reduce((sum, segment) => sum + segment.value, 0),
);

const share = (value: number): number =>
    total.value > 0 ? (value / total.value) * 100 : 0;

/** Whole percentages, except that a small non-zero share is never shown as 0%. */
const shareLabel = (value: number): string => {
    const percent = share(value);

    return percent > 0 && percent < 1 ? '<1%' : `${percent.toFixed(0)}%`;
};

const print = (value: number): string =>
    props.formatValue ? props.formatValue(value) : String(value);
</script>

<template>
    <div>
        <!-- Part-to-whole as one bar. Segments are separated by a gap in the surface color, never by an outline. -->
        <div
            role="img"
            :aria-label="label"
            class="flex h-5 w-full gap-0.5 overflow-hidden rounded-sm"
        >
            <template v-if="total > 0">
                <div
                    v-for="segment in segments.filter((s) => s.value > 0)"
                    :key="segment.key"
                    class="h-full min-w-1 transition-[flex-grow]"
                    :style="{
                        flexGrow: segment.value,
                        flexBasis: 0,
                        backgroundColor: segment.color,
                    }"
                    :title="`${segment.label}: ${print(segment.value)} (${share(segment.value).toFixed(1)}%)`"
                />
            </template>
            <div v-else class="h-full w-full bg-muted" />
        </div>

        <!-- The legend doubles as the table view: every value is readable without hovering. -->
        <dl
            class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3"
        >
            <div
                v-for="segment in segments"
                :key="segment.key"
                class="flex items-start gap-2"
            >
                <span
                    class="mt-1.5 size-2.5 shrink-0 rounded-[2px]"
                    :style="{ backgroundColor: segment.color }"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <dt class="truncate text-xs text-muted-foreground">
                        {{ segment.label }}
                    </dt>
                    <dd class="tabular font-medium">
                        {{ print(segment.value) }}
                        <span class="font-normal text-muted-foreground">
                            {{ shareLabel(segment.value) }}
                        </span>
                    </dd>
                </div>
            </div>
        </dl>
    </div>
</template>
