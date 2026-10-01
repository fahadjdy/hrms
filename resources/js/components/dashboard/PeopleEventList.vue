<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useFormat } from '@/composables/useFormat';
import { show } from '@/routes/employees';
import type { PersonEvent } from '@/types';

defineProps<{
    title: string;
    people: PersonEvent[];
    /** Shown when the list is empty. */
    emptyText: string;
}>();

const { date } = useFormat();
</script>

<template>
    <div>
        <h3 class="text-xs font-medium text-muted-foreground">{{ title }}</h3>
        <p
            v-if="people.length === 0"
            class="mt-2 text-sm text-muted-foreground"
        >
            {{ emptyText }}
        </p>
        <ul v-else class="mt-2 grid gap-2">
            <li
                v-for="person in people"
                :key="person.id"
                class="flex items-baseline justify-between gap-3 text-sm"
            >
                <span class="min-w-0">
                    <Link
                        :href="show(person.id)"
                        class="block truncate font-medium underline-offset-4 hover:underline focus-visible:underline"
                    >
                        {{ person.name }}
                    </Link>
                    <span class="block truncate text-xs text-muted-foreground">
                        {{ person.department ?? person.code }}
                    </span>
                </span>
                <span class="tabular shrink-0 text-xs text-muted-foreground">
                    {{ date(person.date) }}
                </span>
            </li>
        </ul>
    </div>
</template>
