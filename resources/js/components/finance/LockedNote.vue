<script setup lang="ts">
import { Lock } from '@lucide/vue';

withDefaults(
    defineProps<{
        /** Why the record can no longer be changed. */
        text?: string;
    }>(),
    { text: 'In a finalized payroll' },
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 text-xs whitespace-nowrap text-muted-foreground"
        :title="`${text}. Reopen that payroll to change it.`"
    >
        <Lock class="size-3.5" aria-hidden="true" />
        {{ text }}
    </span>
</template>
