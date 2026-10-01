<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { LogIn, Pencil, Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import type { AdminCompany } from '@/components/admin/CompanyForm.vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { toastFirstError } from '@/composables/useResourceForm';
import {
    create,
    edit,
    impersonate,
    index,
    show,
} from '@/routes/admin/companies';
import type { Paginated } from '@/types';

type CompanyRow = AdminCompany & {
    users_count: number;
    employees_count: number;
    active_employees_count: number;
};

const props = defineProps<{
    companies: Paginated<CompanyRow>;
    filters: { search: string; status: string };
    stats: {
        companies: number;
        active_companies: number;
        employees: number;
        users: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Companies', href: index() }],
    },
});

const { date, number } = useFormat();

const { filters, reset } = useQueryFilters(
    () => index.url(),
    {
        search: props.filters.search ?? '',
        status: props.filters.status || null,
    },
    { debounced: ['search'] },
);

const signingIn = ref(false);

/** Open the company as its Company Admin. The use is recorded in its audit log. */
function signInAsAdmin(companyId: number): void {
    signingIn.value = true;

    router.post(
        impersonate.url(companyId),
        {},
        {
            onError: toastFirstError,
            onFinish: () => {
                signingIn.value = false;
            },
        },
    );
}

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Company', primary: true },
    { key: 'email', label: 'Email' },
    {
        key: 'active_employees_count',
        label: 'Active employees',
        align: 'right',
    },
    { key: 'users_count', label: 'Users', align: 'right' },
    { key: 'created_at', label: 'Created' },
    { key: 'is_active', label: 'Status' },
];
</script>

<template>
    <Head title="Companies" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Companies"
            description="Every company on the platform. Each company's employees, attendance, payroll and borrow data is isolated from all the others."
        >
            <Button as-child>
                <Link :href="create()">
                    <Plus />
                    Add company
                </Link>
            </Button>
        </PageHeader>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard label="Companies" :value="number(stats.companies)" />
            <StatCard
                label="Active companies"
                :value="number(stats.active_companies)"
                :hint="`${number(stats.companies - stats.active_companies)} deactivated`"
                tone="positive"
            />
            <StatCard
                label="Active employees"
                :value="number(stats.employees)"
                hint="Across all companies"
            />
            <StatCard
                label="Company users"
                :value="number(stats.users)"
                hint="Admin and HR logins"
            />
        </div>

        <div class="grid gap-2 sm:grid-cols-2 lg:max-w-2xl">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    type="search"
                    placeholder="Company name or email"
                    aria-label="Search companies"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.status"
                :options="statusOptions"
                placeholder="Active and inactive"
                aria-label="Status"
            />
        </div>

        <DataTable :columns="columns" :rows="companies.data">
            <template #cell-name="{ row }">
                <div class="flex min-w-0 items-center gap-3">
                    <img
                        v-if="row.logo_url"
                        :src="row.logo_url"
                        alt=""
                        class="size-8 shrink-0 rounded-md border bg-white object-contain"
                    />
                    <div class="min-w-0">
                        <Link
                            :href="show(row.id)"
                            class="block truncate font-medium underline-offset-4 hover:underline"
                        >
                            {{ row.name }}
                        </Link>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                        >
                            {{
                                [row.city, row.country]
                                    .filter(Boolean)
                                    .join(', ') || row.currency
                            }}
                        </span>
                    </div>
                </div>
            </template>
            <template #cell-email="{ row }">
                {{ row.email ?? '-' }}
            </template>
            <template #cell-active_employees_count="{ row }">
                <span class="tabular">
                    {{ number(row.active_employees_count) }}
                </span>
                <span class="tabular text-xs text-muted-foreground">
                    of {{ number(row.employees_count) }}
                </span>
            </template>
            <template #cell-users_count="{ row }">
                <span class="tabular">{{ number(row.users_count) }}</span>
            </template>
            <template #cell-created_at="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.created_at) }}
                </span>
            </template>
            <template #cell-is_active="{ row }">
                <StatusBadge :tone="row.is_active ? 'positive' : 'neutral'">
                    {{ row.is_active ? 'Active' : 'Inactive' }}
                </StatusBadge>
            </template>
            <template #actions="{ row }">
                <Button
                    v-if="row.is_active"
                    variant="ghost"
                    size="icon-sm"
                    :disabled="signingIn"
                    :aria-label="`Sign in as the admin of ${row.name}`"
                    :title="`Sign in as the admin of ${row.name}`"
                    @click="signInAsAdmin(row.id)"
                >
                    <LogIn />
                </Button>
                <Button variant="ghost" size="icon-sm" as-child>
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
                    <template v-if="filters.search || filters.status">
                        <p class="text-sm font-medium">No companies match</p>
                        <p class="text-sm text-muted-foreground">
                            Try a different name, or clear the filters.
                        </p>
                        <Button variant="outline" size="sm" @click="reset">
                            Clear filters
                        </Button>
                    </template>
                    <template v-else>
                        <p class="text-sm font-medium">No companies yet</p>
                        <p class="max-w-[48ch] text-sm text-muted-foreground">
                            Add the first company and its Company Admin to get
                            started.
                        </p>
                        <Button size="sm" as-child>
                            <Link :href="create()">Add company</Link>
                        </Button>
                    </template>
                </div>
            </template>
        </DataTable>

        <DataPagination :page="companies" noun="companies" />
    </div>
</template>
