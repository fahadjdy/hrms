import { router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

type FilterValue = string | number | boolean | null | undefined;

type Options = {
    /** Keys whose changes wait for the user to stop typing. */
    debounced?: string[];
    /** Only reload these props, when the rest of the page is unaffected. */
    only?: string[];
};

/**
 * Keeps a list page's filters in the URL query string.
 *
 * Changing a filter reloads the page data in place (state and scroll are
 * kept), and always goes back to the first page of results.
 */
export function useQueryFilters<T extends Record<string, FilterValue>>(
    url: () => string,
    initial: T,
    options: Options = {},
) {
    const filters = reactive({ ...initial }) as T;
    let timer: ReturnType<typeof setTimeout> | undefined;

    function apply(): void {
        const query: Record<string, string | number | boolean> = {};

        for (const [key, value] of Object.entries(filters)) {
            if (value !== null && value !== undefined && value !== '') {
                query[key] = value;
            }
        }

        router.get(url(), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            ...(options.only ? { only: options.only } : {}),
        });
    }

    for (const key of Object.keys(initial)) {
        watch(
            () => filters[key as keyof T],
            () => {
                clearTimeout(timer);

                if (options.debounced?.includes(key)) {
                    timer = setTimeout(apply, 300);
                } else {
                    apply();
                }
            },
        );
    }

    function reset(): void {
        for (const key of Object.keys(initial)) {
            (filters as Record<string, FilterValue>)[key] =
                typeof initial[key] === 'string' ? '' : null;
        }
    }

    return { filters, apply, reset };
}
