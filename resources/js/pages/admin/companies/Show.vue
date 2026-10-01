<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { KeyRound, LogIn, Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { AdminCompany } from '@/components/admin/CompanyForm.vue';
import CredentialsDialog from '@/components/admin/CredentialsDialog.vue';
import type { CompanyCredentials } from '@/components/admin/CredentialsDialog.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import DetailList from '@/components/DetailList.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionCard from '@/components/SectionCard.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useFormat } from '@/composables/useFormat';
import {
    toastFirstError,
    useConfirmedAction,
} from '@/composables/useResourceForm';
import { edit, impersonate, index } from '@/routes/admin/companies';
import { update as updateStatus } from '@/routes/admin/companies/status';

type CompanyUser = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    is_active: boolean;
};

const props = defineProps<{
    // Not named `company`: that is the shared prop for the viewer's own tenant.
    managedCompany: AdminCompany;
    stats: {
        employees: number;
        active_employees: number;
        past_employees: number;
        users: number;
        last_payroll: {
            label: string;
            status: string;
            net_payable: number;
        } | null;
    };
    users: CompanyUser[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Companies', href: index() },
            { title: 'Company', href: '#' },
        ],
    },
});

const { date, number } = useFormat();

const company = computed(() => props.managedCompany);

/** Amounts of this company are shown in its own currency, not the viewer's. */
const companyMoney = (amount: number): string =>
    new Intl.NumberFormat(
        props.managedCompany.currency === 'INR' ? 'en-IN' : 'en-US',
        {
            style: 'currency',
            currency: props.managedCompany.currency,
        },
    ).format(amount);

const address = computed(() =>
    [
        props.managedCompany.address,
        props.managedCompany.city,
        props.managedCompany.state,
        props.managedCompany.postal_code,
        props.managedCompany.country,
    ]
        .filter(Boolean)
        .join(', '),
);

const hasActiveUser = computed(() =>
    props.users.some((user) => user.is_active),
);

const statusChange = useConfirmedAction<AdminCompany>();

/**
 * The new Company Admin's login, flashed once right after the company is
 * created. Kept while this page is open so it can be shown again.
 */
const credentials = ref<CompanyCredentials | null>(
    (usePage().flash as { credentials?: CompanyCredentials }).credentials ??
        null,
);
const showCredentials = ref(credentials.value !== null);

const signingIn = ref(false);

/** Sign in as the named user, or as the Company Admin when none is given. */
function signInAs(userId?: number): void {
    signingIn.value = true;

    router.post(
        impersonate.url(props.managedCompany.id),
        userId ? { user_id: userId } : {},
        {
            onError: toastFirstError,
            onFinish: () => {
                signingIn.value = false;
            },
        },
    );
}

const userColumns: DataTableColumn[] = [
    { key: 'name', label: 'User', primary: true },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Role' },
    { key: 'is_active', label: 'Status' },
];
</script>

<template>
    <Head :title="company.name" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            :title="company.name"
            :description="company.legal_name ?? undefined"
        >
            <StatusBadge :tone="company.is_active ? 'positive' : 'neutral'">
                {{ company.is_active ? 'Active' : 'Inactive' }}
            </StatusBadge>
            <Button
                v-if="credentials"
                variant="outline"
                @click="showCredentials = true"
            >
                <KeyRound />
                Share login details
            </Button>
            <Button variant="outline" as-child>
                <Link :href="edit(company.id)">
                    <Pencil />
                    Edit
                </Link>
            </Button>
            <Button
                :disabled="!company.is_active || !hasActiveUser || signingIn"
                :title="
                    company.is_active
                        ? undefined
                        : 'Activate the company to sign in as its admin'
                "
                @click="signInAs()"
            >
                <Spinner v-if="signingIn" />
                <LogIn v-else />
                Sign in as company admin
            </Button>
        </PageHeader>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
                label="Active employees"
                :value="number(stats.active_employees)"
                :hint="`${number(stats.employees)} on record`"
            />
            <StatCard
                label="Past employees"
                :value="number(stats.past_employees)"
                hint="History is kept"
            />
            <StatCard
                label="Users"
                :value="number(stats.users)"
                hint="Admin and HR logins"
            />
            <StatCard
                label="Latest payroll"
                :value="
                    stats.last_payroll
                        ? companyMoney(stats.last_payroll.net_payable)
                        : '-'
                "
                :hint="
                    stats.last_payroll
                        ? `${stats.last_payroll.label} - ${stats.last_payroll.status}`
                        : 'No payroll has been run yet'
                "
            />
        </div>

        <div class="grid items-start gap-5 xl:grid-cols-2">
            <SectionCard title="Company details">
                <div class="flex items-start gap-4">
                    <img
                        v-if="company.logo_url"
                        :src="company.logo_url"
                        alt="Company logo"
                        class="size-14 shrink-0 rounded-md border bg-white object-contain"
                    />
                    <DetailList
                        class="flex-1"
                        :items="[
                            { label: 'Email', value: company.email },
                            { label: 'Phone', value: company.phone },
                            {
                                label: 'GST / tax number',
                                value: company.tax_id,
                            },
                            { label: 'Address', value: address },
                            { label: 'Currency', value: company.currency },
                            { label: 'Timezone', value: company.timezone },
                            {
                                label: 'Created',
                                value: date(company.created_at),
                            },
                        ]"
                    />
                </div>
            </SectionCard>

            <SectionCard
                :title="
                    company.is_active
                        ? 'Deactivate company'
                        : 'Activate company'
                "
            >
                <p class="text-sm text-muted-foreground">
                    <template v-if="company.is_active">
                        Deactivating stops every user of this company from
                        signing in. Nothing is deleted; all employees,
                        attendance and payroll history stay exactly as they are
                        and come back when the company is activated again.
                    </template>
                    <template v-else>
                        This company is deactivated, so none of its users can
                        sign in. Activating it restores access with all data
                        intact.
                    </template>
                </p>
                <Button
                    class="mt-4"
                    :variant="company.is_active ? 'destructive' : 'default'"
                    @click="statusChange.ask(company)"
                >
                    {{
                        company.is_active
                            ? 'Deactivate company'
                            : 'Activate company'
                    }}
                </Button>
            </SectionCard>
        </div>

        <section class="flex flex-col gap-3" aria-labelledby="users-heading">
            <div>
                <h2 id="users-heading" class="text-base font-semibold">
                    Users
                </h2>
                <p class="text-sm text-muted-foreground">
                    The company's admin and HR logins. The company manages them
                    under Settings, Roles &amp; Permissions. Signing in as a
                    user shows the application exactly as they see it, with
                    their permissions, and is recorded in the company's audit
                    log.
                </p>
            </div>
            <DataTable
                :columns="userColumns"
                :rows="users"
                empty-title="No users"
                empty-description="This company has no logins."
            >
                <template #cell-name="{ row }">
                    <span class="font-medium">{{ row.name }}</span>
                </template>
                <template #cell-role="{ row }">
                    {{ row.role ?? 'No role' }}
                </template>
                <template #cell-is_active="{ row }">
                    <StatusBadge :tone="row.is_active ? 'positive' : 'neutral'">
                        {{ row.is_active ? 'Active' : 'Deactivated' }}
                    </StatusBadge>
                </template>
                <template #actions="{ row }">
                    <Button
                        v-if="row.is_active && company.is_active"
                        variant="outline"
                        size="sm"
                        :disabled="signingIn"
                        @click="signInAs(row.id)"
                    >
                        <LogIn />
                        Sign in as {{ row.name }}
                    </Button>
                </template>
            </DataTable>
        </section>
    </div>

    <CredentialsDialog
        v-if="credentials"
        v-model:open="showCredentials"
        :credentials="credentials"
    />

    <ConfirmDialog
        :open="statusChange.target.value !== null"
        :title="
            company.is_active
                ? `Deactivate ${company.name}?`
                : `Activate ${company.name}?`
        "
        :description="
            company.is_active
                ? 'Its users will be signed out and cannot sign in until the company is activated again. No data is deleted.'
                : 'Its users will be able to sign in again.'
        "
        :confirm-label="
            company.is_active ? 'Deactivate company' : 'Activate company'
        "
        :destructive="company.is_active"
        :processing="statusChange.processing.value"
        @update:open="(value) => !value && (statusChange.target.value = null)"
        @confirm="
            statusChange.run('put', updateStatus.url(company.id), {
                is_active: !company.is_active,
            })
        "
    />
</template>
