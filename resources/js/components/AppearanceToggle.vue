<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';

const { toggleAppearance } = useAppearance();
</script>

<template>
    <Button
        variant="ghost"
        size="icon"
        class="size-9"
        aria-label="Switch between light and dark mode"
        title="Switch between light and dark mode"
        @click="toggleAppearance"
    >
        <!-- The icon follows the theme class itself, so it is right on first paint too. -->
        <Sun class="size-5 dark:hidden" />
        <Moon class="hidden size-5 dark:block" />
    </Button>
</template>
