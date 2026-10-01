<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Search } from '@lucide/vue';
import DataPagination from '@/components/DataPagination.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import EmptyState from '@/components/EmptyState.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { calendar } from '@/routes/attendance';
import { show } from '@/routes/employees/attendance';
import type { EmployeeBrief, IdName, Paginated } from '@/types';

const props = defineProps<{
    employees: Paginated<EmployeeBrief>;
    filters: {
        search?: string;
        department_id?: string;
    };
    departments: IdName[];
    /** The month the calendars open on, as `YYYY-MM`. */
    month: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Attendance Calendar', href: calendar() }],
    },
});

const { monthLabel } = useFormat();

const { filters, reset } = useQueryFilters(
    () => calendar.url(),
    {
        search: props.filters.search ?? '',
        department_id: props.filters.department_id
            ? Number(props.filters.department_id)
            : null,
    },
    { debounced: ['search'] },
);
</script>

<template>
    <Head title="Attendance Calendar" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Attendance Calendar"
            :description="`Choose an employee to open their monthly calendar, starting at ${monthLabel(month)}.`"
        />

        <div class="grid gap-2 sm:grid-cols-2 lg:max-w-2xl">
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
        </div>

        <div
            v-if="employees.data.length === 0"
            class="rounded-lg border bg-card"
        >
            <EmptyState
                title="No employees match"
                description="Try a different search, or clear the filters to see everyone."
            >
                <Button variant="outline" size="sm" @click="reset">
                    Clear filters
                </Button>
            </EmptyState>
        </div>

        <ul
            v-else
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
        >
            <li v-for="employee in employees.data" :key="employee.id">
                <Link
                    :href="show(employee.id, { query: { month } })"
                    class="flex h-full items-center justify-between gap-3 rounded-lg border bg-card p-4 transition-colors outline-none hover:border-primary/50 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    :aria-label="`Open the attendance calendar of ${employee.name}`"
                >
                    <EmployeeCell
                        :name="employee.name"
                        :code="employee.code"
                        :subtitle="employee.department ?? employee.designation"
                        :photo-url="employee.photo_url"
                    />
                    <CalendarDays
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                </Link>
            </li>
        </ul>

        <DataPagination :page="employees" noun="employees" />
    </div>
</template>
