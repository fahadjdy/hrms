<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Download, Search, Trash2, Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useFormat } from '@/composables/useFormat';
import { usePermissions } from '@/composables/usePermissions';
import { useQueryFilters } from '@/composables/useQueryFilters';
import { useConfirmedAction } from '@/composables/useResourceForm';
import { destroy, index, show } from '@/routes/documents';
import { show as employeeShow } from '@/routes/employees';
import { store } from '@/routes/employees/documents';
import type { IdName, Paginated } from '@/types';

type DocumentRow = {
    id: number;
    title: string;
    type: string | null;
    original_name: string;
    size: number;
    employee: { id: number; name: string; code: string };
    uploaded_by: string | null;
    created_at: string | null;
};

const props = defineProps<{
    documents: Paginated<DocumentRow>;
    filters: {
        search?: string;
        employee_id?: string;
        type?: string;
    };
    types: string[];
    employees: IdName[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Documents', href: index() }],
    },
});

const { date } = useFormat();
const { can } = usePermissions();
const canManage = computed(() => can('employees.manage'));

const { filters, reset } = useQueryFilters(
    () => index.url(),
    {
        search: props.filters.search ?? '',
        employee_id: props.filters.employee_id
            ? Number(props.filters.employee_id)
            : null,
        type: props.filters.type ?? null,
    },
    { debounced: ['search'] },
);

const hasFilters = computed(
    () =>
        filters.search !== '' ||
        filters.employee_id !== null ||
        (filters.type !== null && filters.type !== ''),
);

const employeeOptions = computed(() =>
    props.employees.map((employee) => ({
        value: employee.id,
        label: employee.name,
    })),
);

const typeOptions = computed(() =>
    props.types.map((type) => ({ value: type, label: type })),
);

const columns: DataTableColumn[] = [
    { key: 'title', label: 'Document', primary: true },
    { key: 'employee', label: 'Employee' },
    { key: 'type', label: 'Type' },
    { key: 'size', label: 'Size', align: 'right' },
    { key: 'uploaded_by', label: 'Uploaded by', hideOnMobile: true },
    { key: 'created_at', label: 'Uploaded on' },
];

function fileSize(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/* Upload */
const uploadOpen = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const employeeError = ref<string>();
const uploadForm = useForm({
    employee_id: null as number | null,
    title: '',
    type: null as string | null,
    file: null as File | null,
});

function openUpload(): void {
    uploadForm.reset();
    uploadForm.clearErrors();
    uploadForm.employee_id = filters.employee_id;
    employeeError.value = undefined;
    uploadOpen.value = true;
}

function onFileChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    uploadForm.file = file;

    // Suggest the file name as the title, without its extension.
    if (file && uploadForm.title.trim() === '') {
        uploadForm.title = file.name.replace(/\.[^.]+$/, '');
    }
}

function submitUpload(): void {
    employeeError.value = undefined;

    if (uploadForm.employee_id === null) {
        employeeError.value = 'Choose the employee this document belongs to.';

        return;
    }

    uploadForm
        .transform((data) => ({
            title: data.title,
            type: data.type,
            file: data.file,
        }))
        .post(store.url(uploadForm.employee_id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadOpen.value = false;

                if (fileInput.value) {
                    fileInput.value.value = '';
                }
            },
        });
}

const removal = useConfirmedAction<DocumentRow>();
</script>

<template>
    <Head title="Documents" />

    <div class="flex flex-col gap-5 p-4 sm:p-6">
        <PageHeader
            title="Documents"
            description="Files kept on employee records, such as ID proofs, offer letters and contracts. Only authorized admin users can open them."
        >
            <Button v-if="canManage" @click="openUpload">
                <Upload />
                Upload document
            </Button>
        </PageHeader>

        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    type="search"
                    placeholder="Title or employee"
                    aria-label="Search documents"
                    class="pl-9"
                />
            </div>
            <NativeSelect
                v-model="filters.employee_id"
                :options="employeeOptions"
                placeholder="All employees"
                aria-label="Employee"
            />
            <NativeSelect
                v-model="filters.type"
                :options="typeOptions"
                placeholder="All document types"
                aria-label="Document type"
            />
        </div>

        <DataTable :columns="columns" :rows="documents.data">
            <template #cell-title="{ row }">
                <a
                    :href="show.url(row.id)"
                    class="font-medium underline-offset-4 hover:underline focus-visible:underline"
                >
                    {{ row.title }}
                </a>
                <span class="block truncate text-xs text-muted-foreground">
                    {{ row.original_name }}
                </span>
            </template>
            <template #cell-employee="{ row }">
                <Link
                    :href="employeeShow(row.employee.id)"
                    class="underline-offset-4 hover:underline focus-visible:underline"
                >
                    {{ row.employee.name }}
                </Link>
                <span class="tabular block text-xs text-muted-foreground">
                    {{ row.employee.code }}
                </span>
            </template>
            <template #cell-type="{ row }">
                {{ row.type ?? '-' }}
            </template>
            <template #cell-size="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ fileSize(row.size) }}
                </span>
            </template>
            <template #cell-uploaded_by="{ row }">
                {{ row.uploaded_by ?? '-' }}
            </template>
            <template #cell-created_at="{ row }">
                <span class="tabular whitespace-nowrap">
                    {{ date(row.created_at) }}
                </span>
            </template>
            <template #actions="{ row }">
                <Button variant="ghost" size="icon-sm" as-child>
                    <a
                        :href="show.url(row.id)"
                        :aria-label="`Download ${row.title}`"
                        :title="`Download ${row.title}`"
                    >
                        <Download />
                    </a>
                </Button>
                <Button
                    v-if="canManage"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="`Delete ${row.title}`"
                    :title="`Delete ${row.title}`"
                    @click="removal.ask(row)"
                >
                    <Trash2 />
                </Button>
            </template>
            <template #empty>
                <div
                    class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                >
                    <template v-if="hasFilters">
                        <p class="text-sm font-medium">No documents match</p>
                        <p class="max-w-[48ch] text-sm text-muted-foreground">
                            Try a different search, or clear the filters to see
                            every document.
                        </p>
                        <Button variant="outline" size="sm" @click="reset">
                            Clear filters
                        </Button>
                    </template>
                    <template v-else>
                        <p class="text-sm font-medium">No documents yet</p>
                        <p class="max-w-[48ch] text-sm text-muted-foreground">
                            Upload ID proofs, offer letters, contracts and other
                            files to keep them with the employee's record.
                        </p>
                        <Button
                            v-if="canManage"
                            variant="outline"
                            size="sm"
                            @click="openUpload"
                        >
                            Upload document
                        </Button>
                    </template>
                </div>
            </template>
        </DataTable>

        <DataPagination :page="documents" noun="documents" />
    </div>

    <Dialog v-model:open="uploadOpen">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-5" @submit.prevent="submitUpload">
                <DialogHeader>
                    <DialogTitle>Upload document</DialogTitle>
                    <DialogDescription>
                        PDF, image, Word or Excel file, up to 10 MB. The file is
                        stored privately with the employee's record.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Employee"
                    for="document-employee"
                    :error="employeeError"
                    required
                >
                    <NativeSelect
                        id="document-employee"
                        v-model="uploadForm.employee_id"
                        :options="employeeOptions"
                        placeholder="Choose an employee"
                        :invalid="!!employeeError"
                    />
                </FormField>

                <FormField
                    label="File"
                    for="document-file"
                    :error="uploadForm.errors.file"
                    required
                >
                    <input
                        id="document-file"
                        ref="fileInput"
                        type="file"
                        required
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                        class="block w-full min-w-0 rounded-md border border-input bg-transparent text-sm shadow-xs outline-none file:mr-3 file:h-9 file:border-0 file:border-r file:border-input file:bg-muted file:px-3 file:text-sm file:font-medium file:text-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        @change="onFileChange"
                    />
                </FormField>

                <FormField
                    label="Title"
                    for="document-title"
                    :error="uploadForm.errors.title"
                    required
                >
                    <Input
                        id="document-title"
                        v-model="uploadForm.title"
                        required
                        maxlength="255"
                        placeholder="e.g. Offer letter"
                    />
                </FormField>

                <FormField
                    label="Document type"
                    for="document-type"
                    :error="uploadForm.errors.type"
                >
                    <NativeSelect
                        id="document-type"
                        v-model="uploadForm.type"
                        :options="typeOptions"
                        placeholder="Not specified"
                    />
                </FormField>

                <progress
                    v-if="uploadForm.progress"
                    :value="uploadForm.progress.percentage"
                    max="100"
                    class="h-1.5 w-full"
                >
                    {{ uploadForm.progress.percentage }}%
                </progress>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="uploadOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="uploadForm.processing">
                        <Spinner v-if="uploadForm.processing" />
                        Upload document
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removal.target.value !== null"
        title="Delete this document?"
        :description="`&quot;${removal.target.value?.title ?? ''}&quot; will be deleted from ${removal.target.value?.employee.name ?? 'the employee'}'s record. This cannot be undone.`"
        confirm-label="Delete document"
        destructive
        :processing="removal.processing.value"
        @update:open="(value) => !value && (removal.target.value = null)"
        @confirm="
            removal.target.value &&
            removal.run('delete', destroy.url(removal.target.value.id))
        "
    />
</template>
