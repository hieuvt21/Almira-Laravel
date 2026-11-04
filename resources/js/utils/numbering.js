// Ghép mã chứng từ để xem trước. Giống App\Support\DocumentNumber::compose ở server.

export const DATE_FORMATS = [
    { value: 'none', label: 'Không' },
    { value: 'yy', label: 'Năm (YY)' },
    { value: 'yymm', label: 'Tháng (YYMM)' },
    { value: 'yymmdd', label: 'Ngày (YYMMDD)' },
];

export const SEPARATORS = [
    { value: 'dash', label: 'Gạch ngang ( - )', char: '-' },
    { value: 'dot', label: 'Dấu chấm ( . )', char: '.' },
    { value: 'none', label: 'Không có', char: '' },
];

const two = (n) => String(n).padStart(2, '0');

export function datePart(format, d = new Date()) {
    const yy = two(d.getFullYear() % 100);
    if (format === 'yy') return yy;
    if (format === 'yymm') return yy + two(d.getMonth() + 1);
    if (format === 'yymmdd') return yy + two(d.getMonth() + 1) + two(d.getDate());
    return '';
}

export function composeNumber(cfg, number, d = new Date()) {
    const sep = SEPARATORS.find((s) => s.value === cfg.separator)?.char ?? '-';
    return [cfg.prefix, datePart(cfg.date_format, d), String(number).padStart(Number(cfg.padding) || 4, '0')]
        .filter((part) => part !== '')
        .join(sep);
}
