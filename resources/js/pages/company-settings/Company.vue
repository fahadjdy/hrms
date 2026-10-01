<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import CompanyProfileFields from '@/components/settings/CompanyProfileFields.vue';
import type { CompanyProfileData } from '@/components/settings/CompanyProfileFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { edit, update } from '@/routes/settings/company';

type CompanyProfile = {
    [K in keyof Omit<CompanyProfileData, 'logo'>]: string | null;
} & { logo_url: string | null };

const props = defineProps<{
    companyProfile: CompanyProfile;
    timezones: string[];
    dateFormats: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Company settings', href: edit() }],
    },
});

const form = useForm<CompanyProfileData>({
    name: props.companyProfile.name ?? '',
    legal_name: props.companyProfile.legal_name ?? '',
    email: props.companyProfile.email ?? '',
    phone: props.companyProfile.phone ?? '',
    address: props.companyProfile.address ?? '',
    city: props.companyProfile.city ?? '',
    state: props.companyProfile.state ?? '',
    country: props.companyProfile.country ?? '',
    postal_code: props.companyProfile.postal_code ?? '',
    tax_id: props.companyProfile.tax_id ?? '',
    currency: props.companyProfile.currency ?? 'INR',
    timezone: props.companyProfile.timezone ?? 'Asia/Kolkata',
    date_format: props.companyProfile.date_format ?? 'd M Y',
    logo: null,
});

// A file upload has to travel as multipart POST, so the PUT is spoofed.
const save = () =>
    form
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(update.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('logo'),
        });
</script>

<template>
    <Head title="Company settings" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Company"
            description="Your company's details, and how money and dates are shown across the system."
        />

        <form class="grid max-w-3xl gap-5" @submit.prevent="save">
            <CompanyProfileFields
                :model-value="form"
                :errors="form.errors"
                :timezones="timezones"
                :date-formats="dateFormats"
                :logo-url="companyProfile.logo_url"
            />

            <div>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Save company details
                </Button>
            </div>
        </form>
    </div>
</template>
