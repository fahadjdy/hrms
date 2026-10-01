<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowDownRight, ArrowRight, ArrowUpRight } from '@lucide/vue';
import type { Tone } from '@/types';

export type StatDelta = {
    value: number;
    direction: 'up' | 'down' | 'flat' | string;
    label: string;
};

const props = withDefaults(
    defineProps<{
        label: string;
        /** The value, already formatted. */
        value: string;
        hint?: string | null;
        tone?: Tone;
        delta?: StatDelta | null;
        /** Where the card leads for the detailed records. */
        href?: string | null;
    }>(),
    { tone: 'neutral' },
);

const dotClass: Record<Tone, string> = {
    positive: 'bg-positive',
    warning: 'bg-warning',
    negative: 'bg-negative',
    info: 'bg-info',
    neutral: 'bg-transparent',
};

const deltaIcon = () =>
    props.delta?.direction === 'up'
        ? ArrowUpRight
        : props.delta?.direction === 'down'
          ? ArrowDownRight
          : ArrowRight;
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href ?? undefined"
        class="group block rounded-lg border bg-card p-4 outline-none"
        :class="
            href
                ? 'transition-colors hover:border-primary/50 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50'
                : ''
        "
    >
        <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
            <span
                v-if="tone !== 'neutral'"
                class="size-1.5 rounded-full"
                :class="dotClass[tone]"
                aria-hidden="true"
            />
            {{ label }}
        </p>
        <p class="mt-1.5 text-2xl leading-tight font-semibold tracking-tight">
            {{ value }}
        </p>
        <p
            v-if="delta"
            class="mt-1.5 flex flex-wrap items-center gap-x-1 text-xs text-muted-foreground"
        >
            <span class="inline-flex items-center font-medium text-foreground">
                <component :is="deltaIcon()" class="size-3.5" />
                {{ delta.value }}%
            </span>
            {{ delta.label }}
        </p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-muted-foreground">
            {{ hint }}
        </p>
    </component>
</template>
