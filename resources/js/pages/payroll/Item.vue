<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FileText, Lock, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import DetailList from '@/components/DetailList.vue';
import PageHeader from '@/components/PageHeader.vue';
import AdjustmentPanel from '@/components/payroll-ledger/AdjustmentPanel.vue';
import type { PayrollAdjustmentEntry } from '@/components/payroll-ledger/AdjustmentPanel.vue';
import PayLedger from '@/components/payroll-ledger/PayLedger.vue';
import SectionCard from '@/components/SectionCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { statusTone } from '@/lib/status';
import { show as employeeShow } from '@/routes/employees';
import { show as attendanceShow } from '@/routes/employees/attendance';
import { index as payrollIndex, show as payrollShow } from '@/routes/payroll';
import { show as slipShow } from '@/routes/salary-slips';
import type {
    AttendanceSummary,
    PayrollBucketAmounts,
    PayrollLine,
    PayrollTotals,
} from '@/types';

const props = defineProps<{
    payroll: {
        id: number;
        label: string;
        status: string;
        status_label: string;
        is_locked: boolean;
        period_start: string;
        period_end: string;
    };
    item: {
        id: number;
        employee_id: number;
        employee_name: string;
        employee_code: string;
        department_name: string | null;
        designation_name: string | null;
        net_salary: number;
        net_payable: number;
        is_adjusted: boolean;
        slip_id: number | null;
    };
    salary: {
        method_label: string;
        divisor: number;
        gross: number;
        per_day_rate: number;
        hourly_rate: number;
        required_minutes_per_day: number;
        segments: {
            revision_id: number;
            effective_date: string;
            from: string;
            to: string;
            days: number;
            gross: number;
            reason: string | null;
        }[];
    } | null;
    attendance: Partial<AttendanceSummary>;
    lines: PayrollLine[];
    buckets: Record<string, PayrollBucketAmounts>;
    totals: PayrollTotals;
    warnings: string[];
    adjustments: PayrollAdjustmentEntry[];
    adjustableBuckets: {
        value: string;
        label: string;
        is_deduction: boolean;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Payroll', href: payrollIndex() }],
    },
});

const { money, minutes, date, percent } = useFormat();
const { can } = usePermissions();

const month = computed(() => props.payroll.period_start.slice(0, 7));

const attendanceItems = computed(() => {
    const summary = props.attendance;

    return [
        { label: 'Working days', value: summary.working_days ?? 0 },
        { label: 'Present', value: summary.present ?? 0 },
        { label: 'Absent', value: summary.absent ?? 0 },
        { label: 'Half day', value: summary.half_day ?? 0 },
        { label: 'Paid leave', value: summary.paid_leave ?? 0 },
        { label: 'Unpaid leave', value: summary.unpaid_leave ?? 0 },
        { label: 'Late', value: summary.late ?? 0 },
        { label: 'Not marked', value: summary.unmarked ?? 0 },
        { label: 'Required hours', value: minutes(summary.required_minutes) },
        { label: 'Worked hours', value: minutes(summary.worked_minutes) },
        { label: 'Short hours', value: minutes(summary.short_minutes) },
        { label: 'Overtime', value: minutes(summary.overtime_minutes) },
        { label: 'Attendance rate', value: percent(summary.attendance_rate) },
    ];
});

const salaryItems = computed(() =>
    props.salary
        ? [
              { label: 'Salary calculation', value: props.salary.method_label },
              {
                  label: 'Days the salary is divided by',
                  value: props.salary.divisor,
              },
              {
                  label: 'Rate per day',
                  value: money(props.salary.per_day_rate),
              },
              {
                  label: 'Rate per hour',
                  value: `${money(props.salary.hourly_rate)} (${minutes(props.salary.required_minutes_per_day)} day)`,
              },
          ]
        : [],
);
</script>

<template>
    <Head :title="`${item.employee_name} - ${payroll.label} pay`" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <div>
            <Button
                variant="ghost"
                size="sm"
                class="-ml-2 text-muted-foreground"
                as-child
            >
                <Link :href="payrollShow(payroll.id)">
                    <ArrowLeft />
                    {{ payroll.label }} payroll
                </Link>
            </Button>
        </div>

        <PageHeader
            :title="item.employee_name"
            :description="
                [
                    item.employee_code,
                    item.designation_name,
                    item.department_name,
                ]
                    .filter(Boolean)
                    .join(' / ')
            "
        >
            <StatusBadge :tone="statusTone('payroll', payroll.status)">
                <Lock v-if="payroll.is_locked" class="size-3" />
                {{ payroll.status_label }}
            </StatusBadge>
            <StatusBadge v-if="item.is_adjusted" tone="warning"
                >Adjusted</StatusBadge
            >
            <Button v-if="item.slip_id" variant="outline" as-child>
                <a
                    :href="slipShow.url(item.slip_id)"
                    target="_blank"
                    rel="noopener"
                >
                    <FileText />
                    Salary slip
                </a>
            </Button>
        </PageHeader>

        <div
            v-if="warnings.length"
            role="alert"
            class="flex items-start gap-3 rounded-lg border border-warning/40 bg-warning-soft p-4 text-sm text-warning"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            <ul class="grid gap-1">
                <li v-for="warning in warnings" :key="warning">
                    {{ warning }}
                </li>
            </ul>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <SectionCard
                :title="`Why ${item.employee_name} is paid ${money(item.net_payable)}`"
                :description="`Every amount for ${date(payroll.period_start)} to ${date(payroll.period_end)}, with how it was worked out.`"
            >
                <PayLedger :lines="lines" :buckets="buckets" :totals="totals" />
            </SectionCard>

            <div class="grid content-start gap-5">
                <AdjustmentPanel
                    :payroll-id="payroll.id"
                    :item-id="item.id"
                    :adjustments="adjustments"
                    :buckets="adjustableBuckets"
                    :editable="!payroll.is_locked && can('payroll.manage')"
                    :locked="payroll.is_locked"
                />

                <SectionCard
                    title="Salary used"
                    description="The salary in effect during this period and the rates derived from it"
                >
                    <p
                        v-if="!salary || salary.segments.length === 0"
                        class="text-sm text-warning"
                    >
                        No salary is set for this period. Add a salary structure
                        for this employee, then recalculate the payroll.
                    </p>
                    <template v-else>
                        <DetailList :items="salaryItems" />
                        <ul class="mt-4 grid gap-2 border-t pt-4 text-sm">
                            <li
                                v-for="segment in salary.segments"
                                :key="segment.revision_id"
                                class="flex items-baseline justify-between gap-3"
                            >
                                <span>
                                    {{ date(segment.from) }} to
                                    {{ date(segment.to) }}
                                    <span class="text-muted-foreground">
                                        ({{ segment.days }} days{{
                                            segment.reason
                                                ? `, ${segment.reason}`
                                                : ''
                                        }})
                                    </span>
                                </span>
                                <span class="tabular font-medium">
                                    {{ money(segment.gross) }} a month
                                </span>
                            </li>
                        </ul>
                    </template>
                </SectionCard>

                <SectionCard
                    title="Attendance in this period"
                    description="The attendance the calculation was based on"
                >
                    <template #actions>
                        <Button
                            v-if="can('attendance.view')"
                            variant="ghost"
                            size="sm"
                            as-child
                        >
                            <Link
                                :href="
                                    attendanceShow(item.employee_id, {
                                        query: { month },
                                    })
                                "
                            >
                                Open calendar
                            </Link>
                        </Button>
                    </template>
                    <DetailList :items="attendanceItems" :columns="3" />
                </SectionCard>

                <p v-if="can('employees.view')" class="text-sm">
                    <Link
                        :href="employeeShow(item.employee_id)"
                        class="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        Open {{ item.employee_name }}'s profile
                    </Link>
                </p>
            </div>
        </div>
    </div>
</template>
