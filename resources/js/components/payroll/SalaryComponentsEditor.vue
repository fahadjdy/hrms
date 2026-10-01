<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormat } from '@/composables/useFormat';

export type SalaryComponentRow = {
    name: string;
    type: 'earning' | 'deduction';
    amount: number | string;
};

const props = withDefaults(
    defineProps<{
        /** Validation errors keyed like `components.0.amount`. */
        errors?: Record<string, string | undefined>;
        /** The request key the rows are sent under. */
        errorPrefix?: string;
        /** Prefix for input ids, so two editors can share a page. */
        idPrefix?: string;
    }>(),
    { errors: () => ({}), errorPrefix: 'components', idPrefix: 'component' },
);

const rows = defineModel<SalaryComponentRow[]>({ required: true });

const { money } = useFormat();

const typeOptions = [
    { value: 'earning', label: 'Earning' },
    { value: 'deduction', label: 'Deduction' },
];

const sum = (type: SalaryComponentRow['type']): number =>
    rows.value
        .filter((row) => row.type === type)
        .reduce((total, row) => total + (Number(row.amount) || 0), 0);

const gross = computed(() => sum('earning'));
const deductions = computed(() => sum('deduction'));

function add(type: SalaryComponentRow['type']): void {
    rows.value = [...rows.value, { name: '', type, amount: '' }];
}

function remove(index: number): void {
    rows.value = rows.value.filter((_, position) => position !== index);
}

const errorFor = (index: number, field: string): string | undefined =>
    props.errors[`${props.errorPrefix}.${index}.${field}`];
</script>

<template>
    <div class="grid gap-3">
        <div
            class="hidden gap-2 text-xs text-muted-foreground sm:grid sm:grid-cols-[minmax(0,1fr)_8.5rem_9.5rem_2rem]"
            aria-hidden="true"
        >
            <span>Component</span>
            <span>Type</span>
            <span class="text-right">Monthly amount</span>
            <span />
        </div>

        <ul class="grid gap-3">
            <li v-for="(row, index) in rows" :key="index" class="grid gap-1.5">
                <div
                    class="grid grid-cols-[minmax(0,1fr)_2rem] gap-2 sm:grid-cols-[minmax(0,1fr)_8.5rem_9.5rem_2rem]"
                >
                    <Input
                        :id="`${idPrefix}-${index}-name`"
                        v-model="row.name"
                        :aria-label="`Component ${index + 1} name`"
                        :aria-invalid="!!errorFor(index, 'name') || undefined"
                        placeholder="e.g. Basic"
                        maxlength="100"
                        class="col-span-2 sm:col-span-1"
                    />
                    <NativeSelect
                        :model-value="row.type"
                        :options="typeOptions"
                        :aria-label="`Component ${index + 1} type`"
                        class="col-span-2 sm:col-span-1"
                        @update:model-value="
                            (value) =>
                                (row.type =
                                    value === 'deduction'
                                        ? 'deduction'
                                        : 'earning')
                        "
                    />
                    <Input
                        :id="`${idPrefix}-${index}-amount`"
                        v-model="row.amount"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        step="0.01"
                        :aria-label="`Component ${index + 1} monthly amount`"
                        :aria-invalid="!!errorFor(index, 'amount') || undefined"
                        placeholder="0.00"
                        class="tabular sm:text-right"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="`Remove component ${index + 1}${row.name ? `, ${row.name}` : ''}`"
                        :disabled="rows.length === 1"
                        @click="remove(index)"
                    >
                        <Trash2 />
                    </Button>
                </div>
                <InputError
                    :message="
                        errorFor(index, 'name') ??
                        errorFor(index, 'amount') ??
                        errorFor(index, 'type')
                    "
                />
            </li>
        </ul>

        <InputError :message="errors[errorPrefix]" />

        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="add('earning')"
            >
                <Plus />
                Add earning
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="add('deduction')"
            >
                <Plus />
                Add recurring deduction
            </Button>
        </div>

        <dl
            class="grid grid-cols-2 gap-3 rounded-md border bg-muted/40 p-3 text-sm"
            aria-live="polite"
        >
            <div>
                <dt class="text-xs text-muted-foreground">
                    Gross monthly salary
                </dt>
                <dd class="tabular text-base font-semibold">
                    {{ money(gross) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">
                    Recurring deductions
                </dt>
                <dd class="tabular text-base font-semibold">
                    {{ deductions > 0 ? money(-deductions) : money(0) }}
                </dd>
            </div>
        </dl>
    </div>
</template>
