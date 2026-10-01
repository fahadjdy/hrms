<script setup lang="ts">
import { Table2, ChartNoAxesColumn } from '@lucide/vue';
import type { Component } from 'vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import SectionCard from '@/components/SectionCard.vue';
import { Button } from '@/components/ui/button';

export type ChartLegendItem = {
    label: string;
    /** A CSS color, e.g. `var(--chart-1)`. */
    color: string;
    /** `line` for line charts, `box` for bars and areas. */
    shape?: 'line' | 'box';
};

export type ChartTable = {
    columns: string[];
    rows: (string | number)[][];
};

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
    /** Shown for two or more series. A single series is named by the title. */
    legend?: ChartLegendItem[];
    /** The same data as a table, for anyone who cannot or would rather not read the chart. */
    table?: ChartTable;
    /** Set when there is nothing to plot. */
    empty?: boolean;
    emptyText?: string;
}>();

const showTable = ref(false);
</script>

<template>
    <SectionCard :title="title" :description="description" :icon="icon">
        <template v-if="table && !empty" #actions>
            <Button
                variant="ghost"
                size="sm"
                type="button"
                class="text-muted-foreground"
                :aria-pressed="showTable"
                @click="showTable = !showTable"
            >
                <component :is="showTable ? ChartNoAxesColumn : Table2" />
                {{ showTable ? 'Show chart' : 'Show table' }}
            </Button>
        </template>

        <EmptyState
            v-if="empty"
            :title="emptyText ?? 'No data for this period'"
            description="Change the date range or filters above to see results here."
        />

        <template v-else>
            <ul
                v-if="legend && legend.length > 1 && !showTable"
                class="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
            >
                <li
                    v-for="item in legend"
                    :key="item.label"
                    class="flex items-center gap-1.5"
                >
                    <span
                        :class="
                            item.shape === 'line'
                                ? 'h-0.5 w-3 rounded-full'
                                : 'size-2.5 rounded-[2px]'
                        "
                        :style="{ backgroundColor: item.color }"
                        aria-hidden="true"
                    />
                    {{ item.label }}
                </li>
            </ul>

            <div v-if="showTable && table" class="overflow-x-auto">
                <table class="tabular w-full text-sm">
                    <thead>
                        <tr class="border-b text-xs text-muted-foreground">
                            <th
                                v-for="(column, index) in table.columns"
                                :key="column"
                                scope="col"
                                class="py-2 font-medium"
                                :class="
                                    index === 0 ? 'text-left' : 'text-right'
                                "
                            >
                                {{ column }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, rowIndex) in table.rows"
                            :key="rowIndex"
                            class="border-b last:border-0"
                        >
                            <td
                                v-for="(cell, index) in row"
                                :key="index"
                                class="py-2"
                                :class="
                                    index === 0 ? 'text-left' : 'text-right'
                                "
                            >
                                {{ cell }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <slot v-else />
        </template>
    </SectionCard>
</template>
