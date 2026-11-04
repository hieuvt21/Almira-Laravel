import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { DEFAULT_FINANCE, formatMoney, roundMoney } from '@/utils/money';

// Dùng trong mọi module có tiền:  const { money, round } = useMoney();  money(1234567) -> "1.234.567 ₫"
export function useMoney() {
    const page = usePage();
    const config = computed(() => ({ ...DEFAULT_FINANCE, ...(page.props.finance ?? {}) }));

    return {
        config,
        money: (amount) => formatMoney(amount, config.value),
        round: (amount) => roundMoney(amount, config.value),
    };
}
