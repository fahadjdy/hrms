<script setup lang="ts">
import { Check, Lock } from '@lucide/vue';
import { computed } from 'vue';
import type { Option } from '@/types';

const props = defineProps<{
    /** The workflow steps in order, from the server. */
    steps: Option[];
    /** The payroll's current status value. */
    current: string;
}>();

const currentIndex = computed(() =>
    Math.max(
        0,
        props.steps.findIndex((step) => step.value === props.current),
    ),
);

const isFinal = computed(() => currentIndex.value === props.steps.length - 1);

type StepState = 'done' | 'current' | 'upcoming';

function stateOf(index: number): StepState {
    if (index < currentIndex.value) {
        return 'done';
    }

    return index === currentIndex.value ? 'current' : 'upcoming';
}
</script>

<template>
    <!-- The steps are a real sequence, so they are numbered and read as an ordered list. -->
    <nav aria-label="Payroll workflow">
        <ol class="flex flex-wrap items-center gap-x-2 gap-y-3 text-sm">
            <li
                v-for="(step, index) in steps"
                :key="step.value"
                class="flex items-center gap-2"
                :aria-current="
                    stateOf(index) === 'current' ? 'step' : undefined
                "
            >
                <span
                    class="tabular flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-medium"
                    :class="{
                        'border-primary bg-primary text-primary-foreground':
                            stateOf(index) === 'current',
                        'border-primary/40 bg-accent text-accent-foreground':
                            stateOf(index) === 'done',
                        'border-border text-muted-foreground':
                            stateOf(index) === 'upcoming',
                    }"
                    aria-hidden="true"
                >
                    <Lock
                        v-if="stateOf(index) === 'current' && isFinal"
                        class="size-3"
                    />
                    <Check
                        v-else-if="stateOf(index) === 'done'"
                        class="size-3.5"
                    />
                    <template v-else>{{ index + 1 }}</template>
                </span>
                <span
                    :class="
                        stateOf(index) === 'current'
                            ? 'font-semibold'
                            : stateOf(index) === 'done'
                              ? 'text-foreground'
                              : 'text-muted-foreground'
                    "
                >
                    {{ step.label }}
                    <span class="sr-only">
                        {{
                            stateOf(index) === 'done'
                                ? '(completed)'
                                : stateOf(index) === 'current'
                                  ? '(current step)'
                                  : '(not started)'
                        }}
                    </span>
                </span>
                <span
                    v-if="index < steps.length - 1"
                    class="mx-1 hidden h-px w-6 bg-border sm:block lg:w-10"
                    aria-hidden="true"
                />
            </li>
        </ol>
    </nav>
</template>
