<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesIdentity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use ManagesIdentity;

    public function index(): Response
    {
        $users = User::with('roles:id,name')
            ->get()
            // Tài khoản admin luôn ở trên cùng, sau đó xếp theo tên
            ->sort(function (User $a, User $b) {
                $byRole = ($a->hasRole('admin') ? 0 : 1) <=> ($b->hasRole('admin') ? 0 : 1);

                return $byRole ?: mb_strtolower($a->name) <=> mb_strtolower($b->name);
            })
            ->values()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'phone' => $u->phone,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
                'role' => $u->roles->first()?->name,
                'created_at' => $u->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => Role::orderBy('id')->pluck('name'),
            'avatarPresets' => User::avatarPresets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeIdentity($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => $this->usernameRules(),
            'phone' => $this->phoneRules(),
            'email' => $this->emailRules(),
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::exists('roles', 'name')],
            ...$this->avatarRules(),
        ], $this->identityMessages());

        $this->guardAdmin($request, $data['role']);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->applyAvatar($request, $user);
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('success', 'Đã thêm người dùng.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->normalizeIdentity($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => $this->usernameRules($user->id),
            'phone' => $this->phoneRules($user->id),
            'email' => $this->emailRules($user->id),
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::exists('roles', 'name')],
            ...$this->avatarRules(),
        ], $this->identityMessages());

        $this->guardAdmin($request, $data['role'], $user);

        if ($user->is($request->user()) && $data['role'] !== $user->roles->first()?->name) {
            return back()->withErrors(['role' => 'Bạn không thể đổi vai trò của chính mình.']);
        }

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
        ]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        $this->applyAvatar($request, $user);
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('success', 'Đã cập nhật người dùng.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Bạn không thể xóa chính mình.');
        }

        $this->guardAdmin($request, '', $user);

        $user->deleteUploadedAvatar();
        $user->delete();

        return redirect()->route('users.index')->with('success', 'Đã xóa người dùng.');
    }

    /**
     * Kiểm tra trùng ngay khi nhập: tên đăng nhập, email hoặc số điện thoại.
     * Người quản lý người dùng được kiểm tra cả 3; người thường chỉ kiểm tra email/SĐT của chính mình.
     */
    public function check(Request $request): JsonResponse
    {
        $field = (string) $request->input('field');
        abort_unless(in_array($field, ['username', 'email', 'phone'], true), 422);

        $me = $request->user();
        $ignore = $request->integer('ignore') ?: null;

        $isManager = $me->can('users.create') || $me->can('users.update');
        $isSelf = $ignore === $me->id && $field !== 'username';
        abort_unless($isManager || $isSelf, 403);

        $value = (string) $request->input('value');
        $value = $field === 'phone' ? preg_replace('/\D/', '', $value) : strtolower(trim($value));

        if ($value === '') {
            return response()->json(['valid' => true, 'available' => true]);
        }

        if ($error = $this->formatError($field, $value)) {
            return response()->json(['valid' => false, 'message' => $error]);
        }

        $exists = User::where($field, $value)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->exists();

        $taken = [
            'username' => 'Tên đăng nhập này đã có người dùng.',
            'email' => 'Email này đã được sử dụng.',
            'phone' => 'Số điện thoại này đã được sử dụng.',
        ];

        return response()->json([
            'valid' => true,
            'available' => ! $exists,
            'message' => $exists ? $taken[$field] : null,
        ]);
    }

    private function formatError(string $field, string $value): ?string
    {
        return match ($field) {
            'username' => match (true) {
                strlen($value) < 3 => 'Tên đăng nhập tối thiểu 3 ký tự.',
                ! preg_match('/^[a-z0-9._-]+$/', $value) => 'Chỉ dùng chữ thường không dấu, số, dấu chấm, gạch dưới hoặc gạch ngang.',
                (bool) preg_match('/^\d{10}$/', $value) => 'Không dùng dãy 10 số làm tên đăng nhập (dễ nhầm với số điện thoại).',
                default => null,
            },
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Email chưa đúng định dạng.',
            'phone' => preg_match('/^\d{10}$/', $value) ? null : 'Số điện thoại gồm đúng 10 số.',
        };
    }

    /**
     * Chỉ quản trị viên mới được gán vai trò admin hoặc sửa/xóa tài khoản admin.
     */
    private function guardAdmin(Request $request, string $role, ?User $target = null): void
    {
        $touchesAdmin = $role === 'admin' || ($target && $target->hasRole('admin'));

        abort_if(
            $touchesAdmin && ! $request->user()->hasRole('admin'),
            403,
            'Chỉ quản trị viên mới được thay đổi tài khoản quản trị.'
        );
    }
}
