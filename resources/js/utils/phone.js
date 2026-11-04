// '0912345678' -> '0912.345.678'
export function formatPhone(value) {
    const d = String(value ?? '').replace(/\D/g, '').slice(0, 10);
    if (d.length <= 4) return d;
    if (d.length <= 7) return `${d.slice(0, 4)}.${d.slice(4)}`;
    return `${d.slice(0, 4)}.${d.slice(4, 7)}.${d.slice(7)}`;
}
