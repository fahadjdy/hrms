<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import EmployeeForm from '@/components/employees/EmployeeForm.vue';
import type {
    EditableEmployee,
    ShiftOption,
} from '@/components/employees/EmployeeForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import { edit, index, show } from '@/routes/employees';
import type { IdName, Option } from '@/types';

const props = defineProps<{
    employee: EditableEmployee;
    departments: IdName[];
    designations: IdName[];
    shifts: ShiftOption[];
    managers: IdName[];
    genders: Option[];
    statuses: Option[];
    employmentTypes: Option[];
}>();

const fullName =
    `${props.employee.first_name} ${props.employee.last_name ?? ''}`.trim();

setLayoutProps({
    breadcrumbs: [
        { title: 'Active Employees', href: index() },
        { title: fullName, href: show(props.employee.id) },
        { title: 'Edit', href: edit(props.employee.id) },
    ],
});
</script>

<template>
    <Head :title="`Edit ${fullName}`" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            :title="`Edit ${fullName}`"
            description="Update the employee's details. Work timing and salary are changed from the employee's profile, so their history is kept."
        />

        <EmployeeForm
            mode="edit"
            :employee="employee"
            :departments="departments"
            :designations="designations"
            :shifts="shifts"
            :managers="managers"
            :genders="genders"
            :statuses="statuses"
            :employment-types="employmentTypes"
        />
    </div>
</template>
