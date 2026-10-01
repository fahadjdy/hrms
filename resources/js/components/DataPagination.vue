<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{
    // Only the paging fields are read, so any paginated list fits.
    page: Paginated<unknown>;
    /** What one row is called, for "Showing 1-15 of 240 employees". */
    noun?: string;
}>();
</script>

<template>
    <nav
        v-if="page.total > 0"
        class="flex flex-col items-center justify-between gap-3 text-sm text-muted-foreground sm:flex-row"
        aria-label="Pagination"
    >
        <p class="tabular">
            Showing {{ page.from }}-{{ page.to }} of {{ page.total }}
            {{ noun ?? 'records' }}
        </p>
        <div v-if="page.last_page > 1" class="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!page.prev_page_url"
                :as-child="!!page.prev_page_url"
            >
                <Link
                    v-if="page.prev_page_url"
                    :href="page.prev_page_url"
                    preserve-scroll
                >
                    <ChevronLeft />
                    Previous
                </Link>
                <template v-else>
                    <ChevronLeft />
                    Previous
                </template>
            </Button>
            <span class="tabular px-1">
                Page {{ page.current_page }} of {{ page.last_page }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!page.next_page_url"
                :as-child="!!page.next_page_url"
            >
                <Link
                    v-if="page.next_page_url"
                    :href="page.next_page_url"
                    preserve-scroll
                >
                    Next
                    <ChevronRight />
                </Link>
                <template v-else>
                    Next
                    <ChevronRight />
                </template>
            </Button>
        </div>
    </nav>
</template>
