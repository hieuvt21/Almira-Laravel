// Định dạng và làm tròn tiền theo cài đặt "Cửa hàng > Tiền tệ & thuế".
// Logic này giống hệt App\Support\Money ở phía server.

export const DEFAULT_FINANCE = {
    currency_symbol: '₫',
    symbol_position: 'after',
    number_format: 'vi',
    decimals: 0,
    rounding_step: 1,
    rounding_mode: 'nearest',
    tax_enabled: false,
    tax_rates: [0, 5, 8, 10],
    tax_default_rate: 10,
    prices_include_tax: true,
};

const SEPARATORS = {
    vi: { thousands: '.', decimal: ',' },
    en: { thousands: ',', decimal: '.' },
    space: { thousands: ' ', decimal: ',' },
};

export function roundMoney(amount, cfg = DEFAULT_FINANCE) {
    const n = Number(amount ?? 0);
    const step = Math.max(1, Number(cfg.rounding_step) || 1);
    if (step === 1) return n;

    const ratio = n / step;
    const rounded = cfg.rounding_mode === 'up' ? Math.ceil(ratio) : cfg.rounding_mode === 'down' ? Math.floor(ratio) : Math.round(ratio);
    return rounded * step;
}

// 1234567 -> "1.234.567 ₫"
export function formatMoney(amount, cfg = DEFAULT_FINANCE) {
    const n = Number(amount ?? 0);
    const { thousands, decimal } = SEPARATORS[cfg.number_format] ?? SEPARATORS.vi;

    const [int, frac] = Math.abs(n).toFixed(Number(cfg.decimals) || 0).split('.');
    const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
    const number = `${n < 0 ? '-' : ''}${grouped}${frac ? decimal + frac : ''}`;

    const symbol = cfg.currency_symbol ?? '';
    if (!symbol) return number;
    return cfg.symbol_position === 'before' ? `${symbol}${number}` : `${number} ${symbol}`;
}
