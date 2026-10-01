<script setup lang="ts">
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import {
    AlarmClock,
    ArrowLeftRight,
    Building2,
    CalendarCheck,
    CalendarDays,
    CalendarX,
    Coins,
    HandCoins,
    History,
    Hourglass,
    Landmark,
    Network,
    Plane,
    ReceiptText,
    Timer,
    TrendingUp,
    UserCheck,
    UserMinus,
    UserRound,
    Users,
    Wallet,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import ChartCard from '@/components/charts/ChartCard.vue';
import LineChart from '@/components/charts/LineChart.vue';
import AttendanceOverviewCard from '@/components/dashboard/AttendanceOverviewCard.vue';
import BorrowOverviewCard from '@/components/dashboard/BorrowOverviewCard.vue';
import DashboardFiltersForm from '@/components/dashboard/DashboardFilters.vue';
import PeopleEventList from '@/components/dashboard/PeopleEventList.vue';
import RankingCard from '@/components/dashboard/RankingCard.vue';
import WidgetSkeleton from '@/components/dashboard/WidgetSkeleton.vue';
import WorkingHoursCard from '@/components/dashboard/WorkingHoursCard.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionCard from '@/components/SectionCard.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { useFormat } from '@/composables/useFormat';
import { statusTone } from '@/lib/status';
import { dashboard } from '@/routes';
import { show as payrollShow } from '@/routes/payroll';
import type {
    ActivityEntry,
    DashboardAttendance,
    DashboardBorrow,
    DashboardCalendar,
    DashboardFilters,
    DashboardKpi,
    DashboardOptions,
    DashboardPayroll,
    DashboardPeople,
    HoursTrendPoint,
    Ranking,
} from '@/types';

const props = defineProps<{
    filters: DashboardFilters;
    today: string;
    options: DashboardOptions;
    kpis: DashboardKpi[];
    // Deferred: these arrive right after the first paint.
    attendance?: DashboardAttendance;
    hoursTrend?: HoursTrendPoint[];
    payroll?: DashboardPayroll;
    borrow?: DashboardBorrow;
    people?: DashboardPeople;
    calendar?: DashboardCalendar;
    activity?: ActivityEntry[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const { money, number, minutes, date, dateTime } = useFormat();

const refreshing = ref(false);

/**
 * Re-read every widget against the new filters. The current figures stay on
 * screen, dimmed, until the new ones arrive, so nothing jumps or flashes.
 */
function applyFilters(filters: DashboardFilters): void {
    const query: Record<string, string | number> = { preset: filters.preset };

    if (filters.preset === 'custom') {
        query.from = filters.from;
        query.to = filters.to;
    }

    if (filters.department_id) {
        query.department_id = filters.department_id;
    }

    if (filters.employee_id) {
        query.employee_id = filters.employee_id;
    }

    if (filters.employee_status) {
        query.employee_status = filters.employee_status;
    }

    router.get(dashboard.url(), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: [
            'filters',
            'kpis',
            'attendance',
            'hoursTrend',
            'payroll',
            'borrow',
            'people',
            'calendar',
            'activity',
        ],
        onStart: () => (refreshing.value = true),
        onFinish: () => (refreshing.value = false),
    });
}

const periodLabel = computed(
    () => `${date(props.filters.from)} to ${date(props.filters.to)}`,
);

const kpiFormat =
    (kpi: DashboardKpi) =>
    (value: number): string =>
        kpi.format === 'money'
            ? money(value, { whole: true })
            : number(Math.round(value), 0);

/** Every figure has its own icon, so the grid can be scanned without reading. */
const KPI_ICONS: Record<string, Component> = {
    total_employees: Users,
    active_employees: UserCheck,
    past_employees: UserMinus,
    present_today: CalendarCheck,
    absent_today: CalendarX,
    on_leave_today: Plane,
    late_today: AlarmClock,
    current_payroll: Wallet,
    total_overtime: Timer,
    total_borrowed: HandCoins,
    borrow_outstanding: Landmark,
    total_deductions: ReceiptText,
};

/** Which way a change is good news. More payroll is neither good nor bad. */
const GOOD_DIRECTION: Record<string, 'up' | 'down'> = {
    total_overtime: 'down',
};

type KpiGroup = {
    key: string;
    title: string;
    description: string;
    grid: string;
    /** Figures in display order, with any column span they need. */
    items: { key: string; span?: string }[];
};

const todayLabel = computed(() => {
    const weekday = new Date(`${props.today}T00:00:00`).toLocaleDateString(
        'en',
        { weekday: 'long' },
    );

    return `${weekday}, ${date(props.today)}`;
});

const kpiGroups = computed<KpiGroup[]>(() => [
    {
        key: 'today',
        title: 'Today',
        description: todayLabel.value,
        grid: 'grid-cols-2 lg:grid-cols-4',
        items: [
            { key: 'present_today' },
            { key: 'absent_today' },
            { key: 'on_leave_today' },
            { key: 'late_today' },
        ],
    },
    {
        key: 'people',
        title: 'People',
        description: 'Headcount right now',
        grid: 'grid-cols-2 sm:grid-cols-3',
        items: [
            { key: 'active_employees' },
            { key: 'total_employees' },
            { key: 'past_employees', span: 'col-span-2 sm:col-span-1' },
        ],
    },
    {
        key: 'money',
        title: 'Payroll and borrow',
        description: 'Latest payroll, the selected period and borrow to date',
        grid: 'sm:grid-cols-2 xl:grid-cols-6',
        items: [
            { key: 'current_payroll', span: 'xl:col-span-2' },
            { key: 'total_overtime', span: 'xl:col-span-2' },
            { key: 'total_deductions', span: 'xl:col-span-2' },
            { key: 'total_borrowed', span: 'xl:col-span-3' },
            { key: 'borrow_outstanding', span: 'sm:col-span-2 xl:col-span-3' },
        ],
    },
]);

const kpiByKey = computed(
    () => new Map(props.kpis.map((kpi) => [kpi.key, kpi])),
);

const groupItems = (group: KpiGroup) =>
    group.items
        .map((item) => ({ ...item, kpi: kpiByKey.value.get(item.key) }))
        .filter(
            (item): item is { key: string; span?: string; kpi: DashboardKpi } =>
                item.kpi !== undefined,
        );

const borrowRanking = computed<Ranking | null>(() =>
    props.borrow
        ? {
              key: 'highest_borrow',
              title: 'Highest Borrow Outstanding',
              metric: "Total still owed across all of an employee's active borrows",
              format: 'money',
              rows: props.borrow.highest_outstanding,
          }
        : null,
);

const hasPayrollTrend = computed(
    () => props.payroll?.trend.some((point) => point.net > 0) ?? false,
);
const hasOvertimeCost = computed(
    () => props.payroll?.trend.some((point) => point.overtime > 0) ?? false,
);
const hasOvertimeHours = computed(
    () =>
        props.hoursTrend?.some((point) => point.overtime_minutes > 0) ?? false,
);
const hasShortHours = computed(
    () => props.hoursTrend?.some((point) => point.short_minutes > 0) ?? false,
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Dashboard"
            :description="`People, attendance, payroll and borrow at a glance. Showing ${periodLabel}.`"
        />

        <DashboardFiltersForm
            :filters="filters"
            :options="options"
            @change="applyFilters"
        />

        <div
            class="flex flex-col gap-5 transition-opacity"
            :class="refreshing ? 'opacity-60' : ''"
            :aria-busy="refreshing"
        >
            <!-- Key figures, grouped by what they answer -->
            <div class="grid gap-5 2xl:grid-cols-[4fr_3fr]">
                <section
                    v-for="group in kpiGroups"
                    :key="group.key"
                    :aria-labelledby="`kpi-${group.key}`"
                    class="flex flex-col"
                    :class="group.key === 'money' ? '2xl:col-span-2' : ''"
                >
                    <div class="mb-2.5 flex flex-wrap items-baseline gap-x-2">
                        <h2
                            :id="`kpi-${group.key}`"
                            class="text-sm font-semibold"
                        >
                            {{ group.title }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{ group.description }}
                        </p>
                    </div>
                    <div class="grid flex-1 gap-3" :class="group.grid">
                        <StatCard
                            v-for="{ key, span, kpi } in groupItems(group)"
                            :key="key"
                            :class="span"
                            :label="kpi.label"
                            :value="kpi.value"
                            :format="kpiFormat(kpi)"
                            :hint="kpi.hint"
                            :tone="kpi.tone"
                            :icon="KPI_ICONS[kpi.key]"
                            :delta="kpi.delta"
                            :good-direction="GOOD_DIRECTION[kpi.key] ?? null"
                            :trend="kpi.trend"
                            :progress="kpi.progress"
                            :href="kpi.href"
                        />
                    </div>
                </section>
            </div>

            <!-- Attendance and payroll trend -->
            <div class="grid gap-4 lg:grid-cols-2">
                <Deferred data="attendance">
                    <template #fallback>
                        <WidgetSkeleton :count="1" columns="" />
                    </template>
                    <AttendanceOverviewCard
                        v-if="attendance"
                        :attendance="attendance"
                    />
                </Deferred>

                <Deferred data="payroll">
                    <template #fallback>
                        <WidgetSkeleton :count="1" columns="" />
                    </template>
                    <ChartCard
                        v-if="payroll"
                        :icon="TrendingUp"
                        title="Salary expense trend"
                        description="Net salary paid per payroll month, without borrow given"
                        :empty="!hasPayrollTrend"
                        empty-text="No payroll has been calculated yet"
                        :table="{
                            columns: ['Month', 'Gross salary', 'Net salary'],
                            rows: payroll.trend.map((point) => [
                                point.label,
                                money(point.gross),
                                money(point.net),
                            ]),
                        }"
                    >
                        <LineChart
                            format="money"
                            label="Net salary expense per month"
                            :labels="payroll.trend.map((point) => point.label)"
                            :series="[
                                {
                                    label: 'Net salary',
                                    data: payroll.trend.map(
                                        (point) => point.net,
                                    ),
                                },
                            ]"
                        />
                    </ChartCard>
                </Deferred>
            </div>

            <!-- Working hours and borrow -->
            <div class="grid gap-4 lg:grid-cols-2">
                <Deferred data="attendance">
                    <template #fallback>
                        <WidgetSkeleton :count="1" columns="" />
                    </template>
                    <WorkingHoursCard
                        v-if="attendance"
                        :attendance="attendance"
                    />
                </Deferred>

                <Deferred data="borrow">
                    <template #fallback>
                        <WidgetSkeleton :count="1" columns="" />
                    </template>
                    <BorrowOverviewCard v-if="borrow" :borrow="borrow" />
                </Deferred>
            </div>

            <!-- Current payroll: department cost and what was deducted -->
            <Deferred data="payroll">
                <template #fallback>
                    <WidgetSkeleton />
                </template>
                <div v-if="payroll" class="grid gap-4 lg:grid-cols-2">
                    <ChartCard
                        :icon="Building2"
                        title="Department payroll"
                        :description="
                            payroll.current
                                ? `Net salary by department in ${payroll.current.label}`
                                : 'Net salary by department'
                        "
                        :empty="payroll.by_department.length === 0"
                        empty-text="No payroll has been calculated yet"
                        :table="{
                            columns: ['Department', 'Employees', 'Net salary'],
                            rows: payroll.by_department.map((row) => [
                                row.label,
                                row.employees,
                                money(row.value),
                            ]),
                        }"
                    >
                        <BarChart
                            horizontal
                            format="money"
                            label="Net salary by department"
                            :labels="
                                payroll.by_department.map((row) => row.label)
                            "
                            :series="[
                                {
                                    label: 'Net salary',
                                    data: payroll.by_department.map(
                                        (row) => row.value,
                                    ),
                                },
                            ]"
                        />
                    </ChartCard>

                    <SectionCard
                        :icon="ReceiptText"
                        title="Deduction summary"
                        :description="
                            payroll.current
                                ? `Why pay was reduced in ${payroll.current.label}`
                                : 'Why pay was reduced'
                        "
                    >
                        <template v-if="payroll.current" #actions>
                            <StatusBadge
                                :tone="
                                    statusTone(
                                        'payroll',
                                        payroll.current.status,
                                    )
                                "
                            >
                                {{ payroll.current.status_label }}
                            </StatusBadge>
                        </template>

                        <p
                            v-if="!payroll.current"
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            No payroll has been calculated yet.
                        </p>
                        <template v-else>
                            <table class="tabular w-full text-sm">
                                <tbody>
                                    <tr
                                        v-for="row in payroll.deductions"
                                        :key="row.key"
                                        class="border-b"
                                    >
                                        <th
                                            scope="row"
                                            class="py-2 text-left font-normal"
                                        >
                                            {{ row.label }}
                                        </th>
                                        <td class="py-2 text-right">
                                            {{ money(row.value) }}
                                        </td>
                                    </tr>
                                    <tr class="border-b">
                                        <th
                                            scope="row"
                                            class="py-2 text-left font-normal"
                                        >
                                            New borrow / advance given
                                            <span
                                                class="block text-xs text-muted-foreground"
                                            >
                                                An advance, not salary
                                            </span>
                                        </th>
                                        <td class="py-2 text-right">
                                            {{
                                                money(
                                                    payroll.current
                                                        .borrow_given,
                                                    { signed: true },
                                                )
                                            }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th
                                            scope="row"
                                            class="py-2 text-left font-semibold"
                                        >
                                            Net payable
                                        </th>
                                        <td
                                            class="py-2 text-right font-semibold"
                                        >
                                            {{
                                                money(
                                                    payroll.current.net_payable,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <Link
                                :href="payrollShow(payroll.current.id)"
                                class="mt-3 inline-block text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Open {{ payroll.current.label }} payroll
                            </Link>
                        </template>
                    </SectionCard>
                </div>
            </Deferred>

            <!-- People -->
            <Deferred data="people">
                <template #fallback>
                    <WidgetSkeleton />
                </template>
                <div v-if="people" class="grid gap-4 lg:grid-cols-2">
                    <ChartCard
                        :icon="Network"
                        title="Department distribution"
                        description="Current employees in each department"
                        :empty="people.by_department.length === 0"
                        empty-text="No employees yet"
                        :table="{
                            columns: ['Department', 'Employees'],
                            rows: people.by_department.map((row) => [
                                row.label,
                                row.value,
                            ]),
                        }"
                    >
                        <BarChart
                            horizontal
                            label="Employees by department"
                            :labels="
                                people.by_department.map((row) => row.label)
                            "
                            :series="[
                                {
                                    label: 'Employees',
                                    data: people.by_department.map(
                                        (row) => row.value,
                                    ),
                                },
                            ]"
                        />
                    </ChartCard>

                    <SectionCard
                        :icon="UserRound"
                        title="Employee status"
                        description="Headcount, movement this month and gender split"
                    >
                        <dl class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                            <div class="rounded-lg bg-muted/50 px-3 py-2.5">
                                <dt class="text-xs text-muted-foreground">
                                    Active
                                </dt>
                                <dd class="tabular text-xl font-semibold">
                                    {{ people.headcount.active }}
                                </dd>
                            </div>
                            <div class="rounded-lg bg-muted/50 px-3 py-2.5">
                                <dt class="text-xs text-muted-foreground">
                                    Past
                                </dt>
                                <dd class="tabular text-xl font-semibold">
                                    {{ people.headcount.past }}
                                </dd>
                            </div>
                            <div class="rounded-lg bg-muted/50 px-3 py-2.5">
                                <dt class="text-xs text-muted-foreground">
                                    New this month
                                </dt>
                                <dd class="tabular text-xl font-semibold">
                                    {{ people.headcount.new_this_month }}
                                </dd>
                            </div>
                            <div class="rounded-lg bg-muted/50 px-3 py-2.5">
                                <dt class="text-xs text-muted-foreground">
                                    Exited this month
                                </dt>
                                <dd class="tabular text-xl font-semibold">
                                    {{ people.headcount.exited_this_month }}
                                </dd>
                            </div>
                            <div class="rounded-lg bg-muted/50 px-3 py-2.5">
                                <dt class="text-xs text-muted-foreground">
                                    On probation
                                </dt>
                                <dd class="tabular text-xl font-semibold">
                                    {{ people.headcount.on_probation }}
                                </dd>
                            </div>
                            <div class="rounded-lg bg-muted/50 px-3 py-2.5">
                                <dt class="text-xs text-muted-foreground">
                                    On notice
                                </dt>
                                <dd class="tabular text-xl font-semibold">
                                    {{ people.headcount.on_notice }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-5 border-t pt-4">
                            <h3
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Gender distribution of current employees
                            </h3>
                            <ul class="mt-2 grid gap-2">
                                <li
                                    v-for="row in people.by_gender"
                                    :key="row.label"
                                    class="grid grid-cols-[9rem_1fr_2.5rem] items-center gap-3 text-sm"
                                >
                                    <span class="truncate">{{
                                        row.label
                                    }}</span>
                                    <span
                                        class="h-1.5 rounded-full bg-muted"
                                        aria-hidden="true"
                                    >
                                        <span
                                            class="block h-full rounded-full bg-chart-neutral"
                                            :style="{
                                                width: `${people.headcount.active > 0 ? (row.value / people.headcount.active) * 100 : 0}%`,
                                            }"
                                        />
                                    </span>
                                    <span
                                        class="tabular text-right font-medium"
                                        >{{ row.value }}</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </SectionCard>
                </div>
            </Deferred>

            <!-- Hours and overtime trends: one measure per chart, never two scales on one plot -->
            <div class="grid gap-4 lg:grid-cols-3">
                <Deferred data="hoursTrend">
                    <template #fallback>
                        <WidgetSkeleton
                            :count="2"
                            columns="lg:col-span-2 lg:grid-cols-2"
                        />
                    </template>
                    <template v-if="hoursTrend">
                        <ChartCard
                            :icon="Timer"
                            title="Overtime trend"
                            description="Overtime hours recorded in attendance per month"
                            :empty="!hasOvertimeHours"
                            empty-text="No overtime hours in the last 12 months"
                            :table="{
                                columns: ['Month', 'Overtime'],
                                rows: hoursTrend.map((point) => [
                                    point.label,
                                    minutes(point.overtime_minutes),
                                ]),
                            }"
                        >
                            <LineChart
                                format="minutes"
                                label="Overtime hours per month"
                                :height="200"
                                :labels="hoursTrend.map((point) => point.label)"
                                :series="[
                                    {
                                        label: 'Overtime hours',
                                        data: hoursTrend.map(
                                            (point) => point.overtime_minutes,
                                        ),
                                    },
                                ]"
                            />
                        </ChartCard>

                        <ChartCard
                            :icon="Hourglass"
                            title="Short hours trend"
                            description="Hours worked below the required hours per month"
                            :empty="!hasShortHours"
                            empty-text="No short hours in the last 12 months"
                            :table="{
                                columns: ['Month', 'Short hours'],
                                rows: hoursTrend.map((point) => [
                                    point.label,
                                    minutes(point.short_minutes),
                                ]),
                            }"
                        >
                            <LineChart
                                format="minutes"
                                label="Short hours per month"
                                :height="200"
                                :labels="hoursTrend.map((point) => point.label)"
                                :series="[
                                    {
                                        label: 'Short hours',
                                        data: hoursTrend.map(
                                            (point) => point.short_minutes,
                                        ),
                                    },
                                ]"
                            />
                        </ChartCard>
                    </template>
                </Deferred>

                <Deferred data="payroll">
                    <template #fallback>
                        <WidgetSkeleton :count="1" columns="" />
                    </template>
                    <ChartCard
                        v-if="payroll"
                        :icon="Coins"
                        title="Overtime cost"
                        description="Overtime paid through payroll per month"
                        :empty="!hasOvertimeCost"
                        empty-text="No overtime has been paid yet"
                        :table="{
                            columns: ['Month', 'Overtime cost'],
                            rows: payroll.trend.map((point) => [
                                point.label,
                                money(point.overtime),
                            ]),
                        }"
                    >
                        <BarChart
                            format="money"
                            label="Overtime cost per month"
                            :height="200"
                            :labels="payroll.trend.map((point) => point.label)"
                            :series="[
                                {
                                    label: 'Overtime cost',
                                    data: payroll.trend.map(
                                        (point) => point.overtime,
                                    ),
                                },
                            ]"
                        />
                    </ChartCard>
                </Deferred>
            </div>

            <!-- Rankings: each states exactly what it measures -->
            <Deferred :data="['attendance', 'borrow']">
                <template #fallback>
                    <WidgetSkeleton
                        :count="3"
                        columns="md:grid-cols-2 xl:grid-cols-3"
                    />
                </template>
                <section
                    v-if="attendance"
                    aria-label="Rankings"
                    class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <RankingCard
                        v-for="ranking in attendance.rankings"
                        :key="ranking.key"
                        :ranking="ranking"
                    />
                    <RankingCard
                        v-if="borrowRanking"
                        :ranking="borrowRanking"
                    />
                </section>
            </Deferred>

            <!-- Calendar, movement and activity -->
            <Deferred :data="['calendar', 'people', 'activity']">
                <template #fallback>
                    <WidgetSkeleton :count="3" columns="lg:grid-cols-3" />
                </template>
                <div class="grid gap-4 lg:grid-cols-3">
                    <SectionCard
                        v-if="calendar"
                        :icon="CalendarDays"
                        title="Holidays and working days"
                        :description="calendar.month_label"
                    >
                        <dl class="grid grid-cols-2 gap-2.5">
                            <div class="rounded-lg bg-muted/50 px-3 py-2.5">
                                <dt class="text-xs text-muted-foreground">
                                    Working days this month
                                </dt>
                                <dd class="tabular text-xl font-semibold">
                                    {{ calendar.working_days }}
                                </dd>
                            </div>
                            <div class="rounded-lg bg-muted/50 px-3 py-2.5">
                                <dt class="text-xs text-muted-foreground">
                                    Remaining, including today
                                </dt>
                                <dd class="tabular text-xl font-semibold">
                                    {{ calendar.remaining_working_days }}
                                </dd>
                            </div>
                        </dl>

                        <h3
                            class="mt-5 text-xs font-medium text-muted-foreground"
                        >
                            Upcoming holidays
                        </h3>
                        <p
                            v-if="calendar.upcoming_holidays.length === 0"
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            No holidays in the next four months.
                        </p>
                        <ul v-else class="mt-2 grid gap-2">
                            <li
                                v-for="holiday in calendar.upcoming_holidays"
                                :key="holiday.date"
                                class="flex items-baseline justify-between gap-3 text-sm"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{
                                        holiday.name
                                    }}</span>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{ holiday.weekday }},
                                        {{ date(holiday.date) }}
                                    </span>
                                </span>
                                <span
                                    class="tabular shrink-0 text-xs text-muted-foreground"
                                >
                                    {{
                                        holiday.in_days === 0
                                            ? 'Today'
                                            : `in ${holiday.in_days} day${holiday.in_days === 1 ? '' : 's'}`
                                    }}
                                </span>
                            </li>
                        </ul>

                        <h3
                            class="mt-5 text-xs font-medium text-muted-foreground"
                        >
                            Upcoming weekly offs
                        </h3>
                        <p class="mt-2 text-sm">
                            <template
                                v-if="calendar.upcoming_weekly_offs.length"
                            >
                                {{
                                    calendar.upcoming_weekly_offs
                                        .map(
                                            (off) =>
                                                `${off.weekday.slice(0, 3)} ${date(off.date)}`,
                                        )
                                        .join(', ')
                                }}
                            </template>
                            <span v-else class="text-muted-foreground">
                                No weekly off is configured.
                            </span>
                        </p>
                    </SectionCard>

                    <SectionCard
                        v-if="people"
                        :icon="ArrowLeftRight"
                        title="Joining and exits"
                        description="Who is new, completing probation or has left"
                    >
                        <div class="grid gap-5">
                            <PeopleEventList
                                title="New employees this month"
                                :people="people.new_joiners"
                                empty-text="Nobody has joined this month."
                            />
                            <PeopleEventList
                                title="Probation ending in the next 45 days"
                                :people="people.upcoming_probation"
                                empty-text="No probation periods are ending soon."
                            />
                            <PeopleEventList
                                title="Recent exits"
                                :people="people.recent_exits"
                                empty-text="Nobody has left the company."
                            />
                        </div>
                    </SectionCard>

                    <SectionCard
                        v-if="activity"
                        :icon="History"
                        title="Recent employee activity"
                        description="The latest audited changes to employee records"
                    >
                        <p
                            v-if="activity.length === 0"
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            Changes to employees, attendance, salary and borrow
                            will appear here.
                        </p>
                        <ol v-else class="grid gap-3">
                            <li
                                v-for="entry in activity"
                                :key="entry.id"
                                class="border-l-2 pl-3 text-sm"
                            >
                                <p>{{ entry.description ?? entry.action }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ entry.user ?? 'System' }},
                                    {{ dateTime(entry.created_at) }}
                                </p>
                            </li>
                        </ol>
                    </SectionCard>
                </div>
            </Deferred>
        </div>
    </div>
</template>
