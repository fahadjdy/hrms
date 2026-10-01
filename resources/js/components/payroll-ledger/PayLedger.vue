<script setup lang="ts">
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import type { PayrollBucketAmounts, PayrollLine, PayrollTotals } from '@/types';

const props = defineProps<{
    lines: PayrollLine[];
    buckets: Record<string, PayrollBucketAmounts>;
    totals: PayrollTotals;
}>();

const { money } = useFormat();

type Section = {
    title: string;
    /** +1 adds to pay, -1 reduces it. */
    sign: 1 | -1;
    buckets: string[];
    note?: string;
};

// The order a payslip is read in: what was earned, what was taken off, and
// only then the advance, which is money lent rather than money earned.
const sections: Section[] = [
    { title: 'Salary', sign: 1, buckets: ['gross_salary'] },
    {
        title: 'Additions',
        sign: 1,
        buckets: ['overtime_amount', 'bonus_amount', 'other_earnings_amount'],
    },
    {
        title: 'Deductions',
        sign: -1,
        buckets: [
            'attendance_deduction',
            'unpaid_leave_deduction',
            'short_hours_deduction',
            'borrow_recovery',
            'other_deductions',
        ],
    },
];

const linesOf = (bucket: string): PayrollLine[] =>
    props.lines.filter((line) => line.bucket === bucket);

const isUsed = (bucket: string): boolean => {
    const amounts = props.buckets[bucket];

    return (
        linesOf(bucket).length > 0 ||
        (amounts !== undefined &&
            (Number(amounts.system) !== 0 || Number(amounts.adjustment) !== 0))
    );
};

const visibleSections = computed(() =>
    sections
        .map((section) => ({
            ...section,
            buckets: section.buckets.filter(isUsed),
        }))
        // Salary is always shown, even at zero, so a missing salary is obvious.
        .filter(
            (section) =>
                section.buckets.length > 0 || section.title === 'Salary',
        ),
);

const advance = computed(() => props.buckets.borrow_given);
const hasAdvance = computed(() => isUsed('borrow_given'));

const signed = (amount: number | string, sign: 1 | -1): string =>
    money(Number(amount) * sign);

// Signed like the amounts beside it, so system + adjustment = final on every
// row: recovering 1,000 less of a borrow reads -3,000, +1,000, -2,000.
const adjustment = (amount: number | string, sign: 1 | -1): string =>
    Number(amount) === 0 ? '' : money(Number(amount) * sign, { signed: true });
</script>

<template>
    <div class="overflow-x-auto">
        <table class="tabular w-full min-w-[20rem] text-sm">
            <caption class="sr-only">
                Pay breakdown: the system-calculated amount, any admin
                adjustment and the final amount of every line
            </caption>
            <thead>
                <tr class="border-b text-xs text-muted-foreground">
                    <th scope="col" class="py-2 pr-3 text-left font-medium">
                        Description
                    </th>
                    <th
                        scope="col"
                        class="hidden px-3 py-2 text-right font-medium sm:table-cell"
                    >
                        System calculated
                    </th>
                    <th
                        scope="col"
                        class="hidden px-3 py-2 text-right font-medium sm:table-cell"
                    >
                        Admin adjustment
                    </th>
                    <th scope="col" class="py-2 pl-3 text-right font-medium">
                        Final amount
                    </th>
                </tr>
            </thead>

            <tbody v-for="section in visibleSections" :key="section.title">
                <tr>
                    <th
                        scope="colgroup"
                        colspan="4"
                        class="pt-5 pb-1 text-left text-xs font-semibold text-muted-foreground"
                    >
                        {{ section.title }}
                    </th>
                </tr>

                <tr v-if="section.buckets.length === 0">
                    <td colspan="4" class="py-2 text-muted-foreground">
                        No salary is set for this period.
                    </td>
                </tr>

                <template v-for="bucket in section.buckets" :key="bucket">
                    <tr class="border-t">
                        <th scope="row" class="py-2 pr-3 text-left font-medium">
                            {{ buckets[bucket].label }}
                            <!-- On a phone the two middle columns fold into this line. -->
                            <span
                                v-if="Number(buckets[bucket].adjustment) !== 0"
                                class="block text-xs font-normal text-muted-foreground sm:hidden"
                            >
                                System
                                {{
                                    signed(
                                        buckets[bucket].system,
                                        section.sign,
                                    )
                                }}, adjusted
                                {{
                                    adjustment(
                                        buckets[bucket].adjustment,
                                        section.sign,
                                    )
                                }}
                            </span>
                        </th>
                        <td class="hidden px-3 py-2 text-right sm:table-cell">
                            {{ signed(buckets[bucket].system, section.sign) }}
                        </td>
                        <td
                            class="hidden px-3 py-2 text-right sm:table-cell"
                            :class="
                                Number(buckets[bucket].adjustment) !== 0
                                    ? 'font-medium text-warning'
                                    : ''
                            "
                        >
                            {{
                                adjustment(
                                    buckets[bucket].adjustment,
                                    section.sign,
                                )
                            }}
                        </td>
                        <td class="py-2 pl-3 text-right font-medium">
                            {{ signed(buckets[bucket].final, section.sign) }}
                        </td>
                    </tr>
                    <tr v-for="line in linesOf(bucket)" :key="line.code">
                        <td class="py-1 pr-3 pl-4">
                            <span class="block">{{ line.label }}</span>
                            <span class="block text-xs text-muted-foreground">
                                {{ line.note }}
                            </span>
                        </td>
                        <td
                            class="hidden px-3 py-1 text-right align-top text-muted-foreground sm:table-cell"
                        >
                            {{ signed(line.amount, section.sign) }}
                        </td>
                        <td class="hidden sm:table-cell" />
                        <td
                            class="py-1 pl-3 text-right align-top text-muted-foreground sm:hidden"
                        >
                            {{ signed(line.amount, section.sign) }}
                        </td>
                        <td class="hidden sm:table-cell" />
                    </tr>
                </template>
            </tbody>

            <tbody>
                <tr class="border-t-2 border-foreground/40">
                    <th scope="row" class="py-2.5 pr-3 text-left font-semibold">
                        Net salary
                        <span
                            class="block text-xs font-normal text-muted-foreground"
                        >
                            Earnings {{ money(totals.total_earnings) }} less
                            deductions {{ money(totals.total_deductions) }}
                        </span>
                    </th>
                    <td class="hidden sm:table-cell" />
                    <td class="hidden sm:table-cell" />
                    <td class="py-2.5 pl-3 text-right align-top font-semibold">
                        {{ money(totals.net_salary) }}
                    </td>
                </tr>
            </tbody>

            <tbody v-if="hasAdvance">
                <tr>
                    <th
                        scope="colgroup"
                        colspan="4"
                        class="pt-5 pb-1 text-left text-xs font-semibold text-muted-foreground"
                    >
                        Borrow / advance given with this salary
                    </th>
                </tr>
                <tr class="border-t">
                    <th scope="row" class="py-2 pr-3 text-left font-medium">
                        {{ advance.label }}
                        <span
                            class="block text-xs font-normal text-muted-foreground"
                        >
                            An advance the employee repays. It is not salary
                            income.
                        </span>
                    </th>
                    <td
                        class="hidden px-3 py-2 text-right align-top sm:table-cell"
                    >
                        {{ money(advance.system, { signed: true }) }}
                    </td>
                    <td class="hidden sm:table-cell" />
                    <td class="py-2 pl-3 text-right align-top font-medium">
                        {{ money(advance.final, { signed: true }) }}
                    </td>
                </tr>
                <tr v-for="line in linesOf('borrow_given')" :key="line.code">
                    <td class="py-1 pr-3 pl-4">
                        <span class="block">{{ line.label }}</span>
                        <span class="block text-xs text-muted-foreground">{{
                            line.note
                        }}</span>
                    </td>
                    <td
                        class="hidden px-3 py-1 text-right align-top text-muted-foreground sm:table-cell"
                    >
                        {{ money(line.amount, { signed: true }) }}
                    </td>
                    <td class="hidden sm:table-cell" />
                    <td
                        class="py-1 pl-3 text-right align-top text-muted-foreground sm:hidden"
                    >
                        {{ money(line.amount, { signed: true }) }}
                    </td>
                    <td class="hidden sm:table-cell" />
                </tr>
            </tbody>

            <tfoot>
                <tr>
                    <td colspan="4" class="h-4" />
                </tr>
                <tr class="ledger-total text-base">
                    <th scope="row" class="py-3 pr-3 text-left font-semibold">
                        Net payable
                    </th>
                    <td class="hidden sm:table-cell" />
                    <td class="hidden sm:table-cell" />
                    <td class="py-3 pl-3 text-right font-semibold">
                        {{ money(totals.net_payable) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>
