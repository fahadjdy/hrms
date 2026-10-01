<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineProps<{
    title?: string;
    description?: string;
    /** A small icon beside the title, for widgets on a dashboard. */
    icon?: Component;
    /** Remove the inner padding, for tables and lists that run edge to edge. */
    flush?: boolean;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <section
        :class="
            cn(
                'rounded-xl border bg-card text-card-foreground shadow-xs',
                $props.class,
            )
        "
    >
        <div
            v-if="title || $slots.actions"
            class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2 border-b px-4 py-3 sm:px-5"
        >
            <div class="flex min-w-0 items-start gap-3">
                <span
                    v-if="icon"
                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/15 ring-inset"
                    aria-hidden="true"
                >
                    <component :is="icon" class="size-4" />
                </span>
                <div class="min-w-0">
                    <h2 v-if="title" class="text-sm font-semibold">
                        {{ title }}
                    </h2>
                    <p
                        v-if="description"
                        class="mt-0.5 text-xs text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
            <div v-if="$slots.actions" class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
        <div :class="flush ? '' : 'p-4 sm:p-5'">
            <slot />
        </div>
    </section>
</template>
