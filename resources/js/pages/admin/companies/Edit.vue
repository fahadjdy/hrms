<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import CompanyForm from '@/components/admin/CompanyForm.vue';
import type { AdminCompany } from '@/components/admin/CompanyForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import { index } from '@/routes/admin/companies';

const props = defineProps<{
    // Not named `company`: that is the shared prop for the viewer's own tenant.
    managedCompany: AdminCompany;
    timezones: string[];
    dateFormats: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Companies', href: index() },
            { title: 'Edit company', href: '#' },
        ],
    },
});

const company = computed(() => props.managedCompany);
</script>

<template>
    <Head :title="`Edit ${company.name}`" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            :title="`Edit ${company.name}`"
            description="Company details and regional settings. The company's own admin can change these too."
        />

        <CompanyForm
            :company="company"
            :timezones="timezones"
            :date-formats="dateFormats"
        />
    </div>
</template>
