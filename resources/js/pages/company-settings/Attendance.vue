<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionCard from '@/components/SectionCard.vue';
import ChoiceCards from '@/components/settings/ChoiceCards.vue';
import type { Choice } from '@/components/settings/ChoiceCards.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { edit, update } from '@/routes/settings/attendance';
import { index as workShifts } from '@/routes/work-shifts';
import type { Option } from '@/types';

type Shift = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    required_minutes: number;
};

const props = defineProps<{
    settings: {
        attendance_mode: string;
        default_work_shift_id: number | null;
        male_work_shift_id: number | null;
        female_work_shift_id: number | null;
        other_work_shift_id: number | null;
        grace_minutes: number;
        lates_per_half_day: number;
        short_hours_tolerance_minutes: number;
    };
    modes: Option[];
    shifts: Shift[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Attendance settings', href: edit() }],
    },
});

const { minutes, time } = useFormat();
const { can } = usePermissions();

const form = useForm({ ...props.settings });

const modeDescriptions: Record<string, string> = {
    automatic:
        'Working days are marked present automatically. You only record the exceptions: absences, leave, late arrivals and short hours.',
    manual: 'You mark attendance for every working day. A working day nobody marked is treated as absent when payroll is calculated.',
};

const modeChoices = computed<Choice[]>(() =>
    props.modes.map((mode) => ({
        value: mode.value,
        label: mode.label,
        description: modeDescriptions[mode.value] ?? '',
    })),
);

const shiftOptions = computed(() =>
    props.shifts.map((shift) => ({
        value: shift.id,
        label: `${shift.name} (${time(shift.start_time)} to ${time(shift.end_time)}, ${minutes(shift.required_minutes)})`,
    })),
);

const save = () => form.put(update.url(), { preserveScroll: true });
</script>

<template>
    <Head title="Attendance settings" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Attendance settings"
            description="How attendance is recorded, which work timing applies to whom, and when a day counts as late or short."
        />

        <form class="grid max-w-3xl gap-5" @submit.prevent="save">
            <SectionCard
                title="Attendance mode"
                description="Weekly offs, holidays and approved leave are filled in for you in both modes."
            >
                <ChoiceCards
                    v-model="form.attendance_mode"
                    name="attendance-mode"
                    legend="Attendance mode"
                    :choices="modeChoices"
                />
                <p
                    v-if="form.errors.attendance_mode"
                    class="mt-2 text-sm text-negative"
                >
                    {{ form.errors.attendance_mode }}
                </p>
            </SectionCard>

            <SectionCard
                title="Work timing"
                description="Timing is taken from the first rule that applies to the employee."
            >
                <ol
                    class="mb-5 grid gap-2 rounded-md bg-muted/60 p-3 text-sm sm:grid-cols-3"
                >
                    <li>
                        <span class="font-medium">1. Employee-specific</span>
                        <span class="block text-muted-foreground">
                            A shift set on the employee's profile
                        </span>
                    </li>
                    <li>
                        <span class="font-medium">2. Gender-based</span>
                        <span class="block text-muted-foreground">
                            The optional defaults below
                        </span>
                    </li>
                    <li>
                        <span class="font-medium">3. Company default</span>
                        <span class="block text-muted-foreground">
                            Everyone else
                        </span>
                    </li>
                </ol>

                <div class="grid gap-4">
                    <FormField
                        label="Company default shift"
                        for="default-shift"
                        :error="form.errors.default_work_shift_id"
                        required
                    >
                        <NativeSelect
                            id="default-shift"
                            v-model="form.default_work_shift_id"
                            :options="shiftOptions"
                            placeholder="Choose a shift"
                            :invalid="!!form.errors.default_work_shift_id"
                        />
                    </FormField>

                    <div>
                        <p class="text-sm font-medium">
                            Gender-based default timing
                        </p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            Optional. Use this only if your company's working
                            hours differ by gender as a company rule. Leave a
                            field on "Company default" to apply no separate
                            timing.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <FormField
                            label="Male staff"
                            for="male-shift"
                            :error="form.errors.male_work_shift_id"
                        >
                            <NativeSelect
                                id="male-shift"
                                v-model="form.male_work_shift_id"
                                :options="shiftOptions"
                                placeholder="Company default"
                            />
                        </FormField>
                        <FormField
                            label="Female staff"
                            for="female-shift"
                            :error="form.errors.female_work_shift_id"
                        >
                            <NativeSelect
                                id="female-shift"
                                v-model="form.female_work_shift_id"
                                :options="shiftOptions"
                                placeholder="Company default"
                            />
                        </FormField>
                        <FormField
                            label="Other staff"
                            for="other-shift"
                            :error="form.errors.other_work_shift_id"
                        >
                            <NativeSelect
                                id="other-shift"
                                v-model="form.other_work_shift_id"
                                :options="shiftOptions"
                                placeholder="Company default"
                            />
                        </FormField>
                    </div>

                    <p
                        v-if="can('attendance.view')"
                        class="text-sm text-muted-foreground"
                    >
                        Shifts are created and edited under
                        <Link
                            :href="workShifts()"
                            class="text-foreground underline underline-offset-4"
                        >
                            Work Shifts</Link
                        >.
                    </p>
                </div>
            </SectionCard>

            <SectionCard
                title="Late and short-hours rules"
                description="Applied when a day is marked with check-in and check-out times."
            >
                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField
                        label="Grace period (minutes)"
                        for="grace-minutes"
                        :error="form.errors.grace_minutes"
                        hint="Arriving within this many minutes of the shift start is not late"
                        required
                    >
                        <Input
                            id="grace-minutes"
                            v-model="form.grace_minutes"
                            type="number"
                            min="0"
                            max="240"
                            required
                            class="tabular"
                        />
                    </FormField>
                    <FormField
                        label="Late arrivals per half-day deduction"
                        for="lates-per-half-day"
                        :error="form.errors.lates_per_half_day"
                        hint="e.g. 3 deducts half a day for every 3 late days. 0 turns this off"
                        required
                    >
                        <Input
                            id="lates-per-half-day"
                            v-model="form.lates_per_half_day"
                            type="number"
                            min="0"
                            max="31"
                            required
                            class="tabular"
                        />
                    </FormField>
                    <FormField
                        label="Short-hours tolerance (minutes)"
                        for="short-tolerance"
                        :error="form.errors.short_hours_tolerance_minutes"
                        hint="A day short by this much or less is not counted as short"
                        required
                    >
                        <Input
                            id="short-tolerance"
                            v-model="form.short_hours_tolerance_minutes"
                            type="number"
                            min="0"
                            max="240"
                            required
                            class="tabular"
                        />
                    </FormField>
                </div>
            </SectionCard>

            <div>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Save attendance settings
                </Button>
            </div>
        </form>
    </div>
</template>
