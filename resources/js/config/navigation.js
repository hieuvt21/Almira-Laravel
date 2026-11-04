// Thêm module mới = thêm một mục ở đây.
// Mục chỉ hiện khi người dùng có quyền `can` và route `route` đã được khai báo.
export const navigation = [
    {
        label: 'Tổng quan',
        items: [
            { label: 'Bảng điều khiển', icon: 'pi pi-objects-column', route: 'dashboard' },
        ],
    },
    {
        label: 'Bán hàng',
        items: [
            { label: 'Bán hàng', icon: 'pi pi-shopping-cart', route: 'pos.index', match: 'pos.*', can: 'sales.pos' },
            { label: 'Đơn hàng', icon: 'pi pi-list', route: 'sales.index', match: 'sales.*', can: 'sales.view' },
            { label: 'Khách hàng', icon: 'pi pi-id-card', route: 'customers.index', match: 'customers.*', can: 'customers.view' },
            { label: 'Ca làm việc', icon: 'pi pi-clock', route: 'shifts.index', match: 'shifts.*', can: 'shifts.own' },
        ],
    },
    {
        label: 'Hàng hóa',
        items: [
            { label: 'Sản phẩm', icon: 'pi pi-box', route: 'products.index', match: 'products.*', can: 'products.view' },
            { label: 'Tồn kho', icon: 'pi pi-database', route: 'inventory.index', match: 'inventory.*', can: 'inventory.view' },
            { label: 'Nhập hàng', icon: 'pi pi-truck', route: 'purchases.index', match: 'purchases.*', can: 'purchases.view' },
        ],
    },
    {
        label: 'Báo cáo',
        items: [
            { label: 'Báo cáo', icon: 'pi pi-chart-bar', route: 'reports.index', match: 'reports.*', can: 'reports.view' },
        ],
    },
    {
        label: 'Hệ thống',
        items: [
            { label: 'Giao diện', icon: 'pi pi-palette', route: 'appearance.edit', match: 'appearance.*' },
            { label: 'Cửa hàng', icon: 'pi pi-building', route: 'store.edit', match: 'store.*', can: 'settings.manage' },
            { label: 'Người dùng', icon: 'pi pi-users', route: 'users.index', match: 'users.*', can: 'users.view' },
            { label: 'Vai trò & quyền', icon: 'pi pi-shield', route: 'roles.index', match: 'roles.*', can: 'roles.manage' },
        ],
    },
];
