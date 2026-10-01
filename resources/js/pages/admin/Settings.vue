<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionCard from '@/components/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { edit, update } from '@/routes/admin/settings';

const props = defineProps<{
    settings: {
        platform_name: string;
        support_email: string;
        default_currency: string;
        default_timezone: string;
        default_date_format: string;
    };
    timezones: string[];
    dateFormats: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Platform Settings', href: edit() }],
    },
});

const form = useForm({ ...props.settings });

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

/** How today's date reads in a format. */
function example(format: string): string {
    const today = new Date();
    const day = String(today.getDate()).padStart(2, '0');
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const year = String(today.getFullYear());

    switch (format) {
        case 'd/m/Y':
            return `${day}/${month}/${year}`;
        case 'm/d/Y':
            return `${month}/${day}/${year}`;
        case 'Y-m-d':
            return `${year}-${month}-${day}`;
        case 'd-m-Y':
            return `${day}-${month}-${year}`;
        default:
            return `${day} ${MONTHS[today.getMonth()]} ${year}`;
    }
}

const save = () => form.put(update.url(), { preserveScroll: true });
</script>

<template>
    <Head title="Platform Settings" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Platform Settings"
            description="Settings for the platform as a whole. Each company keeps its own settings for attendance, payroll and leave."
        />

        <form class="grid max-w-3xl gap-5" @submit.prevent="save">
            <SectionCard title="Platform">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Platform name"
                        for="platform-name"
                        :error="form.errors.platform_name"
                        required
                    >
                        <Input
                            id="platform-name"
                            v-model="form.platform_name"
                            required
                            maxlength="100"
                        />
                    </FormField>
                    <FormField
                        label="Support email"
                        for="support-email"
                        :error="form.errors.support_email"
                        hint="Where companies can reach the platform owner"
                    >
                        <Input
                            id="support-email"
                            v-model="form.support_email"
                            type="email"
                            maxlength="255"
                        />
                    </FormField>
                </div>
            </SectionCard>

            <SectionCard
                title="Defaults for new companies"
                description="Pre-filled when a company is added. Each company can change them afterwards."
            >
                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField
                        label="Currency"
                        for="default-currency"
                        :error="form.errors.default_currency"
                        hint="3-letter code, e.g. INR"
                        required
                    >
                        <Input
                            id="default-currency"
                            :model-value="form.default_currency"
                            required
                            minlength="3"
                            maxlength="3"
                            class="uppercase"
                            @update:model-value="
                                (value) =>
                                    (form.default_currency =
                                        String(value).toUpperCase())
                            "
                        />
                    </FormField>
                    <FormField
                        label="Timezone"
                        for="default-timezone"
                        :error="form.errors.default_timezone"
                        hint="Type to search"
                        required
                    >
                        <Input
                            id="default-timezone"
                            v-model="form.default_timezone"
                            required
                            list="default-timezones"
                            autocomplete="off"
                        />
                        <datalist id="default-timezones">
                            <option
                                v-for="timezone in timezones"
                                :key="timezone"
                                :value="timezone"
                            />
                        </datalist>
                    </FormField>
                    <FormField
                        label="Date format"
                        for="default-date-format"
                        :error="form.errors.default_date_format"
                        hint="Today, in each format"
                        required
                    >
                        <NativeSelect
                            id="default-date-format"
                            :model-value="form.default_date_format"
                            :options="
                                dateFormats.map((format) => ({
                                    value: format,
                                    label: example(format),
                                }))
                            "
                            @update:model-value="
                                (value) =>
                                    (form.default_date_format = String(
                                        value ?? 'd M Y',
                                    ))
                            "
                        />
                    </FormField>
                </div>
            </SectionCard>

            <div>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Save platform settings
                </Button>
            </div>
        </form>
    </div>
</template>
