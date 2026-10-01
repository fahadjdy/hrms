<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';

defineProps<{
    name: string;
    code?: string | null;
    photoUrl?: string | null;
    /** A second line under the name, e.g. the designation. */
    subtitle?: string | null;
    href?: InertiaLinkProps['href'];
}>();

const { getInitials } = useInitials();
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <Avatar class="size-8 shrink-0 rounded-md">
            <AvatarImage v-if="photoUrl" :src="photoUrl" :alt="name" />
            <AvatarFallback class="rounded-md text-xs">
                {{ getInitials(name) }}
            </AvatarFallback>
        </Avatar>
        <div class="min-w-0">
            <Link
                v-if="href"
                :href="href"
                class="block truncate font-medium underline-offset-4 hover:underline focus-visible:underline"
            >
                {{ name }}
            </Link>
            <span v-else class="block truncate font-medium">{{ name }}</span>
            <span
                v-if="code || subtitle"
                class="block truncate text-xs text-muted-foreground"
            >
                {{ [code, subtitle].filter(Boolean).join(' / ') }}
            </span>
        </div>
    </div>
</template>
