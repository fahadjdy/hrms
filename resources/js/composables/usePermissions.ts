import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * What the signed-in user may do. This only decides what to show; every
 * action is authorized again on the server.
 */
export function usePermissions() {
    const page = usePage();
    const permissions = computed(() => page.props.auth.user?.permissions ?? []);

    function can(permission: string): boolean {
        return permissions.value.includes(permission);
    }

    return { can, permissions };
}
