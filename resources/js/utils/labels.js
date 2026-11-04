const ROLE_LABELS = {
    admin: 'Quản trị viên',
    manager: 'Quản lý',
    cashier: 'Thu ngân',
    warehouse: 'Thủ kho',
};

const ROLE_SEVERITY = {
    admin: 'contrast',
    manager: 'info',
    cashier: 'success',
    warehouse: 'warn',
};

export const roleLabel = (name) => ROLE_LABELS[name] ?? name;
export const roleSeverity = (name) => ROLE_SEVERITY[name] ?? 'secondary';

const MODULE_LABELS = {
    users: 'Người dùng',
    roles: 'Vai trò',
    products: 'Sản phẩm',
    inventory: 'Kho',
    sales: 'Bán hàng',
    customers: 'Khách hàng',
    purchases: 'Nhập hàng',
    shifts: 'Ca làm việc',
    reports: 'Báo cáo',
    settings: 'Cài đặt',
};

const ACTION_LABELS = {
    view: 'Xem',
    create: 'Thêm',
    update: 'Sửa',
    delete: 'Xóa',
    adjust: 'Điều chỉnh',
    pos: 'Bán tại quầy',
    refund: 'Hoàn trả',
    own: 'Ca của mình',
    manage: 'Quản lý',
};

// ['users.view', 'users.create', ...] -> [{ key, label, items: [{ name, label }] }]
export function groupPermissions(names) {
    const groups = {};
    for (const name of names) {
        const [module, action] = name.split('.');
        groups[module] ??= { key: module, label: MODULE_LABELS[module] ?? module, items: [] };
        groups[module].items.push({ name, label: ACTION_LABELS[action] ?? action });
    }
    return Object.values(groups);
}
