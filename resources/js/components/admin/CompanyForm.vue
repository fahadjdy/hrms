<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SectionCard from '@/components/SectionCard.vue';
import CompanyProfileFields from '@/components/settings/CompanyProfileFields.vue';
import type { CompanyProfileData } from '@/components/settings/CompanyProfileFields.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { index, show, store, update } from '@/routes/admin/companies';

export type AdminCompany = {
    id: number;
    name: string;
    legal_name: string | null;
    slug: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    postal_code: string | null;
    tax_id: string | null;
    currency: string;
    timezone: string;
    date_format: string;
    is_active: boolean;
    logo_url: string | null;
    created_at: string | null;
};

const props = defineProps<{
    /** The company being edited. Leave out to create a new one. */
    company?: AdminCompany;
    timezones: string[];
    dateFormats: string[];
    /** Regional defaults for a new company. */
    defaults?: { currency: string; timezone: string; date_format: string };
}>();

type FormData = CompanyProfileData & {
    admin_name: string;
    admin_email: string;
    admin_password: string;
};

const form = useForm<FormData>({
    name: props.company?.name ?? '',
    legal_name: props.company?.legal_name ?? '',
    email: props.company?.email ?? '',
    phone: props.company?.phone ?? '',
    address: props.company?.address ?? '',
    city: props.company?.city ?? '',
    state: props.company?.state ?? '',
    country: props.company?.country ?? '',
    postal_code: props.company?.postal_code ?? '',
    tax_id: props.company?.tax_id ?? '',
    currency: props.company?.currency ?? props.defaults?.currency ?? 'INR',
    timezone:
        props.company?.timezone ?? props.defaults?.timezone ?? 'Asia/Kolkata',
    date_format:
        props.company?.date_format ?? props.defaults?.date_format ?? 'd M Y',
    logo: null,
    admin_name: '',
    admin_email: '',
    admin_password: '',
});

function save(): void {
    if (props.company) {
        // A file upload has to travel as multipart POST, so the PUT is spoofed.
        form.transform((data) => {
            const company: Record<string, unknown> = {
                ...data,
                _method: 'put',
            };

            // The first admin is only created together with a new company.
            delete company.admin_name;
            delete company.admin_email;
            delete company.admin_password;

            return company;
        }).post(update.url(props.company.id), { forceFormData: true });

        return;
    }

    form.post(store.url(), { forceFormData: true });
}
</script>

<template>
    <form class="grid max-w-3xl gap-5" @submit.prevent="save">
        <CompanyProfileFields
            :model-value="form"
            :errors="form.errors"
            :timezones="timezones"
            :date-formats="dateFormats"
            :logo-url="company?.logo_url"
        />

        <SectionCard
            v-if="!company"
            title="First Company Admin"
            description="This person signs in to set up and run the company: shifts, employees, attendance and payroll. More users can be added later from the company's own settings."
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    label="Name"
                    for="admin-name"
                    :error="form.errors.admin_name"
                    required
                >
                    <Input
                        id="admin-name"
                        v-model="form.admin_name"
                        required
                        maxlength="255"
                        autocomplete="off"
                    />
                </FormField>
                <FormField
                    label="Email"
                    for="admin-email"
                    :error="form.errors.admin_email"
                    hint="Used to sign in"
                    required
                >
                    <Input
                        id="admin-email"
                        v-model="form.admin_email"
                        type="email"
                        required
                        maxlength="255"
                        autocomplete="off"
                    />
                </FormField>
                <FormField
                    label="Password"
                    for="admin-password"
                    :error="form.errors.admin_password"
                    hint="Share it with the admin; they can change it after signing in"
                    required
                >
                    <PasswordInput
                        id="admin-password"
                        v-model="form.admin_password"
                        required
                        autocomplete="new-password"
                    />
                </FormField>
            </div>
        </SectionCard>

        <div class="flex flex-wrap items-center gap-2">
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ company ? 'Save company' : 'Create company' }}
            </Button>
            <Button variant="outline" as-child>
                <Link :href="company ? show(company.id) : index()">Cancel</Link>
            </Button>
        </div>
    </form>
</template>
