<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Calendar, Clock, X } from '@lucide/vue';
import flatpickr from 'flatpickr';
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect/index';
import type { Instance } from 'flatpickr/dist/types/instance';
import type { Options } from 'flatpickr/dist/types/options';
import type { HTMLAttributes } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

type Mode = 'date' | 'month' | 'time';

const props = withDefaults(
    defineProps<{
        /** `date` picks a day, `month` a whole month, `time` a time of day. */
        mode?: Mode;
        /** Earliest and latest allowed value, in the same format as the model. */
        min?: string | null;
        max?: string | null;
        disabled?: boolean;
        required?: boolean;
        placeholder?: string;
        /** Applied to the wrapper, so widths such as `w-48` work as on an input. */
        class?: HTMLAttributes['class'];
    }>(),
    {
        mode: 'date',
        min: null,
        max: null,
        disabled: false,
        required: false,
        placeholder: undefined,
        class: undefined,
    },
);

/**
 * The value the server works with: `YYYY-MM-DD` for a date, `YYYY-MM` for a
 * month and 24-hour `HH:MM` for a time. What the user sees follows the
 * company's date format.
 */
const model = defineModel<string | null>();

const VALUE_FORMAT: Record<Mode, string> = {
    date: 'Y-m-d',
    month: 'Y-m',
    time: 'H:i',
};

const PLACEHOLDER: Record<Mode, string> = {
    date: 'Select date',
    month: 'Select month',
    time: 'Select time',
};

const page = usePage();

const displayFormat = computed(() => {
    if (props.mode === 'month') {
        return 'F Y';
    }

    if (props.mode === 'time') {
        return 'h:i K';
    }

    return page.props.company?.date_format ?? 'd M Y';
});

const input = ref<HTMLInputElement | null>(null);
let picker: Instance | null = null;

const parse = (value: string | null | undefined): Date | undefined =>
    value ? flatpickr.parseDate(value, VALUE_FORMAT[props.mode]) : undefined;

const current = (): string =>
    picker?.selectedDates[0]
        ? picker.formatDate(picker.selectedDates[0], VALUE_FORMAT[props.mode])
        : '';

onMounted(() => {
    if (!input.value) {
        return;
    }

    const options: Options = {
        dateFormat: displayFormat.value,
        defaultDate: parse(model.value),
        allowInput: true,
        // The same calendar on a phone as on a computer.
        disableMobile: true,
        // A dialog traps focus and clicks inside itself, so there the calendar
        // has to live next to the input instead of at the end of the page.
        static: input.value.closest('[role="dialog"]') !== null,
        onChange: (dates) => {
            const value = dates[0]
                ? flatpickr.formatDate(dates[0], VALUE_FORMAT[props.mode])
                : '';

            if (value !== (model.value ?? '')) {
                model.value = value;
            }
        },
    };

    if (props.mode === 'date') {
        options.minDate = parse(props.min);
        options.maxDate = parse(props.max);
    }

    if (props.mode === 'month') {
        options.minDate = parse(props.min);
        options.maxDate = parse(props.max);
        options.plugins = [
            monthSelectPlugin({
                shorthand: true,
                dateFormat: displayFormat.value,
                altFormat: displayFormat.value,
            }),
        ];
    }

    if (props.mode === 'time') {
        options.enableTime = true;
        options.noCalendar = true;
        options.time_24hr = false;
        options.minuteIncrement = 1;
    }

    picker = flatpickr(input.value, options);
});

onBeforeUnmount(() => {
    picker?.destroy();
    picker = null;
});

watch(model, (value) => {
    if (!picker || (value ?? '') === current()) {
        return;
    }

    const parsed = parse(value);

    if (parsed) {
        picker.setDate(parsed, false);
    } else {
        picker.clear(false);
    }
});

watch(
    () => [props.min, props.max],
    () => {
        if (!picker || props.mode === 'time') {
            return;
        }

        picker.set('minDate', parse(props.min));
        picker.set('maxDate', parse(props.max));
    },
);

const clearable = computed(
    () => !props.required && !props.disabled && Boolean(model.value),
);

function clear(): void {
    picker?.clear(false);
    model.value = '';
    input.value?.focus();
}
</script>

<template>
    <div
        :class="cn('relative w-full min-w-0', props.class)"
        data-slot="date-picker"
    >
        <input
            ref="input"
            v-bind="$attrs"
            type="text"
            autocomplete="off"
            :disabled="disabled"
            :required="required"
            :placeholder="placeholder ?? PLACEHOLDER[mode]"
            data-slot="input"
            class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent py-1 pr-9 pl-3 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40"
        />
        <button
            v-if="clearable"
            type="button"
            class="absolute top-1/2 right-1.5 flex size-6 -translate-y-1/2 items-center justify-center rounded-sm text-muted-foreground outline-none hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring"
            aria-label="Clear"
            @click="clear"
        >
            <X class="size-4" />
        </button>
        <component
            :is="mode === 'time' ? Clock : Calendar"
            v-else
            class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
        />
    </div>
</template>
