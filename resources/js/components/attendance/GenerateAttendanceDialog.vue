<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/attendance/generate';

const props = defineProps<{
    /** The date being viewed; the range defaults to its month up to today. */
    date: string;
    today: string;
    /** `automatic` or `manual`, to explain what will be generated. */
    mode: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    start_date: '',
    end_date: '',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    const end = props.date > props.today ? props.today : props.date;

    form.clearErrors();
    form.start_date = `${end.slice(0, 7)}-01`;
    form.end_date = end;
});

function generate(): void {
    form.post(store.url(), {
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
            <form class="grid gap-5" @submit.prevent="generate">
                <DialogHeader>
                    <DialogTitle>Generate attendance</DialogTitle>
                    <DialogDescription>
                        <template v-if="mode === 'automatic'">
                            Stores weekly offs, holidays and present days for
                            every employee in the date range.
                        </template>
                        <template v-else>
                            Stores weekly offs and holidays for every employee
                            in the date range. Working days stay unmarked until
                            you mark them.
                        </template>
                        Days that already have a record are never overwritten.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField
                        label="From"
                        for="generate-start"
                        :error="form.errors.start_date"
                        required
                    >
                        <DatePicker
                            id="generate-start"
                            v-model="form.start_date"
                            :max="today"
                            required
                        />
                    </FormField>
                    <FormField
                        label="To"
                        for="generate-end"
                        :error="form.errors.end_date"
                        required
                    >
                        <DatePicker
                            id="generate-end"
                            v-model="form.end_date"
                            :min="form.start_date"
                            :max="today"
                            required
                        />
                    </FormField>
                </div>

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
                        Generate attendance
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
