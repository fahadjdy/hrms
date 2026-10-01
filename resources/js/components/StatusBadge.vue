<script setup lang="ts">
import { toneClasses } from '@/lib/status';
import type { Tone } from '@/types';

withDefaults(
    defineProps<{
        tone?: Tone;
    }>(),
    { tone: 'neutral' },
);
</script>

<template>
    <span
        class="inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="toneClasses[tone]"
    >
        <slot />
    </span>
</template>
