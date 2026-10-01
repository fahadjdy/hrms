<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import StatusBadge from '@/components/StatusBadge.vue';
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
import { Switch } from '@/components/ui/switch';
import {
    useConfirmedAction,
    useResourceForm,
} from '@/composables/useResourceForm';
import {
    destroy as destroyRole,
    index,
    store as storeRole,
    update as updateRole,
} from '@/routes/settings/roles';
import {
    store as storeUser,
    update as updateUser,
} from '@/routes/settings/users';
import type { Option } from '@/types';

type Role = {
    id: number;
    name: string;
    slug: string;
    permissions: string[];
    is_system: boolean;
    users_count: number;
    is_admin: boolean;
};

type CompanyUser = {
    id: number;
    name: string;
    email: string;
    role_id: number | null;
    role: string | null;
    is_active: boolean;
    is_self: boolean;
};

const props = defineProps<{
    roles: Role[];
    permissions: Option[];
    users: CompanyUser[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles & permissions', href: index() }],
    },
});

const users = useResourceForm(() => ({
    name: '',
    email: '',
    password: '',
    role_id: null as number | null,
    is_active: true,
}));

const roles = useResourceForm(() => ({
    name: '',
    permissions: [] as string[],
}));

const removal = useConfirmedAction<Role>();

const roleOptions = computed(() =>
    props.roles.map((role) => ({ value: role.id, label: role.name })),
);

const editingUser = computed(() =>
    props.users.find((user) => user.id === users.editingId.value),
);

const userColumns: DataTableColumn[] = [
    { key: 'name', label: 'User', primary: true },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Role' },
    { key: 'is_active', label: 'Status' },
];

const roleColumns: DataTableColumn[] = [
    { key: 'name', label: 'Role', primary: true },
    { key: 'permissions', label: 'Permissions' },
    { key: 'users_count', label: 'Users', align: 'right' },
];

/** Permissions grouped by the area before the dot, e.g. "payroll.view". */
const permissionGroups = computed(() => {
    const groups = new Map<string, Option[]>();

    for (const permission of props.permissions) {
        const area = permission.value.split('.')[0];
        groups.set(area, [...(groups.get(area) ?? []), permission]);
    }

    return [...groups.entries()].map(([area, items]) => ({
        area,
        title: area.replace(/^./, (letter) => letter.toUpperCase()),
        items,
    }));
});

const saveUser = () =>
    users.submit({
        store: storeUser.url(),
        update: (id) => updateUser.url(id),
    });

const saveRole = () =>
    roles.submit({
        store: storeRole.url(),
        update: (id) => updateRole.url(id),
    });
</script>

<template>
    <Head title="Roles & permissions" />

    <div class="flex flex-col gap-8 p-4 sm:p-6">
        <PageHeader
            title="Roles & permissions"
            description="Only the users listed here can sign in. Employees are HR records and never get a login."
        />

        <section class="flex flex-col gap-3" aria-labelledby="users-heading">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 id="users-heading" class="text-base font-semibold">
                        Users
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Admin and HR staff of your company. What each one can do
                        follows from their role.
                    </p>
                </div>
                <Button @click="users.startCreate()">
                    <Plus />
                    Add user
                </Button>
            </div>

            <DataTable :columns="userColumns" :rows="props.users">
                <template #cell-name="{ row }">
                    <span class="font-medium">{{ row.name }}</span>
                    <span v-if="row.is_self" class="text-muted-foreground">
                        (you)
                    </span>
                </template>
                <template #cell-role="{ row }">
                    {{ row.role ?? 'No role' }}
                </template>
                <template #cell-is_active="{ row }">
                    <StatusBadge :tone="row.is_active ? 'positive' : 'neutral'">
                        {{ row.is_active ? 'Active' : 'Deactivated' }}
                    </StatusBadge>
                </template>
                <template #actions="{ row }">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Edit ${row.name}`"
                        @click="
                            users.startEdit(row.id, {
                                name: row.name,
                                email: row.email,
                                role_id: row.role_id,
                                is_active: row.is_active,
                            })
                        "
                    >
                        <Pencil />
                    </Button>
                </template>
            </DataTable>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="roles-heading">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 id="roles-heading" class="text-base font-semibold">
                        Roles
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        A role is a set of permissions. The Company Admin role
                        always has every permission.
                    </p>
                </div>
                <Button variant="outline" @click="roles.startCreate()">
                    <Plus />
                    Add role
                </Button>
            </div>

            <DataTable :columns="roleColumns" :rows="props.roles">
                <template #cell-name="{ row }">
                    <span class="font-medium">{{ row.name }}</span>
                    <span
                        v-if="row.is_system"
                        class="ml-1 text-xs text-muted-foreground"
                    >
                        Built-in
                    </span>
                </template>
                <template #cell-permissions="{ row }">
                    <span class="tabular">
                        {{
                            row.is_admin
                                ? 'All permissions'
                                : `${row.permissions.length} of ${permissions.length}`
                        }}
                    </span>
                </template>
                <template #cell-users_count="{ row }">
                    <span class="tabular">{{ row.users_count }}</span>
                </template>
                <template #actions="{ row }">
                    <template v-if="!row.is_admin">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="`Edit role ${row.name}`"
                            @click="
                                roles.startEdit(row.id, {
                                    name: row.name,
                                    permissions: [...row.permissions],
                                })
                            "
                        >
                            <Pencil />
                        </Button>
                        <Button
                            v-if="!row.is_system"
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="`Delete role ${row.name}`"
                            @click="removal.ask(row)"
                        >
                            <Trash2 />
                        </Button>
                    </template>
                    <span v-else class="text-xs text-muted-foreground">
                        Cannot be changed
                    </span>
                </template>
            </DataTable>
        </section>
    </div>

    <!-- Add / edit user -->
    <Dialog v-model:open="users.open.value">
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-5" @submit.prevent="saveUser">
                <DialogHeader>
                    <DialogTitle>
                        {{ users.editingId.value ? 'Edit user' : 'Add user' }}
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="users.editingId.value">
                            {{ users.form.email }}
                        </template>
                        <template v-else>
                            The user signs in with this email and password.
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Name"
                    for="user-name"
                    :error="users.form.errors.name"
                    required
                >
                    <Input
                        id="user-name"
                        v-model="users.form.name"
                        required
                        maxlength="255"
                        autocomplete="off"
                    />
                </FormField>

                <FormField
                    v-if="!users.editingId.value"
                    label="Email"
                    for="user-email"
                    :error="users.form.errors.email"
                    required
                >
                    <Input
                        id="user-email"
                        v-model="users.form.email"
                        type="email"
                        required
                        maxlength="255"
                        autocomplete="off"
                    />
                </FormField>

                <FormField
                    :label="users.editingId.value ? 'New password' : 'Password'"
                    for="user-password"
                    :error="users.form.errors.password"
                    :hint="
                        users.editingId.value
                            ? 'Leave empty to keep the current password'
                            : undefined
                    "
                    :required="!users.editingId.value"
                >
                    <PasswordInput
                        id="user-password"
                        v-model="users.form.password"
                        :required="!users.editingId.value"
                        autocomplete="new-password"
                    />
                </FormField>

                <FormField
                    label="Role"
                    for="user-role"
                    :error="users.form.errors.role_id"
                    :hint="
                        editingUser?.is_self
                            ? 'You cannot change your own role'
                            : undefined
                    "
                    required
                >
                    <NativeSelect
                        id="user-role"
                        v-model="users.form.role_id"
                        :options="roleOptions"
                        placeholder="Choose a role"
                        :disabled="editingUser?.is_self"
                        :invalid="!!users.form.errors.role_id"
                    />
                </FormField>

                <label
                    v-if="users.editingId.value && !editingUser?.is_self"
                    class="flex items-start gap-3 text-sm"
                >
                    <Switch v-model="users.form.is_active" class="mt-0.5" />
                    <span>
                        <span class="font-medium">Active</span>
                        <span class="block text-muted-foreground">
                            A deactivated user can no longer sign in.
                        </span>
                    </span>
                </label>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="users.open.value = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="users.form.processing">
                        <Spinner v-if="users.form.processing" />
                        {{
                            users.editingId.value ? 'Save changes' : 'Add user'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Add / edit role -->
    <Dialog v-model:open="roles.open.value">
        <DialogContent class="sm:max-w-2xl">
            <form class="grid gap-5" @submit.prevent="saveRole">
                <DialogHeader>
                    <DialogTitle>
                        {{ roles.editingId.value ? 'Edit role' : 'Add role' }}
                    </DialogTitle>
                    <DialogDescription>
                        Tick what users with this role may do. "View"
                        permissions open the pages; "manage" permissions allow
                        changes.
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Role name"
                    for="role-name"
                    :error="roles.form.errors.name"
                    required
                >
                    <Input
                        id="role-name"
                        v-model="roles.form.name"
                        required
                        maxlength="100"
                        placeholder="e.g. Payroll Officer"
                    />
                </FormField>

                <fieldset>
                    <legend class="text-sm font-medium">Permissions</legend>
                    <div
                        class="mt-2 grid max-h-[45vh] gap-x-6 gap-y-4 overflow-y-auto rounded-md border p-3 sm:grid-cols-2"
                    >
                        <div
                            v-for="group in permissionGroups"
                            :key="group.area"
                            class="grid content-start gap-1.5"
                        >
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                {{ group.title }}
                            </p>
                            <label
                                v-for="permission in group.items"
                                :key="permission.value"
                                class="flex items-start gap-2 text-sm"
                            >
                                <input
                                    v-model="roles.form.permissions"
                                    type="checkbox"
                                    :value="permission.value"
                                    class="mt-0.5 size-4 shrink-0 accent-(--primary)"
                                />
                                {{ permission.label }}
                            </label>
                        </div>
                    </div>
                    <p
                        v-if="roles.form.errors.permissions"
                        class="mt-2 text-sm text-negative"
                    >
                        {{ roles.form.errors.permissions }}
                    </p>
                </fieldset>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="roles.open.value = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="roles.form.processing">
                        <Spinner v-if="roles.form.processing" />
                        {{
                            roles.editingId.value ? 'Save changes' : 'Add role'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="removal.target.value !== null"
        title="Delete this role?"
        :description="`${removal.target.value?.name ?? ''} will be removed. A role that is still assigned to users cannot be deleted.`"
        confirm-label="Delete role"
        destructive
        :processing="removal.processing.value"
        @update:open="(value) => !value && (removal.target.value = null)"
        @confirm="
            removal.target.value &&
            removal.run('delete', destroyRole.url(removal.target.value.id))
        "
    />
</template>
