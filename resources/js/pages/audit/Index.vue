<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DatePicker from '@/components/DatePicker.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { index } from '@/routes/audit-logs';
import { show as employeeShow } from '@/routes/employees';
import type { IdName, Option, Paginated } from '@/types';

type Values = Record<string, unknown> | unknown[] | null;

type AuditLog = {
    id: number;
    action: string;
    entity_type: string;
    entity_id: number | null;
    description: string | null;
    user: string | null;
    employee: { id: number; name: string } | null;
    old_values: Values;
    new_values: Values;
    ip_address: string | null;
    created_at: string | null;
};

const props = defineProps<{
    logs: Paginated<AuditLog>;
    filters: {
        search?: string;
        action?: string;
        user_id?: string;
        from?: string;
        to?: string;
    };
    users: IdName[];
    areas: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Audit Logs', href: index() }],
    },
});

const { dateTime } = useFormat();
const { can } = usePermissions();

const { filters, reset } = useQueryFilters(
    () => index.url(),
    {
        search: props.filters.search ?? '',
        action: props.filters.action ?? null,
        user_id: props.filters.user_id ? Number(props.filters.user_id) : null,
        from: props.filters.from ?? '',
        to: props.filters.to ?? '',
    },
    { debounced: ['search'] },
);

const hasFilters = computed(() =>
    Object.values(filters).some((value) => value !== null && value !== ''),
);

const columns: DataTableColumn[] = [
    { key: 'description', label: 'What happened', primary: true },
    { key: 'created_at', label: 'When' },
    { key: 'user', label: 'By' },
    { key: 'action', label: 'Action' },
    { key: 'employee', label: 'Employee' },
    { key: 'ip_address', label: 'IP address', hideOnMobile: true },
];

/** `payroll.borrow_recovery_adjusted` as "Payroll: borrow recovery adjusted". */
function actionLabel(action: string): string {
    const [area, ...rest] = action.split('.');
    const words = (value: string) => value.replaceAll('_', ' ');
    const title = words(area).replace(/^./, (letter) => letter.toUpperCase());

    return rest.length > 0 ? `${title}: ${words(rest.join(' '))}` : title;
}

const fieldLabel = (key: string): string =>
    key.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());

function printValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

const asRecord = (values: Values): Record<string, unknown> =>
    values === null || Array.isArray(values)
        ? Object.fromEntries((values ?? []).map((value, key) => [key, value]))
        : values;

const selected = ref<AuditLog | null>(null);

const hasValues = (log: AuditLog): boolean =>
    Object.keys(asRecord(log.old_values)).length > 0 ||
    Object.keys(asRecord(log.new_values)).length > 0;

/** Every field that appears before or after, with both values side by side. */
const changes = computed(() => {
    if (selected.value === null) {
        return [];
    }

    const before = asRecord(selected.value.old_values);
    const after = asRecord(selected.value.new_values);

    return [...new Set([...Object.keys(before), ...Object.keys(after)])].map(
        (key) => ({
            key,
            label: fieldLabel(key),
            before: key in before ? printValue(before[key]) : '-',
            after: key in after ? printValue(after[key]) : '-',
            changed:
                key in before &&
                key in after &&
                printValue(before[key]) !== printValue(after[key]),
        }),
    );
});
</script>

<template>
    <Head title="Audit Logs" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Audit Logs"
            description="A permanent record of who changed what and when: employees, attendance, salary, payroll, borrow, settlements and settings."
        />

        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    type="search"
                    placeholder="Search descriptions"
                    aria-label="Search audit logs"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.action"
                :options="areas"
                placeholder="All areas"
                aria-label="Area"
            />
            <NativeSelect
                v-model="filters.user_id"
                :options="users.map((u) => ({ value: u.id, label: u.name }))"
                placeholder="All users"
                aria-label="User"
            />
            <DatePicker
                v-model="filters.from"
                aria-label="From date"
                placeholder="From date"
                :max="filters.to || undefined"
            />
            <DatePicker
                v-model="filters.to"
                aria-label="To date"
                placeholder="To date"
                :min="filters.from || undefined"
            />
        </div>

        <DataTable :columns="columns" :rows="logs.data">
            <template #cell-description="{ row }">
                <span class="font-medium">
                    {{ row.description ?? actionLabel(row.action) }}
                </span>
            </template>
            <template #cell-created_at="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ dateTime(row.created_at) }}
                </span>
            </template>
            <template #cell-user="{ row }">
                {{ row.user ?? 'System' }}
            </template>
            <template #cell-action="{ row }">
                <span class="text-muted-foreground">
                    {{ actionLabel(row.action) }}
                </span>
            </template>
            <template #cell-employee="{ row }">
                <template v-if="row.employee">
                    <Link
                        v-if="can('employees.view')"
                        :href="employeeShow(row.employee.id)"
                        class="underline-offset-4 hover:underline"
                    >
                        {{ row.employee.name }}
                    </Link>
                    <template v-else>{{ row.employee.name }}</template>
                </template>
                <span v-else class="text-muted-foreground">-</span>
            </template>
            <template #cell-ip_address="{ row }">
                <span class="tabular text-muted-foreground">
                    {{ row.ip_address ?? '-' }}
                </span>
            </template>
            <template #actions="{ row }">
                <Button
                    v-if="hasValues(row)"
                    variant="ghost"
                    size="sm"
                    :aria-label="`View changes: ${row.description ?? row.action}`"
                    @click="selected = row"
                >
                    View changes
                </Button>
            </template>
            <template #empty>
                <div
                    class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                >
                    <p class="text-sm font-medium">No audit entries match</p>
                    <p class="max-w-[48ch] text-sm text-muted-foreground">
                        Every change made in the system is recorded here. Try a
                        wider date range or another area.
                    </p>
                    <Button
                        v-if="hasFilters"
                        variant="outline"
                        size="sm"
                        @click="reset"
                    >
                        Clear filters
                    </Button>
                </div>
            </template>
        </DataTable>

        <DataPagination :page="logs" noun="entries" />
    </div>

    <Dialog
        :open="selected !== null"
        @update:open="(value) => !value && (selected = null)"
    >
        <DialogContent v-if="selected" class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{ selected.description ?? actionLabel(selected.action) }}
                </DialogTitle>
                <DialogDescription>
                    {{ actionLabel(selected.action) }} by
                    {{ selected.user ?? 'System' }} on
                    {{ dateTime(selected.created_at) }}
                    <template v-if="selected.ip_address">
                        from {{ selected.ip_address }}
                    </template>
                </DialogDescription>
            </DialogHeader>

            <div class="max-h-[60vh] overflow-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b bg-muted/50 text-xs text-muted-foreground"
                        >
                            <th
                                scope="col"
                                class="px-3 py-2 text-left font-medium"
                            >
                                Field
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-left font-medium"
                            >
                                Before
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-left font-medium"
                            >
                                After
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="change in changes"
                            :key="change.key"
                            class="border-b align-top last:border-0"
                        >
                            <th
                                scope="row"
                                class="px-3 py-2 text-left font-medium"
                            >
                                {{ change.label }}
                            </th>
                            <td
                                class="tabular px-3 py-2 break-words text-muted-foreground"
                                :class="change.changed ? 'line-through' : ''"
                            >
                                {{ change.before }}
                            </td>
                            <td class="tabular px-3 py-2 break-words">
                                {{ change.after }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </DialogContent>
    </Dialog>
</template>
