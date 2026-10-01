<script setup lang="ts">
import $ from 'jquery';
import select2Module from 'select2';
import type { HTMLAttributes } from 'vue';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

/** The CommonJS build exports a function that installs `$.fn.select2`. */
const installSelect2 = select2Module as unknown as (
    root: Window,
    jquery: JQueryStatic,
) => void;

// Select2 needs a browser; a server-side render only prints the plain select.
if (typeof window !== 'undefined') {
    installSelect2(window, $);
}

defineOptions({ inheritAttrs: false });

type Value = string | number | null;

const props = defineProps<{
    options: { value: string | number; label: string }[];
    /** Text of the empty choice. Leave out to require a choice. */
    placeholder?: string;
    id?: string;
    disabled?: boolean;
    invalid?: boolean;
    /** Applied to the wrapper, so widths such as `w-48` work as on a select. */
    class?: HTMLAttributes['class'];
}>();

const model = defineModel<Value>({ default: null });

/** Lists shorter than this open without a search box. */
const SEARCH_THRESHOLD = 8;

const select = ref<HTMLSelectElement | null>(null);

const element = (): JQuery<HTMLSelectElement> | null =>
    select.value ? $(select.value) : null;

/** Show the model's value in Select2 without telling Vue it changed. */
function sync(): void {
    element()
        ?.val(model.value === null ? '' : String(model.value))
        .trigger('change.select2');
}

onMounted(() => {
    const $select = element();

    if (!$select || !select.value) {
        return;
    }

    // A dialog traps focus and clicks inside itself, so there the list has
    // to open inside the dialog instead of at the end of the page.
    const dialog = select.value.closest('[role="dialog"]');

    $select.select2({
        width: '100%',
        placeholder: props.placeholder ?? '',
        allowClear: props.placeholder !== undefined,
        minimumResultsForSearch:
            props.options.length < SEARCH_THRESHOLD ? Infinity : 0,
        dropdownParent: dialog ? $(dialog as HTMLElement) : $(document.body),
    });

    sync();

    $select.on('change', () => {
        const raw = String($select.val() ?? '');

        // Hand back the option's own value so numeric ids stay numbers.
        const match = props.options.find(
            (option) => String(option.value) === raw,
        );

        const value = match ? match.value : null;

        if (value !== model.value) {
            model.value = value;
        }
    });

    // Type straight away, without clicking into the search box first.
    $select.on('select2:open', () => {
        document
            .querySelector<HTMLInputElement>(
                '.select2-container--open .select2-search__field',
            )
            ?.focus();
    });
});

onBeforeUnmount(() => {
    const $select = element();

    $select?.off('change select2:open');

    if ($select?.data('select2')) {
        $select.select2('destroy');
    }
});

watch(model, sync);

// New options are rendered by Vue first; Select2 then redraws its label.
watch(
    () => props.options,
    () => nextTick(sync),
    { deep: true },
);
</script>

<template>
    <div
        :class="cn('w-full min-w-0', props.class)"
        :data-invalid="invalid || undefined"
        data-slot="select"
    >
        <select
            :id="id"
            ref="select"
            v-bind="$attrs"
            :disabled="disabled"
            :aria-invalid="invalid || undefined"
            class="h-9 w-full"
        >
            <option v-if="placeholder !== undefined" value=""></option>
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>
    </div>
</template>
