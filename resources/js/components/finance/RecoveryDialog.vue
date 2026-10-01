<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
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
import { store } from '@/routes/borrows/recoveries';

export type RecoverableBorrow = {
    id: number;
    label: string;
    outstanding: number;
};

const props = defineProps<{
    /** The borrows a recovery can be recorded against. */
    borrows: RecoverableBorrow[];
    /** Set when the dialog belongs to one borrow, so there is nothing to pick. */
    borrowId?: number;
    /** The latest date a recovery may carry (the company's today). */
    today: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const { money } = useFormat();

const form = useForm({
    borrow_id: (props.borrowId ?? null) as number | null,
    amount: '' as number | string,
    date: props.today,
    notes: '',
});

const selected = computed(
    () => props.borrows.find((borrow) => borrow.id === form.borrow_id) ?? null,
);

const remaining = computed(() =>
    selected.value
        ? Math.max(0, selected.value.outstanding - Number(form.amount || 0))
        : 0,
);

// Start from a clean form each time the dialog opens.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.reset();
    form.clearErrors();
    form.borrow_id = props.borrowId ?? null;
    form.date = props.today;
});

function submit(): void {
    if (form.borrow_id === null) {
        form.setError('borrow_id', 'Choose the borrow this recovery is for.');

        return;
    }

    form.transform((data) => ({
        amount: data.amount,
        date: data.date,
        notes: data.notes,
    })).post(store.url(form.borrow_id), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Record recovery</DialogTitle>
                    <DialogDescription>
                        For money the employee paid back outside payroll, such
                        as cash returned. Recoveries deducted from salary are
                        recorded automatically when a payroll is finalized.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    v-if="!borrowId"
                    label="Borrow"
                    for="recovery-borrow"
                    :error="form.errors.borrow_id"
                    required
                >
                    <NativeSelect
                        id="recovery-borrow"
                        v-model="form.borrow_id"
                        :options="
                            borrows.map((borrow) => ({
                                value: borrow.id,
                                label: `${borrow.label} (${money(borrow.outstanding)} outstanding)`,
                            }))
                        "
                        placeholder="Choose an active borrow"
                        :invalid="!!form.errors.borrow_id"
                    />
                </FormField>

                <p
                    v-if="selected"
                    class="rounded-md bg-warning-soft px-3 py-2 text-sm text-warning"
                >
                    Outstanding before this recovery:
                    <span class="tabular font-medium">
                        {{ money(selected.outstanding) }}
                    </span>
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Amount recovered"
                        for="recovery-amount"
                        :error="form.errors.amount"
                        :hint="
                            selected && Number(form.amount) > 0
                                ? `${money(remaining)} will remain outstanding`
                                : undefined
                        "
                        required
                    >
                        <Input
                            id="recovery-amount"
                            v-model="form.amount"
                            type="number"
                            inputmode="decimal"
                            min="0.01"
                            step="0.01"
                            :max="selected?.outstanding"
                            required
                            class="tabular text-right"
                        />
                    </FormField>

                    <FormField
                        label="Date"
                        for="recovery-date"
                        :error="form.errors.date"
                        required
                    >
                        <DatePicker
                            id="recovery-date"
                            v-model="form.date"
                            :max="today"
                            required
                        />
                    </FormField>
                </div>

                <FormField
                    label="Notes"
                    for="recovery-notes"
                    :error="form.errors.notes"
                    hint="For example: cash returned, bank transfer reference."
                >
                    <Input
                        id="recovery-notes"
                        v-model="form.notes"
                        maxlength="255"
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
                        Record recovery
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
