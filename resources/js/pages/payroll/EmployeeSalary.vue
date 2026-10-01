<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ArrowRight, History, TrendingUp } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmployeeCell from '@/components/EmployeeCell.vue';
import EmptyState from '@/components/EmptyState.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import SalaryComponentsEditor from '@/components/payroll/SalaryComponentsEditor.vue';
import type { SalaryComponentRow } from '@/components/payroll/SalaryComponentsEditor.vue';
import SectionCard from '@/components/SectionCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { show as employeeShow } from '@/routes/employees';
import { show, store } from '@/routes/employees/salary';
import { index as salaryIndex } from '@/routes/salary';
import type { BreadcrumbItem, EmployeeBrief } from '@/types';

type SalaryComponent = {
    code: string;
    name: string;
    type: string;
    amount: number;
};

type Revision = {
    id: number;
    effective_date: string;
    previous_gross: number;
    new_gross: number;
    previous_components: SalaryComponent[];
    components: SalaryComponent[];
    reason: string | null;
    notes: string | null;
    changed_by: string | null;
    created_at: string | null;
};

type HistoryRevision = Revision & {
    is_current: boolean;
    is_upcoming: boolean;
};

const props = defineProps<{
    employee: EmployeeBrief & { joining_date: string };
    current: Revision | null;
    revisions: HistoryRevision[];
    today: string;
}>();

setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
    breadcrumbs: [
        { title: 'Salary Structure', href: salaryIndex() },
        { title: props.employee.name, href: show(props.employee.id) },
    ],
});

const { money, date, dateTime, number } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('payroll.manage'));

const earnings = computed(
    () => props.current?.components.filter((c) => c.type === 'earning') ?? [],
);
const deductions = computed(
    () => props.current?.components.filter((c) => c.type === 'deduction') ?? [],
);
const deductionTotal = computed(() =>
    deductions.value.reduce((total, c) => total + Number(c.amount), 0),
);
const upcoming = computed(() =>
    props.revisions.filter((revision) => revision.is_upcoming),
);

const open = ref(false);
const form = useForm({
    effective_date: props.today,
    reason: '',
    notes: '',
    components: [] as SalaryComponentRow[],
});

/** A sensible starting structure for an employee who has no salary yet. */
const blankStructure = (): SalaryComponentRow[] => [
    { name: 'Basic', type: 'earning', amount: '' },
    { name: 'HRA', type: 'earning', amount: '' },
    { name: 'Other Allowance', type: 'earning', amount: '' },
];

function startRevision(): void {
    // Without a salary yet, the first one normally starts on the joining date.
    form.effective_date = props.current
        ? props.today
        : props.employee.joining_date;
    form.reason = props.current ? '' : 'Initial salary';
    form.notes = '';
    form.components = props.current
        ? props.current.components.map((component) => ({
              name: component.name,
              type: component.type === 'deduction' ? 'deduction' : 'earning',
              amount: component.amount,
          }))
        : blankStructure();
    form.clearErrors();
    open.value = true;
}

function submit(): void {
    form.post(store.url(props.employee.id), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const change = (revision: Revision): number =>
    Number(revision.new_gross) - Number(revision.previous_gross);

const changePercent = (revision: Revision): string | null =>
    Number(revision.previous_gross) > 0
        ? `${number((change(revision) / Number(revision.previous_gross)) * 100, 1)}%`
        : null;
</script>

<template>
    <Head :title="`Salary - ${employee.name}`" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Salary"
            description="The salary in effect today and every change made to it. A revision adds to the history; earlier revisions are never edited or overwritten, and payroll always uses the salary that was in effect for the pay period."
        >
            <Button v-if="canManage" @click="startRevision">
                <TrendingUp />
                {{ current ? 'Revise salary' : 'Set salary' }}
            </Button>
        </PageHeader>

        <div class="rounded-lg border bg-card p-4">
            <EmployeeCell
                :name="employee.name"
                :code="employee.code"
                :subtitle="
                    [employee.designation, employee.department]
                        .filter(Boolean)
                        .join(', ')
                "
                :photo-url="employee.photo_url"
                :href="
                    can('employees.view')
                        ? employeeShow(employee.id)
                        : undefined
                "
            />
        </div>

        <div
            class="grid items-start gap-5 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]"
        >
            <SectionCard
                title="Current salary"
                :description="
                    current
                        ? `In effect since ${date(current.effective_date)}`
                        : undefined
                "
                :flush="!!current"
            >
                <template v-if="current">
                    <table class="w-full text-sm">
                        <caption class="sr-only">
                            Monthly salary components
                        </caption>
                        <tbody>
                            <tr class="border-b bg-muted/40">
                                <th
                                    scope="colgroup"
                                    colspan="2"
                                    class="px-4 py-2 text-left text-xs font-medium text-muted-foreground sm:px-5"
                                >
                                    Earnings
                                </th>
                            </tr>
                            <tr
                                v-for="component in earnings"
                                :key="component.code"
                                class="border-b"
                            >
                                <th
                                    scope="row"
                                    class="px-4 py-2.5 text-left font-normal sm:px-5"
                                >
                                    {{ component.name }}
                                </th>
                                <td
                                    class="tabular px-4 py-2.5 text-right sm:px-5"
                                >
                                    {{ money(component.amount) }}
                                </td>
                            </tr>
                            <tr class="border-b">
                                <th
                                    scope="row"
                                    class="px-4 py-2.5 text-left font-semibold sm:px-5"
                                >
                                    Gross monthly salary
                                </th>
                                <td
                                    class="tabular px-4 py-2.5 text-right font-semibold sm:px-5"
                                >
                                    {{ money(current.new_gross) }}
                                </td>
                            </tr>
                            <template v-if="deductions.length > 0">
                                <tr class="border-b bg-muted/40">
                                    <th
                                        scope="colgroup"
                                        colspan="2"
                                        class="px-4 py-2 text-left text-xs font-medium text-muted-foreground sm:px-5"
                                    >
                                        Recurring deductions
                                    </th>
                                </tr>
                                <tr
                                    v-for="component in deductions"
                                    :key="component.code"
                                    class="border-b"
                                >
                                    <th
                                        scope="row"
                                        class="px-4 py-2.5 text-left font-normal sm:px-5"
                                    >
                                        {{ component.name }}
                                    </th>
                                    <td
                                        class="tabular px-4 py-2.5 text-right sm:px-5"
                                    >
                                        {{ money(-component.amount) }}
                                    </td>
                                </tr>
                                <tr>
                                    <th
                                        scope="row"
                                        class="px-4 py-2.5 text-left font-semibold sm:px-5"
                                    >
                                        After recurring deductions
                                    </th>
                                    <td
                                        class="tabular px-4 py-2.5 text-right font-semibold sm:px-5"
                                    >
                                        {{
                                            money(
                                                Number(current.new_gross) -
                                                    deductionTotal,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p
                        class="border-t px-4 py-3 text-xs text-muted-foreground sm:px-5"
                    >
                        Attendance, overtime, borrow and one-off deductions are
                        applied on top of this in each month's payroll.
                    </p>
                </template>

                <EmptyState
                    v-else
                    title="No salary is set"
                    description="Payroll cannot pay this employee until a salary structure is added."
                >
                    <Button v-if="canManage" size="sm" @click="startRevision">
                        Set salary
                    </Button>
                </EmptyState>

                <div
                    v-if="upcoming.length > 0"
                    class="border-t bg-info-soft px-4 py-3 text-sm text-info sm:px-5"
                    role="status"
                >
                    A revision to
                    {{ money(upcoming[upcoming.length - 1].new_gross) }} takes
                    effect on
                    {{ date(upcoming[upcoming.length - 1].effective_date) }}.
                </div>
            </SectionCard>

            <SectionCard
                title="Revision history"
                description="Newest first. Nothing here can be edited or removed."
            >
                <EmptyState
                    v-if="revisions.length === 0"
                    :icon="History"
                    title="No revisions yet"
                    description="The first salary you set becomes the first entry in this history."
                />

                <ol v-else class="relative ml-1.5 grid gap-6 border-l pl-6">
                    <li
                        v-for="revision in revisions"
                        :key="revision.id"
                        class="relative"
                    >
                        <span
                            class="absolute top-1.5 -left-[1.8rem] size-2.5 rounded-full border-2 border-card"
                            :class="
                                revision.is_current
                                    ? 'bg-primary'
                                    : 'bg-muted-foreground/50'
                            "
                            aria-hidden="true"
                        />

                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="tabular text-sm font-semibold">
                                {{ date(revision.effective_date) }}
                            </h3>
                            <StatusBadge
                                v-if="revision.is_current"
                                tone="positive"
                            >
                                Current
                            </StatusBadge>
                            <StatusBadge
                                v-else-if="revision.is_upcoming"
                                tone="info"
                            >
                                Upcoming
                            </StatusBadge>
                        </div>

                        <p
                            class="tabular mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm"
                        >
                            <template
                                v-if="Number(revision.previous_gross) > 0"
                            >
                                <span class="text-muted-foreground">
                                    {{ money(revision.previous_gross) }}
                                </span>
                                <ArrowRight
                                    class="size-3.5 text-muted-foreground"
                                    aria-label="changed to"
                                />
                            </template>
                            <span class="text-base font-semibold">
                                {{ money(revision.new_gross) }}
                            </span>
                            <span
                                v-if="Number(revision.previous_gross) > 0"
                                class="text-muted-foreground"
                            >
                                ({{ money(change(revision), { signed: true })
                                }}<template v-if="changePercent(revision)"
                                    >, {{ change(revision) > 0 ? '+' : ''
                                    }}{{ changePercent(revision) }}</template
                                >)
                            </span>
                            <span v-else class="text-muted-foreground">
                                Initial salary
                            </span>
                        </p>

                        <p v-if="revision.reason" class="mt-1 text-sm">
                            {{ revision.reason }}
                        </p>
                        <p
                            v-if="revision.notes"
                            class="mt-1 max-w-[70ch] text-sm whitespace-pre-line text-muted-foreground"
                        >
                            {{ revision.notes }}
                        </p>

                        <details class="group mt-2 text-sm">
                            <summary
                                class="w-fit cursor-pointer rounded-sm text-muted-foreground underline-offset-4 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                Components
                            </summary>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        New
                                    </p>
                                    <ul class="tabular mt-1 grid gap-1">
                                        <li
                                            v-for="component in revision.components"
                                            :key="component.code"
                                            class="flex justify-between gap-3"
                                        >
                                            <span>{{ component.name }}</span>
                                            <span>
                                                {{
                                                    money(
                                                        component.type ===
                                                            'deduction'
                                                            ? -component.amount
                                                            : component.amount,
                                                    )
                                                }}
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <div
                                    v-if="
                                        revision.previous_components.length > 0
                                    "
                                >
                                    <p class="text-xs text-muted-foreground">
                                        Previous
                                    </p>
                                    <ul
                                        class="tabular mt-1 grid gap-1 text-muted-foreground"
                                    >
                                        <li
                                            v-for="component in revision.previous_components"
                                            :key="component.code"
                                            class="flex justify-between gap-3"
                                        >
                                            <span>{{ component.name }}</span>
                                            <span>
                                                {{
                                                    money(
                                                        component.type ===
                                                            'deduction'
                                                            ? -component.amount
                                                            : component.amount,
                                                    )
                                                }}
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </details>

                        <p class="mt-2 text-xs text-muted-foreground">
                            Recorded
                            <template v-if="revision.changed_by">
                                by {{ revision.changed_by }}</template
                            >
                            on {{ dateTime(revision.created_at) }}
                        </p>
                    </li>
                </ol>
            </SectionCard>
        </div>

        <p class="text-sm">
            <Link
                :href="salaryIndex()"
                class="text-muted-foreground underline underline-offset-4 hover:text-foreground"
            >
                Back to salary structure
            </Link>
        </p>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
            <form class="grid gap-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>
                        {{ current ? 'Revise salary' : 'Set salary' }} for
                        {{ employee.name }}
                    </DialogTitle>
                    <DialogDescription>
                        This adds a new revision from the effective date. The
                        existing history stays exactly as it is, and payrolls
                        for earlier periods keep using the earlier salary.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Effective date"
                        for="revision-effective-date"
                        :error="errors.effective_date"
                        :hint="`On or after the joining date, ${date(employee.joining_date)}`"
                        required
                    >
                        <Input
                            id="revision-effective-date"
                            v-model="form.effective_date"
                            type="date"
                            :min="employee.joining_date"
                            required
                        />
                    </FormField>
                    <FormField
                        label="Reason"
                        for="revision-reason"
                        :error="errors.reason"
                    >
                        <Input
                            id="revision-reason"
                            v-model="form.reason"
                            maxlength="255"
                            placeholder="e.g. Annual increment, Promotion"
                        />
                    </FormField>
                </div>

                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">
                        Salary components
                    </legend>
                    <SalaryComponentsEditor
                        v-model="form.components"
                        :errors="errors"
                        id-prefix="revision-component"
                    />
                </fieldset>

                <FormField
                    label="Notes"
                    for="revision-notes"
                    :error="errors.notes"
                >
                    <Textarea
                        id="revision-notes"
                        v-model="form.notes"
                        rows="2"
                        maxlength="2000"
                    />
                </FormField>

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
                        Save revision
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
