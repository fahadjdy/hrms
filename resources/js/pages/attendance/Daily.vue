<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    Info,
    PencilLine,
    Search,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AttendanceStatusChip from '@/components/attendance/AttendanceStatusChip.vue';
import BulkMarkBar from '@/components/attendance/BulkMarkBar.vue';
import type { BulkMark } from '@/components/attendance/BulkMarkBar.vue';
import GenerateAttendanceDialog from '@/components/attendance/GenerateAttendanceDialog.vue';
import MarkAttendanceDialog from '@/components/attendance/MarkAttendanceDialog.vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { toastFirstError } from '@/composables/useResourceForm';
import { index } from '@/routes/attendance';
import { store as bulkStore } from '@/routes/attendance/bulk';
import { show as calendarShow } from '@/routes/employees/attendance';
import type {
    AttendanceDay,
    AttendanceStatusOption,
    EmployeeBrief,
    IdName,
    Paginated,
    Tone,
} from '@/types';

type EmployeeRow = EmployeeBrief & { day: AttendanceDay };

type Kpis = {
    total: number;
    present: number;
    absent: number;
    on_leave: number;
    wfh: number;
    late: number;
    short_hours: number;
    overtime: number;
    unmarked: number;
};

const props = defineProps<{
    date: string;
    today: string;
    isFuture: boolean;
    holidayName: string | null;
    isWeeklyOff: boolean;
    mode: string;
    kpis: Kpis;
    employees: Paginated<EmployeeRow>;
    filters: {
        search: string | null;
        department_id: number | string | null;
        status: string | null;
    };
    departments: IdName[];
    statuses: AttendanceStatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Daily Attendance', href: index() }],
    },
});

const { date: formatDate, minutes, time } = useFormat();
const { can } = usePermissions();

// A future date can be viewed but not marked.
const canMark = computed(() => can('attendance.manage') && !props.isFuture);

const { filters } = useQueryFilters(
    () => index.url(),
    {
        date: props.date,
        search: props.filters.search ?? '',
        department_id: props.filters.department_id
            ? Number(props.filters.department_id)
            : null,
        status: props.filters.status ?? null,
    },
    { debounced: ['search'] },
);

/** Move the viewed date by whole days without any timezone drift. */
function shiftDate(days: number): void {
    const [year, month, day] = filters.date.split('-').map(Number);
    const next = new Date(year, month - 1, day + days);

    filters.date = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`;
}

const weekday = computed(() => {
    const [year, month, day] = props.date.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString('en-US', {
        weekday: 'long',
    });
});

const statusFilterOptions = computed(() => [
    ...props.statuses.map(({ value, label }) => ({ value, label })),
    { value: 'unmarked', label: 'Not marked' },
]);

const kpiCards = computed<
    {
        key: string;
        label: string;
        value: number;
        tone: Tone;
        hint: string;
        status?: string;
    }[]
>(() => [
    {
        key: 'total',
        label: 'Total employees',
        value: props.kpis.total,
        tone: 'neutral',
        hint: 'Employed on this date',
    },
    {
        key: 'present',
        label: 'Present',
        value: props.kpis.present,
        tone: 'positive',
        hint: 'Incl. late, WFH and half days',
    },
    {
        key: 'absent',
        label: 'Absent',
        value: props.kpis.absent,
        tone: 'negative',
        hint: 'Marked absent',
        status: 'absent',
    },
    {
        key: 'on_leave',
        label: 'On leave',
        value: props.kpis.on_leave,
        tone: 'info',
        hint: 'Paid and unpaid leave',
    },
    {
        key: 'wfh',
        label: 'WFH',
        value: props.kpis.wfh,
        tone: 'info',
        hint: 'Working from home',
        status: 'wfh',
    },
    {
        key: 'late',
        label: 'Late',
        value: props.kpis.late,
        tone: 'warning',
        hint: 'After the grace period',
        status: 'late',
    },
    {
        key: 'short_hours',
        label: 'Short hours',
        value: props.kpis.short_hours,
        tone: 'warning',
        hint: 'Worked less than required',
    },
    {
        key: 'overtime',
        label: 'Overtime',
        value: props.kpis.overtime,
        tone: 'neutral',
        hint: 'Worked more than required',
    },
    {
        key: 'unmarked',
        label: 'Not marked',
        value: props.kpis.unmarked,
        tone: props.kpis.unmarked > 0 ? 'warning' : 'neutral',
        hint: 'No attendance stored yet',
        status: 'unmarked',
    },
]);

const kpiHref = (status?: string): string | null =>
    status ? index.url({ query: { date: props.date, status } }) : null;

const columns = computed<DataTableColumn[]>(() => [
    ...(canMark.value
        ? [{ key: 'select', label: 'Select', class: 'w-px' }]
        : []),
    { key: 'name', label: 'Employee', primary: true },
    { key: 'department', label: 'Department', hideOnMobile: true },
    { key: 'status', label: 'Status' },
    { key: 'times', label: 'In / out' },
    { key: 'worked', label: 'Worked', align: 'right' },
    { key: 'short', label: 'Short', align: 'right' },
    { key: 'overtime', label: 'Overtime', align: 'right' },
]);

/* Selection for bulk marking */
const selected = ref<number[]>([]);
const bulkProcessing = ref(false);

const pageIds = computed(() => props.employees.data.map((row) => row.id));
const allSelected = computed(
    () =>
        pageIds.value.length > 0 &&
        pageIds.value.every((id) => selected.value.includes(id)),
);

// A new date, page or filter shows different people, so the selection starts over.
watch(
    () => [props.date, props.employees.current_page, pageIds.value.join(',')],
    () => {
        selected.value = [];
    },
);

function toggle(id: number, checked: boolean): void {
    selected.value = checked
        ? [...new Set([...selected.value, id])]
        : selected.value.filter((existing) => existing !== id);
}

function toggleAll(checked: boolean): void {
    selected.value = checked ? [...pageIds.value] : [];
}

function applyBulk(mark: BulkMark): void {
    bulkProcessing.value = true;

    router.post(
        bulkStore.url(),
        { date: props.date, employee_ids: selected.value, ...mark },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
            },
            onError: toastFirstError,
            onFinish: () => {
                bulkProcessing.value = false;
            },
        },
    );
}

/* Marking one employee */
const markOpen = ref(false);
const marking = ref<EmployeeRow | null>(null);

function startMark(row: EmployeeRow): void {
    marking.value = row;
    markOpen.value = true;
}

const generateOpen = ref(false);
</script>

<template>
    <Head title="Daily Attendance" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Daily Attendance"
            :description="`${weekday}, ${formatDate(date)}. Attendance mode: ${mode === 'automatic' ? 'automatic (working days are present unless marked otherwise)' : 'manual (every working day is marked by you)'}.`"
        >
            <Button
                v-if="can('attendance.manage')"
                variant="outline"
                @click="generateOpen = true"
            >
                <CalendarClock />
                Generate attendance
            </Button>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    type="button"
                    aria-label="Previous day"
                    @click="shiftDate(-1)"
                >
                    <ChevronLeft />
                </Button>
                <Input
                    v-model="filters.date"
                    type="date"
                    aria-label="Attendance date"
                    class="w-[10.5rem]"
                />
                <Button
                    variant="outline"
                    size="icon"
                    type="button"
                    aria-label="Next day"
                    @click="shiftDate(1)"
                >
                    <ChevronRight />
                </Button>
            </div>
            <Button
                v-if="date !== today"
                variant="ghost"
                type="button"
                @click="filters.date = today"
            >
                Go to today
            </Button>
        </div>

        <p
            v-if="holidayName || isWeeklyOff || isFuture"
            role="status"
            class="flex items-start gap-2 rounded-lg border bg-info-soft px-3 py-2 text-sm text-info"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <span>
                <template v-if="holidayName">
                    This date is a holiday: {{ holidayName }}. It never counts
                    as absence.
                </template>
                <template v-else-if="isWeeklyOff">
                    This date is a weekly off. It never counts as absence.
                </template>
                <template v-if="isFuture">
                    This date is in the future, so attendance cannot be marked
                    yet.
                </template>
            </span>
        </p>

        <section
            aria-label="Attendance summary for the day"
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 2xl:grid-cols-9"
        >
            <StatCard
                v-for="card in kpiCards"
                :key="card.key"
                :label="card.label"
                :value="String(card.value)"
                :hint="card.hint"
                :tone="card.tone"
                :href="kpiHref(card.status)"
            />
        </section>

        <div class="grid gap-2 sm:grid-cols-3">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    type="search"
                    placeholder="Name, ID or email"
                    aria-label="Search employees"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.department_id"
                :options="
                    departments.map((d) => ({ value: d.id, label: d.name }))
                "
                placeholder="All departments"
                aria-label="Department"
            />
            <NativeSelect
                v-model="filters.status"
                :options="statusFilterOptions"
                placeholder="All statuses"
                aria-label="Attendance status"
            />
        </div>

        <BulkMarkBar
            v-if="canMark && selected.length > 0"
            :count="selected.length"
            :statuses="statuses"
            :processing="bulkProcessing"
            @apply="applyBulk"
            @clear="selected = []"
        />

        <label
            v-if="canMark && employees.data.length > 0"
            class="flex w-fit items-center gap-2 text-sm text-muted-foreground"
        >
            <input
                type="checkbox"
                class="size-4 rounded border-input accent-primary"
                :checked="allSelected"
                @change="toggleAll(($event.target as HTMLInputElement).checked)"
            />
            Select everyone on this page
        </label>

        <DataTable
            :columns="columns"
            :rows="employees.data"
            empty-title="No employees for this date"
            empty-description="Nobody matches the filters, or nobody had joined by this date. Clear the filters or pick another date."
        >
            <template #cell-select="{ row }">
                <input
                    type="checkbox"
                    class="size-4 rounded border-input accent-primary"
                    :aria-label="`Select ${row.name}`"
                    :checked="selected.includes(row.id)"
                    @change="
                        toggle(
                            row.id,
                            ($event.target as HTMLInputElement).checked,
                        )
                    "
                />
            </template>
            <template #cell-name="{ row }">
                <EmployeeCell
                    :name="row.name"
                    :code="row.code"
                    :subtitle="row.designation"
                    :photo-url="row.photo_url"
                    :href="
                        calendarShow(row.id, {
                            query: { month: date.slice(0, 7) },
                        })
                    "
                />
            </template>
            <template #cell-department="{ row }">
                {{ row.department ?? '-' }}
            </template>
            <template #cell-status="{ row }">
                <AttendanceStatusChip :day="row.day" />
            </template>
            <template #cell-times="{ row }">
                <span
                    v-if="row.day.check_in || row.day.check_out"
                    class="tabular whitespace-nowrap"
                >
                    {{ time(row.day.check_in) }} to
                    {{ time(row.day.check_out) }}
                </span>
                <span v-else class="text-muted-foreground">-</span>
            </template>
            <template #cell-worked="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{
                        row.day.worked_minutes > 0
                            ? minutes(row.day.worked_minutes)
                            : '-'
                    }}
                </span>
            </template>
            <template #cell-short="{ row }">
                <span
                    class="tabular whitespace-nowrap"
                    :class="row.day.short_minutes > 0 ? 'text-warning' : ''"
                >
                    {{
                        row.day.short_minutes > 0
                            ? minutes(row.day.short_minutes)
                            : '-'
                    }}
                </span>
            </template>
            <template #cell-overtime="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{
                        row.day.overtime_minutes > 0
                            ? minutes(row.day.overtime_minutes)
                            : '-'
                    }}
                </span>
            </template>
            <template v-if="canMark" #actions="{ row }">
                <Button
                    v-if="row.day.state !== 'not_employed'"
                    variant="ghost"
                    size="sm"
                    :aria-label="`Mark attendance for ${row.name}`"
                    @click="startMark(row)"
                >
                    <PencilLine />
                    Mark
                </Button>
            </template>
        </DataTable>

        <DataPagination :page="employees" noun="employees" />
    </div>

    <MarkAttendanceDialog
        v-model:open="markOpen"
        :employee="marking"
        :date="date"
        :day="marking?.day ?? null"
        :statuses="statuses"
    />

    <GenerateAttendanceDialog
        v-model:open="generateOpen"
        :date="date"
        :today="today"
        :mode="mode"
    />
</template>
