<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EmployeeForm from '@/components/employees/EmployeeForm.vue';
import type { ShiftOption } from '@/components/employees/EmployeeForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import { create, index } from '@/routes/employees';
import type { IdName, Option } from '@/types';

defineProps<{
    departments: IdName[];
    designations: IdName[];
    shifts: ShiftOption[];
    managers: IdName[];
    genders: Option[];
    statuses: Option[];
    employmentTypes: Option[];
    nextCode: string;
    today: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Active Employees', href: index() },
            { title: 'Add employee', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Add employee" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Add employee"
            description="Create the HR record. Work timing, salary and an existing borrow can be set up now or later."
        />

        <EmployeeForm
            mode="create"
            :departments="departments"
            :designations="designations"
            :shifts="shifts"
            :managers="managers"
            :genders="genders"
            :statuses="statuses"
            :employment-types="employmentTypes"
            :next-code="nextCode"
            :today="today"
        />
    </div>
</template>
