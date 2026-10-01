<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import DatePicker from '@/components/DatePicker.vue';
import { Button } from '@/components/ui/button';

/** The selected month as `YYYY-MM`. */
const model = defineModel<string>({ required: true });

function shift(by: number): void {
    const [year, month] = model.value.split('-').map(Number);
    const next = new Date(year, month - 1 + by, 1);

    model.value = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`;
}
</script>

<template>
    <div class="flex items-center gap-1">
        <Button
            variant="outline"
            size="icon"
            type="button"
            aria-label="Previous month"
            @click="shift(-1)"
        >
            <ChevronLeft />
        </Button>
        <DatePicker
            v-model="model"
            mode="month"
            required
            aria-label="Month"
            class="w-44"
        />
        <Button
            variant="outline"
            size="icon"
            type="button"
            aria-label="Next month"
            @click="shift(1)"
        >
            <ChevronRight />
        </Button>
    </div>
</template>
