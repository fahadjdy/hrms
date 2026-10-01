import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

type FormData = Record<string, any>;

/** Show the first validation message of a failed request as a toast. */
export function toastFirstError(errors: Record<string, string>): void {
    const message = Object.values(errors)[0];

    if (message) {
        toast.error(message);
    }
}

/**
 * The add / edit dialog used by simple record screens (departments, holidays,
 * overtime…): one form that either creates a record or updates the one being
 * edited, and closes the dialog when the server accepts it.
 */
export function useResourceForm<T extends FormData>(defaults: () => T) {
    const open = ref(false);
    const editingId = ref<number | null>(null);
    const form = useForm<T>(defaults());

    function startCreate(values: Partial<T> = {}): void {
        editingId.value = null;
        form.defaults(defaults());
        form.reset();
        Object.assign(form, values);
        form.clearErrors();
        open.value = true;
    }

    function startEdit(id: number, values: Partial<T>): void {
        editingId.value = id;
        form.defaults(defaults());
        form.reset();
        Object.assign(form, values);
        form.clearErrors();
        open.value = true;
    }

    function submit(urls: {
        store: string;
        update: (id: number) => string;
    }): void {
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        };

        if (editingId.value === null) {
            form.post(urls.store, options);
        } else {
            form.put(urls.update(editingId.value), options);
        }
    }

    return { open, editingId, form, startCreate, startEdit, submit };
}

/**
 * A confirmed one-off action on a record, such as delete, approve or cancel.
 * `target` holds the record awaiting confirmation; a server-side refusal is
 * shown as a toast.
 */
export function useConfirmedAction<T>() {
    const target = ref<T | null>(null);
    const processing = ref(false);

    function ask(record: T): void {
        target.value = record as any;
    }

    function run(
        method: 'post' | 'put' | 'delete',
        url: string,
        data: FormData = {},
    ): void {
        processing.value = true;

        router.visit(url, {
            method,
            data,
            preserveScroll: true,
            onSuccess: () => {
                target.value = null;
            },
            onError: toastFirstError,
            onFinish: () => {
                processing.value = false;
            },
        });
    }

    return { target, processing, ask, run };
}
