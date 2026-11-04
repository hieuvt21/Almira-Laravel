<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    /** Màu chủ đề cho phép (tên bảng màu của PrimeVue Aura). */
    public const THEMES = ['teal', 'emerald', 'sky', 'blue', 'indigo', 'violet', 'rose', 'orange'];

    /** Biểu tượng logo có sẵn. 'letter' = chữ cái đầu của tên cửa hàng. */
    public const LOGO_ICONS = [
        'letter', 'pi-shopping-bag', 'pi-shopping-cart', 'pi-box', 'pi-tag', 'pi-gift', 'pi-heart',
        'pi-star', 'pi-bolt', 'pi-building', 'pi-home', 'pi-bookmark', 'pi-sun',
    ];

    protected $fillable = ['key', 'value'];

    /** Toàn bộ cài đặt (có cache). Lỗi DB (chưa migrate) thì dùng giá trị mặc định. */
    public static function values(): array
    {
        try {
            return Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key')->all());
        } catch (\Throwable) {
            return [];
        }
    }

    public static function read(string $key, mixed $default = null): mixed
    {
        return static::values()[$key] ?? $default;
    }

    public static function write(array $data): void
    {
        foreach ($data as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('settings');
    }

    /** Đọc cài đặt dạng JSON, gộp với giá trị mặc định (chỉ gộp ở mức ngoài cùng). */
    public static function json(string $key, array $defaults): array
    {
        $raw = static::read($key);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? array_replace($defaults, $data) : $defaults;
    }

    public static function putJson(string $key, array $value): void
    {
        static::write([$key => json_encode($value, JSON_UNESCAPED_UNICODE)]);
    }

    /** Thông tin liên hệ của cửa hàng (in lên hóa đơn). */
    public static function storeInfo(): array
    {
        return static::json('store_info', [
            'address' => '',
            'phone' => '',
            'email' => '',
            'tax_code' => '',
            'owner' => '',
        ]);
    }

    /** Tiền tệ, làm tròn, thuế. Mặc định cho cửa hàng tạp hóa ở Việt Nam. */
    public static function finance(): array
    {
        return static::json('finance', [
            'currency_symbol' => '₫',
            'symbol_position' => 'after',   // before | after
            'number_format' => 'vi',        // vi: 1.234,5 | en: 1,234.5 | space: 1 234,5
            'decimals' => 0,
            'rounding_step' => 1,           // 1 = không làm tròn, 10, 100, 500, 1000
            'rounding_mode' => 'nearest',   // nearest | up | down
            'tax_enabled' => false,
            'tax_rates' => [0, 5, 8, 10],
            'tax_default_rate' => 10,
            'prices_include_tax' => true,
        ]);
    }

    /** Thông tin thương hiệu cửa hàng, dùng chung cho mọi trang. */
    public static function branding(): array
    {
        $theme = static::read('theme', 'teal');
        $image = static::read('logo_image');

        return [
            'name' => static::read('shop_name', 'Almira'),
            'theme' => in_array($theme, self::THEMES, true) ? $theme : 'teal',
            'logo' => [
                'type' => static::read('logo_type', 'icon'),
                'icon' => static::read('logo_icon', 'letter'),
                'image_url' => $image ? asset($image) : null,
            ],
        ];
    }
}
