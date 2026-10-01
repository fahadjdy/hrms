<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Pencil, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataPagination from '@/components/DataPagination.vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import EmptyState from '@/components/EmptyState.vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { show as employeeShow } from '@/routes/employees';
import { index, update } from '@/routes/leave-balances';
import type { EmployeeBrief, IdName, Paginated } from '@/types';

type Balance = {
    leave_type_id: number;
    name: string;
    code: string;
    is_paid: boolean;
    allocated: number;
    adjustment: number;
    used: number;
    pending: number;
    remaining: number;
};

type EmployeeRow = EmployeeBrief & { balances: Balance[] };

const props = defineProps<{
    employees: Paginated<EmployeeRow>;
    year: number;
    filters: {
        search: string | null;
        department_id: number | string | null;
    };
    leaveTypes: { id: number; name: string; code: string }[];
    departments: IdName[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Leave Balance', href: index() }],
    },
});

const { number } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('leave.manage'));

const { filters, reset } = useQueryFilters(
    () => index.url(),
    {
        year: props.year,
        search: props.filters.search ?? '',
        department_id: props.filters.department_id
            ? Number(props.filters.department_id)
            : null,
    },
    { debounced: ['search'] },
);

/* Editing one balance */
const open = ref(false);
const editing = ref<{ employee: EmployeeRow; balance: Balance } | null>(null);

const form = useForm({
    employee_id: 0,
    leave_type_id: 0,
    year: props.year,
    allocated: 0,
    adjustment: 0,
});

function edit(employee: EmployeeRow, balance: Balance): void {
    editing.value = { employee, balance };
    form.clearErrors();
    form.employee_id = employee.id;
    form.leave_type_id = balance.leave_type_id;
    form.year = props.year;
    form.allocated = balance.allocated;
    form.adjustment = balance.adjustment;
    open.value = true;
}

/** What the balance will be once saved, so the effect is visible before saving. */
const remainingAfterSave = computed(() =>
    editing.value
        ? Number(form.allocated || 0) +
          Number(form.adjustment || 0) -
          editing.value.balance.used
        : 0,
);

function save(): void {
    form.put(update.url(), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Head title="Leave Balance" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Leave Balance"
            description="Remaining leave per employee and leave type. Remaining = allocated + adjustment - used. Used and pending days are always counted from the leave records."
        />

        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    type="button"
                    :aria-label="`Show ${year - 1}`"
                    @click="filters.year = year - 1"
                >
                    <ChevronLeft />
                </Button>
                <span
                    class="tabular min-w-16 text-center text-sm font-semibold"
                    aria-live="polite"
                >
                    {{ year }}
                </span>
                <Button
                    variant="outline"
                    size="icon"
                    type="button"
                    :aria-label="`Show ${year + 1}`"
                    @click="filters.year = year + 1"
                >
                    <ChevronRight />
                </Button>
            </div>
            <div class="grid flex-1 gap-2 sm:grid-cols-2 lg:max-w-2xl">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="filters.search"
                        type="search"
                        placeholder="Name, ID or email"
                        aria-label="Search employees"
                        class="pl-9"
                    />
                </div>
                <NativeSelect
                    v-model="filters.department_id"
                    :options="
                        departments.map((d) => ({ value: d.id, label: d.name }))
                    "
                    placeholder="All departments"
                    aria-label="Department"
                />
            </div>
        </div>

        <div v-if="leaveTypes.length === 0" class="rounded-lg border bg-card">
            <EmptyState
                title="No active leave types"
                description="Add at least one leave type before tracking balances."
            />
        </div>

        <div
            v-else-if="employees.data.length === 0"
            class="rounded-lg border bg-card"
        >
            <EmptyState
                title="No employees match"
                description="Try a different search, or clear the filters to see everyone."
            >
                <Button
                    variant="outline"
                    size="sm"
                    @click="
                        reset();
                        filters.year = year;
                    "
                >
                    Clear filters
                </Button>
            </EmptyState>
        </div>

        <ul v-else class="grid gap-3">
            <li
                v-for="employee in employees.data"
                :key="employee.id"
                class="rounded-lg border bg-card p-4"
            >
                <div class="grid gap-4 xl:grid-cols-[16rem_minmax(0,1fr)]">
                    <EmployeeCell
                        :name="employee.name"
                        :code="employee.code"
                        :subtitle="employee.department"
                        :photo-url="employee.photo_url"
                        :href="employeeShow(employee.id)"
                    />

                    <ul
                        class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <li
                            v-for="balance in employee.balances"
                            :key="balance.leave_type_id"
                            class="rounded-md border px-3 py-2.5"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-xs text-muted-foreground">
                                    {{ balance.name }}
                                </p>
                                <Button
                                    v-if="canManage"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="-mt-1.5 -mr-1.5"
                                    :aria-label="`Edit the ${balance.name} balance of ${employee.name}`"
                                    @click="edit(employee, balance)"
                                >
                                    <Pencil />
                                </Button>
                            </div>
                            <p
                                class="tabular text-lg leading-tight font-semibold"
                            >
                                <span
                                    :class="
                                        balance.remaining < 0
                                            ? 'text-negative'
                                            : ''
                                    "
                                >
                                    {{ number(balance.remaining) }}
                                </span>
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                >
                                    day{{ balance.remaining === 1 ? '' : 's' }}
                                    left
                                </span>
                            </p>
                            <dl
                                class="tabular mt-1.5 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-xs text-muted-foreground"
                            >
                                <dt>Allocated</dt>
                                <dd class="text-right">
                                    {{ number(balance.allocated) }}
                                </dd>
                                <template v-if="balance.adjustment !== 0">
                                    <dt>Adjustment</dt>
                                    <dd class="text-right">
                                        {{ balance.adjustment > 0 ? '+' : ''
                                        }}{{ number(balance.adjustment) }}
                                    </dd>
                                </template>
                                <dt>Used</dt>
                                <dd class="text-right">
                                    {{ number(balance.used) }}
                                </dd>
                                <template v-if="balance.pending > 0">
                                    <dt>Pending</dt>
                                    <dd class="text-right">
                                        {{ number(balance.pending) }}
                                    </dd>
                                </template>
                            </dl>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>

        <DataPagination :page="employees" noun="employees" />
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-5" @submit.prevent="save">
                <DialogHeader>
                    <DialogTitle>
                        Edit {{ editing?.balance.name }} balance
                    </DialogTitle>
                    <DialogDescription>
                        {{ editing?.employee.name }}, {{ year }}. The change is
                        recorded in the audit log.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-2 gap-4">
                    <FormField
                        label="Allocated days"
                        for="balance-allocated"
                        :error="form.errors.allocated"
                        required
                    >
                        <Input
                            id="balance-allocated"
                            v-model.number="form.allocated"
                            type="number"
                            min="0"
                            max="366"
                            step="0.5"
                            required
                            class="tabular"
                        />
                    </FormField>
                    <FormField
                        label="Adjustment"
                        for="balance-adjustment"
                        :error="form.errors.adjustment"
                        hint="Add or take away days, e.g. 2 or -1."
                        required
                    >
                        <Input
                            id="balance-adjustment"
                            v-model.number="form.adjustment"
                            type="number"
                            min="-366"
                            max="366"
                            step="0.5"
                            required
                            class="tabular"
                        />
                    </FormField>
                </div>

                <p
                    v-if="editing"
                    class="tabular rounded-md bg-muted px-3 py-2 text-sm"
                >
                    {{ number(form.allocated || 0) }} allocated
                    {{ Number(form.adjustment || 0) < 0 ? '-' : '+' }}
                    {{ number(Math.abs(Number(form.adjustment || 0))) }}
                    adjustment - {{ number(editing.balance.used) }} used =
                    <span class="font-semibold">
                        {{ number(remainingAfterSave) }} left
                    </span>
                </p>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        Save balance
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
