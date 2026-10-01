<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Pencil, Plus, Search } from '@lucide/vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { statusTone } from '@/lib/status';
import { create, edit, index, show } from '@/routes/employees';
import { show as attendanceShow } from '@/routes/employees/attendance';
import type { EmployeeBrief, IdName, Option, Paginated } from '@/types';

type EmployeeRow = EmployeeBrief & {
    status_label: string;
    employment_type: string;
    joining_date: string;
    phone: string | null;
    email: string | null;
};

const props = defineProps<{
    employees: Paginated<EmployeeRow>;
    filters: {
        search?: string;
        department_id?: string;
        designation_id?: string;
        status?: string;
        employment_type?: string;
    };
    departments: IdName[];
    designations: IdName[];
    statuses: Option[];
    employmentTypes: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Active Employees', href: index() }],
    },
});

const { date } = useFormat();
const { can } = usePermissions();

const toNumber = (value?: string): number | null =>
    value ? Number(value) : null;

const { filters, reset } = useQueryFilters(
    () => index.url(),
    {
        search: props.filters.search ?? '',
        department_id: toNumber(props.filters.department_id),
        designation_id: toNumber(props.filters.designation_id),
        status: props.filters.status ?? null,
        employment_type: props.filters.employment_type ?? null,
    },
    { debounced: ['search'] },
);

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Employee', primary: true },
    { key: 'department', label: 'Department' },
    { key: 'employment_type', label: 'Type' },
    { key: 'joining_date', label: 'Joined' },
    { key: 'phone', label: 'Phone', hideOnMobile: true },
    { key: 'status', label: 'Status' },
];

const options = (items: IdName[]) =>
    items.map((item) => ({ value: item.id, label: item.name }));
</script>

<template>
    <Head title="Active Employees" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Active Employees"
            description="Everyone currently working at the company. Employees are HR records only; they have no login."
        >
            <Button v-if="can('employees.manage')" as-child>
                <Link :href="create()">
                    <Plus />
                    Add employee
                </Link>
            </Button>
        </PageHeader>

        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            <div class="relative lg:col-span-1">
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
                :options="options(departments)"
                placeholder="All departments"
                aria-label="Department"
            />
            <NativeSelect
                v-model="filters.designation_id"
                :options="options(designations)"
                placeholder="All designations"
                aria-label="Designation"
            />
            <NativeSelect
                v-model="filters.status"
                :options="statuses"
                placeholder="All statuses"
                aria-label="Status"
            />
            <NativeSelect
                v-model="filters.employment_type"
                :options="employmentTypes"
                placeholder="All employment types"
                aria-label="Employment type"
            />
        </div>

        <DataTable
            :columns="columns"
            :rows="employees.data"
            empty-title="No employees match"
            empty-description="Try a different search or clear the filters. New employees are added with the Add employee button."
        >
            <template #cell-name="{ row }">
                <EmployeeCell
                    :name="row.name"
                    :code="row.code"
                    :subtitle="row.designation"
                    :photo-url="row.photo_url"
                    :href="show(row.id)"
                />
            </template>
            <template #cell-department="{ row }">
                {{ row.department ?? '-' }}
            </template>
            <template #cell-joining_date="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.joining_date) }}
                </span>
            </template>
            <template #cell-phone="{ row }">
                <span class="tabular">{{ row.phone ?? '-' }}</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :tone="statusTone('employee', row.status)">
                    {{ row.status_label }}
                </StatusBadge>
            </template>
            <template #actions="{ row }">
                <Button
                    v-if="can('attendance.view')"
                    variant="ghost"
                    size="icon-sm"
                    as-child
                >
                    <Link
                        :href="attendanceShow(row.id)"
                        :aria-label="`Attendance calendar of ${row.name}`"
                        :title="`Attendance calendar of ${row.name}`"
                    >
                        <CalendarDays />
                    </Link>
                </Button>
                <Button
                    v-if="can('employees.manage')"
                    variant="ghost"
                    size="icon-sm"
                    as-child
                >
                    <Link
                        :href="edit(row.id)"
                        :aria-label="`Edit ${row.name}`"
                        :title="`Edit ${row.name}`"
                    >
                        <Pencil />
                    </Link>
                </Button>
            </template>
            <template #empty>
                <div
                    class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                >
                    <p class="text-sm font-medium">No employees match</p>
                    <p class="max-w-[48ch] text-sm text-muted-foreground">
                        Try a different search, or clear the filters to see
                        everyone.
                    </p>
                    <Button variant="outline" size="sm" @click="reset">
                        Clear filters
                    </Button>
                </div>
            </template>
        </DataTable>

        <DataPagination :page="employees" noun="employees" />
    </div>
</template>
