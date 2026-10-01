<script setup lang="ts">
export type DetailItem = {
    label: string;
    value?: string | number | null;
};

withDefaults(
    defineProps<{
        items: DetailItem[];
        /** Columns on wide screens. Always one column on a phone. */
        columns?: 2 | 3;
    }>(),
    { columns: 2 },
);
</script>

<template>
    <!-- Label / value pairs. Use the `value-<index>` slot for anything richer than text. -->
    <dl
        class="grid gap-x-6 gap-y-4 text-sm"
        :class="
            columns === 3 ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2'
        "
    >
        <div v-for="(item, index) in items" :key="item.label" class="min-w-0">
            <dt class="text-xs text-muted-foreground">{{ item.label }}</dt>
            <dd class="mt-0.5 break-words">
                <slot :name="`value-${index}`" :item="item">
                    {{
                        item.value === null ||
                        item.value === undefined ||
                        item.value === ''
                            ? '-'
                            : item.value
                    }}
                </slot>
            </dd>
        </div>
    </dl>
</template>
