<script setup lang="ts" generic="T extends Record<string, any>">
import { useMediaQuery } from '@vueuse/core';
import EmptyState from '@/components/EmptyState.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type DataTableColumn = {
    key: string;
    label: string;
    align?: 'left' | 'right' | 'center';
    /** Extra classes for the cell on wide screens. */
    class?: string;
    /** Used as the card heading on small screens. */
    primary?: boolean;
    /** Left out of the card on small screens. */
    hideOnMobile?: boolean;
};

const props = withDefaults(
    defineProps<{
        columns: DataTableColumn[];
        rows: T[];
        rowKey?: string;
        emptyTitle?: string;
        emptyDescription?: string;
    }>(),
    {
        rowKey: 'id',
        emptyTitle: 'Nothing to show yet',
    },
);

defineSlots<
    {
        [key: `cell-${string}`]: (props: { row: T; value: any }) => any;
    } & {
        actions?: (props: { row: T }) => any;
        empty?: () => any;
    }
>();

// A full table from the `md` breakpoint up; one card per row below it, so
// nothing has to be read through a sideways scroll on a phone.
const isWide = useMediaQuery('(min-width: 768px)');

const alignClass = (column: DataTableColumn): string =>
    column.align === 'right'
        ? 'text-right'
        : column.align === 'center'
          ? 'text-center'
          : 'text-left';

const keyOf = (row: T, index: number): string | number =>
    row[props.rowKey] ?? index;
</script>

<template>
    <div v-if="rows.length === 0" class="rounded-lg border bg-card">
        <slot name="empty">
            <EmptyState :title="emptyTitle" :description="emptyDescription" />
        </slot>
    </div>

    <div v-else-if="isWide" class="overflow-x-auto rounded-lg border bg-card">
        <Table>
            <TableHeader>
                <TableRow class="hover:bg-transparent">
                    <TableHead
                        v-for="column in columns"
                        :key="column.key"
                        class="h-10 text-xs font-medium whitespace-nowrap text-muted-foreground"
                        :class="alignClass(column)"
                    >
                        {{ column.label }}
                    </TableHead>
                    <TableHead v-if="$slots.actions" class="w-px">
                        <span class="sr-only">Actions</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="(row, index) in rows" :key="keyOf(row, index)">
                    <TableCell
                        v-for="column in columns"
                        :key="column.key"
                        :class="[alignClass(column), column.class]"
                    >
                        <slot
                            :name="`cell-${column.key}`"
                            :row="row"
                            :value="row[column.key]"
                        >
                            {{ row[column.key] ?? '-' }}
                        </slot>
                    </TableCell>
                    <TableCell v-if="$slots.actions" class="text-right">
                        <div class="flex items-center justify-end gap-1">
                            <slot name="actions" :row="row" />
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>

    <ul v-else class="grid gap-3">
        <li
            v-for="(row, index) in rows"
            :key="keyOf(row, index)"
            class="rounded-lg border bg-card p-4"
        >
            <div
                v-for="column in columns.filter((c) => c.primary)"
                :key="column.key"
                class="min-w-0"
            >
                <slot
                    :name="`cell-${column.key}`"
                    :row="row"
                    :value="row[column.key]"
                >
                    <span class="font-medium">{{ row[column.key] }}</span>
                </slot>
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div
                    v-for="column in columns.filter(
                        (c) => !c.primary && !c.hideOnMobile,
                    )"
                    :key="column.key"
                    class="min-w-0"
                >
                    <dt class="text-xs text-muted-foreground">
                        {{ column.label }}
                    </dt>
                    <dd class="mt-0.5 break-words">
                        <slot
                            :name="`cell-${column.key}`"
                            :row="row"
                            :value="row[column.key]"
                        >
                            {{ row[column.key] ?? '-' }}
                        </slot>
                    </dd>
                </div>
            </dl>
            <div
                v-if="$slots.actions"
                class="mt-3 flex flex-wrap items-center justify-end gap-1 border-t pt-3"
            >
                <slot name="actions" :row="row" />
            </div>
        </li>
    </ul>
</template>
