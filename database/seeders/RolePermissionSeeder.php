<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

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

        // DatabaseSeeder dùng WithoutModelEvents nên Spatie không tự xóa cache,
        // phải xóa thủ công để các quyền vừa tạo được nhận diện.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'admin' => Permission::all()->pluck('name')->all(),
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

        foreach ($roles as $roleName => $perms) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($perms);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Tài khoản mẫu (nhớ đổi mật khẩu khi chạy thật)
        $users = [
            ['Quản trị viên', 'admin', 'admin@almira.test', 'admin'],
            ['Quản lý', 'manager', 'manager@almira.test', 'manager'],
            ['Thu ngân', 'cashier', 'cashier@almira.test', 'cashier'],
            ['Thủ kho', 'warehouse', 'warehouse@almira.test', 'warehouse'],
        ];

        foreach ($users as [$name, $username, $email, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'username' => $username, 'password' => 'password', 'email_verified_at' => now()]
            );
            $user->syncRoles([$role]);
        }
    }
}
