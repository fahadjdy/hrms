<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowRight, Search } from '@lucide/vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DatePicker from '@/components/DatePicker.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { index } from '@/routes/designation-changes';
import { show as employeeShow } from '@/routes/employees';
import type { Option, Paginated, Tone } from '@/types';

type ChangeRow = {
    id: number;
    employee: {
        id: number;
        name: string;
        code: string;
        photo_url: string | null;
        is_past: boolean;
    };
    type: string;
    type_label: string;
    from: string | null;
    to: string;
    effective_date: string;
    reason: string | null;
    changed_by: string | null;
    created_at: string | null;
};

const props = defineProps<{
    changes: Paginated<ChangeRow>;
    filters: {
        search?: string;
        type?: string;
        from?: string;
        to?: string;
    };
    types: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Designation History', href: index() }],
    },
});

const { date, dateTime } = useFormat();

const { filters, reset } = useQueryFilters(
    () => index.url(),
    {
        search: props.filters.search ?? '',
        type: props.filters.type ?? '',
        from: props.filters.from ?? '',
        to: props.filters.to ?? '',
    },
    { debounced: ['search'] },
);

const tone: Record<string, Tone> = {
    initial: 'neutral',
    promotion: 'positive',
    demotion: 'warning',
    change: 'info',
};

const columns: DataTableColumn[] = [
    { key: 'employee', label: 'Employee', primary: true },
    { key: 'effective_date', label: 'Effective date' },
    { key: 'change', label: 'Designation' },
    { key: 'type', label: 'Type' },
    { key: 'reason', label: 'Reason' },
    { key: 'changed_by', label: 'Recorded by' },
];
</script>

<template>
    <Head title="Designation History" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Designation History"
            description="Every promotion, demotion and role change across the company, newest first. Entries are kept permanently; a wrong one is corrected by recording another change on the employee's profile."
        />

        <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <FormField label="Employee" for="change-search">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="change-search"
                        v-model="filters.search"
                        type="search"
                        placeholder="Name, ID or email"
                        class="pl-9"
                    />
                </div>
            </FormField>
            <FormField label="Type" for="change-type">
                <NativeSelect
                    id="change-type"
                    v-model="filters.type"
                    :options="types"
                    placeholder="All types"
                />
            </FormField>
            <FormField label="Effective from" for="change-from">
                <DatePicker
                    id="change-from"
                    v-model="filters.from"
                    placeholder="From date"
                />
            </FormField>
            <FormField label="Effective to" for="change-to">
                <DatePicker
                    id="change-to"
                    v-model="filters.to"
                    :min="filters.from || undefined"
                    placeholder="To date"
                />
            </FormField>
        </div>

        <DataTable :columns="columns" :rows="changes.data">
            <template #cell-employee="{ row }">
                <EmployeeCell
                    :name="row.employee.name"
                    :code="row.employee.code"
                    :photo-url="row.employee.photo_url"
                    :subtitle="row.employee.is_past ? 'Past employee' : null"
                    :href="employeeShow(row.employee.id)"
                />
            </template>
            <template #cell-effective_date="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.effective_date) }}
                </span>
            </template>
            <template #cell-change="{ row }">
                <span class="inline-flex flex-wrap items-center gap-x-1.5">
                    <template v-if="row.from">
                        <span class="text-muted-foreground">{{
                            row.from
                        }}</span>
                        <ArrowRight
                            class="size-3.5 shrink-0 text-muted-foreground"
                            aria-label="to"
                        />
                    </template>
                    <span class="font-medium">{{ row.to }}</span>
                </span>
            </template>
            <template #cell-type="{ row }">
                <StatusBadge :tone="tone[row.type] ?? 'neutral'">
                    {{ row.type_label }}
                </StatusBadge>
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
                    <p class="text-sm font-medium">
                        No designation changes found
                    </p>
                    <p class="max-w-[52ch] text-sm text-muted-foreground">
                        An employee's joining designation and every promotion or
                        role change recorded on their profile appear here. If
                        you are filtering, try a wider date range.
                    </p>
                    <Button variant="outline" size="sm" @click="reset">
                        Clear filters
                    </Button>
                </div>
            </template>
        </DataTable>

        <DataPagination :page="changes" noun="changes" />
    </div>
</template>
