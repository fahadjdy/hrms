<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import SectionCard from '@/components/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useFormat } from '@/composables/useFormat';
import { useConfirmedAction } from '@/composables/useResourceForm';
import { destroy, store } from '@/routes/payroll/items/adjustments';

export type PayrollAdjustmentEntry = {
    id: number;
    bucket: string;
    bucket_label: string;
    amount: number;
    reason: string;
    user: string | null;
    created_at: string | null;
};

type AdjustableBucket = {
    value: string;
    label: string;
    is_deduction: boolean;
};

const props = defineProps<{
    payrollId: number;
    itemId: number;
    adjustments: PayrollAdjustmentEntry[];
    buckets: AdjustableBucket[];
    /** False when the payroll is finalized or the user may not adjust. */
    editable: boolean;
    locked: boolean;
}>();

const { money, dateTime } = useFormat();

const form = useForm({
    bucket: props.buckets[0]?.value ?? '',
    amount: '',
    reason: '',
});

const removal = useConfirmedAction<PayrollAdjustmentEntry>();

const selected = computed(() =>
    props.buckets.find((bucket) => bucket.value === form.bucket),
);

// The same sign means opposite things for an earning and a deduction, so spell it out.
const amountHint = computed(() =>
    selected.value?.is_deduction
        ? 'A positive amount deducts more; a negative amount deducts less.'
        : 'A positive amount pays more; a negative amount pays less.',
);

function add(): void {
    form.post(store.url({ payroll: props.payrollId, item: props.itemId }), {
        preserveScroll: true,
        onSuccess: () => form.reset('amount', 'reason'),
    });
}

const remove = (adjustment: PayrollAdjustmentEntry): void =>
    removal.run(
        'delete',
        destroy.url({
            payroll: props.payrollId,
            item: props.itemId,
            adjustment: adjustment.id,
        }),
    );
</script>

<template>
    <SectionCard
        title="Manual adjustments"
        description="Each adjustment is added to the system-calculated amount and kept with who made it, when and why."
    >
        <p
            v-if="adjustments.length === 0"
            class="text-sm text-muted-foreground"
        >
            No adjustments. Every amount is as the system calculated it.
        </p>
        <ul v-else class="grid gap-3">
            <li
                v-for="adjustment in adjustments"
                :key="adjustment.id"
                class="flex items-start justify-between gap-3 border-l-2 border-warning pl-3 text-sm"
            >
                <div class="min-w-0">
                    <p class="font-medium">
                        {{ adjustment.bucket_label }}
                        <span class="tabular ml-1">
                            {{ money(adjustment.amount, { signed: true }) }}
                        </span>
                    </p>
                    <p>{{ adjustment.reason }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ adjustment.user ?? 'System' }},
                        {{ dateTime(adjustment.created_at) }}
                    </p>
                </div>
                <Button
                    v-if="editable"
                    variant="ghost"
                    size="icon-sm"
                    :disabled="removal.processing.value"
                    :aria-label="`Remove the ${adjustment.bucket_label} adjustment`"
                    @click="remove(adjustment)"
                >
                    <Trash2 />
                </Button>
            </li>
        </ul>

        <form
            v-if="editable"
            class="mt-5 grid gap-4 border-t pt-5"
            @submit.prevent="add"
        >
            <h3 class="text-sm font-semibold">Add an adjustment</h3>

            <FormField
                label="What to adjust"
                for="adjust-bucket"
                :error="form.errors.bucket"
                required
            >
                <NativeSelect
                    id="adjust-bucket"
                    v-model="form.bucket"
                    :options="buckets"
                />
            </FormField>

            <FormField
                label="Amount"
                for="adjust-amount"
                :error="form.errors.amount"
                :hint="amountHint"
                required
            >
                <Input
                    id="adjust-amount"
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    inputmode="decimal"
                    required
                    placeholder="e.g. 500 or -500"
                />
            </FormField>

            <FormField
                label="Reason"
                for="adjust-reason"
                :error="form.errors.reason"
                required
            >
                <Input
                    id="adjust-reason"
                    v-model="form.reason"
                    required
                    minlength="3"
                    maxlength="255"
                />
            </FormField>

            <div>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Add adjustment
                </Button>
            </div>
        </form>

        <p
            v-else-if="locked"
            class="mt-4 border-t pt-4 text-sm text-muted-foreground"
        >
            This payroll is finalized and locked. Reopen the payroll to adjust
            it; reopening is recorded in the audit log.
        </p>
    </SectionCard>
</template>
