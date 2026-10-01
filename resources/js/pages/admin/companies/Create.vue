<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CompanyForm from '@/components/admin/CompanyForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import { create, index } from '@/routes/admin/companies';

defineProps<{
    timezones: string[];
    dateFormats: string[];
    defaults: { currency: string; timezone: string; date_format: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Companies', href: index() },
            { title: 'Add company', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Add company" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Add company"
            description="A new company gets its own isolated data, a default 9:00 to 18:00 shift, Sunday as the weekly off, the standard leave types and roles, and its first Company Admin."
        />

        <CompanyForm
            :timezones="timezones"
            :date-formats="dateFormats"
            :defaults="defaults"
        />
    </div>
</template>
