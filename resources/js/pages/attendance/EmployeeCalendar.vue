<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useLocalStorage, useMediaQuery } from '@vueuse/core';
import { CalendarDays, List } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CalendarGrid from '@/components/attendance-calendar/CalendarGrid.vue';
import DayList from '@/components/attendance-calendar/DayList.vue';
import DayPanel from '@/components/attendance-calendar/DayPanel.vue';
import type { AttendanceLogEntry } from '@/components/attendance-calendar/DayPanel.vue';
import MonthSummary from '@/components/attendance-calendar/MonthSummary.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import MonthNavigator from '@/components/MonthNavigator.vue';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { attendanceClass } from '@/lib/status';
import { calendar } from '@/routes/attendance';
import { show as employeeShow } from '@/routes/employees';
import { show } from '@/routes/employees/attendance';
import type {
    AttendanceDay,
    AttendanceStatusOption,
    AttendanceSummary,
    EmployeeBrief,
} from '@/types';

const props = defineProps<{
    employee: EmployeeBrief & {
        joining_date: string;
        last_working_date: string | null;
    };
    month: string;
    monthLabel: string;
    previousMonth: string;
    nextMonth: string;
    today: string;
    days: AttendanceDay[];
    summary: AttendanceSummary;
    logs: Record<string, AttendanceLogEntry[]>;
    shift: {
        name: string;
        start_time: string;
        end_time: string;
        required_minutes: number;
        source: string | null;
    } | null;
    statuses: AttendanceStatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Attendance Calendar', href: calendar() }],
    },
});

const { minutes, time } = useFormat();
const { can } = usePermissions();

const month = ref(props.month);

watch(
    () => props.month,
    (value) => (month.value = value),
);

watch(month, (value) => {
    if (value && value !== props.month) {
        router.get(
            show.url(props.employee.id),
            { month: value },
            { preserveState: true, preserveScroll: true },
        );
    }
});

// A seven-column calendar is too narrow to read on a phone, so the list is
// the default there. The admin's own choice is remembered.
const isWide = useMediaQuery('(min-width: 640px)');
const preference = useLocalStorage<'calendar' | 'list' | null>(
    'hrms.attendance.view',
    null,
);
const view = computed(
    () => preference.value ?? (isWide.value ? 'calendar' : 'list'),
);

const panelOpen = ref(false);
const selectedDate = ref<string | null>(null);

// Read the selected day from the props, so the panel shows the saved values after an edit.
const selectedDay = computed(
    () => props.days.find((day) => day.date === selectedDate.value) ?? null,
);

function select(day: AttendanceDay): void {
    selectedDate.value = day.date;
    panelOpen.value = true;
}

const shiftSource: Record<string, string> = {
    employee: 'set for this employee',
    gender: 'gender-based default',
    company: 'company default',
};
</script>

<template>
    <Head :title="`Attendance: ${employee.name}`" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <header
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="min-w-0">
                <h1 class="sr-only">
                    Attendance calendar of {{ employee.name }}
                </h1>
                <EmployeeCell
                    :name="employee.name"
                    :code="employee.code"
                    :subtitle="
                        [employee.designation, employee.department]
                            .filter(Boolean)
                            .join(', ')
                    "
                    :photo-url="employee.photo_url"
                    :href="
                        can('employees.view')
                            ? employeeShow(employee.id)
                            : undefined
                    "
                />
                <p v-if="shift" class="mt-2 text-sm text-muted-foreground">
                    {{ shift.name }}: {{ time(shift.start_time) }} to
                    {{ time(shift.end_time) }},
                    {{ minutes(shift.required_minutes) }} required
                    <template v-if="shift.source"
                        >({{ shiftSource[shift.source] }})</template
                    >
                </p>
                <p v-else class="mt-2 text-sm text-warning">
                    No work shift applies to this employee. Set a company
                    default shift in the attendance settings.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <MonthNavigator v-model="month" />
                <div
                    class="inline-flex rounded-md border bg-card p-0.5"
                    role="group"
                    aria-label="View"
                >
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded px-2.5 py-1.5 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :class="
                            view === 'calendar'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-pressed="view === 'calendar'"
                        @click="preference = 'calendar'"
                    >
                        <CalendarDays class="size-4" />
                        Calendar
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded px-2.5 py-1.5 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :class="
                            view === 'list'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-pressed="view === 'list'"
                        @click="preference = 'list'"
                    >
                        <List class="size-4" />
                        List
                    </button>
                </div>
            </div>
        </header>

        <h2 class="text-lg font-semibold">{{ monthLabel }}</h2>

        <CalendarGrid
            v-if="view === 'calendar'"
            :days="days"
            :today="today"
            @select="select"
        />
        <DayList v-else :days="days" :today="today" @select="select" />

        <ul
            class="flex flex-wrap gap-x-3 gap-y-1.5 text-xs text-muted-foreground"
            aria-label="Status codes"
        >
            <li
                v-for="status in statuses"
                :key="status.value"
                class="flex items-center gap-1.5"
            >
                <span
                    class="rounded px-1.5 py-0.5 leading-none font-semibold"
                    :class="attendanceClass(status.value)"
                >
                    {{ status.code }}
                </span>
                {{ status.label }}
            </li>
            <li class="flex items-center gap-1.5">
                <span
                    class="rounded border border-dashed border-warning px-1 py-0.5 leading-none text-warning"
                    >?</span
                >
                Not marked
            </li>
        </ul>

        <MonthSummary :summary="summary" :month-label="monthLabel" />

        <p class="text-sm text-muted-foreground">
            Select any day to see its details{{
                can('attendance.manage') ? ' or correct it' : ''
            }}.
            <Link
                :href="calendar()"
                class="font-medium text-primary underline-offset-4 hover:underline"
            >
                Choose another employee
            </Link>
        </p>
    </div>

    <DayPanel
        v-model:open="panelOpen"
        :employee-id="employee.id"
        :day="selectedDay"
        :statuses="statuses"
        :logs="selectedDate ? (logs[selectedDate] ?? []) : []"
        :today="today"
        :can-edit="can('attendance.manage')"
    />
</template>
