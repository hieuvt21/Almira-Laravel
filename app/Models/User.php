<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /** Ảnh đại diện có sẵn: public/avatars/{key}.svg */
    public const AVATAR_PRESETS = ['a01', 'a02', 'a03', 'a04', 'a05', 'a06', 'a07', 'a08', 'a09', 'a10', 'a11', 'a12'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Địa chỉ ảnh đại diện (null nếu chưa có -> giao diện hiện chữ cái đầu). */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->avatar) {
                return null;
            }

            return str_starts_with($this->avatar, 'preset:')
                ? asset('avatars/'.substr($this->avatar, 7).'.svg')
                : asset($this->avatar);
        });
    }

    /** Xóa file ảnh đã tải lên (không đụng tới ảnh có sẵn). */
    public function deleteUploadedAvatar(): void
    {
        if ($this->avatar && str_starts_with($this->avatar, 'uploads/avatars/')) {
            @unlink(public_path($this->avatar));
        }
    }

    public static function avatarPresets(): array
    {
        return array_map(
            fn (string $key) => ['key' => $key, 'url' => asset("avatars/{$key}.svg")],
            self::AVATAR_PRESETS,
        );
    }

    /**
     * Trang đích sau khi đăng nhập: mọi vai trò đều vào dashboard.
     */
    public function homeRoute(): string
    {
        return 'dashboard';
    }
}
