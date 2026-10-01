<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

withDefaults(
    defineProps<{
        /** How many placeholder cards to show. */
        count?: number;
        columns?: string;
    }>(),
    { count: 2, columns: 'lg:grid-cols-2' },
);
</script>

<template>
    <div class="grid gap-4" :class="columns" aria-hidden="true">
        <div
            v-for="index in count"
            :key="index"
            class="rounded-lg border bg-card p-5"
        >
            <Skeleton class="h-4 w-40" />
            <Skeleton class="mt-2 h-3 w-56" />
            <Skeleton class="mt-6 h-40 w-full" />
        </div>
    </div>
</template>
