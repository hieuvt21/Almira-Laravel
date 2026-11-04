<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cấp số chứng từ / mã theo cài đặt "Cửa hàng > Đánh số chứng từ".
 *
 * Dùng trong module khác:  $code = DocumentNumber::next('invoice');   // HD-261104-0001
 *
 * Số thứ tự tự quay về 1 khi phần ngày tháng của mã đổi (sang ngày/tháng/năm mới).
 * Mẫu không có ngày tháng thì đếm liên tục.
 */
class DocumentNumber
{
    public const DATE_FORMATS = ['none', 'yy', 'yymm', 'yymmdd'];

    /** key => ký tự dấu ngăn */
    public const SEPARATORS = ['dash' => '-', 'dot' => '.', 'none' => ''];

    /** Các loại chứng từ cho cửa hàng tạp hóa. */
    public const TYPES = [
        'invoice' => ['label' => 'Hóa đơn bán hàng', 'prefix' => 'HD', 'date_format' => 'yymmdd', 'padding' => 4],
        'sale_return' => ['label' => 'Phiếu trả hàng', 'prefix' => 'TH', 'date_format' => 'yymm', 'padding' => 4],
        'purchase' => ['label' => 'Phiếu nhập hàng', 'prefix' => 'PN', 'date_format' => 'yymm', 'padding' => 4],
        'stock_check' => ['label' => 'Phiếu kiểm kho', 'prefix' => 'KK', 'date_format' => 'yymm', 'padding' => 4],
        'cash_in' => ['label' => 'Phiếu thu', 'prefix' => 'PT', 'date_format' => 'yymm', 'padding' => 4],
        'cash_out' => ['label' => 'Phiếu chi', 'prefix' => 'PC', 'date_format' => 'yymm', 'padding' => 4],
        'shift' => ['label' => 'Ca làm việc', 'prefix' => 'CA', 'date_format' => 'yymmdd', 'padding' => 3],
        'product' => ['label' => 'Mã sản phẩm', 'prefix' => 'SP', 'date_format' => 'none', 'padding' => 5],
        'customer' => ['label' => 'Mã khách hàng', 'prefix' => 'KH', 'date_format' => 'none', 'padding' => 5],
        'supplier' => ['label' => 'Mã nhà cung cấp', 'prefix' => 'NCC', 'date_format' => 'none', 'padding' => 4],
    ];

    /** Cấu hình mặc định của từng loại. */
    public static function defaults(): array
    {
        return array_map(fn (array $t) => [
            'prefix' => $t['prefix'],
            'date_format' => $t['date_format'],
            'separator' => 'dash',
            'padding' => $t['padding'],
        ], self::TYPES);
    }

    /** Cấu hình đang dùng = mặc định + phần đã lưu. */
    public static function config(): array
    {
        $saved = Setting::json('numbering', []);
        $config = [];

        foreach (self::defaults() as $type => $default) {
            $config[$type] = array_replace($default, is_array($saved[$type] ?? null) ? $saved[$type] : []);
        }

        return $config;
    }

    /** Phần ngày tháng của mã, đồng thời là "kỳ" của bộ đếm. */
    public static function period(string $dateFormat, ?CarbonInterface $at = null): string
    {
        $at ??= now();

        return match ($dateFormat) {
            'yy' => $at->format('y'),
            'yymm' => $at->format('ym'),
            'yymmdd' => $at->format('ymd'),
            default => '',
        };
    }

    /** Ghép mã từ cấu hình và số thứ tự. */
    public static function compose(array $cfg, int $number, ?CarbonInterface $at = null): string
    {
        $separator = self::SEPARATORS[$cfg['separator']] ?? '-';

        $parts = array_filter([
            $cfg['prefix'],
            self::period($cfg['date_format'], $at),
            str_pad((string) $number, (int) $cfg['padding'], '0', STR_PAD_LEFT),
        ], fn ($part) => $part !== '');

        return implode($separator, $parts);
    }

    /** Cấp số tiếp theo (an toàn khi nhiều máy bán cùng lúc). */
    public static function next(string $type, ?CarbonInterface $at = null): string
    {
        $cfg = self::config()[$type] ?? throw new InvalidArgumentException("Loại chứng từ không tồn tại: {$type}");
        $period = self::period($cfg['date_format'], $at);

        return DB::transaction(function () use ($type, $period, $cfg, $at) {
            DB::table('document_sequences')->insertOrIgnore([
                'type' => $type,
                'period' => $period,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('document_sequences')
                ->where('type', $type)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            $number = $row->last_number + 1;

            DB::table('document_sequences')
                ->where('id', $row->id)
                ->update(['last_number' => $number, 'updated_at' => now()]);

            return self::compose($cfg, $number, $at);
        });
    }

    /** Số đã cấp trong kỳ hiện tại của từng loại (để hiển thị). */
    public static function counters(): array
    {
        $counters = [];

        foreach (self::config() as $type => $cfg) {
            $counters[$type] = (int) DB::table('document_sequences')
                ->where('type', $type)
                ->where('period', self::period($cfg['date_format']))
                ->value('last_number');
        }

        return $counters;
    }
}
