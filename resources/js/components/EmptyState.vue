<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import { Inbox } from '@lucide/vue';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: LucideIcon;
    }>(),
    { icon: () => Inbox },
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 px-6 py-10 text-center"
    >
        <component :is="icon" class="size-8 text-muted-foreground/60" />
        <p class="text-sm font-medium">{{ title }}</p>
        <p
            v-if="description"
            class="max-w-[48ch] text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-2">
            <slot />
        </div>
    </div>
</template>
