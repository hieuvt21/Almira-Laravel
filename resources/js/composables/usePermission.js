import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePermission() {
    const page = usePage();

    const user = computed(() => page.props.auth?.user ?? null);
    const roles = computed(() => user.value?.roles ?? []);
    const permissions = computed(() => user.value?.permissions ?? []);
    const isAdmin = computed(() => roles.value.includes('admin'));

    const can = (permission) => isAdmin.value || permissions.value.includes(permission);
    const hasRole = (role) => roles.value.includes(role);

    return { user, roles, permissions, isAdmin, can, hasRole };
}
