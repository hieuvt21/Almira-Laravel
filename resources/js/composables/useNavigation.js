import { computed } from 'vue';
import { navigation } from '@/config/navigation';
import { usePermission } from './usePermission';

export function useNavigation() {
    const { can } = usePermission();

    // Chỉ hiện mục menu khi người dùng có quyền VÀ route đã tồn tại
    const visible = (item) =>
        (!item.can || can(item.can)) && route().has(item.route);

    const groups = computed(() =>
        navigation
            .map((group) => ({ ...group, items: group.items.filter(visible) }))
            .filter((group) => group.items.length > 0),
    );

    const isActive = (item) => route().current(item.match ?? item.route);

    return { groups, isActive };
}
