<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * An toàn khi chạy lại nhiều lần (mỗi lần deploy):
 *  - quyền: chỉ thêm quyền còn thiếu;
 *  - admin: luôn đủ mọi quyền;
 *  - vai trò khác: chỉ gán quyền mặc định khi vai trò MỚI được tạo, không ghi đè phần đã chỉnh trên giao diện;
 *  - tài khoản admin: chỉ tạo khi chưa có, không bao giờ đặt lại mật khẩu.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.manage',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'inventory.view', 'inventory.adjust',
            'sales.pos', 'sales.view', 'sales.refund',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'purchases.view', 'purchases.create', 'purchases.update',
            'shifts.own', 'shifts.manage',
            'reports.view',
            'settings.manage',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // DatabaseSeeder dùng WithoutModelEvents nên Spatie không tự xóa cache
        $registrar->forgetCachedPermissions();

        // Admin luôn có đủ mọi quyền (kể cả quyền của module thêm sau này)
        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());

        $defaults = [
            'manager' => [
                'products.view', 'products.create', 'products.update', 'products.delete',
                'inventory.view', 'inventory.adjust',
                'sales.view', 'sales.refund',
                'customers.view', 'customers.create', 'customers.update',
                'purchases.view', 'purchases.create', 'purchases.update',
                'shifts.manage', 'reports.view',
            ],
            'cashier' => [
                'sales.pos', 'products.view',
                'customers.view', 'customers.create',
                'shifts.own',
            ],
            'warehouse' => [
                'products.view',
                'inventory.view', 'inventory.adjust',
                'purchases.view', 'purchases.create', 'purchases.update',
            ],
        ];

        foreach ($defaults as $name => $perms) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($perms);
            }
        }

        // Tài khoản quản trị đầu tiên. ĐỔI MẬT KHẨU NGAY sau lần đăng nhập đầu.
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            ['name' => 'Quản trị viên', 'password' => 'admin'],
        );

        if ($admin->wasRecentlyCreated) {
            $admin->forceFill(['email_verified_at' => now()])->save();
        }

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        $registrar->forgetCachedPermissions();
    }
}
