<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import SectionCard from '@/components/SectionCard.vue';
import { Input } from '@/components/ui/input';

export type CompanyProfileData = {
    name: string;
    legal_name: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    state: string;
    country: string;
    postal_code: string;
    tax_id: string;
    currency: string;
    timezone: string;
    date_format: string;
    logo: File | null;
};

const props = defineProps<{
    errors: Partial<Record<string, string>>;
    timezones: string[];
    dateFormats: string[];
    /** The logo already saved for the company, if any. */
    logoUrl?: string | null;
    /** Prefix for the control ids, so two forms on a page never clash. */
    idPrefix?: string;
}>();

/** The form's data. Fields are edited in place. */
const form = defineModel<CompanyProfileData>({ required: true });

const id = (name: string): string => `${props.idPrefix ?? 'company'}-${name}`;

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

/** How today's date reads in each offered format. */
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

const dateFormatOptions = computed(() =>
    props.dateFormats.map((format) => ({
        value: format,
        label: example(format),
    })),
);

const moneyExample = computed(() => {
    try {
        return new Intl.NumberFormat(
            form.value.currency === 'INR' ? 'en-IN' : 'en-US',
            { style: 'currency', currency: form.value.currency },
        ).format(520000);
    } catch {
        return null;
    }
});

const preview = ref<string | null>(null);

function onLogoChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    form.value.logo = file;
    preview.value = file ? URL.createObjectURL(file) : null;
}

onBeforeUnmount(() => {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }
});
</script>

<template>
    <SectionCard
        title="Company"
        description="The name and logo appear in the sidebar and on salary slips."
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <FormField
                label="Company name"
                :for="id('name')"
                :error="errors.name"
                required
            >
                <Input
                    :id="id('name')"
                    v-model="form.name"
                    required
                    maxlength="255"
                    autocomplete="organization"
                />
            </FormField>
            <FormField
                label="Legal name"
                :for="id('legal-name')"
                :error="errors.legal_name"
                hint="As registered, if different from the company name"
            >
                <Input
                    :id="id('legal-name')"
                    v-model="form.legal_name"
                    maxlength="255"
                />
            </FormField>
            <FormField label="Email" :for="id('email')" :error="errors.email">
                <Input
                    :id="id('email')"
                    v-model="form.email"
                    type="email"
                    maxlength="255"
                    autocomplete="email"
                />
            </FormField>
            <FormField label="Phone" :for="id('phone')" :error="errors.phone">
                <Input
                    :id="id('phone')"
                    v-model="form.phone"
                    type="tel"
                    maxlength="30"
                    autocomplete="tel"
                />
            </FormField>
            <FormField
                label="GST / tax number"
                :for="id('tax-id')"
                :error="errors.tax_id"
            >
                <Input
                    :id="id('tax-id')"
                    v-model="form.tax_id"
                    maxlength="50"
                />
            </FormField>
            <FormField
                label="Logo"
                :for="id('logo')"
                :error="errors.logo"
                hint="JPG, PNG or WebP, up to 2 MB"
            >
                <div class="flex items-center gap-3">
                    <img
                        v-if="preview ?? logoUrl"
                        :src="(preview ?? logoUrl)!"
                        alt="Company logo"
                        class="size-10 shrink-0 rounded-md border bg-white object-contain"
                    />
                    <input
                        :id="id('logo')"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="block w-full min-w-0 text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:border-input file:bg-background file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-foreground"
                        @change="onLogoChange"
                    />
                </div>
            </FormField>
        </div>
    </SectionCard>

    <SectionCard title="Address">
        <div class="grid gap-4 sm:grid-cols-2">
            <FormField
                label="Address"
                :for="id('address')"
                :error="errors.address"
                class="sm:col-span-2"
            >
                <Input
                    :id="id('address')"
                    v-model="form.address"
                    maxlength="255"
                    autocomplete="street-address"
                />
            </FormField>
            <FormField label="City" :for="id('city')" :error="errors.city">
                <Input :id="id('city')" v-model="form.city" maxlength="100" />
            </FormField>
            <FormField label="State" :for="id('state')" :error="errors.state">
                <Input :id="id('state')" v-model="form.state" maxlength="100" />
            </FormField>
            <FormField
                label="Country"
                :for="id('country')"
                :error="errors.country"
            >
                <Input
                    :id="id('country')"
                    v-model="form.country"
                    maxlength="100"
                    autocomplete="country-name"
                />
            </FormField>
            <FormField
                label="Postal code"
                :for="id('postal-code')"
                :error="errors.postal_code"
            >
                <Input
                    :id="id('postal-code')"
                    v-model="form.postal_code"
                    maxlength="20"
                    autocomplete="postal-code"
                />
            </FormField>
        </div>
    </SectionCard>

    <SectionCard
        title="Regional settings"
        description="These decide how money and dates are shown, and when a working day starts and ends."
    >
        <div class="grid gap-4 sm:grid-cols-3">
            <FormField
                label="Currency"
                :for="id('currency')"
                :error="errors.currency"
                :hint="
                    moneyExample
                        ? `3-letter code. Shown as ${moneyExample}`
                        : '3-letter code, e.g. INR'
                "
                required
            >
                <Input
                    :id="id('currency')"
                    :model-value="form.currency"
                    required
                    minlength="3"
                    maxlength="3"
                    class="uppercase"
                    @update:model-value="
                        (value) => (form.currency = String(value).toUpperCase())
                    "
                />
            </FormField>
            <FormField
                label="Timezone"
                :for="id('timezone')"
                :error="errors.timezone"
                hint="Type to search, e.g. Asia/Kolkata"
                required
            >
                <Input
                    :id="id('timezone')"
                    v-model="form.timezone"
                    required
                    :list="id('timezones')"
                    autocomplete="off"
                />
                <datalist :id="id('timezones')">
                    <option
                        v-for="timezone in timezones"
                        :key="timezone"
                        :value="timezone"
                    />
                </datalist>
            </FormField>
            <FormField
                label="Date format"
                :for="id('date-format')"
                :error="errors.date_format"
                hint="Today, in each format"
                required
            >
                <NativeSelect
                    :id="id('date-format')"
                    :model-value="form.date_format"
                    :options="dateFormatOptions"
                    @update:model-value="
                        (value) => (form.date_format = String(value ?? 'd M Y'))
                    "
                />
            </FormField>
        </div>
    </SectionCard>
</template>
