<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowDownRight, ArrowRight, ArrowUpRight } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import Sparkline from '@/components/charts/Sparkline.vue';
import { useCountUp } from '@/composables/useCountUp';
import type { Tone } from '@/types';

export type StatDelta = {
    value: number;
    direction: 'up' | 'down' | 'flat' | string;
    label: string;
};

export type StatProgress = {
    value: number;
    max: number;
    /** Follows the percentage, e.g. "of 28 active" reads "75% of 28 active". */
    label: string;
    tone?: Tone;
};

const props = withDefaults(
    defineProps<{
        label: string;
        /**
         * The figure. A formatted string is shown as it is; a number glides
         * to each new value and is shown through `format`.
         */
        value: string | number;
        format?: (value: number) => string;
        hint?: string | null;
        tone?: Tone;
        icon?: Component | null;
        delta?: StatDelta | null;
        /** Which way a change is good news; without it a change is just a change. */
        goodDirection?: 'up' | 'down' | null;
        /** Monthly values, oldest first, drawn as a sparkline. */
        trend?: number[] | null;
        progress?: StatProgress | null;
        /** Where the card leads for the detailed records. */
        href?: string | null;
    }>(),
    {
        format: (value: number) => Math.round(value).toLocaleString(),
        hint: null,
        tone: 'neutral',
        icon: null,
        delta: null,
        goodDirection: null,
        trend: null,
        progress: null,
        href: null,
    },
);

/**
 * One set of classes per tone. Neutral figures wear the brand color, so a
 * card only turns green, amber or red when its number means something.
 */
const TONES: Record<
    Tone,
    { chip: string; glow: string; ink: string; bar: string; track: string }
> = {
    positive: {
        chip: 'bg-positive-soft text-positive ring-positive/15',
        glow: 'bg-positive',
        ink: 'text-positive',
        bar: 'bg-positive',
        track: 'bg-positive-soft',
    },
    warning: {
        chip: 'bg-warning-soft text-warning ring-warning/15',
        glow: 'bg-warning',
        ink: 'text-warning',
        bar: 'bg-warning',
        track: 'bg-warning-soft',
    },
    negative: {
        chip: 'bg-negative-soft text-negative ring-negative/15',
        glow: 'bg-negative',
        ink: 'text-negative',
        bar: 'bg-negative',
        track: 'bg-negative-soft',
    },
    info: {
        chip: 'bg-info-soft text-info ring-info/15',
        glow: 'bg-info',
        ink: 'text-info',
        bar: 'bg-info',
        track: 'bg-info-soft',
    },
    neutral: {
        chip: 'bg-primary/10 text-primary ring-primary/15',
        glow: 'bg-primary',
        ink: 'text-primary',
        bar: 'bg-primary',
        track: 'bg-muted',
    },
};

const styles = computed(() => TONES[props.tone]);

const animated = useCountUp(() =>
    typeof props.value === 'number' ? props.value : 0,
);

const shownValue = computed(() =>
    typeof props.value === 'number'
        ? props.format(animated.value)
        : props.value,
);

const finalValue = computed(() =>
    typeof props.value === 'number' ? props.format(props.value) : props.value,
);

const deltaIcon = computed(() =>
    props.delta?.direction === 'up'
        ? ArrowUpRight
        : props.delta?.direction === 'down'
          ? ArrowDownRight
          : ArrowRight,
);

const deltaClass = computed(() => {
    const direction = props.delta?.direction;

    if (!props.goodDirection || direction === 'flat') {
        return 'bg-muted text-muted-foreground';
    }

    return direction === props.goodDirection
        ? 'bg-positive-soft text-positive'
        : 'bg-warning-soft text-warning';
});

const percent = computed(() =>
    props.progress && props.progress.max > 0
        ? Math.round((props.progress.value / props.progress.max) * 100)
        : 0,
);

const progressStyles = computed(
    () => TONES[props.progress?.tone ?? props.tone],
);
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href ?? undefined"
        class="group relative isolate flex flex-col overflow-hidden rounded-xl border bg-card p-4 text-card-foreground shadow-xs outline-none"
        :class="
            href
                ? 'transition-[transform,box-shadow,border-color] duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 active:translate-y-0'
                : ''
        "
    >
        <!-- A soft wash of the card's color in one corner. -->
        <span
            class="pointer-events-none absolute -top-14 -right-14 -z-10 size-40 rounded-full opacity-[0.07] blur-2xl transition-opacity duration-300 group-hover:opacity-[0.12] dark:opacity-[0.12] dark:group-hover:opacity-[0.18]"
            :class="styles.glow"
            aria-hidden="true"
        />

        <div class="flex items-start justify-between gap-3">
            <p
                class="flex min-w-0 items-center gap-1.5 pt-0.5 text-xs leading-snug font-medium text-muted-foreground"
            >
                <span
                    v-if="!icon && tone !== 'neutral'"
                    class="size-1.5 shrink-0 rounded-full"
                    :class="styles.bar"
                    aria-hidden="true"
                />
                {{ label }}
            </p>
            <span
                v-if="icon"
                class="flex size-9 shrink-0 items-center justify-center rounded-lg ring-1 transition-transform duration-200 ease-out ring-inset group-hover:scale-110 group-hover:-rotate-6"
                :class="styles.chip"
                aria-hidden="true"
            >
                <component :is="icon" class="size-[1.125rem]" />
            </span>
        </div>

        <p
            class="tabular text-2xl leading-tight font-semibold tracking-tight"
            :class="icon ? 'mt-1' : 'mt-1.5'"
        >
            <span aria-hidden="true">{{ shownValue }}</span>
            <span class="sr-only">{{ finalValue }}</span>
        </p>

        <p
            v-if="delta"
            class="mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted-foreground"
        >
            <span
                class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 font-medium"
                :class="deltaClass"
            >
                <component
                    :is="deltaIcon"
                    class="size-3.5"
                    aria-hidden="true"
                />
                <span class="sr-only">{{
                    delta.direction === 'up'
                        ? 'Up'
                        : delta.direction === 'down'
                          ? 'Down'
                          : 'Unchanged'
                }}</span>
                {{ delta.value }}%
            </span>
            {{ delta.label }}
        </p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-muted-foreground">
            {{ hint }}
        </p>

        <div v-if="trend || progress" class="mt-auto pt-3">
            <Sparkline
                v-if="trend && trend.length > 1"
                :data="trend"
                :class="styles.ink"
            />

            <div v-if="progress" :class="trend ? 'mt-3' : ''">
                <div
                    class="h-1.5 overflow-hidden rounded-full"
                    :class="progressStyles.track"
                    role="meter"
                    :aria-label="`${label}: ${percent}% ${progress.label}`"
                    :aria-valuenow="percent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div
                        class="h-full rounded-full transition-[width] duration-500 ease-out"
                        :class="progressStyles.bar"
                        :style="{ width: `${percent}%` }"
                    />
                </div>
                <p class="mt-1.5 text-xs text-muted-foreground">
                    <span class="tabular font-medium text-foreground"
                        >{{ percent }}%</span
                    >
                    {{ progress.label }}
                </p>
            </div>
        </div>
    </component>
</template>
