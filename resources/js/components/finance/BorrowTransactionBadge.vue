<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue';
import type { Tone } from '@/types';

defineProps<{
    type: string;
    label: string;
}>();

// Money given to the employee reads as owed (warning); money that came back
// reads as recovered (positive). The two never share a color.
const tones: Record<string, Tone> = {
    opening: 'warning',
    disbursement: 'warning',
    recovery: 'positive',
    settlement: 'positive',
    reversal: 'neutral',
};
</script>

<template>
    <StatusBadge :tone="tones[type] ?? 'neutral'">{{ label }}</StatusBadge>
</template>
