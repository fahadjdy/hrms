<script setup lang="ts">
import { computed } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import ChartCard from '@/components/charts/ChartCard.vue';
import { seriesColor } from '@/composables/useChartTheme';
import { useFormat } from '@/composables/useFormat';
import type { DashboardBorrow } from '@/types';

const props = defineProps<{
    borrow: DashboardBorrow;
}>();

const { money, number } = useFormat();

const tiles = computed(() => [
    {
        label: 'Total borrowed',
        value: money(props.borrow.total_borrowed, { whole: true }),
    },
    {
        label: 'Total recovered',
        value: money(props.borrow.total_recovered, { whole: true }),
    },
    {
        label: 'Outstanding',
        value: money(props.borrow.outstanding, { whole: true }),
    },
    {
        label: 'Recovered this month',
        value: money(props.borrow.recovered_this_month, { whole: true }),
    },
    {
        label: 'Employees with borrow',
        value: number(props.borrow.employees_with_borrow, 0),
    },
]);

const hasTrend = computed(() =>
    props.borrow.trend.some((point) => point.given > 0 || point.recovered > 0),
);
</script>

<template>
    <ChartCard
        title="Borrow overview"
        description="Borrow given against borrow recovered, month by month"
        :legend="[
            { label: 'Borrow given', color: seriesColor(0) },
            { label: 'Borrow recovered', color: seriesColor(1) },
        ]"
        :table="{
            columns: ['Month', 'Given', 'Recovered'],
            rows: borrow.trend.map((point) => [
                point.label,
                money(point.given),
                money(point.recovered),
            ]),
        }"
    >
        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
            <div v-for="tile in tiles" :key="tile.label">
                <dt class="text-xs text-muted-foreground">{{ tile.label }}</dt>
                <dd class="text-base font-semibold">{{ tile.value }}</dd>
            </div>
        </dl>

        <div class="mt-5">
            <BarChart
                v-if="hasTrend"
                format="money"
                label="Borrow given and recovered per month"
                :labels="borrow.trend.map((point) => point.label)"
                :series="[
                    {
                        label: 'Borrow given',
                        data: borrow.trend.map((point) => point.given),
                    },
                    {
                        label: 'Borrow recovered',
                        data: borrow.trend.map((point) => point.recovered),
                    },
                ]"
                :height="220"
            />
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                No borrow has been given or recovered in the last 12 months.
            </p>
        </div>
    </ChartCard>
</template>
