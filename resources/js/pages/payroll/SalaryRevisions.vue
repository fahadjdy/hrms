<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DatePicker from '@/components/DatePicker.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { show as salaryShow } from '@/routes/employees/salary';
import { index } from '@/routes/salary-revisions';
import type { Paginated } from '@/types';

type RevisionRow = {
    id: number;
    employee: { id: number; name: string; code: string };
    effective_date: string;
    previous_gross: number;
    new_gross: number;
    change: number;
    reason: string | null;
    changed_by: string | null;
    created_at: string | null;
};

const props = defineProps<{
    revisions: Paginated<RevisionRow>;
    filters: {
        search?: string;
        from?: string;
        to?: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Salary Revisions', href: index() }],
    },
});

const { money, date, dateTime } = useFormat();

const { filters, reset } = useQueryFilters(
    () => index.url(),
    {
        search: props.filters.search ?? '',
        from: props.filters.from ?? '',
        to: props.filters.to ?? '',
    },
    { debounced: ['search'] },
);

const columns: DataTableColumn[] = [
    { key: 'employee', label: 'Employee', primary: true },
    { key: 'effective_date', label: 'Effective date' },
    { key: 'previous_gross', label: 'Previous salary', align: 'right' },
    { key: 'new_gross', label: 'New salary', align: 'right' },
    { key: 'change', label: 'Change', align: 'right' },
    { key: 'reason', label: 'Reason' },
    { key: 'changed_by', label: 'Changed by' },
];
</script>

<template>
    <Head title="Salary Revisions" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Salary Revisions"
            description="Every salary change across the company, newest first. Each revision is kept permanently; a new revision never replaces an earlier one."
        />

        <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <FormField label="Employee" for="revision-search">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="revision-search"
                        v-model="filters.search"
                        type="search"
                        placeholder="Name, ID or email"
                        class="pl-9"
                    />
                </div>
            </FormField>
            <FormField label="Effective from" for="revision-from">
                <DatePicker id="revision-from" v-model="filters.from" />
            </FormField>
            <FormField label="Effective to" for="revision-to">
                <DatePicker id="revision-to" v-model="filters.to" />
            </FormField>
        </div>

        <DataTable :columns="columns" :rows="revisions.data">
            <template #cell-employee="{ row }">
                <EmployeeCell
                    :name="row.employee.name"
                    :code="row.employee.code"
                    :href="salaryShow(row.employee.id)"
                />
            </template>
            <template #cell-effective_date="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.effective_date) }}
                </span>
            </template>
            <template #cell-previous_gross="{ row }">
                <span class="tabular whitespace-nowrap text-muted-foreground">
                    {{
                        Number(row.previous_gross) > 0
                            ? money(row.previous_gross)
                            : '-'
                    }}
                </span>
            </template>
            <template #cell-new_gross="{ row }">
                <span class="tabular font-medium whitespace-nowrap">
                    {{ money(row.new_gross) }}
                </span>
            </template>
            <template #cell-change="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{
                        Number(row.previous_gross) > 0
                            ? money(row.change, { signed: true })
                            : 'Initial salary'
                    }}
                </span>
            </template>
            <template #cell-reason="{ row }">
                {{ row.reason ?? '-' }}
            </template>
            <template #cell-changed_by="{ row }">
                {{ row.changed_by ?? '-' }}
                <span class="tabular block text-xs text-muted-foreground">
                    {{ dateTime(row.created_at) }}
                </span>
            </template>
            <template #empty>
                <div
                    class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                >
                    <p class="text-sm font-medium">No salary revisions found</p>
                    <p class="max-w-[52ch] text-sm text-muted-foreground">
                        Revisions appear here when a salary is set or changed
                        from an employee's salary page. If you are filtering,
                        try a wider date range.
                    </p>
                    <Button variant="outline" size="sm" @click="reset">
                        Clear filters
                    </Button>
                </div>
            </template>
        </DataTable>

        <DataPagination :page="revisions" noun="revisions" />
    </div>
</template>
