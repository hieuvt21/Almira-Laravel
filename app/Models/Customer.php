<?php

namespace App\Models;

use App\Support\CustomerTiers;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'avatar', 'phone', 'address', 'occupation', 'note', 'birth_date', 'birth_year', 'total_spent'])]
class Customer extends Model
{
    protected static function booted(): void
    {
        // Khóa sắp xếp theo tên gọi, tự cập nhật khi đổi họ tên
        static::saving(function (Customer $customer) {
            if ($customer->isDirty('name') || $customer->sort_name === null) {
                $customer->sort_name = self::makeSortName($customer->name);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'birth_year' => 'integer',
            'total_spent' => 'decimal:2',
        ];
    }

    /** "Nguyễn Văn An" -> "An Nguyễn Văn". */
    public static function makeSortName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2) {
            return trim($name);
        }

        return array_pop($parts).' '.implode(' ', $parts);
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

    /** Module Bán hàng gọi khi thanh toán xong (âm = hoàn trả). */
    public function addSpent(float $amount): void
    {
        $this->increment('total_spent', $amount);
    }

    /**
     * Lịch sử mua hàng gần đây, hiện ở khung chi tiết khách.
     * Module Bán hàng thay phần thân hàm này; mỗi phần tử có dạng:
     * ['code' => 'HD-261104-0001', 'date' => '04/11/2026', 'total' => 125000, 'items_count' => 3]
     * và trả thêm ['orders_count' => ..., 'last_purchase' => 'dd/mm/yyyy'|null].
     */
    public function purchaseHistory(int $limit = 10): array
    {
        return ['orders' => [], 'orders_count' => 0, 'last_purchase' => null];
    }

    /* ---------- Bộ lọc dùng cho danh sách, xuất Excel ---------- */

    /**
     * @param  string  $scope  'basic' = tên, mã, số điện thoại; 'extra' = địa chỉ, ghi chú
     */
    public function scopeSearch(Builder $query, string $search, string $scope = 'basic'): Builder
    {
        $search = trim($search);
        if ($search === '') {
            return $query;
        }

        if ($scope === 'extra') {
            return $query->where(fn ($w) => $w->where('address', 'like', "%{$search}%")->orWhere('note', 'like', "%{$search}%"));
        }

        $digits = preg_replace('/\D+/', '', $search);

        return $query->where(function ($w) use ($search, $digits) {
            $w->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
            if ($digits !== '') {
                $w->orWhere('phone', 'like', "%{$digits}%");
            }
        });
    }

    /** Lọc theo hạng (level 0, 1, 2...). */
    public function scopeInTier(Builder $query, ?int $level): Builder
    {
        $range = $level === null ? null : CustomerTiers::range($level);
        if (! $range) {
            return $query;
        }

        [$min, $max] = $range;
        $query->where('total_spent', '>=', $min);

        return $max === null ? $query : $query->where('total_spent', '<', $max);
    }

    public function scopeOrderedBy(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy('id'),
            'name' => $query->orderBy('sort_name')->orderBy('name')->orderBy('id'),
            'name_desc' => $query->orderByDesc('sort_name')->orderByDesc('name')->orderByDesc('id'),
            // Tuổi nhỏ -> lớn = sinh muộn trước. Người chưa có ngày sinh luôn xếp cuối
            'age_asc' => $query->orderByRaw('birth_year is null')->orderByDesc('birth_year')->orderByDesc('birth_date')->orderByDesc('id'),
            // Tuổi lớn -> nhỏ = sinh sớm trước
            'age_desc' => $query->orderByRaw('birth_year is null')->orderBy('birth_year')->orderBy('birth_date')->orderByDesc('id'),
            // Người có sinh nhật gần nhất tính từ hôm nay (chỉ khi biết đủ ngày tháng), người khác xếp cuối
            'birthday' => $query
                ->orderByRaw('birth_date is null')
                ->orderByRaw('case when month(birth_date) * 100 + day(birth_date) >= ? then 0 else 1 end', [(int) now()->format('nd')])
                ->orderByRaw('month(birth_date) * 100 + day(birth_date)')
                ->orderByDesc('id'),
            'spent' => $query->orderByDesc('total_spent')->orderByDesc('id'),
            'spent_asc' => $query->orderBy('total_spent')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };
    }
}
