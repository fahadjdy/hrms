<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineProps<{
    title?: string;
    description?: string;
    /** Remove the inner padding, for tables and lists that run edge to edge. */
    flush?: boolean;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <section
        :class="
            cn('rounded-lg border bg-card text-card-foreground', $props.class)
        "
    >
        <div
            v-if="title || $slots.actions"
            class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2 border-b px-4 py-3 sm:px-5"
        >
            <div class="min-w-0">
                <h2 v-if="title" class="text-sm font-semibold">{{ title }}</h2>
                <p
                    v-if="description"
                    class="mt-0.5 text-xs text-muted-foreground"
                >
                    {{ description }}
                </p>
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
