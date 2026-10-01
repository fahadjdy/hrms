<script setup lang="ts">
import AppearanceToggle from '@/components/AppearanceToggle.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenu from '@/components/UserMenu.vue';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1 shrink-0" />
            <div
                v-if="breadcrumbs && breadcrumbs.length > 0"
                class="min-w-0 overflow-hidden"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <AppearanceToggle />
            <UserMenu />
        </div>
    </header>
</template>
