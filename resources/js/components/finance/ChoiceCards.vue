<script setup lang="ts">
export type Choice = {
    value: string;
    title: string;
    description: string;
};

defineProps<{
    /** What is being chosen; announced to screen readers and shown above the cards. */
    legend: string;
    /** Groups the radio inputs; must be unique on the page. */
    name: string;
    choices: Choice[];
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <!-- A small set of exclusive options, each with a line that says what it means. -->
    <fieldset class="grid gap-2">
        <legend class="mb-1.5 text-sm font-medium">{{ legend }}</legend>
        <div class="grid gap-2 sm:grid-cols-2">
            <label
                v-for="choice in choices"
                :key="choice.value"
                class="flex cursor-pointer items-start gap-3 rounded-lg border bg-card p-3 transition-colors has-checked:border-primary has-checked:bg-accent has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
            >
                <input
                    v-model="model"
                    type="radio"
                    :name="name"
                    :value="choice.value"
                    class="mt-1 size-4 shrink-0 accent-primary"
                />
                <span class="min-w-0">
                    <span class="block text-sm font-medium">
                        {{ choice.title }}
                    </span>
                    <span class="mt-0.5 block text-xs text-muted-foreground">
                        {{ choice.description }}
                    </span>
                </span>
            </label>
        </div>
    </fieldset>
</template>
