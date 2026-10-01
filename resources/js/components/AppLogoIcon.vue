<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- A ledger page: ruled lines closed by the bookkeeper's double rule. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <path d="M5 5h14" />
        <path d="M5 10h9" />
        <path d="M5 15h14" />
        <path d="M5 19h14" />
    </svg>
</template>
