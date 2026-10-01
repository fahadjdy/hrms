<script setup lang="ts">
export type Choice = {
    value: string;
    label: string;
    /** One or two sentences on what choosing this means. */
    description: string;
    /** A small worked example, shown under the description. */
    example?: string;
};

defineProps<{
    /** Shared `name` of the radio inputs, unique on the page. */
    name: string;
    /** What the group asks, for screen readers. */
    legend: string;
    choices: Choice[];
    /** Number of cards per row on wide screens. */
    columns?: 2 | 3;
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <!-- A single choice among a few options, each explained where it is chosen. -->
    <fieldset>
        <legend class="sr-only">{{ legend }}</legend>
        <div
            class="grid gap-3"
            :class="columns === 3 ? 'lg:grid-cols-3' : 'sm:grid-cols-2'"
        >
            <label
                v-for="choice in choices"
                :key="choice.value"
                class="flex cursor-pointer gap-3 rounded-lg border p-3 transition-colors has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                :class="
                    model === choice.value
                        ? 'border-primary bg-accent/60'
                        : 'hover:border-input'
                "
            >
                <input
                    v-model="model"
                    type="radio"
                    :name="name"
                    :value="choice.value"
                    class="mt-0.5 size-4 shrink-0 accent-(--primary)"
                />
                <span class="grid min-w-0 gap-1 text-sm">
                    <span class="font-medium">{{ choice.label }}</span>
                    <span class="text-muted-foreground">
                        {{ choice.description }}
                    </span>
                    <span
                        v-if="choice.example"
                        class="tabular text-xs text-muted-foreground"
                    >
                        {{ choice.example }}
                    </span>
                </span>
            </label>
        </div>
    </fieldset>
</template>
