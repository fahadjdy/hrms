<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();

// A company user sees their own company; the platform owner sees the product name.
const company = computed(() => page.props.company);
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <img
            v-if="company?.logo_url"
            :src="company.logo_url"
            alt=""
            class="size-full bg-white object-contain"
        />
        <AppLogoIcon v-else class="size-5" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-semibold">
            {{ company?.name ?? page.props.name }}
        </span>
        <span class="truncate text-xs text-sidebar-foreground/70">
            {{ company ? 'HR & Payroll' : 'Platform admin' }}
        </span>
    </div>
</template>
