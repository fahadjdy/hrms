<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import SectionCard from '@/components/SectionCard.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/composables/useFormat';
import { useInitials } from '@/composables/useInitials';
import { index, show, store, update } from '@/routes/employees';
import type { IdName, Option } from '@/types';

export type ShiftOption = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    required_minutes: number;
};

export type EditableEmployee = {
    id: number;
    employee_code: string;
    first_name: string;
    last_name: string | null;
    photo_url: string | null;
    date_of_birth: string | null;
    gender: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    postal_code: string | null;
    joining_date: string;
    department_id: number | null;
    designation_id: number | null;
    employment_type: string;
    reporting_manager_id: number | null;
    status: string;
    is_past: boolean;
    probation_end_date: string | null;
    notes: string | null;
};

type ComponentRow = {
    name: string;
    type: 'earning' | 'deduction';
    amount: number | string;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    employee?: EditableEmployee;
    departments: IdName[];
    designations: IdName[];
    shifts: ShiftOption[];
    managers: IdName[];
    genders: Option[];
    statuses: Option[];
    employmentTypes: Option[];
    /** Suggested employee ID for a new employee. */
    nextCode?: string;
    today?: string;
}>();

const { money, minutes } = useFormat();
const { getInitials } = useInitials();

const isCreate = computed(() => props.mode === 'create');

const form = useForm({
    employee_code: props.employee?.employee_code ?? props.nextCode ?? '',
    first_name: props.employee?.first_name ?? '',
    last_name: props.employee?.last_name ?? '',
    photo: null as File | null,
    date_of_birth: props.employee?.date_of_birth ?? '',
    gender: (props.employee?.gender ?? null) as string | null,
    phone: props.employee?.phone ?? '',
    email: props.employee?.email ?? '',
    address: props.employee?.address ?? '',
    city: props.employee?.city ?? '',
    state: props.employee?.state ?? '',
    country: props.employee?.country ?? '',
    postal_code: props.employee?.postal_code ?? '',
    joining_date: props.employee?.joining_date ?? props.today ?? '',
    department_id: (props.employee?.department_id ?? null) as number | null,
    designation_id: (props.employee?.designation_id ?? null) as number | null,
    employment_type: props.employee?.employment_type ?? 'full_time',
    reporting_manager_id: (props.employee?.reporting_manager_id ?? null) as
        | number
        | null,
    status: props.employee?.status ?? 'active',
    probation_end_date: props.employee?.probation_end_date ?? '',
    notes: props.employee?.notes ?? '',
    // Set up only while adding an employee.
    work_shift_id: null as number | null,
    salary_components: [
        { name: 'Basic', type: 'earning', amount: '' },
        { name: 'HRA', type: 'earning', amount: '' },
        { name: 'Other Allowance', type: 'earning', amount: '' },
    ] as ComponentRow[],
});

// Server messages for nested fields arrive under dotted keys such as
// `salary_components.0.amount`, which the typed form errors do not list.
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

/* Photo */
const photoPreview = ref<string | null>(props.employee?.photo_url ?? null);
let objectUrl: string | null = null;

function onPhotoChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }

    form.photo = file;

    if (file) {
        objectUrl = URL.createObjectURL(file);
        photoPreview.value = objectUrl;
    } else {
        photoPreview.value = props.employee?.photo_url ?? null;
    }
}

onBeforeUnmount(() => {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
    }
});

const displayName = computed(() =>
    `${form.first_name} ${form.last_name}`.trim(),
);

/* Work shift */
const shiftOptions = computed(() =>
    props.shifts.map((shift) => ({
        value: shift.id,
        label: `${shift.name} (${shift.start_time.slice(0, 5)} to ${shift.end_time.slice(0, 5)}, ${minutes(shift.required_minutes)} required)`,
    })),
);

const idOptions = (items: IdName[]) =>
    items.map((item) => ({ value: item.id, label: item.name }));

/* Salary components */
const componentTypes = [
    { value: 'earning', label: 'Earning' },
    { value: 'deduction', label: 'Deduction' },
];

const total = (type: ComponentRow['type']): number =>
    form.salary_components
        .filter((row) => row.type === type)
        .reduce((sum, row) => sum + (Number(row.amount) || 0), 0);

const grossTotal = computed(() => total('earning'));
const deductionTotal = computed(() => total('deduction'));

function addComponent(): void {
    form.salary_components.push({ name: '', type: 'earning', amount: '' });
}

function removeComponent(position: number): void {
    form.salary_components.splice(position, 1);
}

// Rows without an amount are not sent, so the server numbers the rest from
// zero. This maps a row on screen back to its position in what was sent.
const submittedRows = ref<number[]>([]);

function rowError(
    position: number,
    field: 'name' | 'type' | 'amount',
): string | undefined {
    const sent = submittedRows.value.indexOf(position);

    return sent === -1
        ? undefined
        : errors.value[`salary_components.${sent}.${field}`];
}

/* Existing borrow */
const hasBorrow = ref(false);
const borrowAmountError = ref<string>();
const borrow = reactive({
    amount: '' as number | string,
    opening_balance: '' as number | string,
    borrow_date: '',
    reason: '',
    monthly_deduction: '' as number | string,
    installments_count: '' as number | string,
    deduction_start_month: '',
    source_reference: '',
    notes: '',
});

const borrowError = (field: string): string | undefined =>
    errors.value[`existing_borrow.${field}`];

function submit(): void {
    if (!isCreate.value && props.employee) {
        form.transform((data) => {
            const profile: Record<string, unknown> = { ...data };

            delete profile.work_shift_id;
            delete profile.salary_components;

            // Files can only be sent with POST, so the update is a spoofed PUT.
            return { ...profile, _method: 'put' };
        }).post(update.url(props.employee.id), { forceFormData: true });

        return;
    }

    borrowAmountError.value = undefined;

    if (hasBorrow.value && !(Number(borrow.amount) > 0)) {
        borrowAmountError.value =
            'Enter the borrow amount, or switch this section off.';

        return;
    }

    submittedRows.value = form.salary_components
        .map((row, position) => ({ row, position }))
        .filter(({ row }) => Number(row.amount) > 0)
        .map(({ position }) => position);

    form.transform((data) => ({
        ...data,
        salary_components: submittedRows.value.map(
            (position) => data.salary_components[position],
        ),
        existing_borrow: hasBorrow.value
            ? {
                  ...borrow,
                  deduction_start_month: borrow.deduction_start_month
                      ? `${borrow.deduction_start_month}-01`
                      : null,
              }
            : null,
    })).post(store.url());
}
</script>

<template>
    <form class="flex flex-col gap-5" novalidate @submit.prevent="submit">
        <SectionCard
            title="Personal information"
            description="Who the employee is and how to reach them."
        >
            <div class="flex flex-col gap-5">
                <div class="flex flex-wrap items-center gap-4">
                    <Avatar class="size-16 rounded-md">
                        <AvatarImage
                            v-if="photoPreview"
                            :src="photoPreview"
                            alt=""
                        />
                        <AvatarFallback class="rounded-md">
                            {{ getInitials(displayName) || '?' }}
                        </AvatarFallback>
                    </Avatar>
                    <FormField
                        label="Profile photo"
                        for="employee-photo"
                        :error="errors.photo"
                        hint="JPG, PNG or WebP, up to 2 MB."
                        class="min-w-0 flex-1 sm:max-w-sm"
                    >
                        <input
                            id="employee-photo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="block w-full min-w-0 rounded-md border border-input bg-transparent text-sm shadow-xs outline-none file:mr-3 file:h-9 file:border-0 file:border-r file:border-input file:bg-muted file:px-3 file:text-sm file:font-medium file:text-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            @change="onPhotoChange"
                        />
                    </FormField>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <FormField
                        label="Employee ID"
                        for="employee-code"
                        :error="errors.employee_code"
                        hint="Unique within the company."
                        required
                    >
                        <Input
                            id="employee-code"
                            v-model="form.employee_code"
                            required
                            maxlength="50"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        label="First name"
                        for="employee-first-name"
                        :error="errors.first_name"
                        required
                    >
                        <Input
                            id="employee-first-name"
                            v-model="form.first_name"
                            required
                            maxlength="100"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        label="Last name"
                        for="employee-last-name"
                        :error="errors.last_name"
                    >
                        <Input
                            id="employee-last-name"
                            v-model="form.last_name"
                            maxlength="100"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        label="Date of birth"
                        for="employee-dob"
                        :error="errors.date_of_birth"
                    >
                        <Input
                            id="employee-dob"
                            v-model="form.date_of_birth"
                            type="date"
                        />
                    </FormField>
                    <FormField
                        label="Gender"
                        for="employee-gender"
                        :error="errors.gender"
                        hint="Only used to pick a gender-based work shift, if the company has one."
                    >
                        <NativeSelect
                            id="employee-gender"
                            v-model="form.gender"
                            :options="genders"
                            placeholder="Not specified"
                        />
                    </FormField>
                    <FormField
                        label="Phone"
                        for="employee-phone"
                        :error="errors.phone"
                    >
                        <Input
                            id="employee-phone"
                            v-model="form.phone"
                            type="tel"
                            maxlength="30"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        label="Email"
                        for="employee-email"
                        :error="errors.email"
                    >
                        <Input
                            id="employee-email"
                            v-model="form.email"
                            type="email"
                            maxlength="255"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        label="Address"
                        for="employee-address"
                        :error="errors.address"
                        class="sm:col-span-2"
                    >
                        <Input
                            id="employee-address"
                            v-model="form.address"
                            maxlength="255"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        label="City"
                        for="employee-city"
                        :error="errors.city"
                    >
                        <Input
                            id="employee-city"
                            v-model="form.city"
                            maxlength="100"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        label="State"
                        for="employee-state"
                        :error="errors.state"
                    >
                        <Input
                            id="employee-state"
                            v-model="form.state"
                            maxlength="100"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        label="Country"
                        for="employee-country"
                        :error="errors.country"
                    >
                        <Input
                            id="employee-country"
                            v-model="form.country"
                            maxlength="100"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        label="Postal code"
                        for="employee-postal-code"
                        :error="errors.postal_code"
                    >
                        <Input
                            id="employee-postal-code"
                            v-model="form.postal_code"
                            maxlength="20"
                            autocomplete="off"
                        />
                    </FormField>
                </div>
            </div>
        </SectionCard>

        <SectionCard
            title="Employment"
            description="Role, reporting line and where the employee stands today."
        >
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <FormField
                    label="Joining date"
                    for="employee-joining-date"
                    :error="errors.joining_date"
                    hint="Attendance and salary start from this date."
                    required
                >
                    <Input
                        id="employee-joining-date"
                        v-model="form.joining_date"
                        type="date"
                        required
                    />
                </FormField>
                <FormField
                    label="Department"
                    for="employee-department"
                    :error="errors.department_id"
                >
                    <NativeSelect
                        id="employee-department"
                        v-model="form.department_id"
                        :options="idOptions(departments)"
                        placeholder="No department"
                    />
                </FormField>
                <FormField
                    label="Designation"
                    for="employee-designation"
                    :error="errors.designation_id"
                >
                    <NativeSelect
                        id="employee-designation"
                        v-model="form.designation_id"
                        :options="idOptions(designations)"
                        placeholder="No designation"
                    />
                </FormField>
                <FormField
                    label="Employment type"
                    for="employee-employment-type"
                    :error="errors.employment_type"
                    required
                >
                    <NativeSelect
                        id="employee-employment-type"
                        v-model="form.employment_type"
                        :options="employmentTypes"
                    />
                </FormField>
                <FormField
                    label="Reporting manager"
                    for="employee-manager"
                    :error="errors.reporting_manager_id"
                >
                    <NativeSelect
                        id="employee-manager"
                        v-model="form.reporting_manager_id"
                        :options="idOptions(managers)"
                        placeholder="No reporting manager"
                    />
                </FormField>
                <FormField
                    v-if="!employee?.is_past"
                    label="Employee status"
                    for="employee-status"
                    :error="errors.status"
                    required
                >
                    <NativeSelect
                        id="employee-status"
                        v-model="form.status"
                        :options="statuses"
                    />
                </FormField>
                <div v-else class="grid content-start gap-1.5 text-sm">
                    <span class="font-medium">Employee status</span>
                    <p class="text-muted-foreground">
                        Past employee. The status only changes by reinstating
                        the employee from their profile.
                    </p>
                </div>
                <FormField
                    label="Probation ends on"
                    for="employee-probation-end"
                    :error="errors.probation_end_date"
                    hint="Leave empty if there is no probation."
                >
                    <Input
                        id="employee-probation-end"
                        v-model="form.probation_end_date"
                        type="date"
                        :min="form.joining_date || undefined"
                    />
                </FormField>
                <FormField
                    label="Notes"
                    for="employee-notes"
                    :error="errors.notes"
                    class="sm:col-span-2 lg:col-span-3"
                >
                    <Textarea
                        id="employee-notes"
                        v-model="form.notes"
                        rows="3"
                        maxlength="5000"
                    />
                </FormField>
            </div>
        </SectionCard>

        <template v-if="isCreate">
            <SectionCard
                title="Work timing"
                description="Choose a shift only if this employee works different hours from everyone else."
            >
                <FormField
                    label="Employee-specific work shift"
                    for="employee-work-shift"
                    :error="errors.work_shift_id"
                    hint="Leave this on the default and the gender-based shift applies if the company has one, otherwise the company default shift. You can change it later from the employee's profile."
                    class="max-w-xl"
                >
                    <NativeSelect
                        id="employee-work-shift"
                        v-model="form.work_shift_id"
                        :options="shiftOptions"
                        placeholder="Use the default shift (gender or company)"
                    />
                </FormField>
            </SectionCard>

            <SectionCard
                title="Salary"
                description="Monthly salary from the joining date. Rows without an amount are skipped; you can also set the salary later."
            >
                <div class="flex flex-col gap-3">
                    <div
                        v-for="(row, position) in form.salary_components"
                        :key="position"
                        class="grid gap-3 rounded-md border p-3 sm:grid-cols-[minmax(0,1fr)_10rem_10rem_auto] sm:items-start sm:border-0 sm:p-0"
                    >
                        <FormField
                            label="Component"
                            :for="`salary-name-${position}`"
                            :error="rowError(position, 'name')"
                        >
                            <Input
                                :id="`salary-name-${position}`"
                                v-model="row.name"
                                maxlength="100"
                                placeholder="e.g. Conveyance"
                            />
                        </FormField>
                        <FormField
                            label="Type"
                            :for="`salary-type-${position}`"
                            :error="rowError(position, 'type')"
                        >
                            <NativeSelect
                                :id="`salary-type-${position}`"
                                v-model="row.type"
                                :options="componentTypes"
                            />
                        </FormField>
                        <FormField
                            label="Monthly amount"
                            :for="`salary-amount-${position}`"
                            :error="rowError(position, 'amount')"
                        >
                            <Input
                                :id="`salary-amount-${position}`"
                                v-model="row.amount"
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="decimal"
                                class="tabular text-right"
                            />
                        </FormField>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="justify-self-end sm:mt-[1.375rem]"
                            :aria-label="`Remove ${row.name || 'this component'}`"
                            @click="removeComponent(position)"
                        >
                            <Trash2 />
                        </Button>
                    </div>

                    <p
                        v-if="errors.salary_components || errors.components"
                        class="text-sm text-negative"
                    >
                        {{ errors.salary_components ?? errors.components }}
                    </p>

                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t pt-3"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addComponent"
                        >
                            <Plus />
                            Add component
                        </Button>
                        <dl
                            class="tabular flex flex-wrap gap-x-6 gap-y-1 text-sm"
                            aria-live="polite"
                        >
                            <div
                                v-if="deductionTotal > 0"
                                class="flex items-baseline gap-2"
                            >
                                <dt class="text-muted-foreground">
                                    Recurring deductions
                                </dt>
                                <dd>{{ money(deductionTotal) }}</dd>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <dt class="text-muted-foreground">
                                    Gross salary per month
                                </dt>
                                <dd class="text-base font-semibold">
                                    {{ money(grossTotal) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </SectionCard>

            <SectionCard
                title="Existing borrow / advance"
                description="For an employee who joins already owing money, such as an advance carried over. It is recorded as its own borrow and recovered through payroll."
            >
                <template #actions>
                    <label class="flex items-center gap-2 text-sm">
                        <Switch v-model="hasBorrow" />
                        Has an existing borrow
                    </label>
                </template>

                <p v-if="!hasBorrow" class="text-sm text-muted-foreground">
                    No existing borrow will be recorded. New borrows can be
                    added at any time from Employee Finance.
                </p>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <FormField
                        label="Original borrow amount"
                        for="borrow-amount"
                        :error="borrowAmountError ?? borrowError('amount')"
                        required
                    >
                        <Input
                            id="borrow-amount"
                            v-model="borrow.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            class="tabular"
                        />
                    </FormField>
                    <FormField
                        label="Outstanding balance at joining"
                        for="borrow-opening-balance"
                        :error="borrowError('opening_balance')"
                        hint="Leave empty if nothing has been repaid yet."
                    >
                        <Input
                            id="borrow-opening-balance"
                            v-model="borrow.opening_balance"
                            type="number"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            class="tabular"
                        />
                    </FormField>
                    <FormField
                        label="Original borrow date"
                        for="borrow-date"
                        :error="borrowError('borrow_date')"
                        hint="Defaults to the joining date."
                    >
                        <Input
                            id="borrow-date"
                            v-model="borrow.borrow_date"
                            type="date"
                        />
                    </FormField>
                    <FormField
                        label="Monthly deduction"
                        for="borrow-monthly-deduction"
                        :error="borrowError('monthly_deduction')"
                        hint="Recovered from each month's salary."
                    >
                        <Input
                            id="borrow-monthly-deduction"
                            v-model="borrow.monthly_deduction"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            class="tabular"
                        />
                    </FormField>
                    <FormField
                        label="Number of installments"
                        for="borrow-installments"
                        :error="borrowError('installments_count')"
                        hint="Used to work out the monthly deduction when that is left empty."
                    >
                        <Input
                            id="borrow-installments"
                            v-model="borrow.installments_count"
                            type="number"
                            min="1"
                            max="600"
                            step="1"
                            inputmode="numeric"
                            class="tabular"
                        />
                    </FormField>
                    <FormField
                        label="Start deducting from"
                        for="borrow-start-month"
                        :error="borrowError('deduction_start_month')"
                        hint="Defaults to the month after the borrow date."
                    >
                        <Input
                            id="borrow-start-month"
                            v-model="borrow.deduction_start_month"
                            type="month"
                        />
                    </FormField>
                    <FormField
                        label="Reason"
                        for="borrow-reason"
                        :error="borrowError('reason')"
                    >
                        <Input
                            id="borrow-reason"
                            v-model="borrow.reason"
                            maxlength="255"
                        />
                    </FormField>
                    <FormField
                        label="Source / reference"
                        for="borrow-source"
                        :error="borrowError('source_reference')"
                        hint="e.g. previous employer or an agreement number."
                        class="sm:col-span-1 lg:col-span-2"
                    >
                        <Input
                            id="borrow-source"
                            v-model="borrow.source_reference"
                            maxlength="255"
                        />
                    </FormField>
                    <FormField
                        label="Notes"
                        for="borrow-notes"
                        :error="borrowError('notes')"
                        class="sm:col-span-2 lg:col-span-3"
                    >
                        <Textarea
                            id="borrow-notes"
                            v-model="borrow.notes"
                            rows="2"
                            maxlength="2000"
                        />
                    </FormField>
                </div>
            </SectionCard>
        </template>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <p
                v-if="form.hasErrors"
                role="alert"
                class="mr-auto text-sm text-negative"
            >
                Some fields need attention. Check the messages above.
            </p>
            <Button variant="outline" as-child>
                <Link :href="employee ? show(employee.id) : index()">
                    Cancel
                </Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ isCreate ? 'Add employee' : 'Save changes' }}
            </Button>
        </div>
    </form>
</template>
