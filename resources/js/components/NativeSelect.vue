<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Value = string | number | null;

const props = defineProps<{
    options: { value: string | number; label: string }[];
    /** Text of the empty choice. Leave out to require a choice. */
    placeholder?: string;
    id?: string;
    disabled?: boolean;
    invalid?: boolean;
    class?: HTMLAttributes['class'];
}>();

const model = defineModel<Value>({ default: null });

function onChange(event: Event): void {
    const raw = (event.target as HTMLSelectElement).value;

    // Hand back the option's own value so numeric ids stay numbers.
    const match = props.options.find((option) => String(option.value) === raw);

    model.value = match ? match.value : null;
}
</script>

<template>
    <select
        :id="id"
        :value="model ?? ''"
        :disabled="disabled"
        :aria-invalid="invalid || undefined"
        :class="
            cn(
                'h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive',
                props.class,
            )
        "
        @change="onChange"
    >
        <option v-if="placeholder !== undefined" value="">
            {{ placeholder }}
        </option>
        <option
            v-for="option in options"
            :key="option.value"
            :value="option.value"
        >
            {{ option.label }}
        </option>
    </select>
</template>
