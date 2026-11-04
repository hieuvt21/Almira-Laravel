<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Làm tròn và định dạng tiền theo cài đặt "Cửa hàng > Tiền tệ & thuế".
 */
class Money
{
    /** Làm tròn số tiền phải thanh toán theo bước và kiểu đã chọn. */
    public static function round(float|int $amount): float|int
    {
        $cfg = Setting::finance();
        $step = max(1, (int) $cfg['rounding_step']);

        if ($step === 1) {
            return $amount;
        }

        $ratio = $amount / $step;

        return $step * match ($cfg['rounding_mode']) {
            'up' => ceil($ratio),
            'down' => floor($ratio),
            default => round($ratio),
        };
    }

    /** 1234567 -> "1.234.567 ₫" */
    public static function format(float|int $amount): string
    {
        $cfg = Setting::finance();

        [$thousands, $decimal] = match ($cfg['number_format']) {
            'en' => [',', '.'],
            'space' => [' ', ','],
            default => ['.', ','],
        };

        $number = number_format($amount, (int) $cfg['decimals'], $decimal, $thousands);
        $symbol = $cfg['currency_symbol'];

        if ($symbol === '') {
            return $number;
        }

        return $cfg['symbol_position'] === 'before' ? $symbol.$number : $number.' '.$symbol;
    }
}
