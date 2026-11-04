<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Dùng chung cho quản lý người dùng và hồ sơ cá nhân:
 * chuẩn hóa tên đăng nhập / email / số điện thoại, quy tắc kiểm tra và ảnh đại diện.
 */
trait ManagesIdentity
{
    /** Tên đăng nhập, email: viết thường. Số điện thoại: chỉ giữ chữ số. Rỗng -> null. */
    protected function normalizeIdentity(Request $request): void
    {
        $merge = [];

        if ($request->has('username')) {
            $merge['username'] = strtolower(trim((string) $request->input('username')));
        }
        if ($request->has('email')) {
            $email = strtolower(trim((string) $request->input('email')));
            $merge['email'] = $email === '' ? null : $email;
        }
        if ($request->has('phone')) {
            $phone = preg_replace('/\D/', '', (string) $request->input('phone'));
            $merge['phone'] = $phone === '' ? null : $phone;
        }

        $request->merge($merge);
    }

    protected function emailRules(?int $ignoreId = null): array
    {
        return ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)];
    }

    protected function phoneRules(?int $ignoreId = null): array
    {
        return ['nullable', 'digits:10', Rule::unique('users', 'phone')->ignore($ignoreId)];
    }

    protected function usernameRules(?int $ignoreId = null): array
    {
        return [
            'required', 'string', 'min:3', 'max:50',
            'regex:/^[a-z0-9._-]+$/',
            'not_regex:/^\d{10}$/',
            Rule::unique('users', 'username')->ignore($ignoreId),
        ];
    }

    protected function avatarRules(): array
    {
        return [
            'avatar_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'avatar_preset' => ['nullable', Rule::in(User::AVATAR_PRESETS)],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    protected function identityMessages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên.',
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'username.min' => 'Tên đăng nhập tối thiểu 3 ký tự.',
            'username.regex' => 'Tên đăng nhập chỉ gồm chữ thường không dấu, số, dấu chấm, gạch dưới hoặc gạch ngang.',
            'username.not_regex' => 'Tên đăng nhập không được là dãy 10 số (dễ nhầm với số điện thoại).',
            'username.unique' => 'Tên đăng nhập này đã có người dùng.',
            'email.email' => 'Email chưa đúng định dạng.',
            'email.unique' => 'Email này đã được sử dụng.',
            'phone.digits' => 'Số điện thoại gồm đúng 10 số.',
            'phone.unique' => 'Số điện thoại này đã được sử dụng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu tối thiểu 8 ký tự.',
            'role.required' => 'Vui lòng chọn vai trò.',
            'avatar_file.image' => 'Ảnh đại diện phải là JPG, PNG hoặc WebP.',
            'avatar_file.mimes' => 'Ảnh đại diện phải là JPG, PNG hoặc WebP.',
            'avatar_file.max' => 'Ảnh đại diện tối đa 2 MB.',
        ];
    }

    /** Ảnh tải lên > ảnh có sẵn > xóa ảnh. */
    protected function applyAvatar(Request $request, User $user): void
    {
        if ($request->hasFile('avatar_file')) {
            $file = $request->file('avatar_file');
            $name = Str::uuid().'.'.$file->extension();
            $file->move(public_path('uploads/avatars'), $name);

            $user->deleteUploadedAvatar();
            $user->avatar = 'uploads/avatars/'.$name;
        } elseif ($request->filled('avatar_preset')) {
            $user->deleteUploadedAvatar();
            $user->avatar = 'preset:'.$request->input('avatar_preset');
        } elseif ($request->boolean('remove_avatar')) {
            $user->deleteUploadedAvatar();
            $user->avatar = null;
        } else {
            return;
        }

        $user->save();
    }
}
