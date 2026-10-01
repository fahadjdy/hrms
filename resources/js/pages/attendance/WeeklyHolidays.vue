<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionCard from '@/components/SectionCard.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { usePermissions } from '@/composables/usePermissions';
import { edit, update } from '@/routes/weekly-holidays';

const props = defineProps<{
    /** Weekly off days: 0 = Sunday … 6 = Saturday. */
    days: number[];
    workingDaysThisMonth: number;
    monthLabel: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Weekly Holidays', href: edit() }],
    },
});

const WEEKDAYS = [
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
    { value: 0, label: 'Sunday' },
];

const { can } = usePermissions();
const canManage = computed(() => can('attendance.manage'));

const form = useForm({
    days: [...props.days],
});

function setDay(day: number, off: boolean): void {
    form.days = off
        ? [...new Set([...form.days, day])].sort((a, b) => a - b)
        : form.days.filter((value) => value !== day);
}

const summary = computed(() => {
    const names = WEEKDAYS.filter((day) => form.days.includes(day.value)).map(
        (day) => day.label,
    );

    return names.length > 0
        ? `Weekly off: ${names.join(', ')}`
        : 'No weekly off: every day is a working day';
});

function save(): void {
    form.put(update.url(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Weekly Holidays" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Weekly Holidays"
            description="The days of the week the company is closed. They are never counted as absence, and they decide the working days used by attendance, payroll and the per-day salary rate."
        />

        <div class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <SectionCard title="Weekly off days" :description="summary">
                <form class="grid gap-5" @submit.prevent="save">
                    <ul class="grid gap-2 sm:grid-cols-2">
                        <li v-for="day in WEEKDAYS" :key="day.value">
                            <label
                                class="flex items-center justify-between gap-3 rounded-md border px-3 py-2.5 text-sm"
                                :class="
                                    form.days.includes(day.value)
                                        ? 'border-primary/40 bg-accent'
                                        : ''
                                "
                            >
                                <span>
                                    <span class="font-medium">
                                        {{ day.label }}
                                    </span>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{
                                            form.days.includes(day.value)
                                                ? 'Weekly off'
                                                : 'Working day'
                                        }}
                                    </span>
                                </span>
                                <Switch
                                    :model-value="form.days.includes(day.value)"
                                    :disabled="!canManage"
                                    :aria-label="`${day.label} is a weekly off`"
                                    @update:model-value="
                                        (off: boolean) => setDay(day.value, off)
                                    "
                                />
                            </label>
                        </li>
                    </ul>

                    <InputError :message="form.errors.days" />

                    <div
                        v-if="canManage"
                        class="flex flex-wrap items-center gap-3"
                    >
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.isDirty"
                        >
                            <Spinner v-if="form.processing" />
                            Save weekly holidays
                        </Button>
                        <p
                            v-if="form.isDirty"
                            class="text-sm text-muted-foreground"
                        >
                            You have unsaved changes.
                        </p>
                    </div>
                </form>
            </SectionCard>

            <div class="grid content-start gap-3">
                <StatCard
                    :label="`Working days in ${monthLabel}`"
                    :value="String(workingDaysThisMonth)"
                    hint="With the saved weekly offs and holidays"
                />
                <p class="text-sm text-muted-foreground">
                    Changing the weekly offs changes working days from now on
                    and for any payroll that is calculated again. Finalized
                    payrolls keep the amounts they were finalized with.
                </p>
            </div>
        </div>
    </div>
</template>
