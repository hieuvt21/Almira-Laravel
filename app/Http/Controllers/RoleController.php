<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /** Vai trò mặc định: không đổi tên, không xóa được. */
    private const SYSTEM_ROLES = ['admin', 'manager', 'cashier', 'warehouse'];

    public function index(): Response
    {
        $roles = Role::withCount('users')
            ->with('permissions:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'users_count' => $r->users_count,
                'permissions' => $r->permissions->pluck('name')->values(),
                'system' => in_array($r->name, self::SYSTEM_ROLES, true),
                'locked' => $r->name === 'admin',
            ]);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permissions' => Permission::orderBy('id')->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')],
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'name')],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'Đã thêm vai trò.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->name === 'admin', 403, 'Không thể chỉnh sửa vai trò quản trị viên.');

        $isSystem = in_array($role->name, self::SYSTEM_ROLES, true);

        $rules = [
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'name')],
        ];
        if (! $isSystem) {
            $rules['name'] = ['required', 'string', 'max:50', Rule::unique('roles', 'name')->ignore($role->id)];
        }
        $data = $request->validate($rules);

        if (! $isSystem) {
            $role->update(['name' => $data['name']]);
        }
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'Đã cập nhật vai trò.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            return back()->with('error', 'Không thể xóa vai trò mặc định.');
        }
        if ($role->users()->exists()) {
            return back()->with('error', 'Vai trò này đang có người dùng, hãy chuyển họ sang vai trò khác trước.');
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Đã xóa vai trò.');
    }
}
